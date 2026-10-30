<?php

namespace Tests\Feature\AdminGoogle;

use App\Services\ConnectionHealthService;
use App\Services\ConnectionMetadataStore;
use App\Services\ConnectionSources;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GoogleTestCase as TestCase;

class ConnectionHealthTest extends TestCase
{
    private string $directory;
    private array $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/connections-'.bin2hex(random_bytes(8)));
        mkdir($this->directory, 0700, true);
        config([
            'research.settings_file' => $this->directory.'/settings.json',
            'research.connections.metadata_directory' => $this->directory.'/metadata',
            'research.google_sync_enabled' => true,
        ]);
        $this->settings = config('research.default_settings');
        $this->settings['assignments'] = [[
            'id' => 'SITE_01-student', 'school' => 'SITE_01', 'group' => 'student', 'active' => true,
            'form_id' => 'test-form', 'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/test-sheet/edit?gid=0',
        ]];
        $this->saveSettings();
        Http::preventStrayRequests();
        DB::shouldReceive('connection')->never();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function saveSettings(): void
    {
        file_put_contents(config('research.settings_file'), json_encode($this->settings));
    }

    private function source(): array
    {
        return array_values(app(ConnectionSources::class)->all($this->settings))[0];
    }

    private function fake(string $body = "Dấu thời gian,Mã trường,Nhóm đối tượng,Ghi chú\n", int $status = 200): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake([
            '*spreadsheets*' => Http::response($body, $status),
            '*viewform' => Http::response('form', 200),
        ]);
    }

    private function login(): static
    {
        return $this->withSession(['admin_authenticated' => true, 'admin_email' => 'admin@example.test']);
    }

    public function test_get_only_reads_metadata_and_requires_login(): void
    {
        Http::fake();
        $this->get('/admin/connections')->assertRedirect('/login');
        $this->post('/admin/connections/'.$this->source()['source_id'].'/check')->assertRedirect('/login');
        $this->post('/admin/connections/'.$this->source()['source_id'].'/verification')->assertRedirect('/login');
        $this->login()->get('/admin/connections')->assertOk()->assertSee('Chưa kiểm tra')->assertSee('Chưa xác minh');
        Http::assertNothingSent();
    }

    public function test_disabled_missing_and_empty_sheet_are_distinct(): void
    {
        $this->fake();
        $reader = app(GoogleSheetsService::class);
        $source = $this->source();
        $this->assertSame('disabled', $reader->readSource(array_replace($source, ['active' => false]))->status);
        $this->assertSame('unconfigured', $reader->readSource(array_replace($source, ['url' => null]))->status);
        config(['research.google_sync_enabled' => false]);
        $this->assertSame('disabled', $reader->readSource($source)->status);
        Http::assertNothingSent();
        config(['research.google_sync_enabled' => true]);
        $result = $reader->readSource($source);
        $this->assertSame('ok', $result->status);
        $this->assertSame(0, $result->metadata()['row_count']);
    }

    public static function httpErrors(): array
    {
        return [[401, 'UNAUTHORIZED', 1], [403, 'FORBIDDEN', 1], [404, 'NOT_FOUND', 1], [429, 'RATE_LIMITED', 3], [500, 'UPSTREAM_ERROR', 3], [503, 'UPSTREAM_ERROR', 3]];
    }

    #[DataProvider('httpErrors')]
    public function test_http_errors_never_report_success(int $status, string $code, int $attempts): void
    {
        $this->fake('upstream content must not escape', $status);
        $result = app(GoogleSheetsService::class)->readSource($this->source());
        $this->assertSame('error', $result->status);
        $this->assertSame($code, $result->errorCode);
        $this->assertSame([], $result->records);
        Http::assertSentCount($attempts);
    }

    public function test_timeout_is_bounded(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) { $attempts++; throw new ConnectionException('secret upstream URL'); });
        $result = app(GoogleSheetsService::class)->readSource($this->source());
        $this->assertSame('TIMEOUT', $result->errorCode);
        $this->assertStringNotContainsString('secret', json_encode($result->metadata()));
        $this->assertSame(3, $attempts);
    }

    public function test_retry_after_larger_than_budget_stops_without_early_retry(): void
    {
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => '120'])]);
        $this->assertSame('RATE_LIMITED', app(GoogleSheetsService::class)->readSource($this->source())->errorCode);
        Http::assertSentCount(1);
    }

    public static function invalidBodies(): array
    {
        return [
            ['<!DOCTYPE html><html>Login</html>', 'INVALID_PAYLOAD'],
            ['', 'SCHEMA_MISMATCH'],
            ["Dấu thời gian,A,A\n", 'SCHEMA_MISMATCH'],
            ["Dấu thời gian,,B\n", 'SCHEMA_MISMATCH'],
            ["Dấu thời gian,A\nnow,x,extra\n", 'SCHEMA_MISMATCH'],
            ["Wrong,A\n", 'SCHEMA_MISMATCH'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_payload_validation(string $body, string $code): void
    {
        $this->fake($body);
        $this->assertSame($code, app(GoogleSheetsService::class)->readSource($this->source())->errorCode);
    }

    public function test_bom_unicode_multiline_and_shared_source_are_preserved(): void
    {
        $this->settings['assignments'][] = array_replace($this->settings['assignments'][0], [
            'id' => 'SITE_02-lecturer', 'school' => 'SITE_02', 'group' => 'lecturer',
            'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/test-sheet/export?gid=0&format=csv',
        ]);
        $this->fake("\xEF\xBB\xBFDấu thời gian,Mã trường,Nhóm đối tượng,Ghi chú\n2026-10-30,SITE_01,student,\"Tiếng Việt\nxuống dòng\"\n2026-10-30,SITE_02,lecturer,=formula\n");
        $result = app(GoogleSheetsService::class)->responses($this->settings);
        $this->assertCount(1, $result['sources']);
        $this->assertCount(2, $result['records']);
        $this->assertArrayNotHasKey('_source_school', $result['records'][1]);
        $this->assertSame('SITE_02', $result['records'][1]['Mã trường']);
        Http::assertSentCount(1);
    }

    public function test_ambiguous_shared_source_cannot_fall_back_to_first_assignment(): void
    {
        $this->settings['assignments'][] = array_replace($this->settings['assignments'][0], ['id' => 'SITE_02-student', 'school' => 'SITE_02']);
        $this->fake("Dấu thời gian,Ghi chú\n2026-10-30,hello\n");
        $this->assertSame('SCHEMA_MISMATCH', app(GoogleSheetsService::class)->readSource($this->source())->errorCode);
    }

    public function test_success_timestamp_survives_failure_and_metadata_has_no_answers(): void
    {
        $this->fake("Dấu thời gian,Mã trường,Nhóm đối tượng,Ghi chú\n2026-10-30,SITE_01,student,PRIVATE_ANSWER\n");
        $source = $this->source();
        $health = app(ConnectionHealthService::class);
        $first = $health->check($source);
        $this->assertSame('ok', $first['status']);
        $this->assertSame('unverified', $first['verification']['status']);
        $this->travel(31)->seconds();
        $this->fake('private error', 403);
        $second = $health->check($source);
        $this->assertSame('error', $second['status']);
        $this->assertSame($first['last_success_at'], $second['last_success_at']);
        $this->assertNotSame($first['last_checked_at'], $second['last_checked_at']);
        $json = file_get_contents(config('research.connections.metadata_directory').'/'.$source['source_id'].'.json');
        foreach (['PRIVATE_ANSWER', 'private error', 'headers', 'records', 'sheet_csv_url', 'test-form'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->travel(6)->minutes();
        $this->assertTrue($health->snapshot($source)['stale']);
    }

    public function test_check_rate_limit_and_manual_verification_with_revision(): void
    {
        $this->fake();
        $source = $this->source();
        $url = '/admin/connections/'.$source['source_id'];
        $this->login()->postJson($url.'/check')->assertOk()->assertJsonPath('state.status', 'ok')->assertJsonPath('state.verification.status', 'unverified');
        $this->postJson($url.'/check')->assertStatus(429);
        $this->post($url.'/verification', ['revision' => $source['revision'], 'status' => 'verified', 'confirmed' => '1'])->assertRedirect('/admin/connections');
        $this->get('/admin/connections')->assertOk()->assertSee('Đã xác minh');
        $this->settings['assignments'][0]['form_id'] = 'changed-form';
        $this->saveSettings();
        $this->get('/admin/connections')->assertOk()->assertSee('Cấu hình đã thay đổi');
        $this->postJson($url.'/verification', ['revision' => $source['revision'], 'status' => 'verified', 'confirmed' => '1'])->assertStatus(409);
    }

    public function test_real_csrf_middleware_rejects_missing_token(): void
    {
        Http::fake();
        $this->app->instance('env', 'csrf-test');
        $url = '/admin/connections/'.$this->source()['source_id'];
        $this->login()->postJson($url.'/check')->assertStatus(419);
        $this->postJson($url.'/verification')->assertStatus(419);
        Http::assertNothingSent();
    }

    public function test_unsafe_urls_redirects_and_oversized_body_are_rejected(): void
    {
        $reader = app(GoogleSheetsService::class);
        foreach (['http://docs.google.com/spreadsheets/d/id/edit', 'https://user@docs.google.com/spreadsheets/d/id/edit', 'https://docs.google.com:444/spreadsheets/d/id/edit', 'https://evil.test/spreadsheets/d/id/edit', 'https://docs.google.com/spreadsheets/d/id/edit?gid=bad', 'https://docs.google.com/spreadsheets/d/id/edit?gid[]=1'] as $url) {
            try {
                $reader->csvUrl($url);
                $this->fail('Unsafe URL accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);
        $this->assertSame('INVALID_PAYLOAD', $reader->readSource($this->source())->errorCode);
        Http::assertSentCount(1);
        config(['research.connections.max_bytes' => 10]);
        $this->fake(str_repeat('x', 11));
        $this->assertSame('LIMIT_EXCEEDED', $reader->readSource($this->source())->errorCode);
    }

    public function test_corrupt_metadata_is_not_silently_treated_as_success(): void
    {
        $source = $this->source();
        app(ConnectionMetadataStore::class)->read($source['source_id']);
        file_put_contents(config('research.connections.metadata_directory').'/'.$source['source_id'].'.json', '{bad');
        $this->expectException(\RuntimeException::class);
        app(ConnectionHealthService::class)->snapshot($source);
    }

    public function test_disabled_dashboard_never_shows_success_or_calls_google(): void
    {
        Http::fake();
        config(['research.google_sync_enabled' => false]);
        $this->login()->get('/admin')->assertOk()->assertSee('Chưa đọc đủ nguồn')->assertDontSee('Đã đồng bộ');
        Http::assertNothingSent();
    }

    public function test_sources_are_isolated_and_shared_forms_are_checked_once(): void
    {
        $this->settings['assignments'][] = array_replace($this->settings['assignments'][0], ['id' => 'SITE_02-student', 'school' => 'SITE_02']);
        $this->settings['assignments'][] = array_replace($this->settings['assignments'][0], ['id' => 'SITE_03-student', 'school' => 'SITE_03', 'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/bad-sheet/edit']);
        Http::fake([
            '*bad-sheet*' => Http::response('', 403),
            '*test-sheet*' => Http::response("Dấu thời gian,Mã trường,Nhóm đối tượng\n", 200),
            '*viewform' => Http::response('form', 200),
        ]);
        $sources = array_values(app(ConnectionSources::class)->all($this->settings));
        $this->assertCount(2, $sources);
        $health = app(ConnectionHealthService::class);
        $this->assertSame('ok', $health->check($sources[0])['status']);
        Http::assertSentCount(2);
        $this->assertSame('error', $health->check($sources[1])['status']);
        $this->assertSame('ok', $health->snapshot($sources[0])['status']);
        $this->assertCount(2, $health->snapshot($sources[0])['forms']);
    }

    public function test_source_lock_rejects_overlapping_check_without_http(): void
    {
        Http::fake();
        $source = $this->source();
        app(ConnectionMetadataStore::class)->read($source['source_id']);
        $lock = fopen(config('research.connections.metadata_directory').'/'.$source['source_id'].'.json.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->login()->postJson('/admin/connections/'.$source['source_id'].'/check')->assertStatus(429);
            Http::assertNothingSent();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_normalization_keeps_tabs_and_ranges_separate(): void
    {
        $reader = app(GoogleSheetsService::class);
        $this->assertSame($reader->csvUrl('https://docs.google.com/spreadsheets/d/id/edit#gid=001'), $reader->csvUrl('https://docs.google.com/spreadsheets/d/id/export?format=csv&gid=1'));
        $this->assertNotSame($reader->csvUrl('https://docs.google.com/spreadsheets/d/id/edit?gid=1'), $reader->csvUrl('https://docs.google.com/spreadsheets/d/id/edit?gid=2'));
        $this->assertStringEndsWith('&range=A1%3AC10', $reader->csvUrl('https://docs.google.com/spreadsheets/d/e/id/pub?output=csv&range=A1:C10'));
    }
}
