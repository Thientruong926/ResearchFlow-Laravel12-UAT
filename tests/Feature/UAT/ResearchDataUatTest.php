<?php

namespace Tests\Feature\UAT;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResearchDataUatTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
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

    private function publishedProject(): array
    {
        $site = DB::table('sites')->insertGetId([
            'code' => 'UAT_SITE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wave = DB::table('waves')->insertGetId([
            'code' => 'UAT_WAVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $info = DB::table('information_versions')->insertGetId([
            'version' => 1,
            'purpose' => 'UAT approved purpose',
            'activities' => 'UAT activities',
            'duration' => 'UAT duration',
            'voluntary' => 'Voluntary participation',
            'withdrawal' => 'Withdrawal according to protocol',
            'contact' => 'UAT research team',
            'data_use' => 'Synthetic data only',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $survey = DB::table('surveys')->insertGetId([
            'group_code' => 'student',
            'version' => 1,
            'title' => 'UAT Research Data Survey',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $q1 = DB::table('questions')->insertGetId([
            'survey_id' => $survey,
            'code' => 'Q01',
            'label' => 'Continue?',
            'type' => 'single',
            'choices' => json_encode(['1' => 'Yes', '2' => 'No']),
            'required' => true,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $q2 = DB::table('questions')->insertGetId([
            'survey_id' => $survey,
            'code' => 'Q02',
            'label' => 'Follow-up',
            'type' => 'text',
            'condition' => json_encode([
                'code' => 'Q01',
                'equals' => '1',
            ]),
            'required' => false,
            'position' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = Str::random(48);

        DB::table('sample_links')->insert([
            'site_id' => $site,
            'wave_id' => $wave,
            'group_code' => 'student',
            'token' => $token,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('site', 'wave', 'info', 'survey', 'q1', 'q2', 'token');
    }

    public function test_uat_b1_research_id_session_and_branch_consistency(): void
    {
        // Chỉ bật thu thập trong phạm vi bài kiểm thử.
        config(['research.collection_open' => true]);

        $project = $this->publishedProject();

        // Người tham gia DEMO đồng ý tham gia.
        $this->post('/join/'.$project['token'], [
            'agree' => '1',
            'age_group' => 'adult',
            'information_version_id' => $project['info'],
            'survey_id' => $project['survey'],
        ])->assertRedirect();

        $participant = DB::table('participants')->first();
        $session = DB::table('survey_sessions')->first();

        $this->assertNotNull($participant);
        $this->assertNotNull($session);

        // B1: 128 bit ngẫu nhiên, R + 26 ký tự Base32 theo hồ sơ thiết kế.
        $this->assertMatchesRegularExpression('/^R[A-Z2-7]{26}$/D', $participant->research_id);

        // Phiên khảo sát liên kết đúng người và bảng hỏi.
        $this->assertSame(
            (int) $participant->id,
            (int) $session->participant_id
        );

        $this->assertSame(
            (int) $project['survey'],
            (int) $session->survey_id
        );

        $this->assertDatabaseHas('consents', [
            'participant_id' => $participant->id,
            'information_version_id' => $project['info'],
            'decision' => 'agreed',
        ]);

        // Lưu nháp: Q01 = Yes nên Q02 hiển thị.
        $url = '/s/'.$session->resume_token;

        $this->post($url, [
            'action' => 'draft',
            'q_'.$project['q1'] => '1',
            'q_'.$project['q2'] => 'Synthetic answer',
        ])->assertRedirect();

        $this->assertDatabaseHas('answers', [
            'survey_session_id' => $session->id,
            'question_id' => $project['q2'],
        ]);

        // Đổi Q01 thành No: Q02 phải bị xóa.
        $this->post($url, [
            'action' => 'draft',
            'q_'.$project['q1'] => '2',
            'q_'.$project['q2'] => 'Must not be saved',
        ])->assertRedirect();

        $this->assertDatabaseMissing('answers', [
            'survey_session_id' => $session->id,
            'question_id' => $project['q2'],
        ]);

        // Hoàn tất khảo sát.
        $this->post($url, [
            'action' => 'complete',
            'q_'.$project['q1'] => '2',
        ])->assertRedirect();

        $this->assertDatabaseHas('survey_sessions', [
            'id' => $session->id,
            'status' => 'complete',
        ]);

        // Không cho ghi đè sau khi đã hoàn tất.
        $original = DB::table('answers')
            ->where('survey_session_id', $session->id)
            ->where('question_id', $project['q1'])
            ->value('value');

        $this->post($url, [
            'action' => 'complete',
            'q_'.$project['q1'] => '1',
        ])->assertRedirect();

        $this->assertSame(
            $original,
            DB::table('answers')
                ->where('survey_session_id', $session->id)
                ->where('question_id', $project['q1'])
                ->value('value')
        );
    }

    public function test_uat_b2_account_revocation_and_audit(): void
    {
        $owner = $this->userWithRole('owner');
        $viewer = $this->userWithRole('viewer');

        // Viewer được phép xem dashboard.
        $this->actingAs($viewer)
            ->get('/admin')
            ->assertOk();

        // Viewer không được quản lý bảng hỏi.
        $this->get('/admin/surveys')
            ->assertForbidden();

        // Owner khóa tài khoản viewer.
        $this->actingAs($owner)
            ->post('/admin/users/'.$viewer->id.'/toggle')
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $viewer->id,
            'active' => false,
        ]);

        // Kiểm tra nhật ký audit.
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'user.active_toggled',
            'entity' => 'users',
            'entity_id' => $viewer->id,
        ]);

        // Nạp lại trạng thái từ database.
        $viewer->refresh();

        // Tài khoản đã khóa không được truy cập dashboard.
        $this->actingAs($viewer)
            ->get('/admin')
            ->assertForbidden();
    }
}
