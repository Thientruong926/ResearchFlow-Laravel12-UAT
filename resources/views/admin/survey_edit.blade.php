@extends('layout') @section('content')
    <h1>
        {{ $survey->title }} · v{{ $survey->version }}</h1>
    <p>Nhóm: {{ $survey->group_code }} · Trạng thái: <b>{{ $survey->status }}</b></p><a href="{{ route('admin.surveys') }}">← Quay lại registry</a>
    <div class="card">
        <h2>Mapping câu hỏi → biến phân tích</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Mã biến</th>
                    <th>Nhãn câu hỏi</th>
                    <th>Kiểu</th>
                    <th>Bắt buộc</th>
                    <th>Rẽ nhánh</th>
                </tr>
                @foreach ($questions as $q)
                    <tr>
                        <td>Q{{ $survey->id }}_{{ strtoupper($q->code) }}</td>
                        <td>{{ $q->label }}</td>
                        <td>{{ $q->type }}</td>
                        <td>{{ $q->required ? 'Có' : 'Không' }}</td>
                        <td>{{ $q->condition ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
    @if (in_array($survey->status, ['published', 'superseded']))
        <form method="POST" action="{{ route('admin.survey.clone', $survey->id) }}">@csrf
            <button type="submit">Sao chép thành phiên bản nháp mới (giữ nguyên bản đã công bố)</button>
        </form>
    @endif
    @if ($survey->status === 'draft')
        <div class="card">
            <h2>Bộ phiếu và mã phiên bản</h2>
            <form method="POST" action="{{ route('admin.survey.metadata', $survey->id) }}">@csrf
                <label>Bộ phiếu</label><select name="instrument_id"><option @selected($survey->instrument_id === 'OBS2')>OBS2</option><option @selected($survey->instrument_id === 'OBS4')>OBS4</option></select>
                <label>Mã phiên bản mới</label><input name="form_version" value="{{ $survey->form_version }}" required maxlength="64">
                <button>Lưu metadata bản nháp</button>
            </form>
        </div>
        <div class="card">
            <h2>Thêm câu hỏi (chỉ cho phiên bản nháp)</h2>
            <form method="POST" action="{{ route('admin.survey.question.add', $survey->id) }}">@csrf<div class="grid">
                    <div><label>Mã biến (ASCII, không khoảng trắng)</label><input name="code" required placeholder="Q01"></div>
                    <div><label>Kiểu câu hỏi</label><select name="type">
                            <option value="single">Một lựa chọn</option>
                            <option value="likert">Thang đo Likert</option>
                            <option value="number">Số</option>
                            <option value="text">Văn bản ngắn</option>
                        </select></div>
                </div><label>Nội dung câu hỏi</label>
                <textarea name="label" required></textarea><label>Danh sách lựa chọn: mỗi dòng <code>ma=nhãn</code>, dành cho single/Likert</label>
                <textarea name="choices_text" placeholder="1=Hoàn toàn không đồng ý&#10;2=Không đồng ý"></textarea>
                <div class="grid">
                    <div><label>Chỉ hiển thị nếu mã câu trước là</label><input name="condition_code" placeholder="Q01"></div>
                    <div><label>... có giá trị</label><input name="condition_value" placeholder="1"></div>
                    <div><label>Nhãn biến SPSS</label><input name="variable_label"></div>
                    <div><label>Mã missing (nếu có)</label><input name="missing_code"></div>
                    <div><label>Thang đo/nguồn câu hỏi</label><input name="scale"></div>
                    <div><label>Min (nếu kiểu số)</label><input name="min_value" type="number" step="any"></div>
                    <div><label>Max (nếu kiểu số)</label><input name="max_value" type="number" step="any"></div>
                </div><label><input name="required" value="1" type="checkbox"> Câu hỏi bắt buộc theo thiết kế đã duyệt</label><button>Thêm câu hỏi</button>
            </form>
        </div>

        <div class="card">
            <h2>Chỉnh sửa câu hỏi của bản nháp</h2>
            <p class="muted">Có thể đổi nhãn, lựa chọn, điều kiện và thông tin codebook. Mã câu hỏi cố định để giữ mapping; phiên bản đã xuất bản không thể chỉnh sửa.</p>
            @foreach ($questions as $q)
                @php($edChoices = json_decode($q->choices ?? '{}', true) ?: [])
                @php($edCondition = json_decode($q->condition ?? 'null', true) ?: [])
                <details class="card">
                    <summary><strong>{{ $q->code }} · {{ $q->label }}</strong> ({{ $q->type }})</summary>
                    <form method="POST" action="{{ route('admin.survey.question.update', [$survey->id, $q->id]) }}">@csrf
                        <label>Nội dung câu hỏi</label><textarea name="label" required>{{ $q->label }}</textarea>
                        @if (in_array($q->type, ['single', 'likert']))
                            <label>Danh sách lựa chọn (mã=nhãn), giữ các mã được dùng trong rẽ nhánh</label>
                            <textarea name="choices_text" required>{{ implode("
", array_map(fn($key, $value) => $key . '=' . $value, array_keys($edChoices), array_values($edChoices))) }}</textarea>
                        @endif
                        <div class="grid">
                            <div><label>Điều kiện: mã câu trước</label><input name="condition_code" value="{{ $edCondition['code'] ?? '' }}"></div>
                            <div><label>Điều kiện: mã lựa chọn</label><input name="condition_value" value="{{ $edCondition['equals'] ?? '' }}"></div>
                            <div><label>Nhãn biến SPSS</label><input name="variable_label" value="{{ $q->variable_label }}"></div>
                            <div><label>Mã missing</label><input name="missing_code" value="{{ $q->missing_code }}"></div>
                            <div><label>Thang đo / nguồn</label><input name="scale" value="{{ $q->scale }}"></div>
                            <div><label>Giá trị nhỏ nhất</label><input type="number" step="any" name="min_value" value="{{ $q->min_value }}"></div>
                            <div><label>Giá trị lớn nhất</label><input type="number" step="any" name="max_value" value="{{ $q->max_value }}"></div>
                        </div>
                        <label><input type="checkbox" name="required" value="1" @checked($q->required)> Bắt buộc</label>
                        <button>Lưu chỉnh sửa câu hỏi nháp</button>
                    </form>
                    <form method="POST" action="{{ route('admin.survey.question.delete', [$survey->id, $q->id]) }}"
                          onsubmit="return confirm('Chỉ xóa câu hỏi của bản nháp. Xác nhận?')">@csrf
                        <button type="submit" class="warn">Xóa câu hỏi khỏi bản nháp</button>
                    </form>
                </details>
            @endforeach
        </div>

        <form action="{{ route('admin.survey.publish', $survey->id) }}" method="POST">@csrf<button class="warn"
                onclick="return confirm('Đã được phê duyệt nội dung khoa học và pilot? Sau khi công bố phiên bản bị khóa.')">Duyệt và công bố/khóa bảng hỏi</button>
        </form>
    @endif
@endsection
