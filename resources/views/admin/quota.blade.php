@extends('layout') @section('content')
    <h1>
        Quota và phê duyệt</h1>
    <div class="notice">Chỉ nhập chỉ tiêu đã được nghiên cứu sinh xác định. Tạo nháp → gửi duyệt → người khác phê duyệt. Phiên bản cũ được giữ lịch sử.</div>
    <div class="card">
        <h2>Nhập quota nháp</h2>
        <form method="POST" action="{{ route('admin.quota.add') }}">@csrf<div class="grid">
                <div><label>Điểm</label><select name="site_id">
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Đợt</label><select name="wave_id">
                        @foreach ($waves as $wave)
                            <option value="{{ $wave->id }}">{{ $wave->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Nhóm</label><select name="group_code">
                        @foreach (config('research.groups') as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Chỉ tiêu đã duyệt trong kế hoạch</label><input name="target" type="number" min="1" required></div>
            </div><button>Tạo nháp</button></form>
    </div>
    <div class="card">
        <h2>Lịch sử quota và cảnh báo tiến độ</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Điểm/đợt</th>
                    <th>Nhóm</th>
                    <th>Chỉ tiêu</th>
                    <th>Version</th>
                    <th>Trạng thái</th>
                    <th>Duyệt</th>
                </tr>
                @foreach ($quotas as $q)
                    <tr>
                        <td>{{ $q->site }} / {{ $q->wave }}</td>
                        <td>{{ $q->group_code }}</td>
                        <td>{{ $q->target }}</td>
                        <td>v{{ $q->version }}</td>
                        <td>{{ $q->status }}</td>
                        <td>
                            @if ($q->status === 'draft')
                                <form method="POST" action="{{ route('admin.quota.submit', $q->id) }}">@csrf<button class="alt">Gửi duyệt</button></form>
                            @elseif($q->status === 'pending_review' && auth()->user()->hasRole('owner', 'researcher'))
                                <form method="POST" action="{{ route('admin.quota.approve', $q->id) }}">@csrf<button>Phê duyệt</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
