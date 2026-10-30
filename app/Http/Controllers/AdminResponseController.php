<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResponseFilterRequest;
use App\Services\ConnectionSources;
use App\Services\ResearchSettings;
use App\Services\ResponseQueryService;
use App\Services\ResponseReference;
use App\Services\SurveyCsvExporter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminResponseController extends Controller
{
    public function __construct(private ResearchSettings $settings, private ConnectionSources $catalog, private ResponseQueryService $query, private ResponseReference $references) {}

    public function index(ResponseFilterRequest $request)
    {
        $configuration = $this->settings->all();
        $sources = $this->catalog->all($configuration);
        $filters = $request->filters();
        $data = $this->query->read($sources, $filters);
        $records = $this->query->filter($data['records'], $filters);
        $totalRead = count($data['records']);
        unset($data['records']);
        $page = new LengthAwarePaginator(array_slice($records, ($filters['page'] - 1) * $filters['per_page'], $filters['per_page']), count($records), $filters['per_page'], $filters['page'], ['path' => route('admin.responses'), 'query' => array_diff_key($filters, ['page' => true])]);
        $tokens = [];
        foreach ($page as $record) $tokens[$record->sourceId.':'.$record->sourceRowNumber] = $this->references->issue($record, $sources[$record->sourceId]['revision']);
        return view('admin.google_responses', compact('configuration', 'sources', 'filters', 'data', 'totalRead', 'page', 'tokens'));
    }

    public function export(ResponseFilterRequest $request, SurveyCsvExporter $exporter)
    {
        $filters = $request->filters();
        $data = $this->query->read($this->catalog->all($this->settings->all()), $filters);
        if ($data['errors'] || ! $data['total_sources']) return response()->view('admin.response_error', ['message' => 'Không thể xuất dữ liệu đầy đủ. Chọn nguồn khỏe hoặc kiểm tra kết nối.', 'sourceErrors' => $data['errors']], 503);
        $records = $this->query->filter($data['records'], $filters);
        \Illuminate\Support\Facades\Log::info('Responses CSV exported', [
            'admin' => (string) $request->session()->get('admin_email', 'admin'),
            'rows' => count($records),
            'source' => $filters['source'] ?: 'all',
        ]);
        return $exporter->download($records, $data['columns']);
    }

    public function detail(Request $request)
    {
        $token = $request->query('ref');
        if (! is_string($token) || strlen($token) > 4096) return $this->error('Liên kết không hợp lệ. Tải lại danh sách.', 400);
        try {
            $reference = $this->references->decode($token);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            return $this->error($error->getMessage(), $error->getStatusCode());
        }
        $sources = $this->catalog->all($this->settings->all());
        $source = $sources[$reference['source']] ?? null;
        if (! $source || ! hash_equals($source['revision'], $reference['revision'])) return $this->error('Cấu hình nguồn đã thay đổi. Tải lại danh sách.', 409);
        // CSV rereads the selected source; an API implementation can optimize this later.
        $data = $this->query->read($sources, ['source' => $source['source_id']]);
        if ($data['errors']) return $this->error('Không đọc được nguồn phản hồi. Kiểm tra kết nối và thử lại.', 503);
        $record = collect($data['records'])->first(fn ($r) => $r->sourceRowNumber === $reference['row']);
        if (! $record || ! hash_equals($reference['hash'], $this->references->fingerprint($record))) return $this->error('Phản hồi đã bị sửa, xóa hoặc thay đổi vị trí. Tải lại danh sách để tránh xem nhầm.', 409);
        $columns = $data['columns'];
        return view('admin.response_detail', compact('record', 'columns'));
    }

    private function error(string $message, int $status)
    {
        return response()->view('admin.response_error', compact('message'), $status);
    }
}
