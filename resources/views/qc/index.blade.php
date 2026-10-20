@extends('layout') @section('content')<h1>
        Data Quality Control · Review queue</h1>
    <div class="notice">Các cờ kiểm tra chỉ gợi ý xem xét, không tự loại mẫu. Ngưỡng và định nghĩa cần được NCS phê duyệt trước khi áp dụng. Mọi quyết định phải có
        lý do.</div>
    <div class="card">
        <h2>Bộ quy tắc QC</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Mã</th>
                    <th>Định nghĩa</th>
                    <th>Ngưỡng</th>
                    <th>Trạng thái</th>
                    <th>Duyệt</th>
                </tr>
                @foreach ($rules as $rule)
                    <tr>
                        <td>{{ $rule->code }}</td>
                        <td>{{ $rule->definition }}</td>
                        <td>{{ $rule->threshold }}</td>
                        <td>{{ $rule->status }}</td>
                        <td>
                            @if ($rule->status === 'draft' && auth()->user()->hasRole('owner', 'researcher'))
                                <form method="POST" action="{{ route('qc.rule.approve', $rule->id) }}">@csrf<button>Phê duyệt</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
    @if (auth()->user()->hasRole('owner', 'qc_reviewer'))
        <div class="card">
            <h2>Đề xuất quy tắc mới</h2>
            <form method="POST" action="{{ route('qc.rule.add') }}">@csrf<label>Loại quy tắc</label><select name="code">
                    @foreach ($ruleTypes as $code => $label)
                        <option value="{{ $code }}">{{ $code }} · {{ $label }}</option>
                    @endforeach
                </select>
                <label>Ngưỡng đã được thiết kế</label><input name="threshold" type="number" step="0.01" min="0" required><label>Định nghĩa/nguồn phê
                    duyệt</label>
                <textarea name="definition" required placeholder='Với logic_conflict: [{"left":"Q1","left_value":"1","right":"Q2","right_value":"2"}]'></textarea><p class="muted small">Quy tắc logic_conflict: nhập JSON gồm các cặp mã câu hỏi và mã giá trị mâu thuẫn do chủ nhiệm nghiên cứu xác nhận. Ngưỡng của loại này là 0. Không tự tạo ngưỡng hay loại dữ liệu.</p><button>Tạo nháp</button>
            </form>
        </div>
    @endif
    <div class="card">
        <h2>Hàng đợi mẫu hoàn tất</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Session</th>
                    <th>Research ID</th>
                    <th>Nhóm</th>
                    <th>Cờ QC</th>
                    <th>Quyết định mới nhất</th>
                    <th>Hành động</th>
                </tr>
                @forelse($queue as $item)
                    <tr>
                        <td>#{{ $item->id }}</td>
                        <td><code>{{ $item->research_id }}</code></td>
                        <td>{{ $item->group_code }}</td>
                        <td><a href="{{ route('qc.flags', $item->id) }}">{{ $item->flags }} cờ / lịch sử</a></td>
                        <td>{{ $item->decision ?? 'unreviewed' }}</td>
                        <td>
                            <form method="POST" action="{{ route('qc.decide', $item->id) }}">@csrf<select name="decision">
                                    <option value="review">Review</option>
                                    <option value="valid">Valid</option>
                                    <option value="exclude">Exclude</option>
                                </select>
                                <textarea name="reason" placeholder="Lý do và tiêu chí đã duyệt" required maxlength="3000"></textarea><button>Ghi quyết định</button>
                            </form>
                        </td>
                </tr>@empty<tr>
                        <td colspan="6">Chưa có dữ liệu khảo sát hoàn tất.</td>
                    </tr>
                @endforelse
            </table>
        </div>
</div>@endsection
