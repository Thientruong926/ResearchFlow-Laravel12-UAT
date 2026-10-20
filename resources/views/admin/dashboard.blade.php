@extends('layout') @section('content')
    <h1>
        Tổng quan tiến độ nghiên cứu</h1>
    <p class="muted">Dashboard tổng hợp, không hiển thị danh tính người tham gia. Số liệu đếm trực tiếp từ cơ sở dữ liệu.</p>
    <div class="grid">
        @foreach (['sites' => 'Điểm thu mẫu', 'links' => 'Link/QR', 'participants' => 'Research ID', 'complete' => 'Đã hoàn tất', 'drafts' => 'Đang làm dở', 'flags' => 'Cờ QC', 'requests' => 'Yêu cầu chưa xử lý'] as $key => $label)
            <div class="card">
                <p>{{ $label }}</p>
                <h1>{{ $counts[$key] }}</h1>
            </div>
        @endforeach
    </div>
    @if (auth()->user()->hasRole('owner','researcher','quota_manager'))
    <div class="card">
        <h2>Tiến độ theo điểm mẫu / đợt / nhóm</h2>
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mã điểm</th>
                        <th>Đợt</th>
                        <th>Nhóm</th>
                        <th>Khởi tạo</th>
                        <th>Hoàn tất</th>
                        <th>Tỷ lệ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r->site_code }}</td>
                            <td>{{ $r->wave_code }}</td>
                            <td>{{ $r->group_code }}</td>
                            <td>{{ $r->suppressed ? '[ẩn: ô nhỏ]' : $r->total }}</td>
                            <td>{{ $r->suppressed ? '–' : $r->complete }}</td>
                            <td>{{ $r->suppressed ? '–' : (($r->total ? round((100 * $r->complete) / $r->total, 1) : 0).'%') }}</td>
                    </tr>@empty<tr>
                            <td colspan="6">Chưa có phiên khảo sát.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h2>Cảnh báo quota · Dựa trên cấu hình đã duyệt</h2>
        @if(auth()->user()->hasRole('owner','researcher'))<p><a class="btn alt" href="{{ route('admin.reports') }}">Xem các snapshot định kỳ lưu riêng tư</a></p>@endif
        <p>
            @if (auth()->user()->hasRole('owner', 'researcher', 'quota_manager'))
                <a class="btn" href="{{ route('admin.snapshot') }}">Xuất snapshot quota/tiến độ CSV</a>
            @endif
        </p>
        <div class="scroll">
            <table>
                <tr>
                    <th>Điểm/đợt</th>
                    <th>Nhóm</th>
                    <th>Chỉ tiêu</th>
                    <th>Hoàn tất</th>
                    <th>Chênh lệch</th>
                    <th>Tình trạng</th>
                </tr>
                @forelse($alerts as $a)
                    <tr>
                        <td>{{ $a->site_code }} / {{ $a->wave_code }}</td>
                        <td>{{ $a->group_code }}</td>
                        <td>{{ $a->target }}</td>
                        <td>{{ $a->suppressed ? '[ẩn: ô nhỏ]' : $a->completed }}</td>
                        <td>{{ $a->suppressed ? '–' : $a->completed - $a->target }}</td>
                        <td>{{ $a->suppressed ? 'Ẩn theo ngưỡng bảo mật' : ($a->completed > $a->target ? 'Vượt quota' : ($a->completed < $a->target ? 'Thiếu quota' : 'Đạt')) }}</td>
                </tr>@empty<tr>
                        <td colspan="6">Chưa có quota được duyệt. Không tự đặt chỉ tiêu.</td>
                    </tr>
                @endforelse
            </table>
        </div>
    </div>
    @else
        <p class="muted">Vai trò này chỉ xem số liệu tổng quan; chi tiết nhóm và điểm mẫu dành cho nhóm quản trị nghiên cứu. Những ô ít hơn ngưỡng bảo mật sẽ được ẩn.</p>
    @endif
    @if (auth()->user()->hasRole('owner'))
        <p><a href="{{ route('admin.audit') }}">Nhật ký truy cập/quản trị (chỉ owner)</a></p>
    @endif
@endsection
