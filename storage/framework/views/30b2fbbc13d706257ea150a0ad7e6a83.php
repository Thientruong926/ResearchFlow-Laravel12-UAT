 <?php $__env->startSection('content'); ?>
    <h1>
        Registry điểm mẫu, đợt và QR/link</h1>
    <div class="grid">
        <div class="card">
            <h2>Tạo mã điểm mẫu</h2>
            <form method="POST" action="<?php echo e(route('admin.site.add')); ?>"><?php echo csrf_field(); ?><label>Mã cơ sở (không phải tên thật)</label><input name="code" placeholder="SITE_01"
                    required><label>Ghi chú nội bộ (không xuất dữ liệu phân tích)</label><input name="private_label"><button>Tạo điểm mẫu</button></form>
        </div>
        <div class="card">
            <h2>Tạo đợt thu thập</h2>
            <form method="POST" action="<?php echo e(route('admin.wave.add')); ?>"><?php echo csrf_field(); ?><label>Mã đợt</label><input name="code" placeholder="WAVE_01" required><button>Tạo
                    đợt</button></form>
        </div>
        <div class="card">
            <h2>Tạo liên kết theo nhóm</h2>
            <form method="POST" action="<?php echo e(route('admin.link.add')); ?>"><?php echo csrf_field(); ?><label>Điểm</label><select name="site_id" required>
                    <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($s->id); ?>"><?php echo e($s->code); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <label>Đợt</label><select name="wave_id" required>
                    <?php $__currentLoopData = $waves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($w->id); ?>"><?php echo e($w->code); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <label>Đối tượng</label><select name="group_code">
                    <?php $__currentLoopData = config('research.groups'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($code); ?>"><?php echo e($name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <button>Tạo link (tắt mặc định)</button>
            </form>
        </div>
    </div>
    <div class="card">
        <h2>Link đã tạo</h2>
        <div class="scroll">
            <table>
                <tr>
                    <th>Mã điểm</th>
                    <th>Đợt</th>
                    <th>Nhóm</th>
                    <th>Link/QR</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
                <?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($l->site); ?></td>
                        <td><?php echo e($l->wave); ?></td>
                        <td><?php echo e($l->group_code); ?></td>
                        <td><a href="<?php echo e(route('portal.intro', $l->token)); ?>" target="_blank" rel="noopener">Mở đường dẫn</a> · <a
                                href="<?php echo e(route('admin.link.qr', $l->id)); ?>" target="_blank" rel="noopener">QR SVG</a>
                            <details>
                                <summary>Địa chỉ chia sẻ</summary><input readonly value="<?php echo e(route('portal.intro', $l->token)); ?>">
                            </details>
                        </td>
                        <td><?php echo e($l->active ? 'Đang mở' : 'Đang tắt'); ?></td>
                        <td>
                            <form method="POST" action="<?php echo e(route('admin.link.toggle', $l->id)); ?>"><?php echo csrf_field(); ?><button
                                    class="<?php echo e($l->active ? 'warn' : 'alt'); ?>"><?php echo e($l->active ? 'Tắt' : 'Bật'); ?></button></form>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </table>
        </div>
        <p class="muted small">Mở QR SVG để in hoặc chia sẻ trong phạm vi điểm thu mẫu. QR chứa link nghiên cứu, không chứa tên cá nhân.</p>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/admin/sites.blade.php ENDPATH**/ ?>