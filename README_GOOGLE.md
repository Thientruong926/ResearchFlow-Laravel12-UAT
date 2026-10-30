# ResearchFlow – cổng khảo sát Google Forms/Sheets

ResearchFlow là ứng dụng Laravel 12 cung cấp giao diện khảo sát trên máy tính và điện thoại. Câu trả lời được gửi thẳng đến Google Forms và dashboard đọc báo cáo từ Google Sheets; ứng dụng **không lưu câu trả lời khảo sát vào database**.

## Chức năng hiện có

### Người tham gia

- Xem thông tin giới thiệu nghiên cứu và thông tin liên hệ.
- Chọn trường và nhóm: cán bộ quản lý, giảng viên hoặc sinh viên.
- Trả lời khảo sát trên giao diện responsive.
- Kiểm tra câu bắt buộc, giới hạn số và câu hỏi rẽ nhánh trước khi gửi.
- Gửi câu trả lời đến Google Forms và xem trang cảm ơn.
- Lưu nháp tạm trong trình duyệt; máy chủ không lưu bản nháp.

### Người quản lý

- Đăng nhập bằng tài khoản trong `.env`.
- Cài đặt thông tin nghiên cứu, đồng thuận và liên hệ.
- Cấu hình trường, chỉ tiêu và gán Google Form/Sheet theo trường – nhóm.
- Xem trạng thái kết nối, tổng phản hồi, phản hồi theo ngày, trường, nhóm và tỷ lệ so với chỉ tiêu.
- Xem chi tiết, tìm kiếm, lọc và xuất CSV từ Google Sheets.

## Kiến trúc dữ liệu

Phạm vi quản lý mục 4–6 đã hoàn tất đầy đủ 5 sprint: [Sprint 0](docs/SPRINT_0_NEN_TANG.md), [hợp đồng nguồn](docs/HOP_DONG_NGUON_KHAO_SAT.md), [Sprint 1](docs/SPRINT_1_KET_NOI.md), [Sprint 2](docs/SPRINT_2_TRA_CUU_XUAT_DU_LIEU.md), [Sprint 3](docs/SPRINT_3_NOI_DUNG_NGHIEN_CUU.md) và [bàn giao Sprint 4](docs/SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md). Bộ tiêu chí kiểm thử UAT xem tại [Checklist UAT Google](docs/UAT_CHECKLIST_GOOGLE.md). Hướng dẫn CSV bên dưới dành cho demo/dữ liệu được phép công khai; nguồn khảo sát riêng tư sẵn sàng tích hợp adapter API chỉ đọc khi bàn giao. Mã/tài liệu DB cũ trong repository không phải kiến trúc đang chạy.

```text
Người tham gia -> Website -> Google Forms -> Google Sheets
                                            |
                                            v
                                     Dashboard quản lý
```

- Chỉ chuyển câu trả lời khi `GOOGLE_SYNC_ENABLED=true`.
- Website đọc báo cáo từ URL CSV của Google Sheets. Sheet phải có quyền đọc phù hợp hoặc được xuất bản.
- Cấu hình nghiên cứu lưu tại `storage/app/research-settings.json`; file này không chứa câu trả lời và không được đưa lên Git.
- Phiên đăng nhập dùng file (`SESSION_DRIVER=file`). Các route đang hoạt động không dùng MySQL, migration hoặc seeder.

## Cài đặt nhanh trên Windows

Trang nội dung nghiên cứu riêng, preview, revision, hướng dẫn backup/phục hồi và UAT nghiệm thu đã hoàn tất tại [bàn giao Sprint 4](docs/SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md).

Yêu cầu PHP 8.2 trở lên, Composer 2 và Internet. Không cần MySQL, Node.js hay NPM.

```powershell
cd "D:\duong-dan\ResearchFlow_Laravel12_v0.3.0_UAT - Copy"
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Mở `.env` và đặt:

```ini
APP_URL=http://127.0.0.1:8000
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=mat-khau-rieng-tu-it-nhat-12-ky-tu
GOOGLE_SYNC_ENABLED=false
SESSION_DRIVER=file
CACHE_STORE=file
```

Khởi chạy:

```powershell
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

- Người tham gia: <http://127.0.0.1:8000>
- Quản trị: <http://127.0.0.1:8000/login>

## Kết nối Google Forms và Google Sheets

1. Tạo Google Form có các trường: mã trường, nhóm, có sử dụng công cụ số, mức độ, tổng số buổi, số buổi dùng công cụ số và ghi chú.
2. Trong Form, chọn **Nhận đường liên kết điền sẵn**. Điền thử và lấy các mã `entry.123456789` trong URL; chỉ nhập phần số vào website.
3. Ở tab **Câu trả lời**, liên kết Form với Google Sheet.
4. Cấp quyền xem bằng liên kết hoặc xuất bản Sheet, rồi sao chép URL Sheet.
5. Đăng nhập website, vào **Cài đặt**, khai báo trường và gán Form/Sheet cho từng trường – nhóm.
6. Kiểm tra Form ID, bảy entry ID, URL Sheet rồi đánh dấu **Đang hoạt động**.
7. Sau khi nội dung và thông báo chuyển dữ liệu được duyệt, đổi `GOOGLE_SYNC_ENABLED=true` và chạy:

```powershell
php artisan optimize:clear
php artisan research:check
```

Nội dung đồng thuận phải nói rõ câu trả lời được chuyển đến dịch vụ Google. Không thu thông tin nhận dạng nếu chưa được phê duyệt.

## Kiểm thử trước khi demo

```powershell
php artisan route:list
php artisan test
php artisan research:check
php artisan research:verify-flow
```

Quy trình demo: trình bày cài đặt; cho xem kết nối Form/Sheet; gửi một phản hồi thử trong cửa sổ ẩn danh (có mã thử ví dụ `PILOT-001`); chạy `php artisan research:verify-flow --check-code=PILOT-001 --record-verification` để đối chiếu; tải lại trang quản lý xem trạng thái xác minh, bảng thống kê, tìm kiếm, xem chi tiết HMAC và xuất CSV an toàn.

## Giới hạn

- Project không tự tạo Google Form và không dùng Google OAuth. Người quản lý phải tạo Form/Sheet và khai báo entry ID.
- Dashboard chỉ đọc được Sheet có quyền truy cập phù hợp.
- Câu hỏi hiện tại là mẫu kỹ thuật; phải thay bằng bộ câu hỏi được duyệt trước khi thu thập thật.
- `php artisan serve` chỉ dùng để phát triển/demo cục bộ; triển khai thật cần HTTPS và quy trình bảo vệ dữ liệu.
