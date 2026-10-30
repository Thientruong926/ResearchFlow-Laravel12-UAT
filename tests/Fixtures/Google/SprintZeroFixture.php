<?php

namespace Tests\Fixtures\Google;

use Illuminate\Support\Facades\Http;

/** Synthetic data only. Never import this fixture into production settings. */
final class SprintZeroFixture
{
    public static function settings(): array
    {
        $settings = config('research.default_settings');
        $settings['schools'] = [];
        $settings['assignments'] = [];
        foreach (['DEMO_01', 'DEMO_02'] as $school) {
            $settings['schools'][] = ['code' => $school, 'name' => 'Trường giả '.$school, 'targets' => ['manager' => 2, 'lecturer' => 2, 'student' => 2]];
            foreach (['manager', 'lecturer', 'student'] as $group) {
                $error = $school === 'DEMO_02' && $group === 'student';
                $settings['assignments'][] = [
                    'id' => $school.'-'.$group, 'school' => $school, 'group' => $group,
                    'active' => true, 'form_id' => 'fixture-form-'.$group,
                    'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/'.($error ? 'fixture-forbidden' : 'fixture-shared').'/edit?gid=0',
                    'entries' => ['school' => '101', 'group' => '102', 'use' => '103', 'level' => '104', 'total' => '105', 'digital' => '106', 'note' => '107'],
                ];
            }
        }
        return $settings;
    }

    public static function csv(): string
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        $questions = array_map(fn ($n) => 'Câu hỏi '.$n, range(1, 20));
        fputcsv($stream, ['Dấu thời gian', 'Mã trường', 'Nhóm đối tượng', 'Ghi chú', ...$questions]);
        $rows = [
            ['30/10/2026 08:00:00', 'DEMO_01', 'manager', "Tiếng Việt, dấu phẩy và \"nháy\"\nxuống dòng"],
            ['31/02/2026 09:00:00', 'DEMO_01', 'lecturer', '=1+1'],
            ['01/02/2026 10:00:00', 'DEMO_01', 'student', '+SUM(1;2)'],
            ['', 'DEMO_02', 'manager', '@SUM(1)'],
            ['30/10/2026 11:00:00', 'DEMO_02', 'lecturer', '-1+2'],
            ['30/10/2026 11:00:00', 'DEMO_02', 'lecturer', '-1+2'],
        ];
        foreach ($rows as $row) {
            fputcsv($stream, [...$row, ...array_fill(0, 20, 'Dữ liệu giả')]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }

    public static function fakeHttp(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*spreadsheets/d/fixture-shared/*' => Http::response(self::csv(), 200, ['Content-Type' => 'text/csv']),
            '*spreadsheets/d/fixture-forbidden/*' => Http::response('', 403),
            '*forms/d/e/fixture-form-*/viewform' => Http::response('Demo form page', 200),
            '*forms/d/e/fixture-form-*/formResponse' => Http::response('', 200),
        ]);
    }
}
