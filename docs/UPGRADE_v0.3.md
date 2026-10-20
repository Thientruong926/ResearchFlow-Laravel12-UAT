# Hướng dẫn nâng cấp từ ResearchFlow v0.2.0 lên v0.3 (Windows)

## Nguyên tắc an toàn

- **Không** thay ZIP vào thư mục đang chạy khi có phiên khảo sát mở.
- Tạo backup database riêng qua `mysqldump`, kiểm tra SHA-256 và thử phục hồi vào `researchflow_restore` hoặc DB staging hoàn toàn mới. Không khôi phục đè database live.
- Tạo bản sao code/config trong thư mục mới. Không chép `.env` sang ZIP hoặc lên kho công khai. Nếu dùng staging, tạo `.env` staging với **DB riêng** và khóa ứng dụng riêng.
- Khi nâng cấp dữ liệu hiện có, một người quản trị DB có quyền phù hợp cần lập lịch bảo trì, kiểm tra khóa/role và chạy migration trên bản backup staging trước.

## Thiết lập bản mới độc lập

1. Giải nén vào thư mục trống; đảm bảo có `app/`, `routes/`, `database/`, `docs/`, `public/`, `artisan`, `composer.lock`, `.env.example`.
2. Cài PHP >=8.2, Composer 2, php-mbstring, pdo_mysql và các extension mà `composer check-platform-reqs` báo cần.
3. Chạy `composer install --no-interaction --prefer-dist`.
4. `Copy-Item .env.example .env` rồi `php artisan key:generate`.
5. Tạo DB MySQL/MariaDB mới, rỗng, tên riêng không trùng dự án đang chạy; sửa `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (không chia sẻ giá trị thực).
6. Xác nhận `RESEARCH_COLLECTION_OPEN=false`, `RESEARCH_MINOR_FLOW=false`, `RESEARCH_AUDIO_APPROVED=false`, `RESEARCH_SCHEDULE_SNAPSHOTS=false`.
7. `php artisan migrate`, `php artisan test` (PHPUnit sử dụng SQLite `:memory:` theo `phpunit.xml`; xác nhận trước khi chạy), `php artisan route:list`.
8. Tạo tài khoản owner bằng `.env` `SEED_ADMIN_EMAIL` và `SEED_ADMIN_PASSWORD` >=12 ký tự, chạy `php artisan db:seed`; xóa biến mật khẩu khỏi `.env` rồi kiểm tra lại.
9. Chạy `php artisan research:snapshot`, mở `/admin/dashboard/reports` với quyền owner/researcher và kiểm tra CSV/checksum; không kích hoạt scheduler trước khi vận hành được duyệt.
10. Chỉ dùng `php artisan serve --host=127.0.0.1` để UAT cục bộ; production phải có Apache/nginx với `public/` là document root + HTTPS.

## Migration bổ sung

- `2026_10_19_000003_add_cleaning_rules.php`: quy tắc recode được duyệt và nhật ký áp dụng khi export.
- `2026_10_19_000004_add_report_snapshots.php`: registry snapshot định kỳ; file nằm trên local private disk.
- `2026_10_19_000005_add_transcript_reviews.php`: lịch sử duyệt transcript.
- Giữ migration `2026_10_18_000002_add_user_active_flag.php` của v0.2.0.

**Rollback:** Khôi phục dữ liệu đã backup vào môi trường riêng theo SOP sau khi xác minh; tuyệt đối không chạy `migrate:fresh`, `db:wipe`, `rollback` hay `db:seed` bừa bãi trên live. Muốn rollback live cần bản kế hoạch phục hồi được chấp thuận riêng.

## Kiểm chứng vận hành theo quyền

- QC reviewer tạo quy tắc logic; người khác là researcher/owner duyệt; giả lập cặp mã trùng mâu thuẫn → flag/queue, không tự loại.
- Data manager tạo rule recode draft; owner/researcher khác người tạo phê duyệt → chỉ clean CSV/XLSX biến đổi; raw giữ nguyên, `cleaning_applications` truy vết theo export_run.
- Bất kỳ yêu cầu withdrawal đang mở đều khiến phiên dừng gửi đáp án tiếp và dữ liệu không lọt clean, không phá raw.
- Audit những trang đọc dữ liệu nhạy cảm; che ô nhóm ít người ở dashboard (mặc định dưới 5).
- Khi audio bị khóa: không thể review/xuất transcript. Khi được duyệt thật: yêu cầu transcript có body, reviewed rồi mới coding_ready, export loại các trường hợp rút lui.

**Tài liệu này mô tả cách kiểm tra. Nó không thay thế biên bản UAT thực tế của giảng viên.**

## Tập lệnh kiểm tra không sửa DB

Chạy `powershell -ExecutionPolicy Bypass -File scripts/kiem_tra_sau_cai_dat.ps1` trong môi trường thử nghiệm. Tập lệnh không tự chạy migrate/seed và xác nhận PHPunit dùng SQLite memory. Nó **không thay thế** bài test UAT thực tế hay kiểm tra an toàn hosting.
