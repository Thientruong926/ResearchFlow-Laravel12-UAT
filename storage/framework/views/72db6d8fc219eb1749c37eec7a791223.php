 <?php $__env->startSection('content'); ?>
    <h1>
        Tổng quan tiến độ nghiên cứu</h1>
    <p class="muted">Dashboard tổng hợp, không hiển thị danh tính người tham gia. Số liệu đếm trực tiếp từ cơ sở dữ liệu.</p>
    <div class="grid">
        <?php $__currentLoopData = ['sites' => 'Điểm thu mẫu', 'links' => 'Link/QR', 'participants' => 'Research ID', 'complete' => 'Đã hoàn tất', 'drafts' => 'Đang làm dở', 'flags' => 'Cờ QC', 'requests' => 'Yêu cầu chưa xử lý']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="card">
                <p><?php echo e($label); ?></p>
                <h1><?php echo e($counts[$key]); ?></h1>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php if(auth()->user()->hasRole('owner','researcher','quota_manager')): ?>
    <div class="card">
        <h2>Tiến độ theo điểm mẫu / đợt / nhóm</h2>
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mã điểm</th>
                        <th>Đợt</th>
                        <th>Nhóm</th>
                        <th>Khởi tạo</th>
                        <th>Hoàn tất</th>
                        <th>Tỷ lệ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($r->site_code); ?></td>
                            <td><?php echo e($r->wave_code); ?></td>
                            <td><?php echo e($r->group_code); ?></td>
                            <td><?php echo e($r->suppressed ? '[ẩn: ô nhỏ]' : $r->total); ?></td>
                            <td><?php echo e($r->suppressed ? '–' : $r->complete); ?></td>
                            <td><?php echo e($r->suppressed ? '–' : (($r->total ? round((100 * $r->complete) / $r->total, 1) : 0).'%')); ?></td>
                    </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr>
                            <td colspan="6">Chưa có phiên khảo sát.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h2>Cảnh báo quota · Dựa trên cấu hình đã duyệt</h2>
        <?php if(auth()->user()->hasRole('owner','researcher')): ?><p><a class="btn alt" href="<?php echo e(route('admin.reports')); ?>">Xem các snapshot định kỳ lưu riêng tư</a></p><?php endif; ?>
        <p>
            <?php if(auth()->user()->hasRole('owner', 'researcher', 'quota_manager')): ?>
                <a class="btn" href="<?php echo e(route('admin.snapshot')); ?>">Xuất snapshot quota/tiến độ CSV</a>
            <?php endif; ?>
        </p>
        <div class="scroll">
            <table>
                <tr>
                    <th>Điểm/đợt</th>
                    <th>Nhóm</th>
                    <th>Chỉ tiêu</th>
                    <th>Hoàn tất</th>
                    <th>Chênh lệch</th>
                    <th>Tình trạng</th>
                </tr>
                <?php $__empty_1 = true; $__currentLoopData = $alerts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($a->site_code); ?> / <?php echo e($a->wave_code); ?></td>
                        <td><?php echo e($a->group_code); ?></td>
                        <td><?php echo e($a->target); ?></td>
                        <td><?php echo e($a->suppressed ? '[ẩn: ô nhỏ]' : $a->completed); ?></td>
                        <td><?php echo e($a->suppressed ? '–' : $a->completed - $a->target); ?></td>
                        <td><?php echo e($a->suppressed ? 'Ẩn theo ngưỡng bảo mật' : ($a->completed > $a->target ? 'Vượt quota' : ($a->completed < $a->target ? 'Thiếu quota' : 'Đạt'))); ?></td>
                </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr>
                        <td colspan="6">Chưa có quota được duyệt. Không tự đặt chỉ tiêu.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php else: ?>
        <p class="muted">Vai trò này chỉ xem số liệu tổng quan; chi tiết nhóm và điểm mẫu dành cho nhóm quản trị nghiên cứu. Những ô ít hơn ngưỡng bảo mật sẽ được ẩn.</p>
    <?php endif; ?>
    <?php if(auth()->user()->hasRole('owner')): ?>
        <p><a href="<?php echo e(route('admin.audit')); ?>">Nhật ký truy cập/quản trị (chỉ owner)</a></p>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>