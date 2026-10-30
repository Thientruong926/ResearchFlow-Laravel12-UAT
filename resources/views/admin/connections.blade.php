@extends('google_layout')
@section('title', 'Theo dõi kết nối')
@section('content')
@php
    $labels = ['ok'=>'Thành công', 'error'=>'Lỗi', 'disabled'=>'Tắt', 'unconfigured'=>'Chưa cấu hình', 'unchecked'=>'Chưa kiểm tra'];
    $verificationLabels = ['unverified'=>'Chưa xác minh', 'pending'=>'Chờ đối chiếu', 'verified'=>'Đã xác minh', 'not_found'=>'Chưa thấy phản hồi thử', 'changed'=>'Cấu hình đã thay đổi — cần xác minh lại'];
    $formatTime = fn ($time) => $time ? \Carbon\CarbonImmutable::parse($time)->timezone('Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y') : 'Chưa có';
@endphp
<h1>Theo dõi kết nối</h1>
<p class="muted">CSV demo chỉ dành cho dữ liệu giả/được phép công khai. Đọc Sheet thành công và truy cập trang Form không chứng minh luồng Form → Sheet đã hoạt động.</p>
<div class="grid">
    <article class="card stat">Nguồn cấu hình<strong>{{ count($sources) }}</strong></article>
    <article class="card stat">Đọc thành công, còn mới<strong>{{ collect($snapshots)->filter(fn ($s) => $s['status'] === 'ok' && !$s['stale'])->count() }}</strong></article>
    <article class="card stat">Nguồn lỗi<strong>{{ collect($snapshots)->where('status', 'error')->count() }}</strong></article>
    <article class="card stat">Chưa kiểm tra<strong>{{ collect($snapshots)->where('status', 'unchecked')->count() }}</strong></article>
</div>
<div class="actions"><button id="check-all" @disabled(!$sources)>Kiểm tra tất cả</button><a class="btn secondary" href="{{ route('admin.connections') }}">Tải lại trạng thái</a><a href="{{ route('admin.settings') }}">Cấu hình nguồn</a></div>
<p id="progress" role="status" aria-live="polite"></p>
<p class="small muted">Giờ Việt Nam (UTC+7). Kết quả cũ sau {{ config('research.connections.stale_seconds') }} giây; mỗi nguồn kiểm tra tối đa một lần/30 giây. Trang này chỉ đọc metadata khi tải.</p>
@forelse($sources as $id => $source)
@php($state = $snapshots[$id])
<section class="card source-card" data-source="{{ $id }}" data-check-url="{{ route('admin.connections.check', $id) }}">
    <h2 style="overflow-wrap:anywhere">{{ $id }} <span class="pill">CSV demo</span></h2>
    <ul>@foreach($source['assignments'] as $assignment)<li>{{ $assignment['school'] }} / {{ config('research.groups')[$assignment['group']] ?? $assignment['group'] }} — {{ $assignment['active'] ? 'Phiếu đang mở' : 'Phiếu tắt' }}<br><span class="muted">Form: <span data-form="{{ $assignment['id'] }}">{{ $state['forms'][$assignment['id']]['message'] ?? 'Chưa kiểm tra' }}</span></span></li>@endforeach</ul>
    <p>Đọc Sheet: <strong data-status class="pill {{ $state['status'] === 'ok' && !$state['stale'] ? 'ok' : ($state['status'] === 'error' ? 'bad' : '') }}">{{ $labels[$state['status']] }}{{ $state['stale'] ? ' · Kết quả đã cũ' : '' }}</strong></p>
    <p data-message>{{ $state['user_message'] ?? '' }}</p>
    <p>Lần kiểm tra: <span data-checked>{{ $formatTime($state['last_checked_at'] ?? null) }}</span><br>Lần đọc thành công (lịch sử): <span data-success>{{ $formatTime($state['last_success_at'] ?? null) }}</span><br>Thời gian xử lý: <span data-duration>{{ isset($state['duration_ms']) ? $state['duration_ms'].' ms' : '—' }}</span></p>
    <p>Form → Sheet: <strong data-verification>{{ $verificationLabels[$state['verification']['status']] }}</strong> <span data-verification-time>{{ isset($state['verification']['at']) ? $formatTime($state['verification']['at']) : '' }}</span></p>
    <button type="button" class="check-one">Kiểm tra nguồn</button>
    <p class="check-feedback" role="status" aria-live="polite"></p>
    <details><summary>Ghi nhận đối chiếu thủ công</summary>
        <p>Chỉ thực hiện trên Form/Sheet thử nghiệm được chỉ định: gửi phản hồi giả có mã thử qua website, rồi đối chiếu mã ở đúng Sheet và trang phản hồi. Nếu nguồn dùng chung nhiều Form, đối chiếu tất cả Form liên quan. Không nhập câu trả lời hoặc mã thử vào metadata. Nút kiểm tra nguồn không tự gửi Form.</p>
        <form method="POST" action="{{ route('admin.connections.verification', $id) }}">
            @csrf<input type="hidden" name="revision" value="{{ $source['revision'] }}">
            <label for="verify-{{ $id }}">Kết quả đối chiếu</label>
            <select id="verify-{{ $id }}" name="status">@foreach(['pending','verified','not_found','unverified'] as $status)<option value="{{ $status }}" @selected($state['verification']['status'] === $status)>{{ $verificationLabels[$status] }}</option>@endforeach</select>
            <label><input style="width:auto" type="checkbox" name="confirmed" value="1" required> Tôi xác nhận kết quả trên dựa trên đối chiếu thủ công.</label>
            <button>Lưu kết quả đối chiếu</button>
        </form>
    </details>
</section>
@empty<div class="notice">Chưa có nguồn. Cấu hình gán phiếu trước khi kiểm tra.</div>@endforelse
<script>
(() => {
    const cards = [...document.querySelectorAll('.source-card')];
    const all = document.getElementById('check-all');
    const labels = @json($labels);
    const verificationLabels = @json($verificationLabels);
    const time = value => value ? new Date(value).toLocaleString('vi-VN', {timeZone:'Asia/Ho_Chi_Minh'}) : 'Chưa có';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let running = false;
    const lock = busy => { all.disabled = busy || !cards.length; cards.forEach(card => card.querySelector('.check-one').disabled = busy); };
    async function check(card) {
        const feedback = card.querySelector('.check-feedback');
        feedback.textContent = 'Đang kiểm tra…';
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 40000);
        try {
            const response = await fetch(card.dataset.checkUrl, {method:'POST', credentials:'same-origin', headers:{'X-CSRF-TOKEN':csrf, 'Accept':'application/json'}, signal:controller.signal});
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || `Không thể kiểm tra (HTTP ${response.status}). Tải lại trang hoặc đăng nhập lại.`);
            const state = data.state;
            card.querySelector('[data-status]').textContent = labels[state.status] + (state.stale ? ' · Kết quả đã cũ' : '');
            card.querySelector('[data-status]').className = 'pill ' + (state.status === 'ok' && !state.stale ? 'ok' : state.status === 'error' ? 'bad' : '');
            card.querySelector('[data-message]').textContent = state.user_message || '';
            card.querySelector('[data-checked]').textContent = time(state.last_checked_at);
            card.querySelector('[data-success]').textContent = time(state.last_success_at);
            card.querySelector('[data-duration]').textContent = `${state.duration_ms} ms`;
            card.querySelector('[data-verification]').textContent = verificationLabels[state.verification.status];
            card.querySelector('[data-verification-time]').textContent = state.verification.at ? time(state.verification.at) : '';
            card.querySelectorAll('[data-form]').forEach(el => el.textContent = state.forms[el.dataset.form]?.message || 'Chưa kiểm tra');
            feedback.textContent = 'Đã cập nhật trạng thái nguồn. Tải lại trạng thái để cập nhật tổng số.';
        } catch (error) {
            feedback.textContent = error.name === 'AbortError' ? 'Hết thời gian chờ. Tải lại trạng thái trước khi thử lại.' : error.message;
        } finally { clearTimeout(timer); }
    }
    cards.forEach(card => card.querySelector('.check-one').addEventListener('click', async () => {
        if (running) return;
        running = true; lock(true);
        try { await check(card); } finally { running = false; lock(false); }
    }));
    all.addEventListener('click', async () => {
        if (running) return;
        running = true; lock(true);
        let next = 0, completed = 0;
        const worker = async () => {
            while (next < cards.length) {
                const card = cards[next++];
                await check(card);
                document.getElementById('progress').textContent = `Đã xử lý ${++completed}/${cards.length} nguồn. Xem kết quả từng nguồn bên dưới.`;
            }
        };
        try { await Promise.all([worker(), worker()]); } finally { running = false; lock(false); }
    });
})();
</script>
@endsection
