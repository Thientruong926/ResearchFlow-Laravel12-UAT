<?php

use App\Services\ResearchSettings;
use Illuminate\Support\Facades\Artisan;

Artisan::command('research:check', function (ResearchSettings $settings) {
    $assignments = $settings->assignments();
    $active = collect($assignments)->where('active', true)->count();

    $this->info('Storage mode: Google Forms + Google Sheets (no database)');
    $this->info('Runtime: session='.config('session.driver').', cache='.config('cache.default').', queue='.config('queue.default').', failed jobs='.config('queue.failed.driver'));
    $this->info('Reader: CSV demo; private Sheets API adapter not integrated.');
    $this->comment('Configuration check only: no Google request, no Form-to-Sheet verification.');
    $this->info('Google sync: '.(config('research.google_sync_enabled') ? 'ON' : 'OFF'));
    $this->info('Schools: '.count($settings->schools()));
    $this->info("Assignments: {$active} active / ".count($assignments).' configured');
});

Artisan::command('research:verify-flow {--source= : Định danh nguồn cần kiểm tra} {--check-code= : Mã đối chiếu cần tìm trong phản hồi Sheet} {--record-verification : Ghi nhận kết quả đối chiếu vào metadata nguồn} {--json : Xuất kết quả dạng JSON}', function (
    ResearchSettings $settings,
    \App\Services\ConnectionSources $catalog,
    \App\Contracts\SurveyResponseReader $reader,
    \App\Services\ConnectionHealthService $health,
    \App\Services\GoogleFormsService $forms
) {
    $configuration = $settings->all();
    $sources = $catalog->all($configuration);

    if ($sourceFilter = (string) $this->option('source')) {
        $sources = array_filter($sources, fn ($s) => $s['source_id'] === $sourceFilter);
        if ($sources === []) {
            $this->error("Không tìm thấy nguồn: {$sourceFilter}");
            return 1;
        }
    }

    if ($sources === []) {
        $this->warn('Chưa có nguồn nào được cấu hình trong hệ thống.');
        return 1;
    }

    $checkCode = (string) $this->option('check-code');
    $recordVerification = (bool) $this->option('record-verification');
    $outputJson = (bool) $this->option('json');

    $results = [];
    $allOk = true;

    foreach ($sources as $sourceId => $source) {
        try {
            $checkData = $health->check($source);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $checkData = $health->snapshot($source);
        }
        $readResult = $reader->read(new \App\Data\SourceDefinition($source));
        $formsStatus = $checkData['forms'] ?? [];

        $codeFound = false;
        $foundRow = null;
        if ($checkCode !== '' && $readResult->status === 'ok') {
            foreach ($readResult->records as $index => $row) {
                foreach ($row as $val) {
                    if (is_string($val) && str_contains($val, $checkCode)) {
                        $codeFound = true;
                        $foundRow = $readResult->rowNumbers[$index] ?? ($index + 1);
                        break 2;
                    }
                }
            }
        }

        $verificationStatus = null;
        if ($recordVerification) {
            $status = $checkCode !== ''
                ? ($codeFound ? 'verified' : 'not_found')
                : ($readResult->status === 'ok' ? 'verified' : 'unverified');
            $health->verify($source, $source['revision'], $status);
            $verificationStatus = $status;
        }

        $isOk = $readResult->status === 'ok' && ($checkCode === '' || $codeFound);
        if (! $isOk) {
            $allOk = false;
        }

        $results[$sourceId] = [
            'source_id' => $sourceId,
            'revision' => $source['revision'],
            'status' => $readResult->status,
            'error_code' => $readResult->errorCode,
            'message' => $readResult->message,
            'row_count' => count($readResult->records),
            'headers_count' => count($readResult->headers),
            'forms' => $formsStatus,
            'check_code' => $checkCode ?: null,
            'code_found' => $checkCode !== '' ? $codeFound : null,
            'found_row' => $foundRow,
            'recorded_verification' => $verificationStatus,
        ];
    }

    if ($outputJson) {
        $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $allOk ? 0 : 1;
    }

    $this->info('=== KẾT QUẢ KIỂM TRA TÍCH HỢP GOOGLE FLOW (Sprint 4) ===');
    $this->line('Đồng bộ Google: '.(config('research.google_sync_enabled') ? 'ON' : 'OFF'));

    foreach ($results as $id => $res) {
        $this->line("--------------------------------------------------");
        $this->info("Nguồn: {$id}");
        $this->line("Trạng thái đọc Sheet: {$res['status']} ({$res['message']})");
        $this->line("Số dòng đọc được: {$res['row_count']} | Số cột: {$res['headers_count']}");

        foreach ($res['forms'] as $assignId => $fStatus) {
            $formOk = $fStatus['ok'] ? 'OK' : 'LỖI';
            $this->line("Form [{$assignId}]: {$formOk} — {$fStatus['message']}");
        }

        if ($checkCode !== '') {
            if ($res['code_found']) {
                $this->info("BẰNG CHỨNG ĐỐI CHIẾU: Tìm thấy mã [{$checkCode}] tại dòng {$res['found_row']}!");
            } else {
                $this->warn("BẰNG CHỨNG ĐỐI CHIẾU: Chưa tìm thấy mã [{$checkCode}] trong nguồn này.");
            }
        }

        if ($res['recorded_verification']) {
            $this->comment("Đã ghi nhận đối chiếu thủ công: {$res['recorded_verification']}");
        }
    }

    return $allOk ? 0 : 1;
});
