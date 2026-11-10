@extends('google_layout')
@section('title', 'Phiếu khảo sát')
@section('content')
<div class="card survey-heading">
    <span class="pill">{{ $school['name'] }} · {{ $groupName }}</span>
    <h1>{{ $research['title'] }}</h1>
    <p class="muted">Các câu có dấu <span class="required">*</span> là bắt buộc. Bản nháp chỉ được giữ trên thiết bị này và chưa gửi ra ngoài cho đến khi bạn bấm Gửi câu trả lời.</p>
    <div class="notice small">Bạn đang điền phiếu dành cho <strong>{{ $groupName }}</strong> tại <strong>{{ $school['name'] }}</strong>. Nếu cần đổi lựa chọn, hãy quay lại trang trước.</div>
</div>
<form method="POST" action="{{ route('survey.submit') }}" id="survey-form">@csrf
    <input type="hidden" name="school" value="{{ $school['code'] }}">
    <input type="hidden" name="group" value="{{ $group }}">
    @if($questions !== [])
    <section class="card">
        <h2>{{ $assignment['form_title'] ?? 'Phiếu câu hỏi đã đồng bộ' }}</h2>
        @if(!empty($assignment['form_description']))<p class="muted">{{ $assignment['form_description'] }}</p>@endif
        <p class="small muted">Đã đồng bộ {{ count($questions) }} câu hỏi từ Google Forms lúc {{ isset($assignment['form_sync']['synced_at']) ? \Carbon\Carbon::parse($assignment['form_sync']['synced_at'])->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') : 'không rõ' }}.</p>
    </section>
    @foreach($questions as $index => $question)
    @php($field = 'answer['.$question['id'].']')
    <section class="card question">
        <label>{{ $index + 1 }}. {{ $question['title'] }} @if($question['required'] ?? false)<span class="required">*</span>@endif</label>
        @if(!empty($question['description']))<p class="muted">{{ $question['description'] }}</p>@endif

        @if(in_array($question['type'], ['radio','scale'], true))
            @if($question['type'] === 'scale' && (!empty($question['low_label']) || !empty($question['high_label'])))
                <div class="actions" style="justify-content:space-between"><span class="small muted">{{ $question['low_label'] ?? '' }}</span><span class="small muted">{{ $question['high_label'] ?? '' }}</span></div>
            @endif
            <div class="inline-choice">
                @foreach($question['options'] as $option)
                <label class="choice"><input type="radio" name="{{ $field }}" value="{{ $option['value'] }}" @checked(old('answer.'.$question['id']) === $option['value']) @required($question['required'] ?? false)>{{ $option['label'] }}</label>
                @endforeach
            </div>
        @elseif($question['type'] === 'checkbox')
            <div class="inline-choice">
                @foreach($question['options'] as $option)
                <label class="choice"><input type="checkbox" name="{{ $field }}[]" value="{{ $option['value'] }}" @checked(in_array($option['value'], old('answer.'.$question['id'], []), true))>{{ $option['label'] }}</label>
                @endforeach
            </div>
        @elseif($question['type'] === 'dropdown')
            <select name="{{ $field }}" @required($question['required'] ?? false)>
                <option value="">-- Chọn câu trả lời --</option>
                @foreach($question['options'] as $option)<option value="{{ $option['value'] }}" @selected(old('answer.'.$question['id']) === $option['value'])>{{ $option['label'] }}</option>@endforeach
            </select>
        @elseif($question['type'] === 'paragraph')
            <textarea name="{{ $field }}" maxlength="5000" @required($question['required'] ?? false)>{{ old('answer.'.$question['id']) }}</textarea>
        @else
            <input name="{{ $field }}" value="{{ old('answer.'.$question['id']) }}" maxlength="1000" @required($question['required'] ?? false)>
        @endif
    </section>
    @endforeach
    @else
    <div class="notice"><strong>Chế độ tương thích:</strong> Phiếu này chưa đồng bộ câu hỏi từ Google Forms nên đang hiển thị bộ câu hỏi mẫu cũ.</div>
    <section class="card question">
        <label>1. Trong tình huống khảo sát, bạn đã sử dụng công cụ số trong hoạt động dạy hoặc học chưa? <span class="required">*</span></label>
        <div class="inline-choice">
            <label class="choice"><input type="radio" name="use" value="yes" required @checked(old('use') === 'yes')>Đã sử dụng</label>
            <label class="choice"><input type="radio" name="use" value="no" required @checked(old('use') === 'no')>Chưa sử dụng</label>
        </div>
    </section>
    <section class="card question" data-when-use="yes">
        <label for="level">2. Mức độ thuận tiện khi sử dụng công cụ số <span class="required">*</span></label>
        <select id="level" name="level">
            <option value="">-- Chọn mức độ --</option>
            @foreach(['1'=>'Rất thấp','2'=>'Thấp','3'=>'Trung bình','4'=>'Cao','5'=>'Rất cao'] as $value=>$label)<option value="{{ $value }}" @selected(old('level') === $value)>{{ $value }} - {{ $label }}</option>@endforeach
        </select>
    </section>
    <section class="card question">
        <label for="total">3. Tổng số buổi trong phạm vi trả lời <span class="required">*</span></label>
        <input id="total" type="number" name="total" min="0" max="100" step="1" value="{{ old('total') }}" required>
    </section>
    <section class="card question" data-when-use="yes">
        <label for="digital">4. Số buổi có sử dụng công cụ số <span class="required">*</span></label>
        <input id="digital" type="number" name="digital" min="0" max="100" step="1" value="{{ old('digital') }}">
        <p class="small muted">Giá trị không được lớn hơn tổng số buổi.</p>
    </section>
    <section class="card question">
        <label for="note">5. Góp ý thêm</label>
        <textarea id="note" name="note" maxlength="1000" placeholder="Không nhập thông tin nhận dạng cá nhân">{{ old('note') }}</textarea>
    </section>
    @endif
    <section class="card">
        @include('partials.research_consent')
        @include('partials.research_contact')
        <label class="choice"><input type="checkbox" name="consent" value="1" required> Tôi đã đọc thông tin nghiên cứu và đồng ý gửi câu trả lời đến Google Forms. <span class="required">*</span></label>
        <div class="actions"><a class="btn secondary" href="{{ route('home') }}">Quay lại</a><button>Gửi câu trả lời <span aria-hidden="true">→</span></button></div>
        <p id="draft-status" class="small muted">Bản nháp được lưu cục bộ trên trình duyệt.</p>
    </section>
</form>
<script>
const form=document.getElementById('survey-form');
const draftKey='researchflow-draft-{{ $school['code'] }}-{{ $group }}';
function branch(){const use=form.querySelector('[name=use]:checked')?.value;document.querySelectorAll('[data-when-use]').forEach(section=>{const show=use===section.dataset.whenUse;section.hidden=!show;section.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=!show);});}
function updateAnswerStates(){form.querySelectorAll('.question').forEach(question=>{const controls=[...question.querySelectorAll('input:not([type=hidden]),select,textarea')];const answered=controls.some(control=>control.type==='checkbox'||control.type==='radio'?control.checked:control.value.trim()!=='');question.classList.toggle('is-answered',answered);question.querySelectorAll('.choice').forEach(choice=>{const input=choice.querySelector('input');choice.classList.toggle('is-selected',Boolean(input?.checked));});});form.querySelectorAll('.choice').forEach(choice=>{const input=choice.querySelector('input');choice.classList.toggle('is-selected',Boolean(input?.checked));});}
function saveDraft(){const data={};new FormData(form).forEach((value,key)=>{if(key.startsWith('_')||key==='consent')return;if(Object.hasOwn(data,key))data[key]=Array.isArray(data[key])?[...data[key],value]:[data[key],value];else data[key]=value;});localStorage.setItem(draftKey,JSON.stringify(data));document.getElementById('draft-status').textContent='Đã lưu bản nháp trên thiết bị lúc '+new Date().toLocaleTimeString('vi-VN');}
try{const draft=JSON.parse(localStorage.getItem(draftKey)||'{}');Object.entries(draft).forEach(([name,value])=>{const nodes=form.querySelectorAll(`[name="${name}"]`);nodes.forEach(node=>{if(node.type==='radio'||node.type==='checkbox')node.checked=Array.isArray(value)?value.includes(node.value):node.value===value;else node.value=value;});});}catch(e){}
form.querySelectorAll('input,select,textarea').forEach(input=>{input.addEventListener('input',()=>{branch();updateAnswerStates();saveDraft();});input.addEventListener('change',()=>{branch();updateAnswerStates();});});
form.addEventListener('submit',event=>{if(form.total){const total=Number(form.total.value),digital=Number(form.digital?.value);if(!form.total.value||total<0||total>100){event.preventDefault();alert('Tổng số buổi phải từ 0 đến 100.');return;}if(!form.digital?.disabled&&(!form.digital.value||digital<0||digital>total)){event.preventDefault();alert('Số buổi dùng công cụ số phải từ 0 đến tổng số buổi.');}}});
branch();updateAnswerStates();
</script>
@endsection
