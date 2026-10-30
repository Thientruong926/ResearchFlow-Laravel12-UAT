@extends('google_layout')
@section('title', 'Cài đặt nghiên cứu')
@section('content')
<h1>Cài đặt nghiên cứu và kết nối Google</h1>
<div class="notice"><strong>Đồng bộ Google:</strong> {{ $syncEnabled ? 'ĐANG BẬT' : 'ĐANG TẮT' }}. Thay đổi bằng <code>GOOGLE_SYNC_ENABLED</code> trong <code>.env</code>, sau đó chạy <code>php artisan optimize:clear</code>.</div>
<section class="card"><h2>Thông tin nghiên cứu và liên hệ</h2><a class="btn" href="{{ route('admin.settings.research.edit') }}">Sửa nội dung / xem trước</a></section>
<section class="card"><h2>Mapping Google Sheets</h2>
<form method="POST" action="{{ route('admin.settings.columns') }}">@csrf<input type="hidden" name="revision" value="{{ old('revision',$settings['_revision']) }}">
    <h3>Tên cột trong Google Sheets</h3><div class="grid"><div><label>Cột thời gian</label><input name="timestamp_column" value="{{ old('timestamp_column',$settings['sheet_columns']['timestamp']) }}" required></div><div><label>Cột mã trường</label><input name="school_column" value="{{ old('school_column',$settings['sheet_columns']['school']) }}" required></div><div><label>Cột nhóm</label><input name="group_column" value="{{ old('group_column',$settings['sheet_columns']['group']) }}" required></div></div>
    <div class="actions"><button>Lưu thông tin</button></div>
</form></section>
<section class="card"><h2>Danh sách trường và chỉ tiêu</h2><p class="muted">Mỗi dòng: <code>MÃ|Tên trường|Chỉ tiêu CBQL|Chỉ tiêu GV|Chỉ tiêu SV</code>.</p>
<form method="POST" action="{{ route('admin.settings.schools') }}">@csrf<input type="hidden" name="revision" value="{{ old('revision',$settings['_revision']) }}"><textarea name="schools" rows="7" required>{{ old('schools',$schoolLines) }}</textarea><div class="actions"><button>Lưu danh sách trường</button></div></form></section>
<section class="card"><h2>Gán Google Form và Google Sheet</h2><p class="muted">CSV chỉ dành cho dữ liệu demo được phép công khai. Sheet thật riêng tư cần adapter API chỉ đọc. Các entry ID lấy từ liên kết điền trước của Google Form.</p>
<form method="POST" action="{{ route('admin.settings.assignments') }}">@csrf<input type="hidden" name="revision" value="{{ old('revision',$settings['_revision']) }}">
    <div class="grid"><div><label>Trường</label><select name="school" required>@foreach($settings['schools'] as $school)<option value="{{ $school['code'] }}">{{ $school['name'] }}</option>@endforeach</select></div><div><label>Nhóm</label><select name="group" required>@foreach($groups as $code=>$name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select></div></div>
    <label>Google Form ID hoặc URL</label><input name="form_id" required placeholder="https://docs.google.com/forms/d/e/.../viewform">
    <label>Google Sheet URL</label><input type="url" name="sheet_csv_url" required placeholder="https://docs.google.com/spreadsheets/d/.../edit#gid=0">
    <h3>Entry ID của các trường trong Google Form</h3><div class="grid">
        @foreach(['school'=>'Mã trường','group'=>'Nhóm','use'=>'Đã sử dụng','level'=>'Mức độ','total'=>'Tổng số buổi','digital'=>'Số buổi dùng số','note'=>'Góp ý'] as $key=>$label)<div><label>{{ $label }}</label><input name="entry_{{ $key }}" inputmode="numeric" pattern="\d+" required placeholder="123456789"></div>@endforeach
    </div>
    <label class="choice"><input type="checkbox" name="active" value="1"> Mở phiếu này cho người tham gia</label><div class="actions"><button>Lưu cấu hình gán phiếu</button></div>
</form></section>
<section class="card"><h2>Các phiếu đã gán</h2><div class="scroll"><table><thead><tr><th>Trường</th><th>Nhóm</th><th>Form</th><th>Sheet</th><th>Trạng thái</th><th></th></tr></thead><tbody>
@forelse($settings['assignments'] as $assignment)<tr><td>{{ $assignment['school'] }}</td><td>{{ $groups[$assignment['group']] ?? $assignment['group'] }}</td><td><code>{{ Str::limit($assignment['form_id'],24) }}</code></td><td><a href="{{ $assignment['sheet_csv_url'] }}" target="_blank" rel="noopener">Mở Sheet</a></td><td>{{ $assignment['active']?'Đang mở':'Đang tắt' }}</td><td><form method="POST" action="{{ route('admin.settings.assignments.delete',$assignment['id']) }}">@csrf<input type="hidden" name="revision" value="{{ $settings['_revision'] }}"><button class="danger">Xóa</button></form></td></tr>
@empty<tr><td colspan="6">Chưa có cấu hình.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
