<?php

namespace Tests\Feature\UAT;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalSurveyUatTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::create([
            'name' => 'UAT Owner',
            'email' => Str::random(12).'@example.test',
            'password' => 'temporary-password-123',
            'role' => 'owner',
        ]);

        $user->active = true;
        $user->save();

        return $user;
    }

    public function test_uat_a1_a2_full_survey_lifecycle(): void
    {
        $owner = $this->owner();

        // A1: Tạo bảng hỏi v1.
        $this->actingAs($owner)
            ->post('/admin/surveys', [
                'group_code' => 'manager',
                'title' => 'DEMO UAT - Survey Lifecycle',
            ])
            ->assertRedirect();

        $v1 = DB::table('surveys')
            ->where('group_code', 'manager')
            ->where('version', 1)
            ->first();

        $this->assertNotNull($v1);
        $this->assertSame('draft', $v1->status);

        // A1: Thêm câu hỏi gốc Q01.
        $this->post('/admin/surveys/'.$v1->id.'/questions', [
            'code' => 'Q01',
            'label' => 'DEMO: Continue?',
            'type' => 'single',
            'choices_text' => "1=Yes\n2=No",
            'required' => '1',
        ])->assertRedirect();

        $q1 = DB::table('questions')
            ->where('survey_id', $v1->id)
            ->where('code', 'Q01')
            ->first();

        $this->assertNotNull($q1);

        // A1: Thêm Q02 phụ thuộc Q01 = 1.
        $this->post('/admin/surveys/'.$v1->id.'/questions', [
            'code' => 'Q02',
            'label' => 'DEMO: Follow-up question',
            'type' => 'text',
            'condition_code' => 'Q01',
            'condition_value' => '1',
        ])->assertRedirect();

        $q2 = DB::table('questions')
            ->where('survey_id', $v1->id)
            ->where('code', 'Q02')
            ->first();

        $this->assertNotNull($q2);

        $condition = json_decode($q2->condition, true);

        $this->assertSame('Q01', $condition['code']);
        $this->assertSame('1', $condition['equals']);

        // A1: Chỉnh sửa câu hỏi khi còn là bản nháp.
        $this->post(
            '/admin/surveys/'.$v1->id.'/questions/'.$q1->id.'/edit',
            [
                'label' => 'DEMO: Updated Q01',
                'choices_text' => "1=Yes\n2=No",
                'required' => '1',
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('questions', [
            'id' => $q1->id,
            'label' => 'DEMO: Updated Q01',
        ]);

        // A2: Công bố v1.
        $this->post('/admin/surveys/'.$v1->id.'/publish')
            ->assertRedirect();

        $this->assertDatabaseHas('surveys', [
            'id' => $v1->id,
            'status' => 'published',
        ]);

        // A2: Không được sửa v1 sau công bố.
        $this->post(
            '/admin/surveys/'.$v1->id.'/questions/'.$q1->id.'/edit',
            [
                'label' => 'Illegal modification',
                'choices_text' => "1=Yes\n2=No",
            ]
        )->assertStatus(422);

        // A2: Sao chép v1 thành v2.
        $this->post('/admin/surveys/'.$v1->id.'/clone')
            ->assertRedirect();

        $v2 = DB::table('surveys')
            ->where('group_code', 'manager')
            ->where('version', 2)
            ->first();

        $this->assertNotNull($v2);
        $this->assertSame('draft', $v2->status);

        // Kiểm tra câu hỏi được sao chép đầy đủ.
        $this->assertSame(
            2,
            DB::table('questions')
                ->where('survey_id', $v2->id)
                ->count()
        );

        $this->assertDatabaseHas('questions', [
            'survey_id' => $v2->id,
            'code' => 'Q01',
            'label' => 'DEMO: Updated Q01',
        ]);

        // Phiên bản gốc không bị thay đổi.
        $this->assertDatabaseHas('surveys', [
            'id' => $v1->id,
            'status' => 'published',
        ]);
    }
}
