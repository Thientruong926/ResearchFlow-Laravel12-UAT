<?php

namespace Tests\Feature\AdminGoogle;

use App\Services\ConnectionHealthService;
use App\Services\ConnectionSources;
use App\Services\ResearchSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\GoogleTestCase;

class RealGoogleFlowIntegrationTest extends GoogleTestCase
{
    private string $directory;
    private string $settingsFile;
    private string $healthDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/real-google-flow-'.bin2hex(random_bytes(6)));
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

        file_put_contents($this->settingsFile, json_encode($this->pilotSettings(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        \Carbon\CarbonImmutable::setTestNow();
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_end_to_end_pilot_survey_submission_and_sheet_verification_flow(): void
    {
        $trackingCode = 'PILOT-TOKEN-2026-ALPHA';

        Http::fake([
            '*forms/d/e/pilot-form-student/formResponse*' => Http::response('', 200),
            '*forms/d/e/pilot-form-student/viewform*' => Http::response('Form Page', 200),
            '*spreadsheets/d/pilot-sheet*' => Http::response(
                $this->pilotCsvWithCode($trackingCode),
                200,
                ['Content-Type' => 'text/csv; charset=UTF-8']
            ),
        ]);

        // 1. Participant opens survey home and starts survey
        $this->get('/')->assertOk()->assertSee('Khảo sát thử nghiệm tích hợp');
        $this->post('/survey/start', ['school' => 'SITE_PILOT', 'group' => 'student'])
            ->assertRedirect('/survey?school=SITE_PILOT&group=student');

        // 2. Participant submits survey containing the unique tracking code
        $this->post('/survey', [
            'school' => 'SITE_PILOT',
            'group' => 'student',
            'consent' => '1',
            'use' => 'yes',
            'level' => '5',
            'total' => '25',
            'digital' => '20',
            'note' => 'Góp ý kiểm thử: '.$trackingCode,
        ])->assertRedirect('/thank-you');

        Http::assertSent(function (Request $request) use ($trackingCode) {
            return str_ends_with($request->url(), '/formResponse')
                && $request['entry.101'] === 'SITE_PILOT'
                && $request['entry.102'] === 'student'
                && $request['entry.103'] === 'yes'
                && $request['entry.104'] === '5'
                && $request['entry.105'] === '25'
                && $request['entry.106'] === '20'
                && str_contains($request['entry.107'], $trackingCode);
        });

        // 3. Admin logs in
        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];

        // 4. Admin checks connection health for the pilot source
        $sources = app(ConnectionSources::class)->all(app(ResearchSettings::class)->all());
        $sourceId = array_key_first($sources);
        $source = $sources[$sourceId];

        $checkRes = $this->withSession($session)
            ->postJson("/admin/connections/{$sourceId}/check");
        $checkRes->assertOk()
            ->assertJsonPath('state.status', 'ok')
            ->assertJsonPath('state.forms.pilot-assignment.ok', true);

        // 5. Admin records manual verification
        $this->withSession($session)
            ->post("/admin/connections/{$sourceId}/verification", [
                'revision' => $source['revision'],
                'status' => 'verified',
                'confirmed' => '1',
            ])->assertRedirect('/admin/connections');

        $healthSnapshot = app(ConnectionHealthService::class)->snapshot($source);
        $this->assertSame('verified', $healthSnapshot['verification']['status']);

        // 6. Admin searches responses for tracking code
        $listRes = $this->withSession($session)
            ->get("/admin/responses?q={$trackingCode}")
            ->assertOk()
            ->assertSee('1 kết quả phù hợp')
            ->assertSee('Xem chi tiết');

        // Extract detail token from page content
        preg_match('/ref=([^"\'&]+)/', $listRes->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Detail token not found in responses page.');
        $token = urldecode($matches[1]);

        // 7. Admin opens detail page and verifies answers
        $this->withSession($session)
            ->get("/admin/responses/detail?ref={$token}")
            ->assertOk()
            ->assertSee($trackingCode)
            ->assertSee('SITE_PILOT')
            ->assertSee('Sinh viên');

        // 8. Admin exports CSV with filter
        $exportRes = $this->withSession($session)
            ->get("/admin/responses/export?q={$trackingCode}")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('no-store', (string) $exportRes->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $exportRes->headers->get('Cache-Control'));

        $csv = $exportRes->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Exported CSV must contain UTF-8 BOM.');
        $this->assertStringContainsString($trackingCode, $csv);
        $this->assertStringContainsString('SITE_PILOT', $csv);

        // Verify zero temporary export files left on disk
        $this->assertCount(0, File::glob(storage_path('app/*.csv')));
    }

    public function test_artisan_verify_flow_command_locates_test_code_and_records_verification(): void
    {
        $code = 'VERIFY-CODE-999';

        Http::fake([
            '*forms/d/e/pilot-form-student/viewform*' => Http::response('Form Page', 200),
            '*spreadsheets/d/pilot-sheet*' => Http::response(
                $this->pilotCsvWithCode($code),
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        $exitCode = Artisan::call('research:verify-flow', [
            '--check-code' => $code,
            '--record-verification' => true,
        ]);

        $output = Artisan::output();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('BẰNG CHỨNG ĐỐI CHIẾU: Tìm thấy mã ['.$code.']', $output);
        $this->assertStringContainsString('Đã ghi nhận đối chiếu thủ công: verified', $output);

        // Test JSON flag
        $jsonExit = Artisan::call('research:verify-flow', ['--json' => true, '--check-code' => $code]);
        $this->assertSame(0, $jsonExit);
        $data = json_decode(Artisan::output(), true);
        $this->assertIsArray($data);
        $first = reset($data);
        $this->assertTrue($first['code_found']);
        $this->assertSame('ok', $first['status']);

        // Test non-matching code returns exit code 1
        $missingExit = Artisan::call('research:verify-flow', [
            '--check-code' => 'NON_EXISTENT_CODE_123',
            '--record-verification' => true,
        ]);
        $this->assertSame(1, $missingExit);
        $this->assertStringContainsString('Chưa tìm thấy mã', Artisan::output());
    }

    public function test_google_upstream_forbidden_blocks_export_and_preserves_last_success(): void
    {
        $code = 'PREV-SUCCESS-CODE';
        $httpStatus = 200;

        Http::fake([
            '*forms/d/e/pilot-form-student/viewform*' => Http::response('Form', 200),
            '*spreadsheets/d/pilot-sheet*' => function () use (&$httpStatus, $code) {
                return $httpStatus === 200
                    ? Http::response($this->pilotCsvWithCode($code), 200, ['Content-Type' => 'text/csv'])
                    : Http::response('', 403);
            },
        ]);

        $sources = app(ConnectionSources::class)->all(app(ResearchSettings::class)->all());
        $sourceId = array_key_first($sources);
        $source = $sources[$sourceId];

        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];
        $this->withSession($session)->postJson("/admin/connections/{$sourceId}/check")->assertOk();

        $snapshot1 = app(ConnectionHealthService::class)->snapshot($source);
        $this->assertSame('ok', $snapshot1['status']);
        $this->assertNotNull($snapshot1['last_success_at']);
        $successTime = $snapshot1['last_success_at'];

        // 2. Second run Google revokes permissions (403 Forbidden)
        \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::now()->addSeconds(35));
        $httpStatus = 403;

        $this->withSession($session)->postJson("/admin/connections/{$sourceId}/check")->assertOk();

        $snapshot2 = app(ConnectionHealthService::class)->snapshot($source);
        $this->assertSame('error', $snapshot2['status']);
        $this->assertSame('FORBIDDEN', $snapshot2['error_code']);
        $this->assertSame($successTime, $snapshot2['last_success_at'], 'Historical last_success_at must be preserved on error.');

        // 3. Responses index shows incomplete warning
        $this->withSession($session)
            ->get('/admin/responses')
            ->assertOk()
            ->assertSee('Dữ liệu chưa đầy đủ');

        // 4. Export is blocked to prevent silent incomplete data download
        $this->withSession($session)
            ->get('/admin/responses/export')
            ->assertStatus(503)
            ->assertSee('Không thể xuất dữ liệu đầy đủ');
    }

    public function test_google_upstream_html_login_redirect_is_rejected_as_invalid_payload(): void
    {
        Http::fake([
            '*forms/d/e/pilot-form-student/viewform*' => Http::response('Form', 200),
            '*spreadsheets/d/pilot-sheet*' => Http::response(
                '<!DOCTYPE html><html><head><title>Sign in - Google Accounts</title></head><body>Login</body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8']
            ),
        ]);

        $sources = app(ConnectionSources::class)->all(app(ResearchSettings::class)->all());
        $sourceId = array_key_first($sources);

        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];
        $res = $this->withSession($session)->postJson("/admin/connections/{$sourceId}/check");

        $res->assertOk()
            ->assertJsonPath('state.status', 'error')
            ->assertJsonPath('state.error_code', 'INVALID_PAYLOAD');
    }

    private function pilotSettings(): array
    {
        return [
            'research' => [
                'title' => 'Khảo sát thử nghiệm tích hợp',
                'introduction' => 'Giới thiệu kiểm thử tích hợp.',
                'contact_name' => 'Nhóm UAT',
                'contact_email' => 'uat@example.test',
                'consent' => 'Tôi đồng ý tham gia khảo sát thử nghiệm.',
            ],
            'schools' => [
                ['code' => 'SITE_PILOT', 'name' => 'Trường đại học thử nghiệm', 'targets' => ['student' => 50, 'lecturer' => 10, 'manager' => 5]],
            ],
            'assignments' => [
                [
                    'id' => 'pilot-assignment',
                    'school' => 'SITE_PILOT',
                    'group' => 'student',
                    'active' => true,
                    'form_id' => 'pilot-form-student',
                    'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/pilot-sheet/edit?gid=0',
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

    private function pilotCsvWithCode(string $code): string
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Dấu thời gian', 'Mã trường', 'Nhóm đối tượng', 'Có sử dụng', 'Mức độ', 'Tổng số buổi', 'Số buổi dùng số', 'Ghi chú']);
        fputcsv($stream, ['30/10/2026 14:00:00', 'SITE_PILOT', 'student', 'yes', '5', '25', '20', 'Phản hồi pilot: '.$code]);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }
}
