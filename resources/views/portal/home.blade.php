@extends('layout') @section('content')
    <div class="card"><span class="pill">Cổng nghiên cứu số · Prototype</span>
        <h1>Hạ tầng thu thập và quản lý dữ liệu nghiên cứu</h1>
        <p>Đề tài: Quản lý hoạt động ứng dụng công nghệ số trong dạy học cho sinh viên ngành Giáo dục mầm non ở các trường đại học tại TP.HCM.</p>
        <p class="muted">Hệ thống hỗ trợ ba nhóm đối tượng: cán bộ quản lý, giảng viên và sinh viên. Mỗi nhóm nhận liên kết riêng do nhóm nghiên cứu phát hành.
            Không yêu cầu họ tên, mã số sinh viên hoặc email ở bảng hỏi.</p>
        <div class="notice">Chỉ người được nhóm nghiên cứu cung cấp liên kết mới vào khảo sát. Mọi thông tin tham gia và bảng hỏi phải được phê duyệt trước khi mở
            thu thập. Trạng thái: <strong>{{ config('research.collection_open') ? 'Cho phép vận hành liên kết đã duyệt' : 'Chưa mở thu thập' }}</strong>.</div><a
            class="btn" href="{{ route('login') }}">Khu vực quản trị</a>
    </div>
    <div class="grid">
        <div class="card">
            <h2>01. Thông tin</h2>
            <p>Đọc mô tả mục đích, hoạt động, dữ liệu và quyền tham gia.</p>
        </div>
        <div class="card">
            <h2>02. Đồng thuận</h2>
            <p>Đồng thuận theo phiên bản thông tin được công bố.</p>
        </div>
        <div class="card">
            <h2>03. Khảo sát đúng nhóm</h2>
            <p>Câu hỏi có kiểm tra, phân nhánh và lưu nháp.</p>
        </div>
        <div class="card">
            <h2>04. Hoàn tất</h2>
            <p>Ghi mã Research ID và gửi yêu cầu hỗ trợ khi cần.</p>
        </div>
    </div>
@endsection
