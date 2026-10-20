 <?php $__env->startSection('content'); ?>
    <h1>
        Participant Information Sheet + E-consent</h1>
    <div class="card">
        <h2>Phiên bản thông tin mới</h2>
        <p class="muted">Nhập nguyên văn nội dung đã được NCS/hồ sơ đạo đức phê duyệt. Tất cả trường bắt buộc.</p>
        <form action="<?php echo e(route('admin.information.add')); ?>" method="POST"><?php echo csrf_field(); ?> <?php $__currentLoopData = ['purpose' => 'Mục đích', 'activities' => 'Hoạt động tham gia', 'duration' => 'Thời lượng', 'voluntary' => 'Tính tự nguyện', 'withdrawal' => 'Quyền từ chối/rút lui', 'contact' => 'Liên hệ hỗ trợ', 'data_use' => 'Cách sử dụng dữ liệu']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label><?php echo e($name); ?></label>
                <textarea name="<?php echo e($key); ?>" maxlength="10000" required><?php echo e(old($key)); ?></textarea>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <button>
                Lưu nháp bản mới</button>
        </form>
    </div>
    <div class="card">
        <h2>Version register</h2>
        <table>
            <tr>
                <th>Phiên bản</th>
                <th>Trạng thái</th>
                <th>Ngày duyệt</th>
                <th>Thao tác</th>
            </tr>
            <?php $__currentLoopData = $versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>v<?php echo e($v->version); ?></td>
                    <td><?php echo e($v->status); ?></td>
                    <td><?php echo e($v->published_at ?? '–'); ?></td>
                    <td>
                        <?php if($v->status === 'draft'): ?>
                        <form method="POST" action="<?php echo e(route('admin.information.publish', $v->id)); ?>"><?php echo csrf_field(); ?><button>Xuất bản/khóa</button></form><?php else: ?><details>
                                <summary>Xem nội dung</summary>
                                <p><?php echo e($v->purpose); ?></p>
                                <p><?php echo e($v->data_use); ?></p>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/admin/information.blade.php ENDPATH**/ ?>