@extends('layout') @section('content')
    <h1>
        Hàng đợi hỗ trợ và rút lui</h1>
    <div class="notice">Ngay khi nhận yêu cầu rút lui, dữ liệu liên quan được tạm giữ khỏi clean export cho đến khi NCS quyết định rõ việc loại trừ/tiếp tục sử dụng theo protocol. Không tự động xóa raw.</div>
    <div class="card">
        <div class="scroll">
            <table>
                <tr>
                    <th>#</th>
                    <th>Research ID</th>
                    <th>Loại / nội dung</th>
                    <th>Tiếp nhận</th>
                    <th>Trạng thái</th>
                    <th>Hướng xử lý</th>
                </tr>
                @foreach ($requests as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td><code>{{ $r->research_id }}</code></td>
                        <td><strong>{{ $r->type }}</strong>
                            <p>{{ $r->message }}</p>
                        </td>
                        <td>{{ $r->created_at }}</td>
                        <td>{{ $r->status }}</td>
                        <td>
                            @if ($r->status === 'resolved')
                                {{ $r->resolution }}
                            @else
                                <form method="POST" action="{{ route('admin.requests.resolve', $r->id) }}">@csrf
                                    <textarea name="resolution" required placeholder="Biện pháp xử lý đã phê duyệt"></textarea>
                                    @if ($r->type === 'withdrawal')
                                        <label>Quyết định theo protocol được NCS duyệt</label>
                                        <select name="disposition" required>
                                            <option value="">-- Chọn quyết định --</option>
                                            <option value="exclude">Ngừng sử dụng bản ghi cho tập dữ liệu sạch</option>
                                            <option value="retain">Được phép tiếp tục sử dụng theo protocol, nêu căn cứ trong lý do</option>
                                        </select>
                                    @endif
                                    <button>Đánh dấu đã xử lý</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
