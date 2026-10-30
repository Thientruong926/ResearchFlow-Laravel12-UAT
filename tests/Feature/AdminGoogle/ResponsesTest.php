<?php

namespace Tests\Feature\AdminGoogle;

use App\Services\ConnectionSources;
use App\Services\GoogleSheetsService;
use App\Services\ResponseNormalizer;
use App\Services\SurveyCsvExporter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\Google\SprintZeroFixture;
use Tests\GoogleTestCase;

class ResponsesTest extends GoogleTestCase
{
    private string $directory;
    private array $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/responses-'.bin2hex(random_bytes(8)));
        mkdir($this->directory, 0700, true);
        $this->settings = SprintZeroFixture::settings();
        // Five assignments share the healthy source; add the forbidden source explicitly in failure tests.
        array_pop($this->settings['assignments']);
        config(['research.settings_file' => $this->directory.'/settings.json', 'research.connections.metadata_directory' => $this->directory.'/health', 'research.google_sync_enabled' => true]);
        $this->save();
        $this->fake(SprintZeroFixture::csv());
        $this->withSession(['admin_authenticated' => true, 'admin_email' => 'admin@example.test']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function save(): void { file_put_contents(config('research.settings_file'), json_encode($this->settings)); }

    private function source(): array { return array_values(app(ConnectionSources::class)->all($this->settings))[0]; }

    private function fake(string $body, int $status = 200): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['*fixture-shared*' => Http::response($body, $status), '*fixture-forbidden*' => Http::response('', 403)]);
    }

    private function csv(array $rows, array $headers = ['Dấu thời gian', 'Mã trường', 'Nhóm đối tượng', 'Ghi chú']): string
    {
        $stream = fopen('php://memory', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        foreach ($rows as $row) fputcsv($stream, $row, ',', '"', '');
        rewind($stream);
        $body = stream_get_contents($stream);
        fclose($stream);
        return $body;
    }

    private function detailUrl(): string
    {
        $response = $this->get('/admin/responses')->assertOk();
        return '/admin/responses/detail?'.http_build_query(['ref' => array_values($response->viewData('tokens'))[0]]);
    }

    public function test_strict_dates_combined_filters_and_one_sided_date_range(): void
    {
        $this->fake($this->csv([
            ['30/10/2026 00:00:00','DEMO_01','student','TỐT'],
            ['30/10/2026 23:59:59','DEMO_01','student','Tốt'],
            ['31/10/2026 00:00:00','DEMO_01','student','Tốt'],
            ['31/02/2026 12:00:00','DEMO_01','student','Tốt'],
            ['30/10/2026 12:00:00','DEMO_02','student','Tốt'],
        ]));
        $url = '/admin/responses?'.http_build_query(['q'=>'tốt','school'=>'DEMO_01','group'=>'student','source'=>$this->source()['source_id'],'date_from'=>'2026-10-30','date_to'=>'2026-10-30']);
        $response = $this->get($url)->assertOk();
        $this->assertSame(2, $response->viewData('page')->total());
        $this->assertSame(3, $response->viewData('page')->items()[0]->sourceRowNumber);
        $this->assertSame(3, $this->get('/admin/responses?date_to=2026-10-30')->assertOk()->viewData('page')->total());
        $this->assertSame(0, $this->get('/admin/responses?q=tot')->viewData('page')->total());
        $this->get('/admin/responses')->assertSee('Ngày không hợp lệ');
    }

    public function test_pagination_preserves_filters_and_export_ignores_page_size(): void
    {
        $rows = [];
        for ($i = 1; $i <= 61; $i++) $rows[] = ['30/10/2026 12:00:00','DEMO_01','student','ROW-'.$i];
        $this->fake($this->csv($rows));
        $response = $this->get('/admin/responses?group=student&per_page=25&page=2')->assertOk();
        $page = $response->viewData('page');
        $this->assertSame(61, $page->total());
        $this->assertCount(25, $page->items());
        $this->assertSame(27, $page->items()[0]->sourceRowNumber);
        $this->assertStringContainsString('group=student', $page->nextPageUrl());
        $this->assertStringContainsString('page=3', $page->nextPageUrl());
        $export = $this->get('/admin/responses/export?group=student&page=2&per_page=1')->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(61, substr_count($csv, 'ROW-'));
        $this->assertStringContainsString('no-store', $export->headers->get('Cache-Control'));
    }

    public function test_detail_escapes_text_and_token_contains_only_reference_metadata(): void
    {
        $this->fake($this->csv([['30/10/2026 12:00:00','DEMO_01','student',"<script>alert(1)</script>\nRiêng tư"]]));
        $list = $this->get('/admin/responses')->assertOk()->assertDontSee('Riêng tư');
        $token = array_values($list->viewData('tokens'))[0];
        $plain = Crypt::decryptString($token);
        $this->assertStringNotContainsString('Riêng tư', $plain);
        $this->assertSame(['source','row','revision','hash','expires'], array_keys(json_decode($plain, true)));
        $this->get('/admin/responses/detail?'.http_build_query(['ref'=>$token]))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false)->assertSee('Riêng tư');
        $this->assertFalse(session()->has('_old_input'));
    }

    public static function mutations(): array { return [['edit'], ['delete'], ['sort'], ['header'], ['revision']]; }

    #[DataProvider('mutations')]
    public function test_changed_rows_or_schema_never_open_wrong_response(string $mutation): void
    {
        $rows = [['30/10/2026 12:00:00','DEMO_01','student','FIRST'], ['30/10/2026 12:00:00','DEMO_01','student','SECOND']];
        $this->fake($this->csv($rows));
        $url = $this->detailUrl();
        $headers = ['Dấu thời gian','Mã trường','Nhóm đối tượng','Ghi chú'];
        if ($mutation === 'edit') $rows[0][3] = 'CHANGED';
        if ($mutation === 'delete') array_shift($rows);
        if ($mutation === 'sort') $rows = array_reverse($rows);
        if ($mutation === 'header') $headers[3] = 'Câu hỏi khác';
        if ($mutation === 'revision') { $this->settings['source_defaults']['timestamp_format'] = 'Y-m-d'; $this->save(); }
        $this->fake($this->csv($rows, $headers));
        $this->get($url)->assertStatus(409)->assertDontSee('SECOND');
    }

    public function test_invalid_expired_and_unauthenticated_tokens_are_rejected(): void
    {
        $url = $this->detailUrl();
        $this->get('/admin/responses/detail?ref=forged')->assertStatus(400);
        $this->travel(10)->minutes();
        $this->get($url)->assertStatus(410);
        $this->withSession(['admin_authenticated'=>false]);
        foreach (['/admin/responses','/admin/responses/export',$url] as $path) $this->get($path)->assertRedirect('/login');
    }

    public function test_source_failure_blocks_export_but_healthy_source_can_be_selected(): void
    {
        $this->settings = SprintZeroFixture::settings(); $this->save();
        $this->get('/admin/responses')->assertOk()->assertSee('Dữ liệu chưa đầy đủ');
        $this->get('/admin/responses/export')->assertStatus(503)->assertHeaderMissing('Content-Disposition');
        $this->get('/admin/responses/export?source='.$this->source()['source_id'])->assertOk();
        $url = $this->detailUrl();
        $this->fake('', 403);
        $this->get($url)->assertStatus(503);
        $this->get('/admin/responses')->assertOk()->assertSee('Không đọc được dữ liệu');
    }

    public static function invalidFilters(): array
    {
        return array_map(fn ($filter) => [$filter], [['q'=>str_repeat('x',201)], ['school'=>'fake'], ['group'=>'fake'], ['source'=>'fake'], ['date_from'=>'2026-02-31'], ['date_from'=>'2026-10-31','date_to'=>'2026-10-30'], ['date'=>'2026-10-31','date_to'=>'2026-10-30'], ['per_page'=>7], ['page'=>0], ['q'=>['bad']], ['sort'=>'random']]);
    }

    #[DataProvider('invalidFilters')]
    public function test_filter_validation_never_flashes_input(array $filter): void
    {
        $this->get('/admin/responses?'.http_build_query($filter))->assertStatus(422);
        $this->assertFalse(session()->has('_old_input'));
        Http::assertNothingSent();
    }

    public function test_raw_row_positions_with_blank_and_multiline_rows_and_range(): void
    {
        foreach ($this->settings['assignments'] as &$assignment) $assignment['sheet_csv_url'] .= '&range=A5:D20';
        unset($assignment);
        $body = $this->csv([['30/10/2026 12:00:00','DEMO_01','student',"line1\nline2"]]);
        $body .= "\n30/10/2026 12:00:00,DEMO_01,student,last\n";
        $this->fake($body);
        $source = $this->source();
        $read = app(GoogleSheetsService::class)->readSource($source);
        $this->assertSame([6,8], $read->rowNumbers);
        $records = app(ResponseNormalizer::class)->normalize($source, $read);
        $this->assertSame(8, $records[1]->sourceRowNumber);
    }

    public function test_formula_cells_and_headers_are_neutralized_without_mutating_answers(): void
    {
        $exporter = app(SurveyCsvExporter::class);
        foreach (['=1+1','  +SUM(1)','-10','@SUM(1)',"\ttext"," \rtext","\ntext","\u{00A0}=1"] as $value) $this->assertSame("'".$value, $exporter->safeCell($value));
        $note = "Dấu phẩy, \"nháy\"\nxuống dòng";
        $this->fake($this->csv([['30/10/2026 12:00:00','DEMO_01','student',$note]], ['Dấu thời gian','Mã trường','Nhóm đối tượng','=Header']));
        $csv = $this->get('/admin/responses/export')->assertOk()->streamedContent();
        $stream = fopen('php://memory', 'w+'); fwrite($stream, substr($csv, 3)); rewind($stream);
        $header = fgetcsv($stream, 0, ',', '"', ''); $row = fgetcsv($stream, 0, ',', '"', ''); fclose($stream);
        $this->assertStringStartsWith("'=Header", end($header));
        $this->assertSame($note, end($row));
    }

    public function test_limits_fail_before_download_and_empty_is_distinct_from_no_match(): void
    {
        $this->fake($this->csv([]));
        $this->get('/admin/responses')->assertOk()->assertSee('chưa có phản hồi');
        $this->fake(SprintZeroFixture::csv());
        $this->get('/admin/responses?q=not-present')->assertOk()->assertSee('Không có kết quả phù hợp');
        config(['research.responses.max_rows'=>2]);
        $this->get('/admin/responses/export')->assertStatus(413)->assertHeaderMissing('Content-Disposition');
        config(['research.responses.max_rows'=>10000,'research.responses.max_columns'=>2]);
        $this->get('/admin/responses/export')->assertStatus(413);
        config(['research.responses.max_columns'=>100,'research.responses.max_bytes'=>10]);
        $this->get('/admin/responses/export')->assertStatus(413);
        config(['research.responses.max_bytes'=>10485760,'research.connections.max_bytes'=>10]);
        $this->get('/admin/responses/export')->assertStatus(413);
    }

    public function test_multiple_sources_have_distinct_question_keys_and_aggregate_limits(): void
    {
        $this->settings['assignments'][] = ['id'=>'second','school'=>'DEMO_02','group'=>'student','active'=>true,'form_id'=>'second','sheet_csv_url'=>'https://docs.google.com/spreadsheets/d/fixture-second/edit'];
        $this->save();
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        $body = $this->csv([['30/10/2026 12:00:00','DEMO_01','student','same']]);
        Http::fake(['*spreadsheets*'=>Http::response($body, 200)]);
        $response = $this->get('/admin/responses?q=Sinh+viên&sort=oldest')->assertOk();
        $this->assertSame(2, $response->viewData('page')->total());
        $this->assertCount(8, $response->viewData('data')['columns']);
        $items = $response->viewData('page')->items();
        $this->assertSame([], array_intersect_key($items[0]->answers, $items[1]->answers));
        $this->assertLessThan(0, strcmp($items[0]->sourceId, $items[1]->sourceId));
        config(['research.responses.max_columns'=>7]);
        $this->get('/admin/responses/export')->assertStatus(413);
        config(['research.responses.max_columns'=>100,'research.responses.max_rows'=>1]);
        $this->get('/admin/responses/export')->assertStatus(413);
        config(['research.responses.max_rows'=>10000,'research.responses.max_bytes'=>strlen($body) + 1]);
        $this->get('/admin/responses/export')->assertStatus(413);
    }

    public function test_single_day_alias_and_oldest_sort_preserve_date_meaning(): void
    {
        $this->fake($this->csv([
            ['01/02/2026 23:59:59','DEMO_01','student','February'],
            ['02/01/2026 00:00:00','DEMO_01','student','January'],
        ]));
        $response = $this->get('/admin/responses?date=2026-02-01')->assertOk();
        $this->assertSame(1, $response->viewData('page')->total());
        $this->assertSame('2026-02-01T23:59:59+07:00', $response->viewData('page')->items()[0]->submittedAt->format(DATE_ATOM));
        $oldest = $this->get('/admin/responses?sort=oldest')->assertOk()->viewData('page')->items();
        $this->assertSame(3, $oldest[0]->sourceRowNumber);
    }
}
