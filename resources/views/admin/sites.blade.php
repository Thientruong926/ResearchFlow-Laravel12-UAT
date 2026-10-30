@extends('layout') @section('content')
    <h1>
        Registry điểm mẫu, đợt và QR/link</h1>
    @if(auth()->user()->hasRole('owner','researcher','NCS'))<p><a href="{{ route('admin.integrity') }}">Cờ trùng kỹ thuật</a> · <a href="{{ route('data.long.raw') }}">Xuất raw dạng dài theo hồ sơ B</a></p>@endif
    @if(auth()->user()->hasRole('owner','researcher','IT_ADMIN'))
    <div class="grid">
        <div class="card">
            <h2>Tạo mã điểm mẫu</h2>
            <form method="POST" action="{{ route('admin.site.add') }}">@csrf<label>Mã cơ sở (để trống để sinh mã S tự động)</label><input name="code" placeholder="S-ABCDEFGHIJ"><label>Ghi chú nội bộ (không xuất dữ liệu phân tích)</label><input name="private_label"><button>Tạo điểm mẫu</button></form>
        </div>
        <div class="card">
            <h2>Tạo đợt thu thập</h2>
            <form method="POST" action="{{ route('admin.wave.add') }}">@csrf<label>Mã đợt (để trống để sinh tự động)</label><input name="code" placeholder="W-ABCDEFGHIJ">
                <label>Bộ phiếu</label><select name="instrument_id"><option>OBS2</option><option>OBS4</option></select>
                <label>Bắt đầu (giờ Việt Nam)</label><input type="datetime-local" name="valid_from" required>
                <label>Kết thúc (giờ Việt Nam)</label><input type="datetime-local" name="valid_until" required>
                <label>Năm học tham chiếu</label><input name="academic_year" maxlength="32">
                <label>Học kỳ tham chiếu</label><input name="semester" maxlength="32"><button>Tạo
                    đợt</button></form>
        </div>
        <div class="card">
            <h2>Tạo khoa/ngành</h2>
            <form method="POST" action="{{ route('admin.department.add') }}">@csrf
                <label>Cơ sở</label><select name="site_id">@foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->code }}</option>@endforeach</select>
                <label>Mã khoa (để trống để sinh mã D tự động)</label><input name="code" placeholder="D-ABCDEFGHIJ">
                <label>Tên nội bộ</label><input name="private_label"><button>Tạo khoa/ngành</button>
            </form>
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
                    @foreach (array_intersect_key(config('research.groups'), array_flip(['BGH','KHOA','GV','SV'])) as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>
                <label>Khoa/ngành (tùy chọn, phải thuộc cơ sở đã chọn)</label><select name="department_id"><option value="">Không áp dụng</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->code }} · cơ sở #{{ $d->site_id }}</option>@endforeach</select>
                <label>Mã điểm</label><input name="point_code" required maxlength="32" placeholder="P-0001">
                <label>Phiếu đích cố định</label><select name="survey_id" required>@foreach($surveys as $s)@if($s->instrument_id && $s->form_version)<option value="{{ $s->id }}">{{ $s->instrument_id }} / {{ $s->form_version }} / {{ $s->group_code }} / #{{ $s->id }}</option>@endif @endforeach</select>
                <label>Người quản lý link</label><select name="owner_id" required>@foreach($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select>
                <label>Bắt đầu (giờ Việt Nam)</label><input type="datetime-local" name="valid_from" required>
                <label>Kết thúc (giờ Việt Nam)</label><input type="datetime-local" name="valid_until" required>
                <label>Quyết định D11 khi link bị dừng/thu hồi hoặc hết hạn</label><select name="existing_session_policy" required><option value="">Chọn theo quyết định của NCS</option><option value="continue">Cho phiên đang mở tiếp tục nộp</option><option value="stop">Dừng cả phiên đang mở</option></select>
                <label>Mã lớp (tùy chọn)</label><input name="class_code" maxlength="32" pattern="[A-Z0-9_-]{2,32}" placeholder="CLASS_01">
                <p class="muted small">Dùng mã theo bảng mã của nghiên cứu; không nhập tên cá nhân.</p>
                <button>Tạo link (tắt mặc định)</button>
            </form>
        </div>
    </div>
    @endif
    @if(auth()->user()->hasRole('owner','researcher','NCS'))
    <div class="card"><h2>Danh mục cơ sở nội bộ</h2><table><tr><th>Mã cơ sở</th><th>Tên/ghi chú hạn chế truy cập</th></tr>
        @foreach($sites as $site)<tr><td>{{ $site->code }}</td><td>{{ $site->private_label }}</td></tr>@endforeach
    </table><p class="muted small">Tên nội bộ không đi vào QR hoặc tệp phân tích.</p></div>
    @endif
    <div class="card">
        <h2>Link đã tạo</h2>
        <p><a href="{{ route('admin.link.registry') }}">Tải sổ đăng ký link CSV</a></p>
        <div class="scroll">
            <table>
                <tr>
                    <th>Mã điểm</th>
                    <th>Đợt</th>
                    <th>Nhóm</th>
                    <th>Khoa / lớp</th>
                    <th>Link/QR</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
                @foreach ($links as $l)
                    <tr>
                        <td>{{ $l->site }}</td>
                        <td>{{ $l->wave }}</td>
                        <td>{{ $l->group_code }}</td>
                        <td>{{ $departments->firstWhere('id', $l->department_id)?->code ?? '—' }} / {{ $l->class_code ?? '—' }}<br>{{ $l->point_code }} · phiếu #{{ $l->survey_id }}</td>
                        <td>@if($l->token_hashed)URL/QR được cấp một lần khi tạo. Link #{{ $l->id }}@else<a href="{{ route('portal.intro', $l->token) }}" target="_blank" rel="noopener">Mở đường dẫn</a> · <a
                                href="{{ route('admin.link.qr', $l->id) }}" target="_blank" rel="noopener">QR SVG</a>
                            <details>
                                <summary>Địa chỉ chia sẻ</summary><input readonly value="{{ route('portal.intro', $l->token) }}">
                            </details>
                            @endif
                        </td>
                        <td>{{ $l->valid_until && now()->gte($l->valid_until) && $l->status !== 'REVOKED' ? 'EXPIRED' : $l->status }}<br>{{ $l->valid_from }} → {{ $l->valid_until }}</td>
                        <td>
                            @if($l->status !== 'REVOKED')
                            @if(auth()->user()->hasRole('owner','researcher', $l->active ? 'IT_ADMIN' : 'NCS'))<form method="POST" action="{{ route('admin.link.toggle', $l->id) }}">@csrf<button
                                    class="{{ $l->active ? 'warn' : 'alt' }}">{{ $l->active ? 'Tắt' : 'Bật' }}</button></form>
                            @endif
                            <details><summary>Thu hồi / thay thế</summary><form method="POST" action="{{ route('admin.link.revoke', $l->id) }}">@csrf
                                <label>Lý do</label><input name="reason" required maxlength="1000">
                                <label>ID link thay thế (nếu có)</label><input name="replaced_by" type="number" min="1">
                                <button class="warn">Thu hồi</button>
                            </form></details>@endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
        <p class="muted small">Mở QR SVG để in hoặc chia sẻ trong phạm vi điểm thu mẫu. QR chứa link nghiên cứu, không chứa tên cá nhân.</p>
    </div>
@endsection
