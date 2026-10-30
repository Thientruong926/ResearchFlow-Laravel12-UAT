@extends('google_layout')
@section('title', 'Chưa thể đọc dữ liệu')
@section('content')
<h1>Chưa thể hoàn tất yêu cầu</h1>
<div class="error" role="alert">{{ $message }}
@if(!empty($invalidFields))<p>Trường cần kiểm tra: {{ implode(', ', $invalidFields) }}</p>@endif
@if(!empty($sourceErrors))<ul>@foreach($sourceErrors as $source=>$error)<li>{{ $source }}: {{ $error }}</li>@endforeach</ul>@endif</div>
<div class="actions"><a class="btn" href="{{ route('admin.responses') }}">Tải lại danh sách</a><a href="{{ route('admin.connections') }}">Kiểm tra kết nối</a></div>
@endsection
