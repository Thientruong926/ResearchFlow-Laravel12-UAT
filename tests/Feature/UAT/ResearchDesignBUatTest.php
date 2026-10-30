<?php

namespace Tests\Feature\UAT;

use App\Models\User;
use App\Services\DesignCodebookImport;
use App\Services\ResearchId;
use App\Services\TechnicalDuplicates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResearchDesignBUatTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        $user = User::create(['name' => 'B test', 'email' => Str::random(12).'@example.test',
            'password' => 'test-password-12345', 'role' => $role]);
        $user->active = true;
        $user->save();
        return $user;
    }

    private function project(): array
    {
        config(['research.collection_open' => true]);
        $owner = $this->user();
        $site = DB::table('sites')->insertGetId(['code' => 'S-ABCDEFGHIJ', 'private_label' => 'PRIVATE_REAL_SITE']);
        $wave = DB::table('waves')->insertGetId(['code' => 'W-ABCDEFGHIJ', 'instrument_id' => 'OBS4',
            'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(3)]);
        $department = DB::table('departments')->insertGetId(['site_id' => $site, 'code' => 'D-ABCDEFGHIJ']);
        $survey = DB::table('surveys')->insertGetId(['group_code' => 'SV', 'version' => 1, 'title' => 'DEMO',
            'instrument_id' => 'OBS4', 'form_version' => 'DEMO-0.1', 'status' => 'published']);
        $question = DB::table('questions')->insertGetId(['survey_id' => $survey, 'code' => 'SV_NT01',
            'source_item_code' => 'SV-NT01', 'label' => 'DEMO question', 'type' => 'likert',
            'choices' => json_encode(['1' => 'One', '97' => 'Unknown', '98' => 'Not applicable']), 'position' => 1]);
        $info = DB::table('information_versions')->insertGetId(['version' => 1, 'purpose' => 'DEMO',
            'activities' => 'DEMO', 'duration' => 'DEMO', 'voluntary' => 'DEMO', 'withdrawal' => 'DEMO',
            'contact' => 'DEMO', 'data_use' => 'DEMO', 'status' => 'published']);
        DB::table('quota_configs')->insert(['site_id' => $site, 'wave_id' => $wave, 'group_code' => 'SV',
            'target' => 10, 'status' => 'approved']);
        $token = bin2hex(random_bytes(24));
        $link = DB::table('sample_links')->insertGetId(['site_id' => $site, 'wave_id' => $wave,
            'department_id' => $department, 'survey_id' => $survey, 'group_code' => 'SV', 'point_code' => 'P-01',
            'token' => hash('sha256', $token), 'token_hashed' => true, 'status' => 'ACTIVE', 'active' => true, 'existing_session_policy' => 'continue',
            'owner_id' => $owner->id, 'created_by' => $owner->id, 'approved_by' => $owner->id,
            'valid_from' => now()->subHour(), 'valid_until' => now()->addDay()]);
        return compact('owner', 'site', 'wave', 'department', 'survey', 'question', 'info', 'token', 'link');
    }

    private function join(array $p): object
    {
        $this->post('/join/'.$p['token'], ['agree' => '1', 'age_group' => 'adult',
            'information_version_id' => $p['info'], 'survey_id' => $p['survey']])->assertRedirect();
        return DB::table('survey_sessions')->orderByDesc('id')->first();
    }

    public function test_b1_ten_thousand_ids_have_required_format_and_no_duplicates(): void
    {
        $ids = new ResearchId();
        $seen = [];
        for ($i = 0; $i < 10000; $i++) {
            $id = $ids->generate();
            $this->assertMatchesRegularExpression('/^R[A-Z2-7]{26}$/D', $id);
            $this->assertArrayNotHasKey($id, $seen);
            $seen[$id] = true;
        }
    }

    public function test_b1_collision_retries_and_records_technical_event(): void
    {
        $p = $this->project();
        $ids = new class extends ResearchId {
            public int $calls = 0;
            public function generate(): string { return ++$this->calls <= 2 ? 'R'.str_repeat('A', 26) : 'R'.str_repeat('B', 26); }
        };
        $attributes = ['site_id' => $p['site'], 'wave_id' => $p['wave'], 'group_code' => 'SV'];
        $ids->createParticipant($attributes);
        $ids->createParticipant($attributes);
        $this->assertDatabaseCount('participants', 2);
        $this->assertSame(3, $ids->calls);
        $this->assertDatabaseHas('audit_events', ['action' => 'research_id.collision']);
    }

    public function test_b2_closed_expired_unknown_and_revoked_links_create_no_sessions(): void
    {
        $p = $this->project();
        foreach (['DRAFT', 'PAUSED', 'REVOKED', 'EXPIRED'] as $status) {
            DB::table('sample_links')->where('id', $p['link'])->update(['status' => $status]);
            $this->get('/join/'.$p['token'])->assertNotFound();
            $this->post('/join/'.$p['token'], [])->assertNotFound();
        }
        DB::table('sample_links')->where('id', $p['link'])->update(['status' => 'ACTIVE', 'valid_until' => now()]);
        $this->get('/join/'.$p['token'])->assertNotFound();
        $this->get('/join/unknown-token')->assertNotFound();
        $this->assertDatabaseCount('participants', 0);
        $this->assertDatabaseCount('survey_sessions', 0);
        $this->assertDatabaseHas('audit_events', ['action' => 'sampling_route.denied']);
    }

    public function test_b2_link_keeps_exact_form_when_new_version_is_published(): void
    {
        $p = $this->project();
        DB::table('surveys')->where('id', $p['survey'])->update(['status' => 'superseded']);
        $new = DB::table('surveys')->insertGetId(['group_code' => 'SV', 'version' => 2, 'title' => 'NEW',
            'instrument_id' => 'OBS4', 'form_version' => 'DEMO-0.2', 'status' => 'published']);
        $this->get('/join/'.$p['token'])->assertOk()->assertSee('/join/'.$p['token'], false)
            ->assertDontSee('/join/'.hash('sha256', $p['token']), false)
            ->assertViewHas('survey', fn ($s) => $s->id === $p['survey']);
        $this->post('/join/'.$p['token'], ['agree' => '1', 'age_group' => 'adult',
            'information_version_id' => $p['info'], 'survey_id' => $new])->assertSessionHasErrors();
        $session = $this->join($p);
        $this->assertSame($p['survey'], $session->survey_id);
        $this->assertDatabaseHas('participants', ['sample_link_id' => $p['link']]);
    }

    public function test_b2_new_link_stores_only_hash_and_issues_qr_once(): void
    {
        $p = $this->project();
        $response = $this->actingAs($p['owner'])->post('/admin/links', [
            'site_id' => $p['site'], 'wave_id' => $p['wave'], 'department_id' => $p['department'],
            'group_code' => 'SV', 'survey_id' => $p['survey'], 'point_code' => 'P-02', 'existing_session_policy' => 'continue',
            'owner_id' => $p['owner']->id, 'valid_from' => now()->subMinute()->toDateTimeString(),
            'valid_until' => now()->addDay()->toDateTimeString(),
        ])->assertOk()->assertViewIs('admin.link_issued');
        $url = $response->viewData('url');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $link = DB::table('sample_links')->orderByDesc('id')->first();
        $this->assertSame(hash('sha256', $token), $link->token);
        $this->assertNotSame($token, $link->token);
        $this->assertSame('DRAFT', $link->status);
        $this->assertStringContainsString('<svg', $response->viewData('qr'));
        $this->get('/admin/links/'.$link->id.'/qr')->assertStatus(410);
        $this->post('/admin/links/'.$link->id.'/toggle')->assertRedirect();
        $this->assertDatabaseHas('sample_links', ['id' => $link->id, 'status' => 'ACTIVE']);
    }

    public function test_b2_wrong_department_and_wave_instrument_are_rejected(): void
    {
        $p = $this->project();
        DB::table('waves')->where('id', $p['wave'])->update(['instrument_id' => 'OBS2']);
        $this->get('/join/'.$p['token'])->assertNotFound();
        DB::table('waves')->where('id', $p['wave'])->update(['instrument_id' => 'OBS4']);
        DB::table('departments')->where('id', $p['department'])->update(['active' => false]);
        $this->get('/join/'.$p['token'])->assertNotFound();
        $this->assertDatabaseCount('survey_sessions', 0);
    }

    public function test_b2_revoke_preserves_data_and_prevents_reactivation(): void
    {
        $p = $this->project();
        $this->join($p);
        $this->actingAs($p['owner'])->post('/admin/links/'.$p['link'].'/revoke', ['reason' => 'UAT revoke'])->assertRedirect();
        $this->post('/admin/links/'.$p['link'].'/toggle')->assertStatus(422);
        $this->get('/join/'.$p['token'])->assertNotFound();
        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseCount('survey_sessions', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'link.revoked']);
    }

    public function test_b3_missing_codes_long_export_and_replay_keep_raw_data(): void
    {
        $p = $this->project();
        foreach (['97', '98', ''] as $value) {
            $s = $this->join($p);
            $this->post('/s/'.$s->resume_token, ['action' => 'complete', 'q_'.$p['question'] => $value])->assertRedirect();
            $this->assertDatabaseHas('answers', ['survey_session_id' => $s->id,
                'missing_code' => $value === '' ? 'BLANK' : $value]);
        }
        $before = DB::table('answers')->where('survey_session_id', $s->id)->value('value');
        $this->post('/s/'.$s->resume_token, ['action' => 'complete', 'q_'.$p['question'] => '1'])->assertRedirect();
        $this->assertSame($before, DB::table('answers')->where('survey_session_id', $s->id)->value('value'));
        $this->assertDatabaseHas('technical_duplicate_flags', ['survey_session_id' => $s->id, 'rule_code' => 'SESSION_REPLAY']);
        $csv = $this->actingAs($this->user('data_manager'))->get('/admin/data/raw-long.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('SV-NT01', $csv);
        $this->assertStringContainsString('OBS4,DEMO-0.1', $csv);
        $this->assertStringContainsString('BLANK', $csv);
        $this->assertStringNotContainsString('PRIVATE_REAL_SITE', $csv);
        $this->assertStringNotContainsString($p['token'], $csv);
        $this->assertStringNotContainsString($s->resume_token, $csv);
        $this->assertDatabaseCount('participants', 3); // Sharing a QR is expected.
        $this->assertDatabaseCount('technical_duplicate_flags', 1);
    }

    public function test_b3_demo_import_preserves_all_six_forms_and_227_source_items(): void
    {
        $service = app(DesignCodebookImport::class);
        $count = $service->import(base_path('docs/b_design/item_codebook_DEMO.csv'), base_path('docs/b_design/scale_codebook_DEMO.csv'));
        $this->assertSame(227, $count);
        $this->assertDatabaseCount('surveys', 6);
        $this->assertDatabaseCount('questions', 227);
        $this->assertSame(0, DB::table('surveys')->where('status', 'published')->count());
        $this->assertSame(73, DB::table('questions as q')->join('surveys as s', 'q.survey_id', '=', 's.id')->where('s.instrument_id', 'OBS2')->count());
        $this->assertSame(154, DB::table('questions as q')->join('surveys as s', 'q.survey_id', '=', 's.id')->where('s.instrument_id', 'OBS4')->count());
        $this->assertDatabaseHas('questions', ['source_item_code' => 'KHOA-KK01']);
        try {
            $service->import(base_path('docs/b_design/item_codebook_DEMO.csv'), base_path('docs/b_design/scale_codebook_DEMO.csv'));
            $this->fail('Duplicate import must fail.');
        } catch (\InvalidArgumentException $e) {
            $this->assertDatabaseCount('questions', 227);
        }
    }

    public function test_b4_repeated_research_session_flags_both_without_deleting(): void
    {
        $p = $this->project();
        $s = $this->join($p);
        DB::table('survey_sessions')->insert(['participant_id' => $s->participant_id, 'survey_id' => $s->survey_id,
            'resume_token' => Str::random(50), 'started_at' => now()]);
        $this->assertSame(2, app(TechnicalDuplicates::class)->scan());
        app(TechnicalDuplicates::class)->scan();
        $this->assertDatabaseCount('technical_duplicate_flags', 2);
        $this->assertDatabaseCount('survey_sessions', 2);
    }

    public function test_d11_policy_is_explicit_and_respected_for_paused_sessions(): void
    {
        $p = $this->project();
        $s = $this->join($p);
        DB::table('sample_links')->where('id', $p['link'])->update(['active' => false, 'status' => 'PAUSED', 'existing_session_policy' => 'stop']);
        $this->post('/s/'.$s->resume_token, ['action' => 'complete', 'q_'.$p['question'] => '1'])->assertForbidden();
        $this->assertDatabaseCount('answers', 0);
        DB::table('sample_links')->where('id', $p['link'])->update(['existing_session_policy' => 'continue']);
        $this->post('/s/'.$s->resume_token, ['action' => 'complete', 'q_'.$p['question'] => '1'])->assertRedirect();
        $this->assertDatabaseCount('answers', 1);
    }

    public function test_b2_published_metadata_cannot_be_modified_and_clone_needs_new_form_version(): void
    {
        $p = $this->project();
        $this->actingAs($p['owner'])->post('/admin/surveys/'.$p['survey'].'/metadata', [
            'instrument_id' => 'OBS2', 'form_version' => 'ILLEGAL',
        ])->assertStatus(422);
        $this->post('/admin/surveys/'.$p['survey'].'/clone')->assertRedirect();
        $clone = DB::table('surveys')->orderByDesc('id')->first();
        $this->assertNull($clone->form_version);
        $this->post('/admin/surveys/'.$clone->id.'/publish')->assertStatus(422);
        $this->post('/admin/surveys/'.$clone->id.'/metadata', ['instrument_id' => 'OBS4', 'form_version' => 'DEMO-0.2'])->assertRedirect();
        $this->post('/admin/surveys/'.$clone->id.'/publish')->assertRedirect();
        $this->assertDatabaseHas('questions', ['survey_id' => $clone->id, 'source_item_code' => 'SV-NT01']);
    }

    public function test_b5_restricted_registry_and_export_are_audited_without_tokens(): void
    {
        $p = $this->project();
        foreach (['viewer', 'data_manager', 'quota_manager'] as $role) {
            $this->actingAs($this->user($role))->get('/admin/links/registry.csv')->assertForbidden();
        }
        $this->actingAs($p['owner'])->get('/admin/sites')->assertOk();
        $csv = $this->get('/admin/links/registry.csv')->assertOk()->streamedContent();
        $this->assertStringNotContainsString($p['token'], $csv);
        $this->assertStringNotContainsString(hash('sha256', $p['token']), $csv);
        $this->assertStringNotContainsString('PRIVATE_REAL_SITE', $csv);
        $this->assertDatabaseHas('audit_events', ['action' => 'link_registry.exported']);
        $this->assertDatabaseHas('audit_events', ['action' => 'restricted.read']);
    }

    public function test_b5_design_roles_separate_creation_approval_analysis_and_audit(): void
    {
        $p = $this->project();
        $it = $this->user('IT_ADMIN');
        $ncs = $this->user('NCS');
        $this->actingAs($ncs)->post('/admin/links', [])->assertForbidden();
        $this->actingAs($it)->get('/admin/data/raw-long.csv')->assertForbidden();
        $this->get('/admin/sites')->assertOk()->assertDontSee('PRIVATE_REAL_SITE');
        $this->post('/admin/links/'.$p['link'].'/toggle')->assertRedirect(); // IT may pause.
        $this->post('/admin/links/'.$p['link'].'/toggle')->assertForbidden(); // IT cannot approve.
        $this->actingAs($ncs)->post('/admin/links/'.$p['link'].'/toggle')->assertRedirect();
        $this->actingAs($this->user('AUDITOR'))->get('/admin/research-audit')->assertOk();
        $this->get('/admin/data/raw-long.csv')->assertForbidden();
        $this->get('/admin/sites')->assertForbidden();
    }

    public function test_b5_coordinator_sees_only_assigned_site_without_tokens_or_responses(): void
    {
        $p = $this->project();
        $coordinator = $this->user('SITE_COORDINATOR');
        $coordinator->assigned_site_id = $p['site'];
        $coordinator->save();
        $other = DB::table('sites')->insertGetId(['code' => 'S-OTHERZZZZZ']);
        DB::table('sample_links')->insert(['site_id' => $other, 'wave_id' => $p['wave'],
            'group_code' => 'SV', 'token' => Str::random(48), 'point_code' => 'P-OTHER']);
        $response = $this->actingAs($coordinator)->get('/admin/site-link-status')->assertOk();
        $this->get('/admin')->assertRedirect(route('admin.site.status'));
        $response->assertSee('P-01')->assertDontSee('P-OTHER')->assertDontSee('PRIVATE_REAL_SITE')
            ->assertDontSee($p['token'])->assertDontSee(hash('sha256', $p['token']));
        $this->get('/admin/data/raw-long.csv')->assertForbidden();
        $this->get('/admin/links/registry.csv')->assertForbidden();
    }

    public function test_b5_analyst_export_contains_only_valid_records_and_omits_withdrawal(): void
    {
        $p = $this->project();
        $s = $this->join($p);
        $this->post('/s/'.$s->resume_token, ['action' => 'complete', 'q_'.$p['question'] => '1'])->assertRedirect();
        $analyst = $this->user('ANALYST');
        $this->actingAs($analyst)->get('/admin')->assertRedirect(route('admin.analysis'));
        $this->get('/admin/analysis')->assertOk();
        $this->actingAs($analyst)->get('/admin/sites')->assertForbidden();
        $csv = $this->get('/admin/data/raw-long.csv')->assertDownload('research_analysis_long.csv')->streamedContent();
        $this->assertStringNotContainsString('SV-NT01', $csv);
        DB::table('qc_decisions')->insert(['survey_session_id' => $s->id, 'decision' => 'valid',
            'reason' => 'UAT accepted', 'decided_by' => $p['owner']->id]);
        $csv = $this->get('/admin/data/raw-long.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('SV-NT01', $csv);
        DB::table('participants')->where('id', $s->participant_id)->update(['state' => 'withdrawal_requested']);
        $csv = $this->get('/admin/data/raw-long.csv')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('SV-NT01', $csv);
        $this->get('/admin/research-codebook.csv')->assertOk();
    }
}
