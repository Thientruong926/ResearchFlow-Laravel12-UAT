> **Trạng thái bàn giao (2026-10-18):** bản kỹ thuật v0.2.0 phục vụ kiểm thử/pilot. Chưa xác nhận hoàn thành 100% nghiệm thu nghiên cứu thực tế. Xem `docs/RELEASE_READINESS.md` và `docs/INSTALL_PRODUCTION_WINDOWS.md`. Giữ các cờ thu thập, dưới 18 tuổi và âm thanh ở `false` khi chưa có phê duyệt.

# ResearchFlow — Laravel 12, cổng nghiên cứu tích hợp A–E

**Trạng thái:** Mã nguồn nguyên mẫu tích hợp cho 25 gói việc A1–E5. **Chưa thể tuyên bố hoàn tất/đã nghiệm thu 100%.** Các phiếu giao việc không cung cấp câu hỏi nghiên cứu chính thức, mẫu/quota được duyệt, ngưỡng loại mẫu, nội dung consent/hồ sơ đạo đức, kế hoạch lưu dữ liệu, hoặc biên bản UAT. Không được tự tạo các nội dung này rồi sử dụng khảo sát thật. Mặc định thu thập TẮT.

**Yêu cầu môi trường:** Windows 10/11, PHP **8.2+** (8.3/8.4 khuyến nghị), Composer 2, MySQL 8+, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`, `dom`; thêm `zip` để xuất XLSX; Laravel 12. Không cần Node/NPM vì giao diện CSS/JS thuần không dùng Vite. Yêu cầu kết nối Internet trong bước `composer install`. Laravel tự tải thư viện vào `vendor/`.

## 1. Cài trên Windows từng bước (PowerShell)

1. Cài **PHP**, **Composer 2**, và **MySQL Server 8**. Có thể dùng PHP/Composer từ Laravel Herd, PHP độc lập, hoặc PHP của XAMPP nếu đúng phiên bản. Mở PowerShell **mới**, kiểm tra:
   ```powershell
   php -v
   composer -V
   php -m
   mysql --version
   ```
   Nếu `php` hoặc `composer` không được nhận dạng: sửa PATH, sau đó mở terminal mới. Cần `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`, `dom`; `zip` cho XLSX. Không cần đưa thư mục dự án vào `htdocs` khi dùng `artisan serve`.

2. Giải nén `ResearchFlow_Laravel12.zip` vào ví dụ `C:\Projects\ResearchFlow_Laravel12`. Mở PowerShell:
   ```powershell
   cd C:\Projects\ResearchFlow_Laravel12
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```
   **Không chạy `php artisan` trước `composer install`.** File ZIP là mã nguồn Laravel, **không có thư mục `vendor`** hay file `.env` bí mật.

3. Khởi động MySQL, tạo CSDL bằng MySQL Workbench (hoặc MySQL CLI):
   ```sql
   CREATE DATABASE researchflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   Mở `.env` bằng VS Code/Notepad và sửa:
   ```ini
   APP_URL=http://127.0.0.1:8000
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=researchflow
   DB_USERNAME=root
   DB_PASSWORD=mat_khau_mysql_cua_ban
   RESEARCH_COLLECTION_OPEN=false
   RESEARCH_MINOR_FLOW=false
   RESEARCH_AUDIO_APPROVED=false
   SEED_ADMIN_EMAIL=admin@example.test
   SEED_ADMIN_PASSWORD=thay-bang-mat-khau-rieng-tu-12-ky-tu
   ```
   Thay **tất cả** ví dụ bằng thông tin của bạn. **Không dùng tài khoản admin mẫu trên internet thật.** Không chia sẻ `.env`. Nếu mật khẩu có ký tự đặc biệt, đặt trong dấu nháy kép theo cú pháp Dotenv.

4. Tạo bảng, tạo tài khoản quản trị đầu tiên:
   ```powershell
   php artisan migrate
   php artisan db:seed
   ```
   Sau khi seed, **xóa hoặc để trống `SEED_ADMIN_PASSWORD` trong `.env`**. Tài khoản owner đăng nhập bằng email/mật khẩu vừa đặt. Nếu không chạy được `php artisan migrate`, kiểm tra MySQL đang chạy và `pdo_mysql` đã bật.

5. Mở ứng dụng:
   ```powershell
   php artisan optimize:clear
   php artisan serve --host=127.0.0.1 --port=8000
   ```
   Truy cập **http://127.0.0.1:8000** (portal), **http://127.0.0.1:8000/login** (quản trị). Giữ cửa sổ PowerShell chạy server. Đây là máy chủ phát triển, **không phải cấu hình production**.

6. Kiểm tra tự động (cần PHP extension `pdo_sqlite` + `sqlite3` cho DB test chạy trong RAM):
   ```powershell
   php artisan route:list
   php artisan test
   php artisan research:check
   ```
   Nếu test lỗi `could not find driver`, bật extension `pdo_sqlite`/`sqlite3` trong file `php.ini` đang dùng, rồi khởi động lại terminal. `php artisan test` chạy CSDL thử nghiệm riêng, không sử dụng MySQL có dữ liệu thật.

## 2. Dùng website theo đúng thứ tự

**Bước A – Cấp tài khoản, cấu hình nghiên cứu**
1. Đăng nhập `owner`; tạo các tài khoản riêng tại **Người dùng** với vai trò `researcher`, `quota_manager`, `qc_reviewer`, `data_manager`, `viewer` (không dùng chung account).
2. Vào **Điểm mẫu**: tạo mã cơ sở `SITE_...`, mã đợt `WAVE_...`. Chỉ nhập giá trị theo bảng mã được NCS phê duyệt. Nhãn thật của điểm mẫu chỉ để nội bộ, không đưa vào dataset.
3. Vào **Thông tin/Consent**: nhập đầy đủ thông tin đã được NCS/phụ trách đạo đức phê duyệt rồi công bố phiên bản.
4. Vào **Khảo sát**: tạo từng bảng hỏi cho `manager` (CBQL), `lecturer` (giảng viên), `student` (sinh viên); nhập câu hỏi, mã biến, lựa chọn, nhánh; NCS duyệt trước khi công bố. Bản đã công bố khóa sửa. Tạo bản mới khi cần thay đổi.

**Bước B – Sampling và kiểm soát**
5. **Quota**: quota_manager tạo nháp bằng đúng chỉ tiêu đã được NCS chốt, gửi duyệt; owner/researcher khác người nộp phê duyệt. Khi cấu hình mới được duyệt, quota cũ chuyển trạng thái superseded.
6. **QC**: qc_reviewer tạo cờ ở trạng thái draft; researcher/owner khác người tạo phê duyệt. Chỉ các cờ đã duyệt được áp dụng khi người tham gia nộp khảo sát. QC reviewer đánh dấu `Valid / Review / Exclude` kèm lý do.
7. Trước khi thu thập: hoàn thiện pilot/UAT, hồ sơ đạo đức, xác nhận danh sách nhóm và cơ sở. Sửa `.env`: `RESEARCH_COLLECTION_OPEN=true`, chạy `php artisan config:clear`; vào **Điểm mẫu**, tạo link/QR và **Bật** từng link nếu đã đủ thông tin/khảo sát/quota công bố. Link mặc định tắt.

**Bước C – Participant flow**
8. Người tham gia truy cập đúng QR/link; đọc phiên bản thông tin tham gia; xác nhận tuổi, đồng thuận; hoàn thành bảng hỏi đúng nhóm. Có lưu nháp/tự lưu khi có mạng và rẽ nhánh; sau khi nộp hoàn tất, câu trả lời khóa. Hệ thống tạo UUID Research ID không chứa MSSV/email/điện thoại.
9. Người tham gia lưu link khảo sát riêng (resume link) để xem Research ID và gửi yêu cầu hỗ trợ/rút lui. NCS xem ở **Yêu cầu**, xử lý có ghi lý do, không tự động xóa dữ liệu.

**Bước D – QC và phân tích**
10. **Tổng quan** hiển thị số mẫu, tỷ lệ hoàn tất, quota alerts và xuất báo cáo snapshot CSV.
11. **QC**: review cờ kỹ thuật và nhập quyết định thủ công có audit. **Data/SPSS**: xem codebook, export validation, raw/clean CSV, XLSX (cần ext-zip), tạo SPSS `.sps`, lưu cleaning log và registry transcript. `Clean` chỉ gồm session có quyết định mới nhất là **Valid**; raw không thay đổi.
12. Trong SPSS, xuất file CSV cùng scope với file `.sps`; sửa `CHANGE_TO_FULL_CSV_PATH.csv` trong `.sps` thành đường dẫn tệp CSV vừa xuất. Các trường xuất mặc định kiểu string, cần xác nhận thang đo/scoring/recode theo codebook được duyệt trước khi chạy thống kê. Đừng tự áp dụng reverse/scoring nếu chưa có quy tắc.

## 3. Các vai trò

| Vai trò | Quyền chính |
|---|---|
| `owner` | Cấp tài khoản, audit, cấu hình, duyệt, xuất dữ liệu |
| `researcher` | Quản lý nguồn nghiên cứu, bảng hỏi, thông tin tham gia, duyệt quota/rules, xử lý yêu cầu |
| `quota_manager` | Tạo/gửi quota, xem cảnh báo; không tự phê duyệt |
| `qc_reviewer` | Đề xuất QC rule, xem cờ, đánh giá Valid/Review/Exclude |
| `data_manager` | Codebook, export, validation, cleaning log, transcription |
| `viewer` | Chỉ xem số liệu tổng hợp |

## 4. Những giới hạn cần biết trước khi dùng dữ liệu thực

- **Không xác nhận đạt 100%/production.** Mã nguồn mới kiểm tra cú pháp PHP và kiểm tra cấu trúc tĩnh; chưa thể chạy `composer install`/Laravel feature tests trong môi trường tạo gói do không có mạng Composer, thư viện Laravel và PDO SQLite. **Bạn phải chạy bộ test, UAT và pilot trên máy thật**.
- Tài liệu 5 phiếu giao việc chỉ mô tả gói việc kỹ thuật, **không kèm** bộ câu hỏi, quota, tiêu chí khoa học đã phê duyệt, consent thực tế, pháp lý về người dưới 18 tuổi, thời hạn lưu trữ, cấu hình hosting, hoặc biên bản pilot. Các dữ liệu đó cần NCS cung cấp; không tự suy diễn.
- Luồng dưới 18 tuổi chỉ là **prototype** và mặc định **tắt**, không phải quy trình xác thực giám hộ. Không bật chỉ bằng sửa `.env` nếu chưa có hồ sơ bảo vệ người chưa thành niên và giải pháp kiểm chứng phù hợp.
- File audio/transcript lưu private, mặc định **tắt**. Chỉ bật `RESEARCH_AUDIO_APPROVED=true` sau khi bổ sung điều khoản consent riêng và quy trình lưu trữ, kiểm soát truy cập.
- SPSS syntax ở giai đoạn nhập dữ liệu: chưa tự động reverse-code/scoring, chưa xuất native `.sav`; value labels chỉ có hiệu lực khi import theo syntax. XLSX xuất cell string nhằm giảm nguy cơ sai mã; không thay thế kiểm tra thủ công.
- Cờ duplicate_token chỉ ghi nhận gửi lại **cùng** resume token, không chứng minh trùng người. Không fingerprint, không thu IP/số điện thoại để chống trùng.
- Production cần HTTPS, privacy assessment, session hardening, rate limiting theo hạ tầng, queue worker, monitoring, lịch backup, kiểm thử restore, khóa account, kiểm tra bảo mật và phê duyệt của đơn vị chủ trì; `php artisan serve` chỉ phục vụ demo/pilot cục bộ.
- Xem `docs/` cho bảng kiểm A1–E5, sơ đồ ERD, SOP, UAT, data dictionary và phần tồn đọng.

## 5. Lỗi cài đặt hay gặp

- `vendor/autoload.php not found`: chạy `composer install` trong đúng thư mục gốc sau giải nén.
- `No application encryption key`: chạy `php artisan key:generate`.
- `SQLSTATE[HY000] [1045] Access denied`: kiểm tra `DB_USERNAME`, `DB_PASSWORD` trong `.env`, chạy `php artisan config:clear`.
- `SQLSTATE[HY000] [1049] Unknown database`: tạo DB bằng SQL ở bước 3.
- `could not find driver`: bật `pdo_mysql` cho MySQL, `pdo_sqlite` cho chạy test.
- `Class ZipArchive not found`: bật `extension=zip` trong PHP hoặc chọn export CSV.
- `403`: tài khoản chưa có role tương ứng; không sửa quyền bằng cách bỏ middleware.
- `404` trên QR/link: `RESEARCH_COLLECTION_OPEN=false` hoặc link chưa bật, hoặc chưa công bố thông tin/câu hỏi.
- `419 Page Expired`: form hết phiên/CSRF; refresh trang và đăng nhập lại, không xóa middleware CSRF.
- Nếu đổi `.env`: chạy `php artisan optimize:clear` rồi thử lại.

## v0.3 — bản tăng cường nghiệp vụ để UAT (2026-10-19)

Bản này bổ sung kiểm soát rút lui, QC mâu thuẫn từ đặc tả có duyệt, recode có workflow và audit riêng cho clean export, snapshot riêng tư có lịch opt-in, ẩn ô thống kê nhỏ, ghi log đọc dữ liệu giới hạn và registry transcript coding-ready. Xem `docs/RELEASE_READINESS_v0.3.md`, `docs/UPGRADE_v0.3.md` và `docs/UAT_CHECKLIST.md`.

**Không cam kết hoàn thành 100%/đủ điều kiện thu thập thật:** cần nội dung khoa học chính thức và phê duyệt giảng viên, kiểm thử Laravel mới, UAT/SPSS thật, security/hosting và backup restore. Giữ cờ thu thập mặc định OFF.
