 <?php $__env->startSection('content'); ?>
    <h1>
        <?php echo e($survey->title); ?> · v<?php echo e($survey->version); ?></h1>
    <p>Nhóm: <?php echo e($survey->group_code); ?> · Trạng thái: <b><?php echo e($survey->status); ?></b></p><a href="<?php echo e(route('admin.surveys')); ?>">← Quay lại registry</a>
    <div class="card">
        <h2>Mapping câu hỏi → biến phân tích</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Mã biến</th>
                    <th>Nhãn câu hỏi</th>
                    <th>Kiểu</th>
                    <th>Bắt buộc</th>
                    <th>Rẽ nhánh</th>
                </tr>
                <?php $__currentLoopData = $questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td>Q<?php echo e($survey->id); ?>_<?php echo e(strtoupper($q->code)); ?></td>
                        <td><?php echo e($q->label); ?></td>
                        <td><?php echo e($q->type); ?></td>
                        <td><?php echo e($q->required ? 'Có' : 'Không'); ?></td>
                        <td><?php echo e($q->condition ?? '—'); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </table>
        </div>
    </div>
    <?php if(in_array($survey->status, ['published', 'superseded'])): ?>
        <form method="POST" action="<?php echo e(route('admin.survey.clone', $survey->id)); ?>"><?php echo csrf_field(); ?>
            <button type="submit">Sao chép thành phiên bản nháp mới (giữ nguyên bản đã công bố)</button>
        </form>
    <?php endif; ?>
    <?php if($survey->status === 'draft'): ?>
        <div class="card">
            <h2>Thêm câu hỏi (chỉ cho phiên bản nháp)</h2>
            <form method="POST" action="<?php echo e(route('admin.survey.question.add', $survey->id)); ?>"><?php echo csrf_field(); ?><div class="grid">
                    <div><label>Mã biến (ASCII, không khoảng trắng)</label><input name="code" required placeholder="Q01"></div>
                    <div><label>Kiểu câu hỏi</label><select name="type">
                            <option value="single">Một lựa chọn</option>
                            <option value="likert">Thang đo Likert</option>
                            <option value="number">Số</option>
                            <option value="text">Văn bản ngắn</option>
                        </select></div>
                </div><label>Nội dung câu hỏi</label>
                <textarea name="label" required></textarea><label>Danh sách lựa chọn: mỗi dòng <code>ma=nhãn</code>, dành cho single/Likert</label>
                <textarea name="choices_text" placeholder="1=Hoàn toàn không đồng ý&#10;2=Không đồng ý"></textarea>
                <div class="grid">
                    <div><label>Chỉ hiển thị nếu mã câu trước là</label><input name="condition_code" placeholder="Q01"></div>
                    <div><label>... có giá trị</label><input name="condition_value" placeholder="1"></div>
                    <div><label>Nhãn biến SPSS</label><input name="variable_label"></div>
                    <div><label>Mã missing (nếu có)</label><input name="missing_code"></div>
                    <div><label>Thang đo/nguồn câu hỏi</label><input name="scale"></div>
                    <div><label>Min (nếu kiểu số)</label><input name="min_value" type="number" step="any"></div>
                    <div><label>Max (nếu kiểu số)</label><input name="max_value" type="number" step="any"></div>
                </div><label><input name="required" value="1" type="checkbox"> Câu hỏi bắt buộc theo thiết kế đã duyệt</label><button>Thêm câu hỏi</button>
            </form>
        </div>

        <div class="card">
            <h2>Chỉnh sửa câu hỏi của bản nháp</h2>
            <p class="muted">Có thể đổi nhãn, lựa chọn, điều kiện và thông tin codebook. Mã câu hỏi cố định để giữ mapping; phiên bản đã xuất bản không thể chỉnh sửa.</p>
            <?php $__currentLoopData = $questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php ($edChoices = json_decode($q->choices ?? '{}', true) ?: []); ?>
                <?php ($edCondition = json_decode($q->condition ?? 'null', true) ?: []); ?>
                <details class="card">
                    <summary><strong><?php echo e($q->code); ?> · <?php echo e($q->label); ?></strong> (<?php echo e($q->type); ?>)</summary>
                    <form method="POST" action="<?php echo e(route('admin.survey.question.update', [$survey->id, $q->id])); ?>"><?php echo csrf_field(); ?>
                        <label>Nội dung câu hỏi</label><textarea name="label" required><?php echo e($q->label); ?></textarea>
                        <?php if(in_array($q->type, ['single', 'likert'])): ?>
                            <label>Danh sách lựa chọn (mã=nhãn), giữ các mã được dùng trong rẽ nhánh</label>
                            <textarea name="choices_text" required><?php echo e(implode("
", array_map(fn($key, $value) => $key . '=' . $value, array_keys($edChoices), array_values($edChoices)))); ?></textarea>
                        <?php endif; ?>
                        <div class="grid">
                            <div><label>Điều kiện: mã câu trước</label><input name="condition_code" value="<?php echo e($edCondition['code'] ?? ''); ?>"></div>
                            <div><label>Điều kiện: mã lựa chọn</label><input name="condition_value" value="<?php echo e($edCondition['equals'] ?? ''); ?>"></div>
                            <div><label>Nhãn biến SPSS</label><input name="variable_label" value="<?php echo e($q->variable_label); ?>"></div>
                            <div><label>Mã missing</label><input name="missing_code" value="<?php echo e($q->missing_code); ?>"></div>
                            <div><label>Thang đo / nguồn</label><input name="scale" value="<?php echo e($q->scale); ?>"></div>
                            <div><label>Giá trị nhỏ nhất</label><input type="number" step="any" name="min_value" value="<?php echo e($q->min_value); ?>"></div>
                            <div><label>Giá trị lớn nhất</label><input type="number" step="any" name="max_value" value="<?php echo e($q->max_value); ?>"></div>
                        </div>
                        <label><input type="checkbox" name="required" value="1" <?php if($q->required): echo 'checked'; endif; ?>> Bắt buộc</label>
                        <button>Lưu chỉnh sửa câu hỏi nháp</button>
                    </form>
                    <form method="POST" action="<?php echo e(route('admin.survey.question.delete', [$survey->id, $q->id])); ?>"
                          onsubmit="return confirm('Chỉ xóa câu hỏi của bản nháp. Xác nhận?')"><?php echo csrf_field(); ?>
                        <button type="submit" class="warn">Xóa câu hỏi khỏi bản nháp</button>
                    </form>
                </details>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <form action="<?php echo e(route('admin.survey.publish', $survey->id)); ?>" method="POST"><?php echo csrf_field(); ?><button class="warn"
                onclick="return confirm('Đã được phê duyệt nội dung khoa học và pilot? Sau khi công bố phiên bản bị khóa.')">Duyệt và công bố/khóa bảng hỏi</button>
        </form>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/admin/survey_edit.blade.php ENDPATH**/ ?>