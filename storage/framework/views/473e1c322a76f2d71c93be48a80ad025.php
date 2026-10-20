 <?php $__env->startSection('content'); ?>
    <?php if($previewMode ?? false): ?>
        <div class="notice">
            <strong>CHẾ ĐỘ XEM TRƯỚC DEMO</strong>
            <p>Giao diện E-Consent chỉ để minh họa.
                Không ghi nhận đồng thuận hoặc tạo người tham gia.</p>
        </div>
    <?php endif; ?>
    <h1>
        Thông tin nghiên cứu và đồng thuận</h1>
    <div class="notice">Nhóm: <b><?php echo e(config('research.groups')[$link->group_code] ?? $link->group_code); ?></b> · Thông tin v<?php echo e($info->version); ?> · Bảng hỏi
        v<?php echo e($survey->version); ?></div>
    <div class="card">
        <h2>Phiếu thông tin người tham gia</h2>
        <?php $__currentLoopData = ['purpose' => 'Mục đích', 'activities' => 'Nội dung tham gia', 'duration' => 'Thời lượng', 'voluntary' => 'Tự nguyện', 'withdrawal' => 'Quyền từ chối/rút lui', 'contact' => 'Đầu mối liên hệ', 'data_use' => 'Sử dụng dữ liệu']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <h3><?php echo e($label); ?></h3>
            <p style="white-space:pre-line"><?php echo e($info->$key); ?></p>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <div class="card">
        <h2>Xác nhận tham gia</h2>
        <p>Việc tham gia là tự nguyện. Không điền họ tên thật hoặc thông tin nhận dạng nếu câu hỏi không yêu cầu theo hồ sơ được duyệt.</p>
        <?php if(!($previewMode ?? false)): ?>
            <form method="POST" action="<?php echo e(route('portal.join', $link->token)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="information_version_id"
                    value="<?php echo e($info->id); ?>"><input type="hidden" name="survey_id" value="<?php echo e($survey->id); ?>"><label>Nhóm tuổi</label><select name="age_group"
                    required id="age">
                    <option value="adult">Từ 18 tuổi trở lên</option>
                    <option value="minor">Dưới 18 tuổi</option>
                </select>
                <div class="notice" id="minor-note" style="display:none">
                    <?php if(config('research.minor_flow')): ?>
                        Luồng dưới 18 tuổi đang là bản mẫu; chỉ sử dụng khi đã có quy trình guardian permission và assent được phê duyệt.
                    <?php else: ?>
                        Hiện nghiên cứu không mở luồng dành cho người dưới 18 tuổi.
                    <?php endif; ?>
                </div>
                <?php if(config('research.minor_flow')): ?>
                    <div id="guardian" style="display:none"><label><input type="checkbox" name="guardian_permission" value="1">Xác nhận đã có permission của
                            người
                            giám hộ theo quy trình được duyệt</label><label><input type="checkbox" name="assent" value="1">Xác nhận assent phù hợp</label>
                    </div>
                <?php endif; ?>
                <label><input type="checkbox" name="agree" value="1" required> Tôi đã đọc bản thông tin v<?php echo e($info->version); ?> và đồng ý tham
                    gia.</label><button>Đồng ý và bắt đầu khảo sát</button>
            </form>
        <?php else: ?>
            <div class="notice">
                <strong>Biểu mẫu xác nhận tham gia (xem trước)</strong>
            </div>

            <label>Nhóm tuổi</label>
            <select disabled>
                <option>Từ 18 tuổi trở lên</option>
                <option>Dưới 18 tuổi</option>
            </select>

            <label>
                <input type="checkbox" disabled>
                Tôi đã đọc bản thông tin v<?php echo e($info->version); ?>

                và đồng ý tham gia.
            </label>

            <button type="button" disabled>
                Đồng ý và bắt đầu khảo sát
            </button>
        <?php endif; ?>
    </div>
    <script>
        let a = document.getElementById('age');

        function upd() {
            let b = a.value === 'minor';
            document.getElementById('minor-note').style.display = b ? 'block' : 'none';
            let g = document.getElementById('guardian');
            if (g) g.style.display = b ? 'block' : 'none'
        }
        a.addEventListener('change', upd);
        upd();
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\daihoc\kì 1 năm 4\TTNN2\colleague_code1\ResearchFlow_Laravel12_v0.3.0_UAT - Copy\resources\views/portal/intro.blade.php ENDPATH**/ ?>