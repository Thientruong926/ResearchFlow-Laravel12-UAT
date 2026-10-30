# Sprint 2 — Tra cứu, chi tiết và xuất phản hồi

Ngày bàn giao code: 30/10/2026. Đã tiếp tục từ `SPRINT_2_DIEM_DUNG.md` theo yêu cầu người dùng.

**Code và kiểm thử tự động đã triển khai. Chưa nghiệm thu tích hợp Google thật hoặc UAT trực quan trình duyệt; adapter API riêng tư vẫn chưa được thành viên mục 1 bàn giao.**

## Tiến độ theo đầu việc

| ID | Kết quả | Bằng chứng |
|---|---|---|
| S2-01 | ResponseNormalizer parse ngày strict, warning cho ngày sai, số dòng tuyệt đối theo range/dòng trống, key câu hỏi theo nguồn/vị trí/nhãn | Test ngày dd/MM, 31/02, nguồn chung, Unicode/multiline, range A5, schema đổi |
| S2-02 | ResponseFilterRequest và ResponseQueryService: bộ lọc AND, tìm không phân biệt hoa/thường nhưng phân biệt dấu; khoảng ngày bao gồm ngày đầu/cuối theo timezone nguồn | Test kết hợp từ khóa/trường/nhóm/nguồn/ngày; chỉ date_to; ngày đảo; filter giả/mảng/q dài |
| S2-03 | Bảng gọn và phân trang 25/50/100; giữ bộ lọc; tổng kết quả và số nguồn đọc thành công; phân biệt rỗng/không khớp/không đọc được | Test 61 dòng, trang 2 có 25 dòng, liên kết trang sau giữ bộ lọc; Blade biên dịch đạt |
| S2-04 | Token Crypt 10 phút, source/row/revision/HMAC không chứa câu trả lời; đọc lại nguồn và đối chiếu dòng | Test token giả/hết hạn, mất đăng nhập, sửa/xóa/sắp xếp dòng, đổi header hoặc revision; trả 409 thay vì mở nhầm |
| S2-05 | Stream CSV qua php://output, UTF-8 BOM, union cột theo key nguồn, bảo vệ công thức cả header/ô; chặn xuất thiếu nguồn | Test xuất cả 61 dòng từ trang 2, dấu phẩy/nháy/xuống dòng, công thức sau whitespace/tab/CR/LF, 403 chặn xuất và nguồn khỏe vẫn xuất được |
| S2-06 | Giới hạn trước stream; script đo 10.000 × 100 dùng dữ liệu giả trong RAM | Đo thực trên PHP 8.2.12; chi tiết bên dưới. Không phải số đo tải Google hoặc kiểm thử nhiều người dùng đồng thời |

## Cách dùng

- Vào **Phản hồi** (`/admin/responses`). Chọn từ khóa, trường, nhóm, nguồn, ngày và thứ tự; bấm **Lọc dữ liệu**.
- Ngày không hợp lệ vẫn xem được khi không lọc ngày, nằm cuối danh sách và có cảnh báo. Không tự suy đoán dd/MM thành MM/dd hoặc gán hôm nay.
- **Xem chi tiết** mở toàn bộ câu hỏi/giá trị, escape HTML và giữ xuống dòng. Liên kết hết hạn sau 10 phút; nếu dữ liệu hoặc cấu hình đã đổi, tải lại danh sách.
- **Xuất tất cả kết quả CSV** xuất toàn bộ bộ lọc, không giới hạn ở trang đang xem. Backend đọc lại dữ liệu lúc xuất; không lưu snapshot trên server và không bảo đảm snapshot giao dịch xuyên nhiều Sheet.
- Nếu một nguồn lỗi/tắt/thiếu cấu hình, danh sách cảnh báo dữ liệu chưa đầy đủ; export bị chặn. Chọn rõ một nguồn khỏe để xuất lại. Bộ lọc trường/nhóm không tự bỏ qua nguồn lỗi, vì không thể biết chắc các hàng của nguồn đó khi chưa đọc được.
- URL `date=YYYY-MM-DD` cũ còn tương thích, tương đương khoảng một ngày. Export bỏ qua page/per_page.

## Giới hạn và cấu hình

- `research.responses`: tối đa 10.000 dòng, 100 cột câu hỏi (union theo nguồn), 10 MiB payload tổng cho phạm vi đọc; token TTL 600 giây.
- `research.connections.max_bytes` hiện là 10 MiB cho mỗi tải HTTP. Vượt giới hạn trả `LIMIT_EXCEEDED`, route dữ liệu trả HTTP 413 trước khi tạo file download. Không cắt bớt hàng/cột để trả thành công.
- CSV có thêm 5 cột ngữ cảnh nguồn/thời gian/trường/nhóm/cảnh báo ngoài cột câu hỏi. Cột cùng nhãn ở hai nguồn vẫn là hai cột riêng.
- Một request đọc có ngân sách 30 giây; nguồn chưa đọc được trong ngân sách trở thành lỗi, export không xuất thiếu.
- Nguồn CSV phải dùng range A1 tường minh, range cột hoặc range hàng để xác định vị trí; named range chưa hỗ trợ và bị báo schema lỗi. Dòng đầu payload là header; schema cũ có tên cột bắt đầu `_source_` bị từ chối vì xung đột metadata nội bộ.
- Format ngày/timezone hiện lấy từ `source_defaults` trong cấu hình nghiên cứu, mặc định `d/m/Y H:i:s` và `Asia/Ho_Chi_Minh`. Nếu Sheet đang dùng `Y-m-d H:i`, cần khai báo đúng format trước khi dùng bộ lọc ngày; không tự đoán định dạng. UI cấu hình format riêng từng nguồn chưa có.
- Adapter API tương lai phải cung cấp `rowNumbers` đúng vị trí tuyệt đối và `payloadBytes` đúng kích thước; không suy row từ index đã bỏ dòng trống. CSV detail hiện đọc lại toàn nguồn đã chọn, sau đó tìm đúng dòng và kiểm HMAC; tối ưu API đọc một dòng vẫn là phần tích hợp adapter.

## Kiểm thử và hiệu năng

Đã chạy:

```powershell
& C:/xampp/php/php.exe artisan test
# 63 passed (472 assertions), 6.25 giây ở lần kiểm tra cuối

& C:/xampp/php/php.exe artisan route:list --path=admin/responses
# index, detail, export đều thuộc AdminResponseController

& C:/xampp/php/php.exe artisan view:cache
# Blade templates cached successfully

& C:/xampp/php/php.exe -d memory_limit=256M scripts/benchmark-google-responses.php
```

`git diff --check` không phát hiện lỗi whitespace. Tests kế thừa GoogleTestCase chặn DB/Redis và stray HTTP; chỉ dùng fixture giả.

Số đo thực tại máy Windows hiện tại, PHP 8.2.12, một nguồn giả, 10.000 dòng × 100 cột:

| Chỉ số | Kết quả |
|---|---:|
| Payload CSV đầu vào | 6.171.021 byte |
| CSV xuất | 6.864.474 byte |
| Đọc + chuẩn hóa + lọc/sắp xếp | 1,113 giây |
| Stream CSV | 0,375 giây |
| Tổng (gồm tạo 25 token) | 1,498 giây |
| RAM nền | 20 MiB |
| RAM đỉnh | 220 MiB |
| PHP memory_limit dùng khi đo | 256M |

Script không ghi payload ra ổ đĩa: tạo fixture trong php://memory, fake HTTP, stream qua bộ đếm byte, chỉ in số đo. Kết quả này chứng minh fixture cụ thể chạy được; không chứng minh mọi tổ hợp dữ liệu tới 10 MiB hoặc nhiều request đồng thời đều vừa 256M. Phải đo lại ngân sách RAM/concurrency trên máy triển khai; phân trang chỉ giảm HTML, không giảm RAM đọc nguồn.

## File chính và việc còn chờ

- Controller: `AdminResponseController`; request: `ResponseFilterRequest`; service: `ResponseNormalizer`, `ResponseQueryService`, `ResponseReference`, `SurveyCsvExporter`.
- View: `admin/google_responses`, `admin/response_detail`, `admin/response_error`; giữ route name hiện có, thêm `admin.responses.detail`.
- Đã xóa method responses/export/headers cũ khỏi GoogleAdminController sau khi chuyển route; dashboard/cài đặt giữ nguyên chức năng hiện hành.
- Test mới: `tests/Feature/AdminGoogle/ResponsesTest.php`. Baseline/Sprint 0 đã cập nhật kỳ vọng cho bảng gọn, streamed CSV và chặn nguồn thiếu.
- Tất cả route quản lý dùng admin.session; response private/no-store qua middleware hiện có. Không ghi câu trả lời vào cache/file/log, không flash filter lỗi vào session; export không tạo file tạm. Khi triển khai, cấu hình access log/proxy không ghi query string chứa từ khóa hoặc token chi tiết.
- Dashboard summarize cũ vẫn giữ hợp đồng hàng thô và logic ngày cũ; Sprint 2 áp dụng strict date cho tra cứu/chi tiết/export. Chưa thay toàn bộ báo cáo mục 3 của thành viên khác.
- Adapter API riêng tư và Google thật còn chờ; không gửi thử Form thật. UAT trực quan desktop/mobile/keyboard vẫn thuộc nghiệm thu tiếp theo, chưa đánh dấu đạt chỉ từ test Blade.
- Vấn đề flash input ở đường lỗi người tham gia đã ghi ở Sprint 0 vẫn cần phối hợp riêng; không tuyên bố toàn bộ luồng người tham gia đã được audit trong Sprint 2.

Không tự commit, không deploy public, không thay credential. Các thay đổi có sẵn của người dùng được giữ nguyên.
