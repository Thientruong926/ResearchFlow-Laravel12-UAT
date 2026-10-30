<?php

/** Synthetic in-memory benchmark. Emits measurements only, never survey payloads. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ConnectionSources;
use App\Services\ResponseQueryService;
use App\Services\ResponseReference;
use App\Services\SurveyCsvExporter;
use Illuminate\Support\Facades\Http;

config(['research.google_sync_enabled' => true]);
Illuminate\Support\Facades\DB::shouldReceive('connection')->never();
Illuminate\Support\Facades\Redis::shouldReceive('connection')->never();
Http::preventStrayRequests();
$settings = config('research.default_settings');
$settings['assignments'] = [['id'=>'benchmark-student','school'=>'DEMO','group'=>'student','active'=>true,'form_id'=>'benchmark-form','sheet_csv_url'=>'https://docs.google.com/spreadsheets/d/benchmark-only/edit?gid=0']];
$stream = fopen('php://memory', 'w+');
$headers = ['Dấu thời gian','Mã trường','Nhóm đối tượng', ...array_map(fn ($n) => 'Câu '.$n, range(1,97))];
fputcsv($stream, $headers, ',', '"', '');
for ($i = 0; $i < 10000; $i++) fputcsv($stream, ['30/10/2026 12:00:00','DEMO','student', ...array_fill(0,97,'giả')], ',', '"', '');
rewind($stream);
$body = stream_get_contents($stream);
fclose($stream);
$payloadBytes = strlen($body);
Http::fake(['*benchmark-only*' => Http::response($body, 200, ['Content-Type'=>'text/csv'])]);
unset($body);
$sources = app(ConnectionSources::class)->all($settings);
$filters = ['source'=>'','q'=>'giả','school'=>'','group'=>'','date_from'=>'2026-10-30','date_to'=>'2026-10-30','sort'=>'newest'];
$baseline = memory_get_usage(true);
memory_reset_peak_usage();
$start = hrtime(true);
$data = app(ResponseQueryService::class)->read($sources, $filters);
$records = app(ResponseQueryService::class)->filter($data['records'], $filters);
$readSeconds = (hrtime(true) - $start) / 1e9;
if (count($records) !== 10000 || count($data['columns']) !== 100 || $data['errors']) throw new RuntimeException('Benchmark result incomplete');
foreach (array_slice($records, 0, 25) as $record) app(ResponseReference::class)->issue($record, $sources[$record->sourceId]['revision']);
$exportStart = hrtime(true);
$exportBytes = 0;
ob_start(function ($chunk) use (&$exportBytes) { $exportBytes += strlen($chunk); return ''; }, 8192);
try {
    app(SurveyCsvExporter::class)->download($records, $data['columns'])->sendContent();
} finally {
    ob_end_flush();
}
Http::assertSentCount(1);
Mockery::close();
echo json_encode([
    'fixture'=>'synthetic-only', 'php'=>PHP_VERSION, 'rows'=>count($records), 'source_columns'=>count($data['columns']),
    'payload_bytes'=>$payloadBytes, 'export_bytes'=>$exportBytes,
    'read_normalize_filter_seconds'=>round($readSeconds, 3),
    'export_seconds'=>round((hrtime(true) - $exportStart) / 1e9, 3),
    'total_seconds'=>round((hrtime(true) - $start) / 1e9, 3),
    'baseline_memory_mib'=>round($baseline/1048576, 2),
    'peak_memory_mib'=>round(memory_get_peak_usage(true)/1048576, 2),
    'memory_limit'=>ini_get('memory_limit'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
