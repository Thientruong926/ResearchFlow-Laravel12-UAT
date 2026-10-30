# Sprint 0 — Nền tảng và hợp đồng

Ngày 30/10/2026. Thực hiện bổ sung sau Sprint 1 theo yêu cầu; giữ chức năng Sprint 1 và các thay đổi có sẵn.

| ID | Kết quả | Trạng thái |
|---|---|---|
| S0-01 | PHP 8.2.12 tại `C:/xampp/php/php.exe`; bootstrap nạp routes/google.php; route list hiện 19 route ứng dụng (không tính vendor); bốn test baseline đạt 27 assertions | Đạt baseline tự động; không xác nhận Google thật |
| S0-02 | Mặc định session/cache file, queue sync, failed-job null; kiểm tra cấu hình lúc boot; test chặn DB/Redis, chạy route với file session/cache trong thư mục tạm | Đã triển khai và test |
| S0-03 | SurveyResponseReader + SourceDefinition + ReadResult hiện hành + DTO NormalizedRecord; điểm thay adapter dùng chung cho dữ liệu và health | Code/test có; chờ thành viên mục 1–3 xác nhận hợp đồng và lịch bàn giao |
| S0-04 | Fixture hai trường/ba nhóm, nguồn chung/403, 24 cột, Unicode/ngày lỗi/công thức/trùng nội dung; dùng layout Blade hiện có | Đã có fixture/test render; UAT trình duyệt còn chờ |

**Không đánh dấu hoàn tất điều kiện ra Sprint 0:** chưa biết tên người làm adapter API riêng tư và thời hạn bàn giao. Các phần độc lập có thể tiếp tục bằng fake theo kế hoạch. Xem [hợp đồng nguồn v1](HOP_DONG_NGUON_KHAO_SAT.md).

## Cấu hình không DB

`config/session.php`, `config/cache.php`, `config/queue.php` đặt mặc định an toàn ngay cả khi thiếu biến môi trường; các tùy chọn khác vẫn gộp từ cấu hình Laravel 12. `ResearchRuntimePolicy` chạy qua `ResearchServiceProvider` lúc boot:

- Môi trường chạy: file session/cache/limiter; sync queue; không lưu failed-job.
- Testing: cho phép thêm array session/cache. Test file dùng thư mục testing riêng và dọn sau test.
- Cấu hình database/Redis hoặc driver ngoài danh sách bị từ chối rõ ràng trước khi phục vụ route. Sửa SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION trong môi trường triển khai nếu lỗi policy; không bỏ policy để bật DB.
- Không bật lại routes/web.php, không chạy migration/seeder, không sửa `.env` hoặc credential thật.
- `Tests\GoogleTestCase` chặn DB/Redis connection và stray HTTP cho cả baseline lẫn bộ test AdminGoogle; không ảnh hưởng lớp TestCase của suite DB cũ chưa được đăng ký.

`research:check` chỉ in cấu hình và cảnh báo adapter API chưa tích hợp; không gọi Google, không chứng nhận kết nối thành công.

## Kiểm chứng

```powershell
& C:/xampp/php/php.exe artisan test --filter=GoogleSurveyFlowTest
# 4 passed (27 assertions), baseline trước thay đổi Sprint 0
& C:/xampp/php/php.exe artisan test
# 37 passed (289 assertions), gồm baseline + Sprint 1 + Sprint 0
& C:/xampp/php/php.exe artisan route:list --except-vendor
& C:/xampp/php/php.exe artisan research:check
```

Bộ test Sprint 0 kiểm tra contract fixture, nguồn chung, dữ liệu thô không bị sửa/xóa trùng, API riêng tư không fallback CSV, lỗi cấu hình DB/Redis, trang công khai/quản lý/export/check nguồn với file session/cache và DB bị chặn. Test chỉ dùng dữ liệu giả; không gọi nguồn Google thật.

Kết quả cuối: **37 test đạt, 289 assertions**; `git diff --check` không báo lỗi. `research:check` xác nhận session=file, cache=file, queue=sync, failed jobs=null; môi trường hiện đồng bộ OFF, một trường, chưa có assignment. Đây là cấu hình thực được đọc, không phải bằng chứng tích hợp Google.

## Giới hạn baseline cần theo dõi

- Strict date normalization và xuất CSV chống công thức/chặn nguồn thiếu còn thuộc Sprint 2. Test export Sprint 0 chỉ chứng minh route chạy không DB; không phải nghiệm thu tính đầy đủ/an toàn của export.
- CSV hiện lấy header ở hàng đầu payload; chưa có mapping số dòng tuyệt đối phục vụ token chi tiết.
- `ResearchSettings` version/khóa toàn giao dịch/backup là Sprint 3.
- Đường lỗi người tham gia trong GoogleSurveyController còn dùng `withInput()` và validation tự flash input; cần phối hợp người phụ trách khảo sát để loại dữ liệu câu trả lời khỏi session khi xử lý lỗi. Test Sprint 0 về session chỉ kiểm tra luồng quản lý đọc dữ liệu, chưa chứng nhận toàn bộ luồng lỗi người tham gia không lưu payload.
- Chưa UAT Google thật hoặc kiểm tra trực quan desktop/mobile. Không tự gửi dữ liệu thử vào Form đang thu thập.
