@extends('google_layout')
@section('title', 'Tra cứu phản hồi')
@section('content')
<h1>Tra cứu phản hồi</h1>
<p class="muted">Tìm không phân biệt hoa/thường, có phân biệt dấu tiếng Việt. Dữ liệu đọc lại mỗi lần xem hoặc xuất, có thể thay đổi so với màn hình.</p>
@if($data['errors'])<div class="notice"><strong>Dữ liệu chưa đầy đủ.</strong> Kết quả chỉ thuộc nguồn đọc được.<ul>@foreach($data['errors'] as $id=>$error)<li>{{ $id }}: {{ $error }}</li>@endforeach</ul></div>@endif
<p>Đọc thành công {{ $data['success'] }}/{{ $data['total_sources'] }} nguồn · {{ $data['checked_at']->timezone('Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y') }} (giờ Việt Nam).</p>
<section class="card"><form method="GET" action="{{ route('admin.responses') }}"><div class="filters">
    <div><label for="q">Từ khóa</label><input id="q" name="q" maxlength="200" value="{{ $filters['q'] }}"></div>
    <div><label for="school">Trường</label><select id="school" name="school"><option value="">Tất cả</option>@foreach($configuration['schools'] as $school)<option value="{{ $school['code'] }}" @selected($filters['school']===$school['code'])>{{ $school['name'] }}</option>@endforeach</select></div>
    <div><label for="group">Nhóm</label><select id="group" name="group"><option value="">Tất cả</option>@foreach(config('research.groups') as $code=>$name)<option value="{{ $code }}" @selected($filters['group']===$code)>{{ $name }}</option>@endforeach</select></div>
    <div><label for="source">Nguồn</label><select id="source" name="source"><option value="">Tất cả</option>@foreach($sources as $id=>$source)<option value="{{ $id }}" @selected($filters['source']===$id)>{{ $id }}</option>@endforeach</select></div>
    <div><label for="date_from">Từ ngày</label><input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}"></div>
    <div><label for="date_to">Đến ngày</label><input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}"></div>
    <div><label for="sort">Thứ tự</label><select id="sort" name="sort"><option value="newest" @selected($filters['sort']==='newest')>Mới nhất trước</option><option value="oldest" @selected($filters['sort']==='oldest')>Cũ nhất trước</option></select></div>
    <div><label for="per_page">Số dòng/trang</label><select id="per_page" name="per_page">@foreach([25,50,100] as $size)<option @selected($filters['per_page']===$size)>{{ $size }}</option>@endforeach</select></div>
    </div><div class="actions"><button>Lọc dữ liệu</button><a class="btn secondary" href="{{ route('admin.responses') }}">Xóa bộ lọc</a></div></form></section>
<p class="small muted">Ngày lọc tính theo múi giờ nguồn; ngày lỗi chỉ xuất hiện khi không lọc ngày. Phân trang không giảm lượng dữ liệu đọc từ Google.</p>
<section class="card"><h2>{{ $page->total() }} kết quả phù hợp{{ $data['errors'] ? ' (dữ liệu chưa đầy đủ)' : '' }}</h2>
@if(!$data['errors'] && $data['total_sources'])<a class="btn" href="{{ route('admin.responses.export', array_diff_key($filters, ['page'=>true,'per_page'=>true])) }}">Xuất tất cả kết quả CSV</a>@else<p>Chọn một nguồn khỏe để xuất CSV.</p>@endif
<div class="scroll"><table><thead><tr><th>Thời gian (giờ nguồn)</th><th>Trường</th><th>Nhóm</th><th>Nguồn</th><th>Chi tiết</th></tr></thead><tbody>
@forelse($page as $record)<tr><td>{{ $record->submittedAt?->format('d/m/Y H:i:s T') ?? 'Ngày không hợp lệ' }}@foreach($record->warnings as $warning)<p class="small">{{ $warning }}</p>@endforeach</td><td>{{ $record->schoolCode ?? 'Không rõ' }}</td><td>{{ config('research.groups')[$record->groupCode] ?? $record->groupCode ?? 'Không rõ' }}</td><td style="overflow-wrap:anywhere">{{ $record->sourceId }}</td><td><a href="{{ route('admin.responses.detail', ['ref'=>$tokens[$record->sourceId.':'.$record->sourceRowNumber]]) }}">Xem chi tiết</a></td></tr>
@empty
<tr><td colspan="5">
@if(!$data['total_sources'])
Chưa có nguồn được cấu hình.
@elseif(!$data['success'])
Không đọc được dữ liệu. Kiểm tra kết nối.
@elseif(!$totalRead)
Nguồn đọc được chưa có phản hồi.
@elseif($page->total())
Trang này không có kết quả. Chuyển về trang đầu.
@else
Không có kết quả phù hợp bộ lọc.
@endif
</td></tr>
@endforelse
</tbody></table></div><nav aria-label="Phân trang phản hồi" class="actions">
<a style="color:var(--blue)" href="{{ $page->url(1) }}">Trang đầu</a>
@if($page->previousPageUrl())<a style="color:var(--blue)" href="{{ $page->previousPageUrl() }}">Trang trước</a>@endif
<span>Trang {{ $page->currentPage() }}/{{ $page->lastPage() }}</span>
@if($page->hasMorePages())<a style="color:var(--blue)" href="{{ $page->nextPageUrl() }}">Trang sau</a>@endif
</nav></section>
@endsection
