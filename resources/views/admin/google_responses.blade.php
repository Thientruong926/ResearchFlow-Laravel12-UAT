@extends('google_layout')
@section('title', 'Chi tiết phản hồi')
@section('content')
<div class="actions" style="justify-content:space-between"><div><h1>Chi tiết phản hồi</h1><p class="muted">Tìm kiếm và lọc trên dữ liệu đọc trực tiếp từ Google Sheets.</p></div><a class="btn" href="{{ route('admin.responses.export',request()->query()) }}">Xuất CSV</a></div>
@if($sync['errors'])<div class="notice"><ul>@foreach($sync['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="card"><form method="GET" action="{{ route('admin.responses') }}"><div class="filters">
    <div><label>Tìm kiếm</label><input name="q" value="{{ $filters['q'] }}" placeholder="Tìm trong toàn bộ phản hồi"></div>
    <div><label>Trường</label><select name="school"><option value="">Tất cả</option>@foreach($configuration['schools'] as $school)<option value="{{ $school['code'] }}" @selected($filters['school']===$school['code'])>{{ $school['name'] }}</option>@endforeach</select></div>
    <div><label>Nhóm</label><select name="group"><option value="">Tất cả</option>@foreach(config('research.groups') as $code=>$name)<option value="{{ $code }}" @selected($filters['group']===$code)>{{ $name }}</option>@endforeach</select></div>
    <div><label>Ngày</label><input type="date" name="date" value="{{ $filters['date'] }}"></div>
    </div><div class="actions"><button>Lọc dữ liệu</button><a class="btn secondary" href="{{ route('admin.responses') }}">Xóa bộ lọc</a></div></form></section>
<section class="card"><h2>{{ count($records) }} phản hồi</h2><div class="scroll"><table><thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>
@forelse($records as $record)<tr>@foreach($headers as $header)<td>{{ $record[$header] ?? '' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ max(1,count($headers)) }}">Không có phản hồi phù hợp.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
