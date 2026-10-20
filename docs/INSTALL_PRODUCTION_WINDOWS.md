# Hướng dẫn cài đặt và kiểm thử kỹ thuật (Windows / Laravel 12)

**Dành cho bộ phận IT của giảng viên.** Đây là tài liệu **cài đặt phần mềm**, không phải giấy xác nhận phê duyệt nghiên cứu hay kiểm định bảo mật production.

## 1. Chuẩn bị

- Windows Server hoặc Windows có XAMPP thử nghiệm; PHP 8.2+ và các extension mà `composer check-platform-reqs` yêu cầu (pdo_mysql, mbstring, openssl, fileinfo, xml, zip...); Composer 2; MySQL/MariaDB UTF8MB4.
- Máy chủ triển khai thật phải dùng Apache/Nginx trỏ `DocumentRoot` về thư mục **`public/`**, bật HTTPS và tắt directory listing. Không dùng `php artisan serve` để công khai ra Internet.
- Tạo một database **MỚI, RỖNG** dành riêng cho dự án, ví dụ `researchflow_new`, dùng tài khoản DB riêng có quyền tối thiểu phù hợp. Không sử dụng/khôi phục đè DB dự án khác.

## 2. Cài mã nguồn

Mở PowerShell trong thư mục vừa giải nén ZIP:

```powershell
composer install --no-interaction --prefer-dist
Copy-Item .env.example .env
php artisan key:generate
```

Sửa `.env` bằng trình soạn thảo, đặt các thông số đúng với máy nhận:

```ini
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=researchflow_new
DB_USERNAME=researchflow_app
DB_PASSWORD=THAY_BANG_MAT_KHAU_CUA_BAN
RESEARCH_COLLECTION_OPEN=false
RESEARCH_MINOR_FLOW=false
RESEARCH_AUDIO_APPROVED=false
```

Mật khẩu trên chỉ là **placeholder**. Không bao giờ gửi tệp `.env` qua email/tài liệu bàn giao. Ở production, dùng `APP_ENV=production`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, và kiểm tra quyền ghi `storage/` và `bootstrap/cache/`.

## 3. Database và tài khoản quản trị

Dùng database **mới, rỗng**:

```powershell
php artisan migrate
```

Đặt `SEED_ADMIN_EMAIL` và `SEED_ADMIN_PASSWORD` (mật khẩu riêng >= 12 ký tự) **tạm thời** trong `.env`, trước khi chạy config cache:

```powershell
php artisan db:seed
```

Xóa `SEED_ADMIN_PASSWORD` khỏi `.env` sau khi tạo tài khoản, không gửi/đăng ảnh thông tin mật khẩu. Không cấp tài khoản `owner` chung cho nhiều người.

## 4. Kiểm thử bản ZIP (DB thử riêng)

```powershell
php artisan test
php artisan route:list
composer check-platform-reqs
```

Các test Laravel dùng môi trường kiểm thử cấu hình trong `phpunit.xml`; cần kiểm tra driver DB thử không trỏ tới dữ liệu thực. **Không** chạy migrate/seed/test nếu chưa xác nhận DB đích.

Đối với test giao diện cục bộ (chưa public Internet):

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Mở `http://127.0.0.1:8000/login` và đăng nhập tài khoản đã seed. Kiểm tra bằng dữ liệu giả lập; cấu hình 3 bảng hỏi, thông tin tham gia, quota và các QR/link chỉ theo bản đã được NCS phê duyệt.

## 5. Triển khai thật và bàn giao

- Hoàn tất checklist `docs/RELEASE_READINESS.md` và `docs/UAT_CHECKLIST.md` **trước khi** bật `RESEARCH_COLLECTION_OPEN=true`.
- Dùng Apache/nginx + HTTPS, không public mã nguồn/`.env`/log/database dump. Xem tài liệu root `.htaccess` ở `public/`.
- Chỉ sau khi tạo tài khoản, kiểm tra production setup mới dùng: `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`. **Nếu thay đổi `.env`, chạy lại `php artisan config:clear` và cache.**
- Có kế hoạch sao lưu và diễn tập khôi phục vào DB kiểm thử; xác định quyền xử lý dữ liệu, thời hạn lưu và quyền rút lui.
- Liên hệ chủ nhiệm nghiên cứu để duyệt nội dung và nhận biên bản UAT; nhóm IT không tự suy diễn các quyết định chuyên môn.
