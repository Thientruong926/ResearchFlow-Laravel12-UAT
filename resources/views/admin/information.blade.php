@extends('layout') @section('content')
    <h1>
        Participant Information Sheet + E-consent</h1>
    <div class="card">
        <h2>Phiên bản thông tin mới</h2>
        <p class="muted">Nhập nguyên văn nội dung đã được NCS/hồ sơ đạo đức phê duyệt. Tất cả trường bắt buộc.</p>
        <form action="{{ route('admin.information.add') }}" method="POST">@csrf @foreach (['purpose' => 'Mục đích', 'activities' => 'Hoạt động tham gia', 'duration' => 'Thời lượng', 'voluntary' => 'Tính tự nguyện', 'withdrawal' => 'Quyền từ chối/rút lui', 'contact' => 'Liên hệ hỗ trợ', 'data_use' => 'Cách sử dụng dữ liệu'] as $key => $name)
                <label>{{ $name }}</label>
                <textarea name="{{ $key }}" maxlength="10000" required>{{ old($key) }}</textarea>
            @endforeach
            <button>
                Lưu nháp bản mới</button>
        </form>
    </div>
    <div class="card">
        <h2>Version register</h2>
        <table>
            <tr>
                <th>Phiên bản</th>
                <th>Trạng thái</th>
                <th>Ngày duyệt</th>
                <th>Thao tác</th>
            </tr>
            @foreach ($versions as $v)
                <tr>
                    <td>v{{ $v->version }}</td>
                    <td>{{ $v->status }}</td>
                    <td>{{ $v->published_at ?? '–' }}</td>
                    <td>
                        @if ($v->status === 'draft')
                        <form method="POST" action="{{ route('admin.information.publish', $v->id) }}">@csrf<button>Xuất bản/khóa</button></form>@else<details>
                                <summary>Xem nội dung</summary>
                                <p>{{ $v->purpose }}</p>
                                <p>{{ $v->data_use }}</p>
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
