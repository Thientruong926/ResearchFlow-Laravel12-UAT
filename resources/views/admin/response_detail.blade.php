@extends('google_layout')
@section('title', 'Chi tiết phản hồi')
@section('content')
<h1>Chi tiết phản hồi</h1>
<p><a href="{{ route('admin.responses') }}">Về danh sách</a></p>
<section class="card"><p style="overflow-wrap:anywhere">Nguồn: {{ $record->sourceId }} · Vị trí dòng: {{ $record->sourceRowNumber }} (không phải mã người tham gia)</p>
<p>{{ $record->submittedAt?->format('d/m/Y H:i:s T') ?? 'Ngày gửi không hợp lệ' }} · {{ $record->schoolCode ?? 'Không rõ trường' }} · {{ config('research.groups')[$record->groupCode] ?? $record->groupCode }}</p>
@foreach($record->warnings as $warning)<div class="notice">{{ $warning }}</div>@endforeach
<dl>@foreach($record->answers as $key=>$value)<dt style="font-weight:700;margin-top:20px;overflow-wrap:anywhere">{{ $columns[$key] }}</dt><dd style="white-space:pre-wrap;overflow-wrap:anywhere;margin:8px 0">{{ $value }}</dd>@endforeach</dl></section>
@endsection
