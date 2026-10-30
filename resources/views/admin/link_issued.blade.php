@extends('layout')
@section('content')
<h1>Đã tạo link #{{ $id }}</h1>
<div class="card">
    <p>Link đang ở trạng thái nháp. Lưu URL và QR dưới đây để bàn giao cho người phụ trách điểm. Hệ thống chỉ lưu mã băm nên không thể hiển thị lại URL sau khi rời trang.</p>
    <label>URL điểm thu mẫu</label><input readonly value="{{ $url }}">
    <div style="max-width:360px">{!! $qr !!}</div>
    <p><a download="sampling-link-{{ $id }}.svg" href="data:image/svg+xml;base64,{{ base64_encode($qr) }}">Tải QR SVG</a></p>
    <p><a href="{{ route('admin.sites') }}">Về registry để kiểm tra và duyệt mở link</a></p>
</div>
@endsection
