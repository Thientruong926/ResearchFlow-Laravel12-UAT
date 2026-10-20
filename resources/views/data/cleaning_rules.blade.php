@extends('layout')
@section('content')
<h1>Quy tắc làm sạch dẫn xuất (chỉ bản clean)</h1>
<div class="notice">Quy tắc recode do nhóm nghiên cứu đặc tả và người khác phê duyệt trước khi áp dụng. Raw data không đổi. Từng thay đổi được ghi cùng mã quy tắc, session, bản xuất và giá trị trước/sau.</div>
<div class="card"><h2>Đề xuất recode</h2>
@if(auth()->user()->hasRole('owner','researcher','data_manager'))
<form method="POST" action="{{ route('data.rule.create') }}">@csrf
<label>Câu hỏi</label><select name="question_id" required>
@foreach($questions as $q)
<option value="{{ $q->id }}">{{ $q->group_code }} · v{{ $q->version }} · {{ $q->code }} · {{ $q->label }} · mã: {{ implode(', ',array_keys(json_decode($q->choices??'{}',true)?:[])) }} · missing: {{ $q->missing_code??'trống' }}</option>
@endforeach
</select>
<label>Mã nguồn chính xác</label><input name="source_code" required maxlength="64" value="{{ old('source_code') }}">
<label>Mã đích (để trống = missing)</label><input name="target_code" maxlength="64" value="{{ old('target_code') }}">
<label>Lý do, tài liệu quy tắc đã duyệt</label><textarea name="reason" maxlength="3000" required>{{ old('reason') }}</textarea>
<button>Tạo quy tắc nháp</button>
</form>@endif</div>
<div class="card"><h2>Danh sách quy tắc</h2><div class="scroll"><table><tr><th>ID</th><th>Nhóm / v / câu</th><th>Nguồn → đích</th><th>Trạng thái</th><th>Lý do</th><th>Thao tác</th></tr>
@foreach($rules as $rule)
<tr><td>{{ $rule->id }}</td><td>{{ $rule->group_code }} v{{ $rule->version }} / {{ $rule->code }}</td><td>{{ $rule->source_code }} → {{ $rule->target_code??'[missing]' }}</td><td>{{ $rule->status }}</td><td>{{ $rule->reason }}</td><td>
@if($rule->status==='draft' && auth()->user()->hasRole('owner','researcher'))
<form method="POST" action="{{ route('data.rule.approve',$rule->id) }}">@csrf<button>Duyệt</button></form>
@endif
@if(in_array($rule->status,['draft','approved']) && auth()->user()->hasRole('owner','researcher'))
<form method="POST" action="{{ route('data.rule.retire',$rule->id) }}">@csrf<button class="alt">Ngừng áp dụng</button></form>
@endif
</td></tr>
@endforeach</table></div></div>
@endsection
