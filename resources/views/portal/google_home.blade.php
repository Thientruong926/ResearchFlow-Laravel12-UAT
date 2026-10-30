@extends('google_layout')
@section('title', $research['title'])
@section('content')
@include('partials.research_intro')

<div class="grid" style="margin-top:18px">
    <article class="card"><h2>1. Chọn đúng đối tượng</h2><p class="muted">Chọn trường và nhóm tham gia để hệ thống mở đúng phiếu khảo sát.</p></article>
    <article class="card"><h2>2. Trả lời thuận tiện</h2><p class="muted">Giao diện tối ưu cho máy tính và điện thoại, có kiểm tra câu bắt buộc.</p></article>
    <article class="card"><h2>3. Gửi an toàn</h2><p class="muted">Câu trả lời được chuyển trực tiếp đến Google Forms, website không lưu database.</p></article>
</div>

<section class="card" style="max-width:760px;margin:24px auto">
    <h2>Bắt đầu khảo sát</h2>
    @include('partials.research_consent')
    @unless($syncEnabled)<div class="notice"><strong>Chế độ cấu hình:</strong> Đồng bộ Google đang tắt. Người quản lý cần hoàn tất kết nối trước khi nhận phản hồi.</div>@endunless
    <form method="POST" action="{{ route('survey.start') }}">@csrf
        <label for="school">Trường tham gia</label>
        <select name="school" id="school" required>
            <option value="">-- Chọn trường --</option>
            @foreach($schools as $school)<option value="{{ $school['code'] }}" @selected(old('school') === $school['code'])>{{ $school['name'] }}</option>@endforeach
        </select>
        <label>Nhóm đối tượng tham gia</label>
        <div class="inline-choice">
            @foreach($groups as $code => $name)<label class="choice"><input type="radio" name="group" value="{{ $code }}" required @checked(old('group') === $code)>{{ $name }}</label>@endforeach
        </div>
        <div class="actions"><button>Tiếp tục đến phiếu khảo sát →</button></div>
    </form>
    @include('partials.research_contact')
</section>
@endsection
