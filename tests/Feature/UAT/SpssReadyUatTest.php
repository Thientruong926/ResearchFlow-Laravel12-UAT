<?php

namespace Tests\Feature\UAT;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpssReadyUatTest extends TestCase
{
    use RefreshDatabase;

    private function analyst(): User
    {
        $user = User::create([
            'name' => 'UAT E Analyst',
            'email' => Str::random(12).'@example.test',
            'password' => 'temporary-password-123',
            'role' => 'data_manager',
        ]);

        $user->active = true;
        $user->save();

        return $user;
    }

    private function project(): array
    {
        $site = DB::table('sites')->insertGetId([
            'code' => 'UAT_E_SITE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wave = DB::table('waves')->insertGetId([
            'code' => 'UAT_E_WAVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $survey = DB::table('surveys')->insertGetId([
            'group_code' => 'student',
            'version' => 1,
            'title' => 'Synthetic SPSS Survey',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $question = DB::table('questions')->insertGetId([
            'survey_id' => $survey,
            'code' => 'Q01',
            'label' => 'Synthetic question',
            'type' => 'single',
            'choices' => json_encode([
                '1' => 'Yes',
                '2' => 'No',
            ]),
            'condition' => 'null',
            'required' => true,
            'position' => 1,
            'variable_label' => 'Synthetic question',
            'missing_code' => null,
            'scale' => 'nominal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('site', 'wave', 'survey', 'question');
    }

    private function completedSession(
        array $project,
        string $state = 'consented',
        ?string $decision = null
    ): array {
        $researchId = (string) Str::uuid();

        $participant = DB::table('participants')->insertGetId([
            'research_id' => $researchId,
            'site_id' => $project['site'],
            'wave_id' => $project['wave'],
            'group_code' => 'student',
            'minor' => false,
            'state' => $state,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = DB::table('survey_sessions')->insertGetId([
            'participant_id' => $participant,
            'survey_id' => $project['survey'],
            'resume_token' => Str::random(50),
            'status' => 'complete',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('answers')->insert([
            'survey_session_id' => $session,
            'question_id' => $project['question'],
            'value' => json_encode('1'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($decision !== null) {
            DB::table('qc_decisions')->insert([
                'survey_session_id' => $session,
                'decision' => $decision,
                'reason' => 'Synthetic UAT QC review',
                'decided_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return compact('participant', 'session', 'researchId');
    }

    public function test_uat_e1_raw_clean_export_and_qc_filtering(): void
    {
        $analyst = $this->analyst();
        $this->actingAs($analyst);

        $project = $this->project();

        // Hợp lệ: được giữ trong raw và clean.
        $valid = $this->completedSession(
            $project,
            'consented',
            'valid'
        );

        // Chưa QC: chỉ được giữ trong raw.
        $unreviewed = $this->completedSession(
            $project,
            'consented'
        );

        // Đã rút lui: vẫn giữ trong raw, loại khỏi clean.
        $withdrawn = $this->completedSession(
            $project,
            'withdrawn',
            'valid'
        );

        $raw = $this->post('/admin/data/export', [
            'scope' => 'raw',
            'format' => 'csv',
        ]);

        $raw->assertOk()->assertDownload();

        $rawCsv = $raw->getContent();

        $this->assertStringContainsString(
            $valid['researchId'],
            $rawCsv
        );

        $this->assertStringContainsString(
            $unreviewed['researchId'],
            $rawCsv
        );

        $this->assertStringContainsString(
            $withdrawn['researchId'],
            $rawCsv
        );

        $clean = $this->post('/admin/data/export', [
            'scope' => 'clean',
            'format' => 'csv',
        ]);

        $clean->assertOk()->assertDownload();

        $cleanCsv = $clean->getContent();

        $this->assertStringContainsString(
            $valid['researchId'],
            $cleanCsv
        );

        $this->assertStringNotContainsString(
            $unreviewed['researchId'],
            $cleanCsv
        );

        $this->assertStringNotContainsString(
            $withdrawn['researchId'],
            $cleanCsv
        );

        $this->assertDatabaseHas('export_runs', [
            'scope' => 'raw',
            'format' => 'csv',
            'rows' => 3,
            'actor_id' => $analyst->id,
        ]);

        $this->assertDatabaseHas('export_runs', [
            'scope' => 'clean',
            'format' => 'csv',
            'rows' => 1,
            'actor_id' => $analyst->id,
        ]);

        $this->assertSame(
            2,
            DB::table('audit_events')
                ->where('action', 'data.export')
                ->count()
        );
    }

    public function test_uat_e2_codebook_spss_mapping_and_audit(): void
    {
        $analyst = $this->analyst();
        $this->actingAs($analyst);

        $project = $this->project();

        $variable = 'Q'.$project['survey'].'_Q01';

        // Codebook CSV phải chứa tên biến và mã câu hỏi.
        $codebook = $this->get('/admin/data/codebook.csv');

        $codebook->assertOk()
            ->assertDownload('research_codebook.csv');

        $csv = $codebook->getContent();

        $this->assertStringContainsString(
            'VARIABLE',
            $csv
        );

        $this->assertStringContainsString(
            'QUESTION_CODE',
            $csv
        );

        $this->assertStringContainsString(
            $variable,
            $csv
        );

        $this->assertStringContainsString(
            'Q01',
            $csv
        );

        // Xuất file cú pháp SPSS.
        $sps = $this->post('/admin/data/export', [
            'scope' => 'raw',
            'format' => 'sps',
        ]);

        $sps->assertOk()->assertDownload();

        $syntax = $sps->getContent();

        $this->assertStringContainsString(
            $variable,
            $syntax
        );

        $this->assertStringContainsString(
            'VARIABLE LABELS',
            $syntax
        );

        $this->assertDatabaseHas('export_runs', [
            'scope' => 'raw',
            'format' => 'sps',
            'actor_id' => $analyst->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'data.codebook_export',
            'actor_id' => $analyst->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'data.export',
            'actor_id' => $analyst->id,
        ]);
    }
}
