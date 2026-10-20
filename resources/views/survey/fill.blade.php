@extends('layout') @section('content')<h1>
        {{ $survey->title }}</h1>
    <div class="notice">Nhóm: {{ config('research.groups')[$person->group_code] ?? $person->group_code }} · Phiên bản {{ $survey->version }} · Trạng thái:
        <b>{{ $session->status }}</b></div>
    @if ($session->status === 'complete')
        <div class="card">
            <h2>Đã hoàn tất</h2>
            <p>Câu trả lời đã khóa, không sửa dữ liệu gốc sau khi nộp.</p>
            <p>Mã Research ID: <code>{{ $person->research_id }}</code></p><a class="btn" href="{{ route('portal.support', $session->resume_token) }}">Gửi yêu cầu hỗ
                trợ / rút lui</a>
        </div>
    @else
        <div class="card">
            <p>Những câu hỏi có dấu * là bắt buộc khi nộp. Bạn có thể lưu nháp trước.</p>
            <form method="POST" action="{{ route('survey.save', $session->resume_token) }}" id="survey-form">@csrf @foreach ($qs as $q)
                    @php($choices = json_decode($q->choices ?? '{}', true) ?? []) @php($condition = json_decode($q->condition ?? 'null', true))
                    <div class="card question" data-code="{{ $q->code }}"
                        @if ($condition) data-cond-code="{{ $condition['code'] }}" data-cond-value="{{ $condition['equals'] }}" @endif><label
                            for="q{{ $q->id }}">{{ $loop->iteration }}. {{ $q->label }} @if ($q->required)
                                <span class="bad">*</span>
                            @endif
                        </label>
                        @if (in_array($q->type, ['single', 'likert']))
                            <select name="q_{{ $q->id }}" id="q{{ $q->id }}" data-answer="1">
                                <option value="">-- Chọn câu trả lời --</option>
                                @foreach ($choices as $key => $label)
                                    <option value="{{ $key }}" @selected((string) old('q_' . $q->id, $answers[$q->id] ?? '') === (string) $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @elseif($q->type === 'number')
                            <input type="number" step="any" @if ($q->min_value !== null) min="{{ $q->min_value }}" @endif
                                @if ($q->max_value !== null) max="{{ $q->max_value }}" @endif name="q_{{ $q->id }}" id="q{{ $q->id }}"
                                data-answer="1" value="{{ old('q_' . $q->id, $answers[$q->id] ?? '') }}">
                        @else
                            <textarea name="q_{{ $q->id }}" id="q{{ $q->id }}" data-answer="1" maxlength="5000">{{ old('q_' . $q->id, $answers[$q->id] ?? '') }}</textarea>
                        @endif
                    </div>
                @endforeach
                <p class="muted small" id="autosaveStatus" aria-live="polite">Bản nháp được lưu tự động khi chỉnh sửa nếu có mạng.</p>
                <div class="actions"><button name="action" value="draft" class="alt" formnovalidate>Lưu nháp</button><button name="action"
                        value="complete">Nộp hoàn tất</button><a class="btn alt" href="{{ route('portal.support', $session->resume_token) }}">Hỗ trợ / rút lui</a>
                </div>
            </form>
        </div>
        <script>
            function updateBranches() {
                let els = [...document.querySelectorAll('.question')],
                    values = {};
                els.forEach(el => {
                    let c = el.dataset.condCode;
                    let vis = !c || values[c] === el.dataset.condValue;
                    el.style.display = vis ? 'block' : 'none';
                    el.querySelectorAll('input,select,textarea').forEach(input => input.disabled = !vis);
                    let input = el.querySelector('[data-answer]');
                    values[el.dataset.code] = vis ? input?.value : null;
                });
            }
            document.querySelectorAll('[data-answer]').forEach(x => x.addEventListener('input', updateBranches));
            updateBranches();
            let f = document.getElementById('survey-form'),
                st = document.getElementById('autosaveStatus'),
                timer, submitted = false, saving = Promise.resolve(), dirty = false, revision = 0;
            f.addEventListener('submit', () => {
                submitted = true;
                clearTimeout(timer)
            });
            let asyncSave = async () => {
                if (submitted) return;
                let body = new FormData(f);
                body.set('action', 'draft');
                let snapshotRevision = revision;
                saving = saving.catch(() => {}).then(async () => {
                if (submitted) return;
                st.textContent = 'Đang lưu nháp…';
                try {
                    let res = await fetch(f.action, {
                        method: 'POST',
                        body,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (res.ok && revision === snapshotRevision) dirty = false;
                    st.textContent = res.ok ? (dirty ? 'Có thay đổi mới, sẽ tiếp tục lưu.' : 'Đã tự lưu bản nháp (trực tuyến).') : 'Không thể tự lưu; sẽ thử lại khi có kết nối.';
                } catch (e) {
                    st.textContent = 'Mất kết nối; dữ liệu chưa được lưu lên máy chủ. Không đóng trang.';
                }
                });
            };
            f.querySelectorAll('input,select,textarea').forEach(x => x.addEventListener('input', () => {
                dirty = true; revision++;
                clearTimeout(timer);
                timer = setTimeout(asyncSave, 3500);
            }));
            window.addEventListener('online', () => { if (dirty && !submitted) asyncSave(); });
            window.addEventListener('beforeunload', event => {
                if (dirty && !submitted) { event.preventDefault(); event.returnValue = ''; }
            });
        </script>
    @endif @endsection
