 <?php $__env->startSection('content'); ?>
    <div class="card" style="max-width:440px;margin:auto">
        <h1>Đăng nhập</h1>
        <form action="<?php echo e(route('login.submit')); ?>" method="POST"><?php echo csrf_field(); ?><label>Email</label><input name="email" type="email" autocomplete="username"
                value="<?php echo e(old('email')); ?>" required><label>Mật khẩu</label><input name="password" type="password" autocomplete="current-password"
                required><button>Đăng nhập</button></form>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/auth/login.blade.php ENDPATH**/ ?>