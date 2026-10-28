<?php

namespace App\Http\Controllers;

use App\Services\GoogleFormsService;
use App\Services\GoogleSheetsService;
use App\Services\ResearchSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class GoogleAdminController extends Controller
{
    public function dashboard(
        ResearchSettings $settings,
        GoogleSheetsService $sheets,
        GoogleFormsService $forms
    ) {
        $configuration = $settings->all();
        $sync = $sheets->responses($configuration);
        $summary = $sheets->summarize($sync['records'], $configuration);
        $connections = collect($configuration['assignments'])->map(function (array $assignment) use ($forms, $sync) {
            $form = $forms->connectionStatus($assignment);
            $sheetError = collect($sync['errors'])->first(
                fn (string $error) => str_starts_with($error, ($assignment['id'] ?? '').':')
            );
            return $assignment + [
                'form_status' => $form,
                'sheet_status' => [
                    'ok' => ! $sheetError && ! empty($assignment['sheet_csv_url']) && config('research.google_sync_enabled'),
                    'message' => $sheetError ?: (empty($assignment['sheet_csv_url']) ? 'Chưa cấu hình' : (config('research.google_sync_enabled') ? 'Đã đồng bộ' : 'Đồng bộ đang tắt')),
                ],
            ];
        });

        return view('admin.google_dashboard', compact('configuration', 'sync', 'summary', 'connections'));
    }

    public function settings(ResearchSettings $settings)
    {
        return view('admin.google_settings', [
            'settings' => $settings->all(),
            'schoolLines' => $settings->schoolsAsLines(),
            'groups' => config('research.groups'),
            'syncEnabled' => config('research.google_sync_enabled'),
        ]);
    }

    public function updateResearch(Request $request, ResearchSettings $settings)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:300'],
            'introduction' => ['required', 'string', 'max:5000'],
            'contact_name' => ['required', 'string', 'max:200'],
            'contact_email' => ['required', 'email', 'max:200'],
            'consent' => ['required', 'string', 'max:5000'],
            'timestamp_column' => ['required', 'string', 'max:200'],
            'school_column' => ['required', 'string', 'max:200'],
            'group_column' => ['required', 'string', 'max:200'],
        ]);

        $settings->updateResearch(
            collect($data)->only(['title', 'introduction', 'contact_name', 'contact_email', 'consent'])->all(),
            [
                'timestamp' => $data['timestamp_column'],
                'school' => $data['school_column'],
                'group' => $data['group_column'],
            ]
        );
        Log::info('Research settings updated', ['admin' => $request->session()->get('admin_email')]);

        return back()->with('ok', 'Đã lưu thông tin nghiên cứu và tên cột Google Sheets.');
    }

    public function updateSchools(Request $request, ResearchSettings $settings)
    {
        $data = $request->validate(['schools' => ['required', 'string', 'max:20000']]);

        try {
            $settings->updateSchools($data['schools']);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['schools' => $exception->getMessage()]);
        }

        Log::info('School settings updated', ['admin' => $request->session()->get('admin_email')]);
        return back()->with('ok', 'Đã lưu danh sách trường và chỉ tiêu.');
    }

    public function upsertAssignment(Request $request, ResearchSettings $settings)
    {
        $data = $request->validate([
            'school' => ['required', Rule::in(array_column($settings->schools(), 'code'))],
            'group' => ['required', Rule::in(array_keys(config('research.groups')))],
            'form_id' => ['required', 'string', 'max:500'],
            'sheet_csv_url' => ['required', 'url:http,https', 'max:1000'],
            'active' => ['nullable', 'boolean'],
            'entry_school' => ['required', 'digits_between:1,30'],
            'entry_group' => ['required', 'digits_between:1,30'],
            'entry_use' => ['required', 'digits_between:1,30'],
            'entry_level' => ['required', 'digits_between:1,30'],
            'entry_total' => ['required', 'digits_between:1,30'],
            'entry_digital' => ['required', 'digits_between:1,30'],
            'entry_note' => ['required', 'digits_between:1,30'],
        ]);

        if (strtolower((string) parse_url($data['sheet_csv_url'], PHP_URL_HOST)) !== 'docs.google.com') {
            return back()->withInput()->withErrors([
                'sheet_csv_url' => 'URL phải là liên kết Google Sheets trên miền docs.google.com.',
            ]);
        }

        $settings->upsertAssignment([
            'school' => $data['school'],
            'group' => $data['group'],
            'form_id' => $data['form_id'],
            'sheet_csv_url' => $data['sheet_csv_url'],
            'active' => $request->boolean('active'),
            'entries' => [
                'school' => $data['entry_school'],
                'group' => $data['entry_group'],
                'use' => $data['entry_use'],
                'level' => $data['entry_level'],
                'total' => $data['entry_total'],
                'digital' => $data['entry_digital'],
                'note' => $data['entry_note'],
            ],
        ]);

        Log::info('Google assignment updated', ['school' => $data['school'], 'group' => $data['group']]);
        return back()->with('ok', 'Đã gán Google Form và Google Sheet cho trường/nhóm.');
    }

    public function removeAssignment(string $id, ResearchSettings $settings)
    {
        $settings->removeAssignment($id);
        Log::info('Google assignment removed', ['assignment' => $id]);
        return back()->with('ok', 'Đã xóa cấu hình gán phiếu.');
    }

    public function responses(Request $request, ResearchSettings $settings, GoogleSheetsService $sheets)
    {
        $configuration = $settings->all();
        $sync = $sheets->responses($configuration);
        $filters = [
            'q' => trim((string) $request->query('q')),
            'school' => trim((string) $request->query('school')),
            'group' => trim((string) $request->query('group')),
            'date' => trim((string) $request->query('date')),
        ];
        $records = $sheets->filter($sync['records'], $configuration, $filters);
        $headers = $this->headers($records);

        return view('admin.google_responses', compact('configuration', 'sync', 'filters', 'records', 'headers'));
    }

    public function export(Request $request, ResearchSettings $settings, GoogleSheetsService $sheets)
    {
        $configuration = $settings->all();
        $sync = $sheets->responses($configuration);
        $filters = [
            'q' => trim((string) $request->query('q')),
            'school' => trim((string) $request->query('school')),
            'group' => trim((string) $request->query('group')),
            'date' => trim((string) $request->query('date')),
        ];
        $records = $sheets->filter($sync['records'], $configuration, $filters);
        $headers = $this->headers($records);

        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers);
        foreach ($records as $record) {
            fputcsv($stream, array_map(fn (string $header) => $record[$header] ?? '', $headers));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        Log::info('Google Sheet responses exported', ['rows' => count($records)]);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="google_survey_responses_'.now()->format('Ymd_His').'.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function headers(array $records): array
    {
        $headers = [];
        foreach ($records as $record) {
            foreach (array_keys($record) as $header) {
                if (! str_starts_with($header, '_') && ! in_array($header, $headers, true)) {
                    $headers[] = $header;
                }
            }
        }
        return $headers;
    }
}
