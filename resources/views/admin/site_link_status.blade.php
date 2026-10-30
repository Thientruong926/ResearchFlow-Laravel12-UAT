@extends('layout')
@section('content')
<h1>Trạng thái link tại điểm được phân công</h1>
<div class="card"><table><tr><th>ID</th><th>Cơ sở</th><th>Điểm</th><th>Đợt</th><th>Nhóm</th><th>Trạng thái</th></tr>
@foreach($links as $link)<tr><td>{{ $link->id }}</td><td>{{ $link->site_code }}</td><td>{{ $link->point_code }}</td><td>{{ $link->wave_code }}</td><td>{{ $link->group_code }}</td><td>{{ $link->valid_until && now()->gte($link->valid_until) && $link->status !== 'REVOKED' ? 'EXPIRED' : $link->status }}</td></tr>@endforeach
</table></div>
@endsection
