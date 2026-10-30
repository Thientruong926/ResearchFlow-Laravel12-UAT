# Sprint 1 — Theo dõi trạng thái kết nối

Ngày cập nhật: 30/10/2026. Phạm vi: S1-01 đến S1-05 trong `KE_HOACH_QUAN_LY_MUC_4_6.md`.

## Tiến độ

| ID | Kết quả triển khai | Kiểm chứng |
|---|---|---|
| S1-01 | `ConnectionReadResult`, nguồn có `ok/disabled/unconfigured/error`; dashboard không còn suy thành công từ thiếu lỗi; `synced_at` chỉ có khi tất cả nguồn đọc thành công | Test nguồn tắt/thiếu/0 dòng, lỗi HTTP, payload, dashboard |
| S1-02 | JSON riêng tư theo nguồn, khóa file xuyên suốt kiểm tra/ghi, thay file nguyên tử; UTC, hiển thị UTC+7; giữ lịch sử thành công khi lỗi | Test timestamp, nội dung metadata không chứa câu trả lời, khóa chồng lấn, JSON hỏng |
| S1-03 | `/admin/connections`, kiểm tra từng nguồn/tất cả (tối đa 2 request frontend), tiến độ, lỗi riêng nguồn, stale sau 5 phút, CSRF/throttle; tối đa 3 lần GET trong ngân sách 30 giây/nguồn | Test route đăng nhập, CSRF thực bật, HTTP retry/Retry-After, cooldown 30 giây; Blade biên dịch thành công |
| S1-04 | Gộp URL cùng spreadsheet/tab/range; giữ mọi assignment; revision gắn cấu hình nguồn và chế độ đồng bộ; xác minh thủ công có thời gian/revision riêng | Test nguồn chung tải một lần, không fallback nhầm trường, Form truy cập được không tự xác minh, revision cũ trả 409 |
| S1-05 | Bộ test riêng `tests/Feature/AdminGoogle/ConnectionHealthTest.php`; không gọi suite DB cũ | 31 tests / 162 assertions đạt, gồm 4 test hồi quy hiện có |

**Trạng thái:** đã triển khai code và kiểm thử tự động bằng HTTP fake. Chưa nghiệm thu tích hợp Google thật hoặc UAT trình duyệt desktop/mobile; không coi test fake là bằng chứng luồng gửi thật đã hoạt động.

## Cách sử dụng

1. Đăng nhập quản lý, chọn **Kết nối** trên menu.
2. Bấm **Kiểm tra nguồn** hoặc **Kiểm tra tất cả**. GET trang chỉ đọc metadata, không gửi request Google.
3. Phân biệt **Trang Form truy cập được**, **Đọc Sheet thành công** và **Form → Sheet đã xác minh**. Sheet hợp lệ không có câu trả lời vẫn là đọc thành công.
4. Xem lần kiểm tra và lần đọc thành công lịch sử. Kết quả quá 5 phút có nhãn cũ; không tự chuyển thành lỗi. Sau kiểm tra qua fetch, từng thẻ được cập nhật; bấm **Tải lại trạng thái** để cập nhật tổng số và nhãn cũ theo thời gian hiện tại.
5. Trên Form/Sheet thử được chỉ định, gửi dữ liệu giả có mã thử qua website và đối chiếu đúng Sheet cùng trang phản hồi. Với nguồn dùng chung nhiều Form, đối chiếu mọi Form trước khi đánh dấu nguồn đã xác minh. Mở **Ghi nhận đối chiếu thủ công**, chọn trạng thái và xác nhận. Không nhập mã thử/câu trả lời vào metadata.

Nút kiểm tra không gửi phản hồi Form. Không có polling nền. Phiếu đang tắt hoặc tắt đồng bộ toàn cục không gọi Google. Mỗi nguồn có cooldown 30 giây và khóa để tránh chạy chồng lấn; lỗi 429 có thể do cooldown hoặc thao tác đang chạy.

## Lưu trữ và vận hành

- Metadata: `storage/app/private/connection-health/src_*.json`; `.lock` là khóa theo nguồn. Thư mục này đã nằm trong quy tắc bỏ qua Git hiện có.
- Chỉ lưu ID/revision nguồn, trạng thái/mã lỗi/thông báo cố định, timestamp, số dòng, thời lượng, trạng thái trang Form và xác minh. Không lưu header/câu trả lời, URL, credential hay thông báo lỗi thô từ upstream.
- Payload HTTP và CSV parser dùng `php://memory`. Giới hạn tải ban đầu 5 MiB, đã nâng lên 10 MiB ở Sprint 2 cùng giới hạn tổng phạm vi đọc; không cắt rồi báo thành công.
- Chỉ HTTPS; URL đầu vào phải thuộc Google Sheets trên `docs.google.com`; redirect được kiểm tra từng chặng, tối đa 3 chặng, chỉ tới đường dẫn Google cho phép. Redirect sang trang đăng nhập hoặc miền khác bị từ chối. Không fallback sang nguồn công khai khác.
- File JSON hỏng phải báo lỗi, không tự dùng trạng thái thành công/default. Khôi phục metadata từ bản hợp lệ hoặc quản trị viên di chuyển riêng file hỏng ra ngoài thư mục vận hành rồi kiểm tra lại; không sửa/xóa file cấu hình nghiên cứu.
- Triển khai một máy chủ có ổ đĩa bền vững, web root là `public/`, ACL thư mục metadata chỉ cho tài khoản ứng dụng/quản trị đọc ghi. Không phục vụ `storage/app/private` qua web.
- Giữ session/cache bằng file, queue sync trong môi trường chạy. Test dùng session/cache memory và thư mục testing riêng, không dùng database.

## Kiểm tra đã chạy

PHP được tìm thấy tại `C:/xampp/php/php.exe`, phiên bản 8.2.12; `php` chưa có trong PATH của phiên làm việc.

```powershell
& C:/xampp/php/php.exe artisan test
# 31 passed (162 assertions)

& C:/xampp/php/php.exe artisan route:list --path=admin/connections
# GET /admin/connections
# POST /admin/connections/{source}/check
# POST /admin/connections/{source}/verification

& C:/xampp/php/php.exe artisan view:cache
# Blade templates cached successfully
```

`git diff --check` không phát hiện lỗi whitespace. Bộ test mới chặn request HTTP ngoài fake và đặt kỳ vọng không gọi DB connection. Không chạy các suite DB cũ.

## Phụ thuộc và giới hạn còn lại

- Adapter hiện có là **CSV demo**. Chưa có adapter Sheets API riêng tư/service account được bàn giao trong repository này. Không yêu cầu công khai Sheet thật để dùng chức năng.
- Hợp đồng hiện dùng cấu hình assignment có sẵn: URL canonical tạo source ID, SHA-256 cấu hình tạo revision. Tab/range được giữ trong URL canonical; header nằm ở dòng đầu. Hợp đồng API/DTO đầy đủ của Sprint 0 cần phối hợp với thành viên mục 1 khi tích hợp.
- Xác minh là xác nhận thủ công của quản lý tại thời điểm ghi nhận, không tự đọc `linkedSheetId` hoặc tự đối chiếu mã gửi; không bảo đảm các phản hồi gửi sau đó.
- Chưa có bằng chứng thử trên Google thật, thu hồi quyền thật hoặc kiểm thử trực quan 1366 px / 390 px. Các việc này cần nguồn thử được chỉ định và nằm trong nghiệm thu tích hợp/UAT.
- Chuẩn hóa ngày strict, chi tiết phản hồi, lọc/phân trang/xuất an toàn thuộc Sprint 2. Version/backup cho toàn bộ kho cấu hình nghiên cứu thuộc Sprint 3; Sprint 1 chỉ dùng fingerprint để vô hiệu kết quả kiểm tra cũ.
- Giữ các thay đổi có sẵn của người dùng và không bật lại `routes/web.php`, không tạo DB/model/migration, không sửa credential hoặc triển khai public.
