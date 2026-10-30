@extends('google_layout')
@section('title', 'Chưa thể cập nhật cấu hình')
@section('content')
<h1>Chưa thể hoàn tất yêu cầu</h1><div class="error" role="alert">{{ $message }}</div>
@if(session('admin_authenticated'))<a href="{{ route('admin.settings') }}">Tải lại cấu hình</a>@endif
@endsection
