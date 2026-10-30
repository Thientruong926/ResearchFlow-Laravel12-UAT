@extends('google_layout')
@section('title', 'Nội dung nghiên cứu')
@section('content')
<h1>Nội dung nghiên cứu và liên hệ</h1>
<p><a href="{{ route('admin.settings') }}">Cấu hình trường và kết nối</a> · <a href="{{ route('home') }}">Trang người tham gia</a></p>
@if(isset($saveError))<div class="error" role="alert">{{ $saveError }} Nội dung vừa nhập vẫn được giữ bên dưới. Hãy sao chép nội dung cần giữ trước khi <a href="{{ route('admin.settings.research.edit') }}">tải bản mới nhất</a>.</div>@endif
@if($preview)
<div class="notice" role="status">Xem trước — chưa lưu thay đổi.</div>
@include('partials.research_intro')
<section class="card">@include('partials.research_consent') @include('partials.research_contact')</section>
@endif
<section class="card"><form method="POST" action="{{ route('admin.settings.research') }}">
@csrf<input type="hidden" name="revision" value="{{ old('revision', $revision) }}">
@foreach(['title'=>'Tiêu đề nghiên cứu','introduction'=>'Giới thiệu nghiên cứu','consent'=>'Nội dung đồng thuận','contact_name'=>'Người/nhóm liên hệ','contact_email'=>'Email liên hệ','contact_phone'=>'Điện thoại (không bắt buộc)','contact_address'=>'Địa chỉ (không bắt buộc)'] as $field=>$label)
<label for="{{ $field }}">{{ $label }}</label>
@if(in_array($field, ['introduction','consent','contact_address']))
<textarea id="{{ $field }}" name="{{ $field }}" maxlength="{{ $field === 'contact_address' ? 500 : 5000 }}" @required($field !== 'contact_address') aria-describedby="error-{{ $field }}">{{ old($field,$research[$field] ?? '') }}</textarea>
@else
<input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'contact_email' ? 'email' : 'text' }}" value="{{ old($field,$research[$field] ?? '') }}" maxlength="{{ $field === 'title' ? 300 : ($field === 'contact_phone' ? 30 : 200) }}" @required($field !== 'contact_phone') aria-describedby="error-{{ $field }}">
@endif
<span id="error-{{ $field }}" class="small" style="color:var(--bad)">@error($field){{ $message }}@enderror</span>
@endforeach
@error('revision')<p class="error">{{ $message }} Vui lòng tải lại trang.</p>@enderror
<div class="actions"><button class="secondary" formaction="{{ route('admin.settings.research.preview') }}">Xem trước</button><button>Lưu thay đổi</button></div>
</form></section>
@endsection
