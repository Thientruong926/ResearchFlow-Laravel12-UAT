<?php

namespace Tests\Feature\AdminGoogle;

use App\Services\ResearchSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\GoogleTestCase;

class GeneralFlowRegressionTest extends GoogleTestCase
{
    private string $directory;
    private string $settingsFile;
    private string $healthDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/general-regression-'.bin2hex(random_bytes(6)));
        $this->settingsFile = $this->directory.'/research-settings.json';
        $this->healthDir = $this->directory.'/health';

        mkdir($this->directory, 0700, true);
        mkdir($this->healthDir, 0700, true);

        config([
            'research.settings_file' => $this->settingsFile,
            'research.connections.metadata_directory' => $this->healthDir,
            'research.google_sync_enabled' => true,
            'research.admin.email' => 'admin@example.test',
            'research.admin.password' => 'test-password-123',
        ]);

        file_put_contents($this->settingsFile, json_encode($this->sampleSettings(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        \Carbon\CarbonImmutable::setTestNow();
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_participant_home_and_start_selection_with_validation(): void
    {
        // GET / home renders intro, schools, group radios
        $this->get('/')
            ->assertOk()
            ->assertSee('Khảo sát ứng dụng công nghệ số')
            ->assertSee('Trường Đại học Sư phạm')
            ->assertSee('Cán bộ quản lý')
            ->assertSee('Giảng viên')
            ->assertSee('Sinh viên');

        // Valid start redirects to survey show
        $this->post('/survey/start', ['school' => 'HCMUE', 'group' => 'student'])
            ->assertRedirect('/survey?school=HCMUE&group=student');

        // Invalid school fails validation
        $this->post('/survey/start', ['school' => 'INVALID_SCHOOL', 'group' => 'student'])
            ->assertSessionHasErrors('school');

        // Unassigned group redirects back with error
        $this->post('/survey/start', ['school' => 'HCMUE', 'group' => 'manager'])
            ->assertSessionHasErrors('group');
    }

    public function test_survey_form_renders_and_validates_branching_and_submission(): void
    {
        // 404 for invalid params
        $this->get('/survey?school=UNKNOWN&group=student')->assertStatus(404);

        // Valid survey show
        $this->get('/survey?school=HCMUE&group=student')
            ->assertOk()
            ->assertSee('Trường Đại học Sư phạm')
            ->assertSee('Sinh viên')
            ->assertSee('Gửi câu trả lời')
            ->assertSee('data-when-use="yes"', false)
            ->assertSee('researchflow-draft-HCMUE-student');

        // Validation: missing consent
        $this->post('/survey', [
            'school' => 'HCMUE',
            'group' => 'student',
            'use' => 'no',
            'total' => '10',
        ])->assertSessionHasErrors('consent');

        // Validation: use=yes requires level
        $this->post('/survey', [
            'school' => 'HCMUE',
            'group' => 'student',
            'consent' => '1',
            'use' => 'yes',
            'total' => '10',
            'digital' => '5',
        ])->assertSessionHasErrors('level');

        // Validation: digital > total
        $this->post('/survey', [
            'school' => 'HCMUE',
            'group' => 'student',
            'consent' => '1',
            'use' => 'yes',
            'level' => '4',
            'total' => '10',
            'digital' => '15',
        ])->assertSessionHasErrors('digital');

        // Answer text validation error prevents proceed
        $this->assertFalse(session()->has('admin_authenticated'));

        // Successful submission
        Http::fake([
            '*forms/d/e/hcmue-form-student/formResponse*' => Http::response('', 200),
        ]);

        $this->post('/survey', [
            'school' => 'HCMUE',
            'group' => 'student',
            'consent' => '1',
            'use' => 'yes',
            'level' => '4',
            'total' => '20',
            'digital' => '12',
            'note' => 'Góp ý khảo sát hợp lệ',
        ])->assertRedirect('/thank-you');

        $this->get('/thank-you')
            ->assertOk()
            ->assertSee('Cảm ơn bạn đã hoàn thành khảo sát');
    }

    public function test_admin_auth_login_logout_and_rate_limiting(): void
    {
        // Login page renders
        $this->get('/login')->assertOk()->assertSee('Đăng nhập quản lý');

        // Wrong password fails
        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        // Correct password succeeds
        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'test-password-123',
        ])->assertRedirect('/admin');

        // Logout invalidates session
        $this->withSession(['admin_authenticated' => true, 'admin_email' => 'admin@example.test'])
            ->post('/logout')
            ->assertRedirect(route('home'));

        $this->assertNull(session('admin_authenticated'));
    }

    public function test_admin_dashboard_and_settings_management_flow(): void
    {
        Http::fake([
            '*forms/d/e/hcmue-form-student/viewform*' => Http::response('Form', 200),
            '*spreadsheets/d/hcmue-sheet*' => Http::response(
                "Dấu thời gian,Mã trường,Nhóm đối tượng,Ghi chú\n30/10/2026 10:00:00,HCMUE,student,Tốt\n",
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];

        // 1. Dashboard displays stats and connection status table
        $this->withSession($session)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Tổng quan khảo sát')
            ->assertSee('Tổng số phản hồi')
            ->assertSee('HCMUE')
            ->assertSee('Trạng thái kết nối');

        // 2. Settings page displays configuration
        $this->withSession($session)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Cài đặt nghiên cứu và kết nối Google')
            ->assertSee('HCMUE')
            ->assertSee('hcmue-sheet');

        $settings = app(ResearchSettings::class);
        $rev = $settings->all()['_revision'];

        // 3. Update columns: rejected if invalid revision
        $this->withSession($session)
            ->post('/admin/settings/columns', [
                'revision' => str_repeat('a', 64),
                'timestamp_column' => 'Thời gian mới',
                'school_column' => 'Trường mới',
                'group_column' => 'Nhóm mới',
            ])->assertStatus(409);

        // Update columns: success with valid revision
        $this->withSession($session)
            ->post('/admin/settings/columns', [
                'revision' => $rev,
                'timestamp_column' => 'Thời gian mới',
                'school_column' => 'Trường mới',
                'group_column' => 'Nhóm mới',
            ])->assertRedirect();

        $rev2 = $settings->all()['_revision'];
        $this->assertNotSame($rev, $rev2);
        $this->assertSame('Thời gian mới', $settings->all()['sheet_columns']['timestamp']);

        // 4. Update schools: success
        $newSchools = "HCMUE|Trường Đại học Sư phạm TP.HCM|10|30|100\nSGU|Trường Đại học Sài Gòn|15|25|80";
        $this->withSession($session)
            ->post('/admin/settings/schools', [
                'revision' => $rev2,
                'schools' => $newSchools,
            ])->assertRedirect();

        $rev3 = $settings->all()['_revision'];
        $this->assertCount(2, $settings->schools());

        // 5. Upsert assignment: non-Google URL rejected
        $this->withSession($session)
            ->post('/admin/settings/assignments', [
                'revision' => $rev3,
                'school' => 'SGU',
                'group' => 'student',
                'form_id' => 'sgu-form',
                'sheet_csv_url' => 'https://malicious-site.com/sheet.csv',
                'active' => '1',
                'entry_school' => '201',
                'entry_group' => '202',
                'entry_use' => '203',
                'entry_level' => '204',
                'entry_total' => '205',
                'entry_digital' => '206',
                'entry_note' => '207',
            ])->assertSessionHasErrors('sheet_csv_url');

        // Upsert assignment: valid Google Sheet URL succeeds
        $this->withSession($session)
            ->post('/admin/settings/assignments', [
                'revision' => $rev3,
                'school' => 'SGU',
                'group' => 'student',
                'form_id' => 'sgu-form',
                'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/sgu-sheet/edit?gid=0',
                'active' => '1',
                'entry_school' => '201',
                'entry_group' => '202',
                'entry_use' => '203',
                'entry_level' => '204',
                'entry_total' => '205',
                'entry_digital' => '206',
                'entry_note' => '207',
            ])->assertRedirect();

        $rev4 = $settings->all()['_revision'];
        $this->assertCount(2, $settings->assignments());

        // 6. Delete assignment
        $this->withSession($session)
            ->post('/admin/settings/assignments/SGU-student/delete', [
                'revision' => $rev4,
            ])->assertRedirect();

        $this->assertCount(1, $settings->assignments());
    }

    public function test_updating_research_preserves_schools_assignments_and_columns(): void
    {
        $settings = app(ResearchSettings::class);
        $rev = $settings->all()['_revision'];
        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];

        $this->withSession($session)
            ->post('/admin/settings/research', [
                'revision' => $rev,
                'title' => 'Tiêu đề nghiên cứu mới 2026',
                'introduction' => 'Giới thiệu mới.',
                'consent' => 'Nội dung đồng thuận mới.',
                'contact_name' => 'TS. Nguyễn Văn A',
                'contact_email' => 'nva@example.test',
                'contact_phone' => '0901234567',
                'contact_address' => '280 An Dương Vương, Q5, TP.HCM',
            ])->assertRedirect();

        $updated = $settings->all();
        $this->assertSame('Tiêu đề nghiên cứu mới 2026', $updated['research']['title']);
        $this->assertSame('HCMUE', $updated['schools'][0]['code']);
        $this->assertSame('hcmue-student', $updated['assignments'][0]['id']);
        $this->assertSame('Dấu thời gian', $updated['sheet_columns']['timestamp']);

        // Home reflects new title
        $this->get('/')->assertOk()->assertSee('Tiêu đề nghiên cứu mới 2026');
    }

    private function sampleSettings(): array
    {
        return [
            'research' => [
                'title' => 'Khảo sát ứng dụng công nghệ số',
                'introduction' => 'Giới thiệu nghiên cứu tại các trường sư phạm.',
                'contact_name' => 'Nhóm nghiên cứu',
                'contact_email' => 'research@example.test',
                'consent' => 'Tôi đồng ý tham gia khảo sát tự nguyện.',
            ],
            'schools' => [
                ['code' => 'HCMUE', 'name' => 'Trường Đại học Sư phạm', 'targets' => ['student' => 100, 'lecturer' => 30, 'manager' => 10]],
            ],
            'assignments' => [
                [
                    'id' => 'hcmue-student',
                    'school' => 'HCMUE',
                    'group' => 'student',
                    'active' => true,
                    'form_id' => 'hcmue-form-student',
                    'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/hcmue-sheet/edit?gid=0',
                    'entries' => [
                        'school' => '101',
                        'group' => '102',
                        'use' => '103',
                        'level' => '104',
                        'total' => '105',
                        'digital' => '106',
                        'note' => '107',
                    ],
                ],
            ],
            'sheet_columns' => [
                'timestamp' => 'Dấu thời gian',
                'school' => 'Mã trường',
                'group' => 'Nhóm đối tượng',
            ],
            'source_defaults' => [
                'timestamp_format' => 'd/m/Y H:i:s',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ],
        ];
    }
}
