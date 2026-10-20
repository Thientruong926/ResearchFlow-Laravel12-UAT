 <?php $__env->startSection('content'); ?>
    <h1>
        Quota và phê duyệt</h1>
    <div class="notice">Chỉ nhập chỉ tiêu đã được nghiên cứu sinh xác định. Tạo nháp → gửi duyệt → người khác phê duyệt. Phiên bản cũ được giữ lịch sử.</div>
    <div class="card">
        <h2>Nhập quota nháp</h2>
        <form method="POST" action="<?php echo e(route('admin.quota.add')); ?>"><?php echo csrf_field(); ?><div class="grid">
                <div><label>Điểm</label><select name="site_id">
                        <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($site->id); ?>"><?php echo e($site->code); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div><label>Đợt</label><select name="wave_id">
                        <?php $__currentLoopData = $waves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $wave): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($wave->id); ?>"><?php echo e($wave->code); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div><label>Nhóm</label><select name="group_code">
                        <?php $__currentLoopData = config('research.groups'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($code); ?>"><?php echo e($name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div><label>Chỉ tiêu đã duyệt trong kế hoạch</label><input name="target" type="number" min="1" required></div>
            </div><button>Tạo nháp</button></form>
    </div>
    <div class="card">
        <h2>Lịch sử quota và cảnh báo tiến độ</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Điểm/đợt</th>
                    <th>Nhóm</th>
                    <th>Chỉ tiêu</th>
                    <th>Version</th>
                    <th>Trạng thái</th>
                    <th>Duyệt</th>
                </tr>
                <?php $__currentLoopData = $quotas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($q->site); ?> / <?php echo e($q->wave); ?></td>
                        <td><?php echo e($q->group_code); ?></td>
                        <td><?php echo e($q->target); ?></td>
                        <td>v<?php echo e($q->version); ?></td>
                        <td><?php echo e($q->status); ?></td>
                        <td>
                            <?php if($q->status === 'draft'): ?>
                                <form method="POST" action="<?php echo e(route('admin.quota.submit', $q->id)); ?>"><?php echo csrf_field(); ?><button class="alt">Gửi duyệt</button></form>
                            <?php elseif($q->status === 'pending_review' && auth()->user()->hasRole('owner', 'researcher')): ?>
                                <form method="POST" action="<?php echo e(route('admin.quota.approve', $q->id)); ?>"><?php echo csrf_field(); ?><button>Phê duyệt</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </table>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/admin/quota.blade.php ENDPATH**/ ?>