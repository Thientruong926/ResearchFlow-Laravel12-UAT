@extends('layout')
@section('content')
<h1>Bản báo cáo tiến độ / QC định kỳ</h1>
<div class="notice">Bản CSV được tạo trên máy chủ bằng lệnh <code>php artisan research:snapshot</code>; scheduler phải được cấu hình riêng. Tệp lưu ngoài thư mục public và được đối chiếu SHA-256 khi tải.</div>
<div class="card"><table><tr><th>Mã</th><th>Ngày tạo</th><th>Số quota</th><th>Checksum SHA-256</th><th>Tệp</th></tr>
@forelse($reports as $report)
<tr><td>#{{ $report->id }}</td><td>{{ $report->created_at }}</td><td>{{ $report->quota_rows }}</td><td><code>{{ $report->sha256 }}</code></td><td><a href="{{ route('admin.report.download',$report->id) }}">Tải CSV</a></td></tr>
@empty<tr><td colspan="5">Chưa có snapshot định kỳ.</td></tr>@endforelse
</table></div>
@endsection
