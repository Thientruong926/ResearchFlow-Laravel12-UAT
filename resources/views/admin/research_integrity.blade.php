@extends('layout')
@section('content')
<h1>Cờ trùng kỹ thuật</h1>
<p>Cờ chỉ là dấu hiệu để rà soát. Quét chung một QR không tạo cờ trùng người. Câu trả lời và quyết định QC được giữ riêng.</p>
@foreach($flags as $flag)
<div class="card">
    <p>Phiên #{{ $flag->survey_session_id }} · {{ $flag->research_id }} · {{ $flag->rule_code }} · {{ $flag->status }}</p>
    <p>{{ $flag->review_note }} · {{ $flag->reviewed_at }}</p>
    <form method="POST" action="{{ route('admin.integrity.review', $flag->id) }}">@csrf
        <label>Kết quả rà soát</label><select name="status"><option>REVIEWED</option><option>OPEN</option></select>
        <label>Giải trình</label><textarea name="review_note" required maxlength="2000"></textarea><button>Lưu rà soát</button>
    </form>
</div>
@endforeach
{{ $flags->links() }}
@endsection
