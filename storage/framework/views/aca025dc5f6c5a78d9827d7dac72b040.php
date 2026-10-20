 <?php $__env->startSection('content'); ?>
    <h1>
        Quản lý bảng hỏi theo nhóm</h1>
    <div class="notice">Không tự thêm câu hỏi nội dung khoa học. Chỉ công bố phiên bản đã được nghiên cứu sinh/phụ trách chuyên môn phê duyệt. Sau công bố, câu hỏi
        bị khóa, mọi thay đổi dùng phiên bản mới.</div>
    <div class="card">
        <h2>Phiên bản bảng hỏi mới</h2>
        <form method="POST" action="<?php echo e(route('admin.surveys.add')); ?>"><?php echo csrf_field(); ?><label>Nhóm đối tượng</label><select name="group_code">
                <?php $__currentLoopData = config('research.groups'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($code); ?>"><?php echo e($name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <label>Tiêu đề đã duyệt</label><input name="title" required><button>Tạo bản nháp</button>
        </form>
    </div>
    <div class="card">
        <h2>Version register</h2>
        <table>
            <tr>
                <th>Nhóm</th>
                <th>Phiên bản</th>
                <th>Tiêu đề</th>
                <th>Trạng thái</th>
                <th>Ngày phát hành</th>
            </tr>
            <?php $__currentLoopData = $surveys; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($s->group_code); ?></td>
                    <td><a href="<?php echo e(route('admin.survey.edit', $s->id)); ?>">v<?php echo e($s->version); ?></a></td>
                    <td><?php echo e($s->title); ?></td>
                    <td><?php echo e($s->status); ?></td>
                    <td><?php echo e($s->published_at ?? '–'); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/admin/surveys.blade.php ENDPATH**/ ?>