<?php

namespace Tests\Feature\UAT;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotaConsentUatTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role): User
    {
        $user = User::create([
            'name' => 'UAT D '.$role,
            'email' => Str::random(12).'@example.test',
            'password' => 'temporary-password-123',
            'role' => $role,
        ]);

        $user->active = true;
        $user->save();

        return $user;
    }

    private function siteAndWave(): array
    {
        $site = DB::table('sites')->insertGetId([
            'code' => 'UAT_D_SITE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wave = DB::table('waves')->insertGetId([
            'code' => 'UAT_D_WAVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('site', 'wave');
    }

    public function test_uat_d1_quota_approval_workflow(): void
    {
        $maker = $this->member('quota_manager');
        $approver = $this->member('owner');

        $project = $this->siteAndWave();

        // Bước 1: Tạo quota nháp.
        $this->actingAs($maker)
            ->post('/admin/quota', [
                'site_id' => $project['site'],
                'wave_id' => $project['wave'],
                'group_code' => 'student',
                'target' => 20,
            ])
            ->assertRedirect();

        $quota = DB::table('quota_configs')->first();

        $this->assertNotNull($quota);
        $this->assertSame('draft', $quota->status);
        $this->assertSame(1, (int) $quota->version);

        // Bước 2: Gửi duyệt.
        $this->actingAs($maker)
            ->post('/admin/quota/'.$quota->id.'/submit')
            ->assertRedirect();

        $this->assertDatabaseHas('quota_configs', [
            'id' => $quota->id,
            'status' => 'pending_review',
            'submitted_by' => $maker->id,
        ]);

        // Bước 3: Người gửi không được tự phê duyệt.
        $this->actingAs($maker)
            ->post('/admin/quota/'.$quota->id.'/approve')
            ->assertForbidden();

        $this->assertDatabaseHas('quota_configs', [
            'id' => $quota->id,
            'status' => 'pending_review',
        ]);

        // Bước 4: Owner phê duyệt.
        $this->actingAs($approver)
            ->post('/admin/quota/'.$quota->id.'/approve')
            ->assertRedirect();

        $this->assertDatabaseHas('quota_configs', [
            'id' => $quota->id,
            'status' => 'approved',
            'approved_by' => $approver->id,
        ]);

        // Bước 5: Audit phải lưu lịch sử.
        $this->assertDatabaseHas('audit_events', [
            'action' => 'quota.drafted',
            'entity_id' => $quota->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'quota.submitted',
            'entity_id' => $quota->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'quota.approved',
            'entity_id' => $quota->id,
        ]);

        // Bước 6: Không được phê duyệt lại quota đã duyệt.
        $this->post('/admin/quota/'.$quota->id.'/approve')
            ->assertStatus(422);
    }

    public function test_uat_d2_withdrawal_request_resolution_and_audit(): void
    {
        $project = $this->siteAndWave();
        $approver = $this->member('owner');

        // Tạo participant DEMO, không thu thập dữ liệu thật.
        $participantId = DB::table('participants')->insertGetId([
            'research_id' => (string) Str::uuid(),
            'site_id' => $project['site'],
            'wave_id' => $project['wave'],
            'group_code' => 'student',
            'minor' => false,
            'state' => 'consented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tạo yêu cầu rút lui DEMO.
        $requestId = DB::table('participant_requests')->insertGetId([
            'participant_id' => $participantId,
            'type' => 'withdrawal',
            'message' => 'Synthetic UAT withdrawal request',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('participants')
            ->where('id', $participantId)
            ->update([
                'state' => 'withdrawal_requested',
                'updated_at' => now(),
            ]);

        $this->assertDatabaseHas('participant_requests', [
            'id' => $requestId,
            'status' => 'new',
        ]);

        // Owner giải quyết yêu cầu theo hướng loại khỏi phân tích.
        $this->actingAs($approver)
            ->post('/admin/requests/'.$requestId.'/resolve', [
                'resolution' => 'Synthetic UAT: approved withdrawal',
                'disposition' => 'exclude',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('participant_requests', [
            'id' => $requestId,
            'status' => 'resolved',
            'handled_by' => $approver->id,
        ]);

        $this->assertDatabaseHas('participants', [
            'id' => $participantId,
            'state' => 'withdrawn',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'request.resolved',
            'entity_id' => $requestId,
        ]);

        // Không được giải quyết lại yêu cầu đã hoàn thành.
        $this->post('/admin/requests/'.$requestId.'/resolve', [
            'resolution' => 'Repeated decision',
            'disposition' => 'retain',
        ])->assertStatus(422);

        // Dữ liệu gốc vẫn tồn tại để phục vụ kiểm toán.
        $this->assertDatabaseHas('participant_requests', [
            'id' => $requestId,
            'type' => 'withdrawal',
        ]);
    }
}
