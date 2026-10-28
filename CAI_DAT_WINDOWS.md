# Hướng dẫn khởi chạy ResearchFlow trên Windows

> **Lưu ý:** hướng dẫn đang áp dụng nằm trong `README_GOOGLE.md`. Phiên bản mới không cần MySQL, không chạy `migrate`/`db:seed`; phần bên dưới là tài liệu cài đặt kiến trúc cũ.

**Đây là gói mã nguồn Laravel 12; cần Composer tải `vendor/` trước khi chạy, và chưa được nghiệm thu production.**

## Chuẩn bị
1. Cài PHP ≥8.2, Composer 2, MySQL 8. Bật `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `dom`, `xml`; `zip` nếu cần xuất XLSX. Bật `pdo_sqlite` và `sqlite3` nếu muốn chạy unit/feature test.
2. Giải nén ZIP, mở thư mục bằng VS Code, mở Terminal PowerShell tại thư mục chứa `artisan`.
3. Kiểm tra `php -v`, `composer -V`, `mysql --version`.

## Lệnh cài đặt
```powershell
cd C:\Projects\ResearchFlow_Laravel12
composer install
Copy-Item .env.example .env
php artisan key:generate
```
Mở MySQL Workbench chạy:
```sql
CREATE DATABASE researchflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Mở `.env`, sửa `DB_DATABASE=researchflow`, `DB_USERNAME`, `DB_PASSWORD` cho đúng máy. Đặt `SEED_ADMIN_EMAIL` và `SEED_ADMIN_PASSWORD` (mật khẩu riêng ≥12 ký tự). Cứ để `RESEARCH_COLLECTION_OPEN=false` để bảo vệ việc thử nghiệm.

```powershell
php artisan migrate
php artisan db:seed
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```
Truy cập `http://127.0.0.1:8000/login`, đăng nhập bằng tài khoản vừa seed. Xóa `SEED_ADMIN_PASSWORD` khỏi `.env` sau khi seed.

## Kiểm thử trước khi trình diễn
Mở Terminal mới ở cùng thư mục:
```powershell
php artisan route:list
php artisan test
php artisan research:check
```
Trong quản trị tạo mã site, wave, tài khoản riêng. Nhập **nội dung khoa học do NCS phê duyệt** vào information sheet, 3 phiên bản bảng hỏi, quota và QC rule. Sau pilot/UAT mới đổi cờ thu thập, mở từng link. Không dùng nội dung ví dụ trong README để thu thập dữ liệu thật.

Đọc **README.md** để biết luồng sử dụng A–E, lỗi thường gặp, quyền và hạn chế. Đọc `docs/UAT_CHECKLIST.md` để nghiệm thu. Nếu `composer install` không chạy, kiểm tra kết nối Internet, proxy của Composer và phiên bản PHP; không thể chạy project nếu `vendor/` chưa được cài.
