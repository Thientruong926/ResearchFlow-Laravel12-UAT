<?php

namespace Tests\Feature\AdminGoogle;

use App\Services\ResearchRuntimePolicy;
use App\Services\ResearchSettings;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\GoogleTestCase;

class AccessControlAndNoDbAuditTest extends GoogleTestCase
{
    private string $directory;
    private string $settingsFile;
    private string $healthDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/audit-'.bin2hex(random_bytes(6)));
        $this->settingsFile = $this->directory.'/research-settings.json';
        $this->healthDir = $this->directory.'/health';

        mkdir($this->directory, 0700, true);
        mkdir($this->healthDir, 0700, true);
        mkdir($this->directory.'/sessions', 0700, true);

        config([
            'research.settings_file' => $this->settingsFile,
            'research.connections.metadata_directory' => $this->healthDir,
            'research.google_sync_enabled' => true,
            'research.admin.email' => 'admin@example.test',
            'research.admin.password' => 'test-password-123',
            'session.driver' => 'file',
            'session.files' => $this->directory.'/sessions',
        ]);

        file_put_contents($this->settingsFile, json_encode($this->sampleSettings(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_unauthenticated_requests_to_all_admin_routes_are_redirected_without_data_leak(): void
    {
        $routes = [
            ['GET', '/admin'],
            ['GET', '/admin/connections'],
            ['POST', '/admin/connections/src_test/check'],
            ['POST', '/admin/connections/src_test/verification'],
            ['GET', '/admin/responses'],
            ['GET', '/admin/responses/export'],
            ['GET', '/admin/responses/detail'],
            ['GET', '/admin/settings'],
            ['GET', '/admin/settings/research'],
            ['POST', '/admin/settings/research/preview'],
            ['POST', '/admin/settings/research'],
            ['POST', '/admin/settings/columns'],
            ['POST', '/admin/settings/schools'],
            ['POST', '/admin/settings/assignments'],
            ['POST', '/admin/settings/assignments/test/delete'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->call($method, $uri);
            $response->assertStatus(302);
            $response->assertRedirect('/login');

            // Must never contain sensitive survey answers or confidential keys
            $content = $response->getContent();
            $this->assertStringNotContainsString('Câu trả lời bí mật', $content);
            $this->assertStringNotContainsString('test-password-123', $content);
        }
    }

    public function test_real_csrf_middleware_protects_all_admin_post_routes(): void
    {
        $this->app->instance('env', 'csrf-test');

        $sources = app(\App\Services\ConnectionSources::class)->all(app(ResearchSettings::class)->all());
        $sourceId = array_key_first($sources);

        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];

        $postRoutes = [
            "/admin/connections/{$sourceId}/check",
            "/admin/connections/{$sourceId}/verification",
            '/admin/settings/research/preview',
            '/admin/settings/research',
            '/admin/settings/columns',
            '/admin/settings/schools',
            '/admin/settings/assignments',
            '/admin/settings/assignments/hcmue-student/delete',
        ];

        foreach ($postRoutes as $uri) {
            $response = $this->withSession($session)
                ->withHeaders(['Accept' => 'text/html'])
                ->post($uri, ['any' => 'payload']);

            $this->assertSame(419, $response->getStatusCode(), "Route [{$uri}] must require a valid CSRF token.");
        }
    }

    public function test_zero_database_usage_and_policy_enforcement(): void
    {
        // Enforce runtime policy
        app(ResearchRuntimePolicy::class)->assertValid();

        // Deliberately break any potential database connection config
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '192.0.2.1', // Non-routable TEST-NET-1 IP
            'database.connections.mysql.port' => 65530,
            'database.connections.mysql.database' => 'nonexistent_db',
        ]);

        // Explicit mock expectation: connection() must NEVER be called
        DB::shouldReceive('connection')->never();

        Http::fake([
            '*forms/d/e/hcmue-form-student/viewform*' => Http::response('Form Page', 200),
            '*forms/d/e/hcmue-form-student/formResponse*' => Http::response('', 200),
            '*spreadsheets/d/hcmue-sheet*' => Http::response(
                "Dấu thời gian,Mã trường,Nhóm đối tượng,Ghi chú\n30/10/2026 10:00:00,HCMUE,student,Tốt\n",
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        // Participant flow works without database
        $this->get('/')->assertOk();
        $this->get('/survey?school=HCMUE&group=student')->assertOk();
        $this->post('/survey', [
            'school' => 'HCMUE',
            'group' => 'student',
            'consent' => '1',
            'use' => 'no',
            'total' => '10',
            'note' => 'Không dùng database',
        ])->assertRedirect('/thank-you');

        // Admin flow works without database
        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];
        $this->withSession($session)->get('/admin')->assertOk();
        $this->withSession($session)->get('/admin/responses')->assertOk();
        $this->withSession($session)->get('/admin/responses/export')->assertOk();
        $this->withSession($session)->get('/admin/connections')->assertOk();
        $this->withSession($session)->get('/admin/settings')->assertOk();
        $this->withSession($session)->get('/admin/settings/research')->assertOk();
    }

    public function test_no_survey_answers_persisted_to_logs_or_disk_storage(): void
    {
        $uniqueAnswer = 'ANSWER-SECRET-NO-LEAK-999';

        Http::fake([
            '*forms/d/e/hcmue-form-student/formResponse*' => Http::response('', 200),
            '*forms/d/e/hcmue-form-student/viewform*' => Http::response('Form Page', 200),
            '*spreadsheets/d/hcmue-sheet*' => Http::response(
                "Dấu thời gian,Mã trường,Nhóm đối tượng,Ghi chú\n30/10/2026 10:00:00,HCMUE,student,{$uniqueAnswer}\n",
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        // Submit answer
        $this->post('/survey', [
            'school' => 'HCMUE',
            'group' => 'student',
            'consent' => '1',
            'use' => 'yes',
            'level' => '5',
            'total' => '10',
            'digital' => '10',
            'note' => $uniqueAnswer,
        ])->assertRedirect('/thank-you');

        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];

        // View and export using normal filter
        $this->withSession($session)->get('/admin/responses?group=student')->assertOk();
        $this->withSession($session)->get('/admin/responses/export?group=student')->assertOk();

        // Verify session files do NOT contain the survey answer
        $sessionFiles = File::files($this->directory.'/sessions');
        foreach ($sessionFiles as $file) {
            $this->assertStringNotContainsString($uniqueAnswer, $file->getContents(), 'Survey answers must not be stored in session files.');
        }

        // Verify metadata files do NOT contain the answer
        $metadataFiles = File::files($this->healthDir);
        foreach ($metadataFiles as $file) {
            $this->assertStringNotContainsString($uniqueAnswer, $file->getContents(), 'Survey answers must not be stored in connection health metadata files.');
        }

        // Verify settings JSON does NOT contain the answer
        $this->assertStringNotContainsString($uniqueAnswer, file_get_contents($this->settingsFile), 'Survey answers must not be stored in settings JSON.');

        // Verify zero temporary export files left on disk
        $this->assertCount(0, File::glob(storage_path('app/*.csv')));
        $this->assertCount(0, File::glob($this->directory.'/*.csv'));
    }

    public function test_privacy_and_security_http_headers(): void
    {
        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];

        $protectedUris = ['/admin', '/admin/responses', '/admin/settings', '/admin/connections', '/survey?school=HCMUE&group=student', '/thank-you'];

        Http::fake([
            '*forms/d/e/hcmue-form-student/viewform*' => Http::response('Form', 200),
            '*spreadsheets/d/hcmue-sheet*' => Http::response("Dấu thời gian,Mã trường,Nhóm đối tượng\n", 200, ['Content-Type' => 'text/csv']),
        ]);

        foreach ($protectedUris as $uri) {
            $res = str_starts_with($uri, '/admin')
                ? $this->withSession($session)->get($uri)
                : $this->get($uri);

            $res->assertHeader('Referrer-Policy', 'no-referrer');
            $res->assertHeader('X-Content-Type-Options', 'nosniff');
            $res->assertHeader('X-Frame-Options', 'DENY');

            $cacheControl = (string) $res->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cacheControl, "Route [{$uri}] must have no-store in Cache-Control");
            $this->assertStringContainsString('private', $cacheControl, "Route [{$uri}] must have private in Cache-Control");
        }
    }

    private function sampleSettings(): array
    {
        return [
            'research' => [
                'title' => 'Khảo sát ứng dụng công nghệ số',
                'introduction' => 'Giới thiệu nghiên cứu.',
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
