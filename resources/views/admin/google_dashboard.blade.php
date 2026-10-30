@extends('google_layout')
@section('title', 'Tổng quan phản hồi')
@section('content')
<div class="actions" style="justify-content:space-between"><div><h1>Tổng quan khảo sát</h1><p class="muted">Dữ liệu đọc trực tiếp từ Google Sheets; website không lưu phản hồi vào database.</p></div><a class="btn secondary" href="{{ route('admin.dashboard') }}">Đồng bộ lại</a></div>
@if($sync['errors'])<div class="notice"><strong>Trạng thái đồng bộ:</strong><ul>@foreach($sync['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="grid">
    <article class="card stat"><span class="muted">Tổng số phản hồi</span><strong>{{ $summary['total'] }}</strong></article>
    <article class="card stat"><span class="muted">Tổng chỉ tiêu</span><strong>{{ $summary['target'] }}</strong></article>
    <article class="card stat"><span class="muted">Tỷ lệ phản hồi</span><strong>{{ $summary['response_rate'] === null ? '—' : $summary['response_rate'].'%' }}</strong></article>
    <article class="card stat"><span class="muted">Đọc đủ nguồn thành công lúc</span><strong style="font-size:20px">{{ $sync['synced_at']?->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') ?? 'Chưa đọc đủ nguồn' }}</strong></article>
</div>
<div class="grid" style="margin-top:18px">
    @foreach(['by_date'=>'Phản hồi theo ngày','by_school'=>'Phân bố theo trường','by_group'=>'Phân bố theo nhóm'] as $key=>$title)
    <section class="card"><h2>{{ $title }}</h2>
        @forelse($summary[$key] as $label=>$count)
            <div class="chart-row"><span>{{ $key==='by_group' ? (config('research.groups')[$label] ?? $label) : $label }}</span><div class="bar"><span style="width:{{ $summary['total'] ? max(3,round($count/$summary['total']*100)) : 0 }}%"></span></div><strong>{{ $count }}</strong></div>
        @empty<p class="muted">Chưa có dữ liệu.</p>@endforelse
    </section>
    @endforeach
</div>
<section class="card"><div class="actions" style="justify-content:space-between"><h2>Trạng thái kết nối</h2><a class="btn" href="{{ route('admin.settings') }}">Cấu hình kết nối</a></div>
    <div class="scroll"><table><thead><tr><th>Trường</th><th>Nhóm</th><th>Google Forms</th><th>Google Sheets</th><th>Trạng thái phiếu</th></tr></thead><tbody>
        @forelse($connections as $connection)<tr><td>{{ $connection['school'] }}</td><td>{{ config('research.groups')[$connection['group']] ?? $connection['group'] }}</td><td><span class="pill {{ $connection['form_status']['ok']?'ok':'bad' }}">{{ $connection['form_status']['message'] }}</span></td><td><span class="pill {{ $connection['sheet_status']['ok']?'ok':'bad' }}">{{ $connection['sheet_status']['message'] }}</span></td><td>{{ $connection['active']?'Đang mở':'Đang tắt' }}</td></tr>
        @empty<tr><td colspan="5">Chưa gán phiếu khảo sát.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
