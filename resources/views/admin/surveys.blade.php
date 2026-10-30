@extends('layout') @section('content')
    <h1>
        Quản lý bảng hỏi theo nhóm</h1>
    <div class="notice">Không tự thêm câu hỏi nội dung khoa học. Chỉ công bố phiên bản đã được nghiên cứu sinh/phụ trách chuyên môn phê duyệt. Sau công bố, câu hỏi
        bị khóa, mọi thay đổi dùng phiên bản mới.</div>
    <div class="card">
        <h2>Phiên bản bảng hỏi mới</h2>
        <form method="POST" action="{{ route('admin.surveys.add') }}">@csrf<label>Nhóm đối tượng</label><select name="group_code">
                @foreach (config('research.groups') as $code => $name)
                    <option value="{{ $code }}">{{ $name }}</option>
                @endforeach
            </select>
            <label>Bộ phiếu</label><select name="instrument_id"><option value="">Bản cũ chưa phân loại</option><option>OBS2</option><option>OBS4</option></select>
            <label>Mã phiên bản phiếu</label><input name="form_version" maxlength="64" placeholder="DEMO-0.1">
            <label>Tiêu đề đã duyệt</label><input name="title" required><button>Tạo bản nháp</button>
        </form>
    </div>
    <div class="card">
        <h2>Version register</h2>
        <table>
            <tr>
                <th>Nhóm</th>
                <th>Phiên bản</th>
                <th>Tiêu đề</th>
                <th>Trạng thái</th>
                <th>Ngày phát hành</th>
            </tr>
            @foreach ($surveys as $s)
                <tr>
                    <td>{{ $s->group_code }}</td>
                    <td><a href="{{ route('admin.survey.edit', $s->id) }}">v{{ $s->version }}</a></td>
                    <td>{{ $s->title }}<br>{{ $s->instrument_id }} / {{ $s->form_version }}</td>
                    <td>{{ $s->status }}</td>
                    <td>{{ $s->published_at ?? '–' }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
