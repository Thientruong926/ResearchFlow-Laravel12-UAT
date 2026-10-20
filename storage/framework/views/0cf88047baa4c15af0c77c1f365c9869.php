 <?php $__env->startSection('content'); ?><h1>
        <?php echo e($survey->title); ?></h1>
    <div class="notice">Nhóm: <?php echo e(config('research.groups')[$person->group_code] ?? $person->group_code); ?> · Phiên bản <?php echo e($survey->version); ?> · Trạng thái:
        <b><?php echo e($session->status); ?></b></div>
    <?php if($session->status === 'complete'): ?>
        <div class="card">
            <h2>Đã hoàn tất</h2>
            <p>Câu trả lời đã khóa, không sửa dữ liệu gốc sau khi nộp.</p>
            <p>Mã Research ID: <code><?php echo e($person->research_id); ?></code></p><a class="btn" href="<?php echo e(route('portal.support', $session->resume_token)); ?>">Gửi yêu cầu hỗ
                trợ / rút lui</a>
        </div>
    <?php else: ?>
        <div class="card">
            <p>Những câu hỏi có dấu * là bắt buộc khi nộp. Bạn có thể lưu nháp trước.</p>
            <form method="POST" action="<?php echo e(route('survey.save', $session->resume_token)); ?>" id="survey-form"><?php echo csrf_field(); ?> <?php $__currentLoopData = $qs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php ($choices = json_decode($q->choices ?? '{}', true) ?? []); ?> <?php ($condition = json_decode($q->condition ?? 'null', true)); ?>
                    <div class="card question" data-code="<?php echo e($q->code); ?>"
                        <?php if($condition): ?> data-cond-code="<?php echo e($condition['code']); ?>" data-cond-value="<?php echo e($condition['equals']); ?>" <?php endif; ?>><label
                            for="q<?php echo e($q->id); ?>"><?php echo e($loop->iteration); ?>. <?php echo e($q->label); ?> <?php if($q->required): ?>
                                <span class="bad">*</span>
                            <?php endif; ?>
                        </label>
                        <?php if(in_array($q->type, ['single', 'likert'])): ?>
                            <select name="q_<?php echo e($q->id); ?>" id="q<?php echo e($q->id); ?>" data-answer="1">
                                <option value="">-- Chọn câu trả lời --</option>
                                <?php $__currentLoopData = $choices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($key); ?>" <?php if((string) old('q_' . $q->id, $answers[$q->id] ?? '') === (string) $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        <?php elseif($q->type === 'number'): ?>
                            <input type="number" step="any" <?php if($q->min_value !== null): ?> min="<?php echo e($q->min_value); ?>" <?php endif; ?>
                                <?php if($q->max_value !== null): ?> max="<?php echo e($q->max_value); ?>" <?php endif; ?> name="q_<?php echo e($q->id); ?>" id="q<?php echo e($q->id); ?>"
                                data-answer="1" value="<?php echo e(old('q_' . $q->id, $answers[$q->id] ?? '')); ?>">
                        <?php else: ?>
                            <textarea name="q_<?php echo e($q->id); ?>" id="q<?php echo e($q->id); ?>" data-answer="1" maxlength="5000"><?php echo e(old('q_' . $q->id, $answers[$q->id] ?? '')); ?></textarea>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <p class="muted small" id="autosaveStatus" aria-live="polite">Bản nháp được lưu tự động khi chỉnh sửa nếu có mạng.</p>
                <div class="actions"><button name="action" value="draft" class="alt" formnovalidate>Lưu nháp</button><button name="action"
                        value="complete">Nộp hoàn tất</button><a class="btn alt" href="<?php echo e(route('portal.support', $session->resume_token)); ?>">Hỗ trợ / rút lui</a>
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
    <?php endif; ?> <?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/survey/fill.blade.php ENDPATH**/ ?>