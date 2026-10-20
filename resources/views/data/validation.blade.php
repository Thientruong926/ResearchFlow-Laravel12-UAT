@extends('layout') @section('content')
    <h1>Export validation · Đối soát</h1>
    <div class="grid">
        <div class="card">
            <p>Session hoàn tất trong DB</p>
            <h1>{{ $raw }}</h1>
        </div>
        <div class="card">
            <p>Research ID khác nhau</p>
            <h1>{{ $distinct }}</h1>
        </div>
        <div class="card">
            <p>Session QC Valid</p>
            <h1>{{ $valid }}</h1>
        </div>
        <div class="card">
            <p>Session đủ điều kiện vào clean export</p>
            <h1>{{ $clean }}</h1>
        </div>
        <div class="card">
            <p>Giá trị DB NULL</p>
            <h1>{{ $missing }}</h1>
        </div>
        <div class="card">
            <p>JSON không hợp lệ</p>
            <h1>{{ $invalid }}</h1>
        </div>
    </div>
    <p>Nguyên tắc kiểm tra: số dòng CSV raw = {{ $raw }}; clean = {{ $clean }}. Xuất CSV UTF-8 BOM và kiểm tra dấu tiếng Việt, dấu phân cách, mã lựa
        chọn, số thập phân trong SPSS import wizard. Đối soát trên dữ liệu phát sinh khi chạy, không tuyên bố kiểm tra thực địa đã hoàn tất.</p>
@endsection
