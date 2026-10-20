@extends('layout') @section('content')
    <h1>
        SPSS-ready · Dataset · Transcription</h1>
    <div class="notice">Raw data không sửa sau khi nộp. Bộ clean chỉ lấy session có QC mới nhất là <code>valid</code> và không có trạng thái rút lui đang chờ xử lý hoặc đã loại khỏi phân tích. Dữ liệu chưa duyệt
        vẫn giữ nguyên ở raw.</div>
    <div class="grid">
        <div class="card">
            <h2>Đã hoàn tất</h2>
            <h1>{{ $count }}</h1>
            <p>Session trong bảng nguồn</p>
        </div>
        <div class="card">
            <h2>Codebook v1+</h2><a class="btn" href="{{ route('data.codebook') }}">Xem mapping biến</a>
        </div>
        <div class="card">
            <h2>Validation</h2><a class="btn" href="{{ route('data.validate') }}">Kiểm tra đối soát</a>
        </div>
    </div>
    <div class="card">
        <h2>Xuất dữ liệu phân tích</h2>
        <form method="POST" action="{{ route('data.export') }}">@csrf<div class="grid">
                <div><label>Phạm vi</label><select name="scope">
                        <option value="raw">Raw (tất cả hoàn tất)</option>
                        <option value="clean">Clean (chỉ Valid)</option>
                    </select></div>
                <div><label>Định dạng</label><select name="format">
                        <option value="csv">CSV UTF-8</option>
                        <option value="xlsx">Excel XLSX (cần ext-zip)</option>
                        <option value="sps">SPSS .sps hướng dẫn import</option>
                    </select></div>
            </div><button>Xuất tệp và ghi checksum</button></form>
        <p class="muted small">Tệp .sps dùng cùng CSV (cùng raw/clean); thay đường dẫn CSV trong syntax. Biến số và mã lựa chọn dạng số được định dạng numeric trong SPSS; mã có số 0 đứng đầu vẫn là chuỗi. Không tự suy diễn reverse/scoring khi chưa có quy tắc được duyệt. XLSX giữ giá trị thành văn bản để tránh vô tình chuyển nhãn và mã định
            danh.</p>
    </div>
    <div class="card">
        <h2>Nhật ký xuất gần đây</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Thời gian</th>
                    <th>Scope</th>
                    <th>Format</th>
                    <th>Số dòng</th>
                    <th>SHA-256</th>
                </tr>
                @foreach ($runs as $run)
                    <tr>
                        <td>{{ $run->created_at }}</td>
                        <td>{{ $run->scope }}</td>
                        <td>{{ $run->format }}</td>
                        <td>{{ $run->rows }}</td>
                        <td><code>{{ $run->sha256 }}</code></td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
    <div class="card">
        <h2>Cleaning log / chú giải dẫn xuất</h2><p><a class="btn" href="{{ route('data.rules') }}">Quản lý quy tắc recode đã duyệt</a></p>
        <form method="POST" action="{{ route('data.cleaning') }}">@csrf<div class="grid">
                <div><label>Session ID</label><input name="survey_session_id" type="number" min="1" required></div>
                <div><label>Thao tác</label><select name="operation">
                        <option value="annotation">Ghi chú</option>
                        <option value="recode">Recode</option>
                        <option value="missing">Missing</option>
                        <option value="exclusion">Exclusion</option>
                        <option value="derived">Derived</option>
                    </select></div>
            </div><label>Lý do / quy tắc (bắt buộc)</label>
            <textarea name="reason" required></textarea>
            <div class="grid">
                <div><label>Giá trị trước (nếu có)</label><input name="before_value"></div>
                <div><label>Giá trị sau (nếu có)</label><input name="after_value"></div>
            </div><button>Thêm nhật ký (không thay đổi raw)</button>
        </form>
        <div class="scroll">
            <table>
                <tr>
                    <th>Session</th>
                    <th>Thao tác</th>
                    <th>Lý do</th>
                    <th>Thời gian</th>
                </tr>
                @foreach ($logs as $l)
                    <tr>
                        <td>{{ $l->survey_session_id }}</td>
                        <td>{{ $l->operation }}</td>
                        <td>{{ $l->reason }}</td>
                        <td>{{ $l->created_at }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
    <div class="card">
        <h2>Voice / transcript registry (khóa mặc định)</h2><p><a class="btn" href="{{ route('data.transcript.export') }}">Xuất bản transcript đã coding-ready (chỉ khi được duyệt)</a></p>
        <p>Chỉ mở chức năng âm thanh nếu hồ sơ consent và đạo đức cho phép; file được lưu riêng, không công khai. Transcript dùng Research ID.</p>
        <p>Trạng thái: {{ config('research.audio_approved') ? 'Đã được mở bằng cấu hình' : 'Chưa kích hoạt' }}</p>
        <form method="POST" action="{{ route('data.transcript') }}" enctype="multipart/form-data">@csrf<label>Research ID (UUID)</label><input name="research_id"
                required><label>File audio (tối đa 20 MB; tùy chọn)</label><input type="file" name="audio" accept="audio/*"><label>Nội dung transcript đã kiểm
                tra (tùy chọn)</label>
            <textarea name="body"></textarea><label><input type="checkbox" name="permission_confirmed" value="1" required>Tôi xác nhận xử lý âm thanh đúng hồ sơ đồng
                thuận đã duyệt.</label><button>Đăng ký transcript</button>
        </form>
        <div class="scroll">
            <table>
                <tr>
                    <th>Research ID</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                    <th>Kiểm tra</th>
                </tr>
                @foreach ($transcripts as $t)
                    <tr>
                        <td><code>{{ $t->research_id }}</code></td>
                        <td>{{ $t->status }}</td>
                        <td>{{ $t->created_at }}</td>
                        <td>
                        @if(config('research.audio_approved') && $t->status==='received')
                        <form method="POST" action="{{ route('data.transcript.transcribe',$t->id) }}">@csrf
                            <textarea name="body" maxlength="200000" required placeholder="Nhập bản chép lời đã được kiểm tra, không dùng tên thật nếu không được phê duyệt"></textarea><button>Lưu bản chép lời</button>
                        </form>
                        @endif
                        @if(config('research.audio_approved') && auth()->user()->hasRole('owner','researcher') && in_array($t->status,['received','transcribed','reviewed']))
                        <form method="POST" action="{{ route('data.transcript.review',$t->id) }}">@csrf
                            <select name="status">
                                @if($t->status==='transcribed')<option value="reviewed">Đã kiểm tra</option>@endif
                                @if($t->status==='reviewed')<option value="coding_ready">Coding-ready</option>@endif
                                <option value="rejected">Không chấp nhận</option>
                            </select><textarea name="reason" maxlength="3000" required placeholder="Lý do và hồ sơ phê duyệt"></textarea><button>Ghi nhận</button>
                        </form>
                        @else — @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
