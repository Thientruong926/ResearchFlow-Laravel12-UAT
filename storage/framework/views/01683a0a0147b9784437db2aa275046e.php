 <?php $__env->startSection('content'); ?>
    <h1>
        Người dùng và phân quyền</h1>
    <div class="card">
        <h2>Tạo tài khoản cá nhân</h2>
        <p class="muted">Không dùng chung tài khoản. Đặt mật khẩu riêng đủ mạnh; chỉ owner được tạo người dùng.</p>
        <form method="POST" action="<?php echo e(route('admin.users.add')); ?>"><?php echo csrf_field(); ?><div class="grid">
                <div><label>Tên hiển thị</label><input name="name" required></div>
                <div><label>Email quản trị</label><input name="email" type="email" required></div>
                <div><label>Mật khẩu (≥12 ký tự)</label><input name="password" type="password" required minlength="12"></div>
                <div><label>Vai trò</label><select name="role">
                        <?php $__currentLoopData = ['owner', 'researcher', 'quota_manager', 'qc_reviewer', 'data_manager', 'viewer']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($role); ?>"><?php echo e($role); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
            </div><button>Tạo tài khoản</button></form>
    </div>
    <div class="card">
        <h2>Danh sách tài khoản</h2>
        <table>
            <tr>
                <th>Email</th>
                <th>Tên</th>
                <th>Vai trò</th>
                <th>Trạng thái</th>
                <th>Quản lý</th>
            </tr>
            <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($user->email); ?></td>
                    <td><?php echo e($user->name); ?></td>
                    <td><?php echo e($user->role); ?></td>
                    <td><?php echo e($user->active ? 'Đang hoạt động' : 'Đã khóa'); ?></td>
                    <td>
                      <?php if($user->id !== auth()->id()): ?>
                        <form method="POST" action="<?php echo e(route('admin.users.toggle', $user->id)); ?>"><?php echo csrf_field(); ?>
                          <button class="alt" onclick="return confirm('Xác nhận thay đổi trạng thái tài khoản?')">
                            <?php echo e($user->active ? 'Khóa tài khoản' : 'Mở khóa'); ?>

                          </button>
                        </form>
                      <?php else: ?>
                        Tài khoản hiện tại
                      <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/admin/users.blade.php ENDPATH**/ ?>