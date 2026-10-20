<?php

namespace Tests\Feature\UAT;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardQcUatTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role): User
    {
        $user = User::create([
            'name' => 'UAT '.$role,
            'email' => Str::random(12).'@example.test',
            'password' => 'temporary-password-123',
            'role' => $role,
        ]);

        $user->active = true;
        $user->save();

        return $user;
    }

    private function completedSession(): array
    {
        $site = DB::table('sites')->insertGetId([
            'code' => 'UAT_SITE_C',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wave = DB::table('waves')->insertGetId([
            'code' => 'UAT_WAVE_C',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $survey = DB::table('surveys')->insertGetId([
            'group_code' => 'student',
            'version' => 1,
            'title' => 'UAT Dashboard Survey',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $participant = DB::table('participants')->insertGetId([
            'research_id' => (string) Str::uuid(),
            'site_id' => $site,
            'wave_id' => $wave,
            'group_code' => 'student',
            'minor' => false,
            'state' => 'consented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = DB::table('survey_sessions')->insertGetId([
            'participant_id' => $participant,
            'survey_id' => $survey,
            'resume_token' => Str::random(50),
            'status' => 'complete',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('site', 'wave', 'survey', 'participant', 'session');
    }

    public function test_uat_c1_dashboard_qc_decision_and_audit(): void
    {
        $project = $this->completedSession();

        $owner = $this->member('owner');
        $reviewer = $this->member('qc_reviewer');

        // Dashboard của owner phải có dữ liệu tổng hợp.
        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk();

        // Quy tắc QC mới phải bắt đầu ở trạng thái draft.
        $this->actingAs($reviewer)
            ->post('/admin/qc/rules', [
                'code' => 'too_fast',
                'threshold' => 30,
                'definition' => 'Synthetic UAT: duration below 30 seconds',
            ])
            ->assertRedirect();

        $rule = DB::table('quality_rules')->first();

        $this->assertNotNull($rule);
        $this->assertSame('draft', $rule->status);

        // Người tạo không được tự phê duyệt.
        $this->post('/admin/qc/rules/'.$rule->id.'/approve')
            ->assertForbidden();

        // Owner phê duyệt quy tắc.
        $this->actingAs($owner)
            ->post('/admin/qc/rules/'.$rule->id.'/approve')
            ->assertRedirect();

        $this->assertDatabaseHas('quality_rules', [
            'id' => $rule->id,
            'status' => 'approved',
            'approved_by' => $owner->id,
        ]);

        // Reviewer ghi quyết định QC cho phiên hoàn tất.
        $this->actingAs($reviewer)
            ->post('/admin/qc/'.$project['session'].'/decide', [
                'decision' => 'valid',
                'reason' => 'Synthetic UAT session reviewed',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('qc_decisions', [
            'survey_session_id' => $project['session'],
            'decision' => 'valid',
            'decided_by' => $reviewer->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $reviewer->id,
            'action' => 'qc.decision',
        ]);
    }

    public function test_uat_c2_small_cell_and_snapshot_integrity(): void
    {
        Storage::fake('local');

        $project = $this->completedSession();
        $owner = $this->member('owner');

        config(['research.dashboard_min_cell' => 5]);

        // Chỉ có một phiên nên bảng phân nhóm phải ẩn ô nhỏ.
        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk()
            ->assertSee('[ẩn: ô nhỏ]');

        // Tạo quota đã được phê duyệt bằng dữ liệu DEMO.
        DB::table('quota_configs')->insert([
            'site_id' => $project['site'],
            'wave_id' => $project['wave'],
            'group_code' => 'student',
            'target' => 10,
            'version' => 1,
            'status' => 'approved',
            'approved_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sinh snapshot lưu trữ riêng tư.
        $this->artisan('research:snapshot')
            ->assertExitCode(0);

        $report = DB::table('report_snapshots')->first();

        $this->assertNotNull($report);

        $disk = Storage::disk('local');

        $this->assertTrue(
            $disk->exists($report->storage_path)
        );

        $bytes = $disk->get($report->storage_path);

        $this->assertSame(
            $report->sha256,
            hash('sha256', $bytes)
        );

        // Giả lập nội dung snapshot bị thay đổi.
        $disk->put(
            $report->storage_path,
            'TAMPERED SYNTHETIC CONTENT'
        );

        // Download phải bị chặn vì checksum không khớp.
        $this->actingAs($owner)
            ->get('/admin/dashboard/reports/'.$report->id)
            ->assertStatus(409);
    }
}
