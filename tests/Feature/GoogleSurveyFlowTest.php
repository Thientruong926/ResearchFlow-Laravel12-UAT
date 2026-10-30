<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\GoogleTestCase as TestCase;

class GoogleSurveyFlowTest extends TestCase
{
    private string $settingsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsFile = storage_path('framework/testing/research-settings-google-test.json');
        config([
            'research.settings_file' => $this->settingsFile,
            'research.connections.metadata_directory' => storage_path('framework/testing/google-flow-health'),
            'research.google_sync_enabled' => true,
            'research.admin.email' => 'admin@example.test',
            'research.admin.password' => 'test-password-123',
        ]);

        file_put_contents($this->settingsFile, json_encode($this->settings(), JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        if (isset($this->settingsFile) && is_file($this->settingsFile)) {
            unlink($this->settingsFile);
        }
        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('framework/testing/google-flow-health'));

        parent::tearDown();
    }

    public function test_participant_can_open_home_and_select_an_assigned_survey(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Khảo sát ứng dụng công nghệ số')
            ->assertSee('Trường kiểm thử');

        $this->post('/survey/start', ['school' => 'SITE_01', 'group' => 'student'])
            ->assertRedirect('/survey?school=SITE_01&group=student');

        $this->get('/survey?school=SITE_01&group=student')
            ->assertOk()
            ->assertSee('Sinh viên')
            ->assertSee('Gửi câu trả lời');
    }

    public function test_submission_is_validated_and_sent_to_google_forms_without_database(): void
    {
        Http::fake([
            'https://docs.google.com/forms/d/e/*/formResponse' => Http::response('', 200),
        ]);

        $this->post('/survey', [
            'school' => 'SITE_01',
            'group' => 'student',
            'consent' => '1',
            'use' => 'yes',
            'level' => '4',
            'total' => '20',
            'digital' => '15',
            'note' => 'Dữ liệu kiểm thử',
        ])->assertRedirect('/thank-you');

        Http::assertSent(function (Request $request) {
            return str_ends_with($request->url(), '/formResponse')
                && $request['entry.101'] === 'SITE_01'
                && $request['entry.102'] === 'student'
                && $request['entry.103'] === 'yes'
                && $request['entry.104'] === '4'
                && $request['entry.105'] === '20'
                && $request['entry.106'] === '15'
                && $request['entry.107'] === 'Dữ liệu kiểm thử';
        });
    }

    public function test_admin_login_and_dashboard_read_google_sheet_data(): void
    {
        $this->get('/login')->assertOk()->assertSee('Đăng nhập quản lý');

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'test-password-123',
        ])->assertRedirect('/admin');

        Http::fake([
            'https://docs.google.com/forms/d/e/*/viewform' => Http::response('form', 200),
            'https://docs.google.com/spreadsheets/d/*' => Http::response(
                "Dấu thời gian,Mã trường,Nhóm đối tượng,Ghi chú\n2026-10-28 08:00,SITE_01,student,Tốt\n",
                200
            ),
        ]);

        $this->withSession(['admin_authenticated' => true, 'admin_email' => 'admin@example.test'])
            ->get('/admin')
            ->assertOk()
            ->assertSee('1')
            ->assertSee('SITE_01')
            ->assertSee('Sinh viên');
    }

    public function test_response_list_can_filter_and_export_csv(): void
    {
        Http::fake([
            'https://docs.google.com/spreadsheets/d/*' => Http::response(
                "Dấu thời gian,Mã trường,Nhóm đối tượng,Ghi chú\n2026-10-28 08:00,SITE_01,student,Tốt\n2026-10-28 09:00,SITE_01,lecturer,Khác\n",
                200
            ),
        ]);

        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];

        $this->withSession($session)
            ->get('/admin/responses?group=student')
            ->assertOk()
            ->assertSee('1 kết quả phù hợp')
            ->assertSee('Xem chi tiết')
            ->assertDontSee('Khác');

        $export = $this->withSession($session)
            ->get('/admin/responses/export?group=student')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $export->streamedContent();
        $this->assertStringContainsString('SITE_01', $csv);
        $this->assertStringContainsString('Tốt', $csv);
        $this->assertStringNotContainsString('lecturer', $csv);
    }

    private function settings(): array
    {
        return [
            'research' => [
                'title' => 'Khảo sát ứng dụng công nghệ số',
                'introduction' => 'Nội dung giới thiệu kiểm thử.',
                'contact_name' => 'Nhóm nghiên cứu',
                'contact_email' => 'research@example.test',
                'consent' => 'Tôi đồng ý gửi câu trả lời đến Google Forms.',
            ],
            'schools' => [[
                'code' => 'SITE_01',
                'name' => 'Trường kiểm thử',
                'targets' => ['manager' => 10, 'lecturer' => 20, 'student' => 70],
            ]],
            'assignments' => [[
                'id' => 'SITE_01-student',
                'school' => 'SITE_01',
                'group' => 'student',
                'form_id' => '1FAIpQLTestFormId',
                'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/test-sheet/edit?gid=0',
                'active' => true,
                'entries' => [
                    'school' => '101',
                    'group' => '102',
                    'use' => '103',
                    'level' => '104',
                    'total' => '105',
                    'digital' => '106',
                    'note' => '107',
                ],
            ]],
            'sheet_columns' => [
                'timestamp' => 'Dấu thời gian',
                'school' => 'Mã trường',
                'group' => 'Nhóm đối tượng',
            ],
        ];
    }
}
