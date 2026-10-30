<?php

namespace App\Http\Requests;

use App\Services\ConnectionSources;
use App\Services\ResearchSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResponseFilterRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->session()->get('admin_authenticated'); }

    protected function prepareForValidation(): void
    {
        // Preserve the old single-day URL without guessing or overwriting a supplied range.
        if ($this->filled('date')) {
            $this->merge(['date_from' => $this->input('date_from') ?? $this->input('date'), 'date_to' => $this->input('date_to') ?? $this->input('date')]);
        }
        if ($this->routeIs('admin.responses.export')) $this->merge(['page' => 1, 'per_page' => 25]);
    }

    public function rules(): array
    {
        $settings = app(ResearchSettings::class)->all();
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'school' => ['nullable', 'string', Rule::in(array_column($settings['schools'], 'code'))],
            'group' => ['nullable', 'string', Rule::in(array_keys(config('research.groups')))],
            'source' => ['nullable', 'string', Rule::in(array_keys(app(ConnectionSources::class)->all($settings)))],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $data = $this->validated();
        return [
            'q' => trim($data['q'] ?? ''), 'school' => $data['school'] ?? '', 'group' => $data['group'] ?? '',
            'source' => $data['source'] ?? '', 'date_from' => $data['date_from'] ?? '', 'date_to' => $data['date_to'] ?? '',
            'sort' => $data['sort'] ?? 'newest', 'page' => (int) ($data['page'] ?? 1), 'per_page' => (int) ($data['per_page'] ?? 25),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        // Do not flash search text, arbitrary query input or tokens into session.
        throw new HttpResponseException(response()->view('admin.response_error', ['message' => 'Bộ lọc không hợp lệ. Kiểm tra độ dài từ khóa, trường/nhóm/nguồn, khoảng ngày và số dòng mỗi trang.', 'invalidFields' => $validator->errors()->keys()], 422));
    }
}
