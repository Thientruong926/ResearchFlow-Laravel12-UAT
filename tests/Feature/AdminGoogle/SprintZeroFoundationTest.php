<?php

namespace Tests\Feature\AdminGoogle;

use App\Contracts\SurveyResponseReader;
use App\Data\SourceDefinition;
use App\Services\ConnectionSources;
use App\Services\GoogleSheetsService;
use App\Services\ResearchRuntimePolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Google\SprintZeroFixture;
use Tests\GoogleTestCase;

class SprintZeroFoundationTest extends GoogleTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/sprint-zero-'.bin2hex(random_bytes(8)));
        mkdir($this->directory.'/sessions', 0700, true);
        config([
            'research.settings_file' => $this->directory.'/settings.json',
            'research.connections.metadata_directory' => $this->directory.'/health',
            'research.google_sync_enabled' => true,
            'session.driver' => 'file', 'session.files' => $this->directory.'/sessions',
            'cache.default' => 'file', 'cache.stores.file.path' => $this->directory.'/cache',
            'cache.stores.file.lock_path' => $this->directory.'/cache',
        ]);
        file_put_contents(config('research.settings_file'), json_encode(SprintZeroFixture::settings(), JSON_THROW_ON_ERROR));
        SprintZeroFixture::fakeHttp();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_file_runtime_and_admin_routes_work_with_database_connections_forbidden(): void
    {
        app(ResearchRuntimePolicy::class)->assertValid();
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('null', config('queue.failed.driver'));
        Cache::put('sprint-zero-metadata-only', 'ok', 30);
        $this->assertSame('ok', Cache::get('sprint-zero-metadata-only'));
        $this->get('/')->assertOk()->assertSee('Trường giả DEMO_01');
        $this->get('/survey?school=DEMO_01&group=student')->assertOk();
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'test-password-123'])->assertRedirect('/admin');
        foreach (['/admin', '/admin/settings', '/admin/connections', '/admin/responses'] as $url) {
            $this->get($url)->assertOk()->assertSee('Kết nối');
        }
        $this->get('/admin/responses/export')->assertStatus(503);
        $source = array_values(app(ConnectionSources::class)->all(SprintZeroFixture::settings()))[0];
        $this->get('/admin/responses/export?source='.$source['source_id'])->assertOk();
        $this->postJson('/admin/connections/'.$source['source_id'].'/check')->assertOk()->assertJsonPath('state.status', 'ok');
        $this->assertNotEmpty(File::files($this->directory.'/sessions'));
        foreach (File::allFiles($this->directory.'/sessions') as $file) {
            $this->assertStringNotContainsString('Dữ liệu giả', $file->getContents());
        }
    }

    public function test_contract_fixture_preserves_raw_data_and_partial_source_failure(): void
    {
        $settings = SprintZeroFixture::settings();
        $this->assertCount(2, $settings['schools']);
        $this->assertCount(6, $settings['assignments']);
        $this->assertCount(3, array_unique(array_column($settings['assignments'], 'group')));
        $sources = array_values(app(ConnectionSources::class)->all($settings));
        $this->assertCount(2, $sources);
        $this->assertCount(5, $sources[0]['assignment_ids']);
        $reader = app(SurveyResponseReader::class);
        $result = $reader->read(new SourceDefinition($sources[0]));
        $this->assertSame('ok', $result->status);
        $this->assertCount(24, $result->headers);
        $this->assertCount(6, $result->records);
        $this->assertSame($result->records[4], $result->records[5]);
        $this->assertSame('31/02/2026 09:00:00', $result->records[1]['Dấu thời gian']);
        $this->assertSame('=1+1', $result->records[1]['Ghi chú']);
        $this->assertStringContainsString("\nxuống dòng", $result->records[0]['Ghi chú']);
        $this->assertNotNull($result->checkedAt);
        $this->assertNotNull($result->lastSuccessAt);
        $error = $reader->read(new SourceDefinition($sources[1]));
        $this->assertSame('FORBIDDEN', $error->errorCode);
        $this->assertNull($error->lastSuccessAt);
        $summary = app(GoogleSheetsService::class)->summarize($result->records, $settings);
        $this->assertSame(6, $summary['total']);
        $this->assertSame(3, $summary['by_school']['DEMO_01']);
        Http::assertSentCount(2);
    }

    public function test_private_transport_cannot_silently_fall_back_to_public_csv(): void
    {
        $source = array_values(app(ConnectionSources::class)->all(SprintZeroFixture::settings()))[0];
        $source['transport'] = 'sheets_api';
        $result = app(SurveyResponseReader::class)->read(new SourceDefinition($source));
        $this->assertSame('NOT_CONFIGURED', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_database_session_is_rejected_before_any_connection(): void
    {
        config(['session.driver' => 'database']);
        $this->expectException(\LogicException::class);
        app(ResearchRuntimePolicy::class)->assertValid();
    }

    public function test_redis_cache_is_rejected_before_any_connection(): void
    {
        config(['cache.default' => 'redis']);
        $this->expectException(\LogicException::class);
        app(ResearchRuntimePolicy::class)->assertValid();
    }

    public function test_database_queue_is_rejected_before_any_connection(): void
    {
        config(['queue.default' => 'database']);
        $this->expectException(\LogicException::class);
        app(ResearchRuntimePolicy::class)->assertValid();
    }
}
