<?php

namespace App\Http\Controllers;

use App\Services\GoogleFormsService;
use App\Services\GoogleFormQuestionImporter;
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
        $connections = [];
        foreach (app(\App\Services\ConnectionSources::class)->all($configuration) as $source) {
            $state = app(\App\Services\ConnectionHealthService::class)->snapshot($source);
            foreach ($source['assignments'] as $assignment) {
                $connections[] = $assignment + [
                    'form_status' => $state['forms'][$assignment['id']] ?? ['ok' => false, 'message' => 'Chưa kiểm tra — xem trang Kết nối'],
                    'sheet_status' => [
                        'ok' => $state['status'] === 'ok' && ! $state['stale'],
                        'message' => $state['stale'] ? 'Kết quả đã cũ — xem trang Kết nối' : match ($state['status']) {
                            'ok' => 'Đọc Sheet thành công', 'disabled' => 'Tắt', 'unconfigured' => 'Chưa cấu hình',
                            'error' => 'Lỗi đọc Sheet', default => 'Chưa kiểm tra — xem trang Kết nối',
                        },
                    ],
                ];
            }
        }

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

    public function updateColumns(Request $request, ResearchSettings $settings)
    {
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:64'],
            'timestamp_column' => ['required', 'string', 'max:200'],
            'school_column' => ['required', 'string', 'max:200'],
            'group_column' => ['required', 'string', 'max:200'],
        ]);

        $settings->updateColumns(
            [
                'timestamp' => $data['timestamp_column'],
                'school' => $data['school_column'],
                'group' => $data['group_column'],
            ], $data['revision'], (string) $request->session()->get('admin_email', 'admin')
        );
        return back()->with('ok', 'Đã lưu tên cột Google Sheets.');
    }

    public function updateSchools(Request $request, ResearchSettings $settings)
    {
        $data = $request->validate(['schools' => ['required', 'string', 'max:20000'], 'revision' => ['required','string','size:64']]);

        try {
            $settings->updateSchools($data['schools'], $data['revision'], (string) $request->session()->get('admin_email', 'admin'));
        } catch (RuntimeException $exception) {
            if ($exception instanceof \App\Exceptions\SettingsStorageException || $exception instanceof \Symfony\Component\HttpKernel\Exception\ConflictHttpException) throw $exception;
            return back()->withInput()->withErrors(['schools' => $exception->getMessage()]);
        }

        Log::info('School settings updated', ['admin' => $request->session()->get('admin_email')]);
        return back()->with('ok', 'Đã lưu danh sách trường và chỉ tiêu.');
    }

    public function upsertAssignment(Request $request, ResearchSettings $settings)
    {
        $data = $request->validate([
            'revision' => ['required','string','size:64'],
            'school' => ['required', Rule::in(array_column($settings->schools(), 'code'))],
            'group' => ['required', Rule::in(array_keys(config('research.groups')))],
            'form_id' => ['required', 'string', 'max:500'],
            'sheet_csv_url' => ['required', 'url:http,https', 'max:1000'],
            'active' => ['nullable', 'boolean'],
            'entry_school' => ['required', 'digits_between:1,30'],
            'entry_group' => ['required', 'digits_between:1,30'],
            'entry_use' => ['nullable', 'digits_between:1,30'],
            'entry_level' => ['nullable', 'digits_between:1,30'],
            'entry_total' => ['nullable', 'digits_between:1,30'],
            'entry_digital' => ['nullable', 'digits_between:1,30'],
            'entry_note' => ['nullable', 'digits_between:1,30'],
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
                'use' => $data['entry_use'] ?? null,
                'level' => $data['entry_level'] ?? null,
                'total' => $data['entry_total'] ?? null,
                'digital' => $data['entry_digital'] ?? null,
                'note' => $data['entry_note'] ?? null,
            ],
        ], $data['revision'], (string) $request->session()->get('admin_email', 'admin'));

        Log::info('Google assignment updated', ['school' => $data['school'], 'group' => $data['group']]);
        return back()->with('ok', 'Đã gán Google Form và Google Sheet cho trường/nhóm.');
    }

    public function syncAssignment(
        string $id,
        Request $request,
        ResearchSettings $settings,
        GoogleFormQuestionImporter $importer
    ) {
        $data = $request->validate(['revision' => ['required', 'string', 'size:64']]);
        $assignment = $settings->assignmentById($id);
        abort_unless($assignment, 404);

        try {
            $import = $importer->import((string) ($assignment['form_id'] ?? ''));
            $settings->syncAssignmentQuestions(
                $id,
                $import,
                $data['revision'],
                (string) $request->session()->get('admin_email', 'admin')
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['sync' => $exception->getMessage()]);
        }

        Log::info('Google Form questions synchronized', [
            'assignment' => $id,
            'questions' => count($settings->assignmentById($id)['questions'] ?? []),
            'source' => $import['source'],
        ]);

        $message = 'Đã đồng bộ '.count($settings->assignmentById($id)['questions'] ?? []).' câu hỏi hiển thị từ Google Form.';
        if ($import['warnings'] !== []) {
            $message .= ' Có '.count($import['warnings']).' cảnh báo cần xem.';
        }

        return back()->with('ok', $message);
    }

    public function removeAssignment(string $id, Request $request, ResearchSettings $settings)
    {
        $data = $request->validate(['revision'=>['required','string','size:64']]);
        $settings->removeAssignment($id, $data['revision'], (string) $request->session()->get('admin_email', 'admin'));
        Log::info('Google assignment removed', ['assignment' => $id]);
        return back()->with('ok', 'Đã xóa cấu hình gán phiếu.');
    }

}
