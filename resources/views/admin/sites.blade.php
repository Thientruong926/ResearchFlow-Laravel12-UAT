@extends('layout') @section('content')
    <h1>
        Registry điểm mẫu, đợt và QR/link</h1>
    <div class="grid">
        <div class="card">
            <h2>Tạo mã điểm mẫu</h2>
            <form method="POST" action="{{ route('admin.site.add') }}">@csrf<label>Mã cơ sở (không phải tên thật)</label><input name="code" placeholder="SITE_01"
                    required><label>Ghi chú nội bộ (không xuất dữ liệu phân tích)</label><input name="private_label"><button>Tạo điểm mẫu</button></form>
        </div>
        <div class="card">
            <h2>Tạo đợt thu thập</h2>
            <form method="POST" action="{{ route('admin.wave.add') }}">@csrf<label>Mã đợt</label><input name="code" placeholder="WAVE_01" required><button>Tạo
                    đợt</button></form>
        </div>
        <div class="card">
            <h2>Tạo liên kết theo nhóm</h2>
            <form method="POST" action="{{ route('admin.link.add') }}">@csrf<label>Điểm</label><select name="site_id" required>
                    @foreach ($sites as $s)
                        <option value="{{ $s->id }}">{{ $s->code }}</option>
                    @endforeach
                </select>
                <label>Đợt</label><select name="wave_id" required>
                    @foreach ($waves as $w)
                        <option value="{{ $w->id }}">{{ $w->code }}</option>
                    @endforeach
                </select>
                <label>Đối tượng</label><select name="group_code">
                    @foreach (config('research.groups') as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>
                <button>Tạo link (tắt mặc định)</button>
            </form>
        </div>
    </div>
    <div class="card">
        <h2>Link đã tạo</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Mã điểm</th>
                    <th>Đợt</th>
                    <th>Nhóm</th>
                    <th>Link/QR</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
                @foreach ($links as $l)
                    <tr>
                        <td>{{ $l->site }}</td>
                        <td>{{ $l->wave }}</td>
                        <td>{{ $l->group_code }}</td>
                        <td><a href="{{ route('portal.intro', $l->token) }}" target="_blank" rel="noopener">Mở đường dẫn</a> · <a
                                href="{{ route('admin.link.qr', $l->id) }}" target="_blank" rel="noopener">QR SVG</a>
                            <details>
                                <summary>Địa chỉ chia sẻ</summary><input readonly value="{{ route('portal.intro', $l->token) }}">
                            </details>
                        </td>
                        <td>{{ $l->active ? 'Đang mở' : 'Đang tắt' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.link.toggle', $l->id) }}">@csrf<button
                                    class="{{ $l->active ? 'warn' : 'alt' }}">{{ $l->active ? 'Tắt' : 'Bật' }}</button></form>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
        <p class="muted small">Mở QR SVG để in hoặc chia sẻ trong phạm vi điểm thu mẫu. QR chứa link nghiên cứu, không chứa tên cá nhân.</p>
    </div>
@endsection
