<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResearchSettingsRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->session()->get('admin_authenticated'); }

    public function rules(): array
    {
        return [
            'title'=>['required','string','max:300'],
            'introduction'=>['required','string','max:5000'],
            'consent'=>['required','string','max:5000'],
            'contact_name'=>['required','string','max:200'],
            'contact_email'=>['required','email','max:200'],
            'contact_phone'=>['nullable','string','max:30','regex:/^[0-9+().\s\-]+$/u'],
            'contact_address'=>['nullable','string','max:500'],
            'revision'=>['required','string','regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function attributes(): array
    {
        return ['title'=>'tiêu đề','introduction'=>'giới thiệu','consent'=>'đồng thuận','contact_name'=>'người liên hệ','contact_email'=>'email','contact_phone'=>'điện thoại','contact_address'=>'địa chỉ','revision'=>'phiên bản cấu hình'];
    }

    public function messages(): array
    {
        return ['required'=>':attribute là bắt buộc.', 'string'=>':attribute phải là văn bản.', 'max'=>':attribute không được dài quá :max ký tự.', 'email'=>'Email liên hệ không hợp lệ.', 'contact_phone.regex'=>'Điện thoại chỉ gồm số, khoảng trắng và các dấu + ( ) . -.', 'revision.regex'=>'Phiên bản cấu hình không hợp lệ. Vui lòng tải lại trang.'];
    }

    public function research(): array
    {
        $data = $this->safe()->except('revision');
        foreach ($data as &$value) $value = trim($value ?? '');
        $data += ['contact_phone'=>'','contact_address'=>''];
        return $data;
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): never
    {
        // Keep only editable public content, never arbitrary submitted integration/credential fields.
        throw new \Illuminate\Http\Exceptions\HttpResponseException(redirect()->route('admin.settings.research.edit')->withErrors($validator)->withInput($this->only(array_keys($this->rules()))));
    }
}
