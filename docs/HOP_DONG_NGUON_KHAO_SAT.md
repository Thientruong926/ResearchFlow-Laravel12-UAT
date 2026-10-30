# Hợp đồng nguồn khảo sát v1 — Sprint 0

Ngày: 30/10/2026. Trạng thái: **đã có interface, DTO, fixture và test trong repository; chờ xác nhận của thành viên mục 1–3**. Không phải biên bản đã thống nhất với người khác.

## Điểm tích hợp đang chạy

`App\Contracts\SurveyResponseReader::read(SourceDefinition $source, ?float $deadline): ConnectionReadResult` là điểm thay adapter, được bind trong `ResearchServiceProvider`.

- Trang kiểm tra kết nối và `GoogleSheetsService::responses()` đều gọi interface này.
- Binding hiện tại là `GoogleSheetsService` (CSV demo). `readSource(array)` được giữ để tương thích Sprint 1; tích hợp mới dùng `read()`.
- `deadline` là mốc `microtime(true)` tuyệt đối cho yêu cầu hiện tại, không phải timestamp lưu JSON. Adapter phải tôn trọng ngân sách này.
- Adapter API chưa có: nếu truyền `transport=sheets_api` cho adapter CSV, trả `unconfigured/NOT_CONFIGURED`, không gọi HTTP, không chuyển sang CSV.
- Không thay chữ ký `responses`, `summarize`, `filter` đang phục vụ dashboard. `responses` trả `records`, `errors`, `sources`, `checked_at`, `synced_at`; nếu một nguồn lỗi, `errors` phải khác rỗng và `synced_at=null`.

## SourceDefinition

DTO readonly chứa mảng `definition`, chỉ dùng phía server:

| Trường | Quy ước |
|---|---|
| `source_id` | ID ổn định cho nguồn; hiện `src_` + 24 ký tự SHA-256 từ URL canonical, không phải URL hoặc credential |
| `revision` | SHA-256 cấu hình có ảnh hưởng đọc/đối chiếu; thay mapping, assignment, Form, định dạng ngày, timezone hoặc chế độ đồng bộ làm vô hiệu kết quả cũ |
| `spreadsheet_id` | ID `/spreadsheets/d/{id}`; không lấy published ID `/d/e/` làm spreadsheet ID của API |
| `published_id` | Chỉ có khi nguồn CSV dùng URL `/d/e/{published_id}/pub`; spreadsheet_id khi đó là null |
| `sheet_id` | Gid/tab dạng chuỗi số; giữ riêng tab trong định danh nguồn |
| `range` | Vùng đọc, null nghĩa toàn vùng CSV hiện tại; nguồn khác vùng không gộp |
| `header_row` | Hiện adapter CSV lấy hàng đầu payload (`1`); API phải thống nhất hàng header và vị trí tuyệt đối trong Sheet khi bàn giao |
| `transport` | `demo_csv` hoặc `sheets_api`; không tự fallback |
| `timestamp_format` | Định dạng PHP explicit; mặc định `d/m/Y H:i:s`, cấu hình ở `source_defaults` |
| `timezone` | IANA timezone; mặc định `Asia/Ho_Chi_Minh` |
| `columns` | Mapping nhãn `timestamp`, `school`, `group`; không chứa nội dung câu trả lời |
| `assignment_ids` | Danh sách mọi assignment liên quan, thứ tự ổn định |
| `assignments`, `active`, `url` | Trường tương thích hiện hành phía server; không serialize ra client hoặc metadata |

`ConnectionSources` chuyển cấu hình assignment hiện có sang hợp đồng này. Hiện chưa có UI cấu hình từng nguồn API hoặc định dạng ngày riêng từng nguồn. Nguồn API phải có cấu hình riêng tường minh trước khi đưa vào sử dụng; không gắn nhãn API cho URL CSV.

Gộp cùng nguồn chỉ để không đọc lặp. Không gộp/xóa các hàng có cùng nội dung hoặc timestamp. Nguồn có nhiều trường/nhóm cần cột phân loại; chỉ fallback nếu tất cả assignment của nguồn có cùng một giá trị cho trường tương ứng. Thiếu mapping ở nguồn chung trả `SCHEMA_MISMATCH`.

## ReadResult hiện hành

Tái sử dụng `ConnectionReadResult` của Sprint 1:

| Thuộc tính PHP | Ý nghĩa |
|---|---|
| `sourceId` | ID nguồn |
| `status` | `ok`, `disabled`, `unconfigured`, `error` |
| `errorCode`, `message`, `retryable` | Lỗi phân loại và thông báo an toàn; không chứa URL/token/body upstream |
| `headers`, `records` | Nhãn và hàng thô trong bộ nhớ; chưa phải NormalizedRecord |
| `checkedAt` | UTC ISO-8601 khi tạo kết quả đọc; HealthService ghi riêng `last_checked_at` từ đầu lần thử |
| `lastSuccessAt` | UTC ISO-8601 của lần đọc này nếu thành công, null nếu không; lịch sử thành công giữa các request do HealthService quản lý |
| `metadata()` | Danh sách trường vận hành cho phép lưu, gồm row_count/schema_version; không có headers/records |

Sheet hợp lệ chỉ có header là `ok`, row_count=0. File trống không có header là lỗi schema. Mã lỗi: `DISABLED`, `NOT_CONFIGURED`, `UNAUTHORIZED`, `FORBIDDEN`, `NOT_FOUND`, `RATE_LIMITED`, `TIMEOUT`, `UPSTREAM_ERROR`, `INVALID_PAYLOAD`, `SCHEMA_MISMATCH`.

Không sửa metadata() thành serialize toàn DTO. HTTP 200 trang Form chỉ chứng minh trang truy cập được. Chỉ thao tác đối chiếu thủ công có revision mới ghi nhận luồng Form → Sheet.

## NormalizedRecord cho Sprint 2

DTO `App\Data\NormalizedRecord` khai báo `sourceId`, `sourceRowNumber`, `submittedAt`, `schoolCode`, `groupCode`, `answers`, `warnings`. **Sprint 2 đã có ResponseNormalizer và sử dụng cho tra cứu/chi tiết/export; xem bàn giao Sprint 2.** Reader bổ sung `rowNumbers` và `payloadBytes` trong bộ nhớ, không đưa hai trường này vào metadata().

- `submittedAt` là DateTimeImmutable hoặc null; parse strict theo source, giữ warning khi ngày sai, không dùng ngày hôm nay thay thế.
- `sourceRowNumber` phải là dòng tuyệt đối tại thời điểm đọc. Adapter API phải bàn giao vị trí hàng, kể cả khi range bắt đầu sau hàng 1 hoặc có dòng trống. Không suy từ index đã lọc bỏ dòng trống.
- Key câu hỏi ổn định theo nguồn/cột; nhãn hiển thị không phải định danh xuyên nguồn.
- Hàng trùng nội dung vẫn là hai phản hồi. Token chi tiết có revision/HMAC sẽ triển khai Sprint 2.
- `summarize()` hiện vẫn nhận hàng thô và còn logic đoán ngày cũ. Fixture ngày lỗi dùng để giữ đầu vào cho Sprint 2, không chứng nhận chuẩn hóa strict đã xong.

## Fixture và layout

`Tests\Fixtures\Google\SprintZeroFixture` chỉ chứa dữ liệu giả:

- Hai trường `DEMO_01/DEMO_02`, ba nhóm manager/lecturer/student; sáu assignment.
- Năm assignment dùng chung một Sheet, assignment còn lại trả 403.
- Sáu hàng, 24 cột, BOM/Unicode, dấu phẩy/nháy/xuống dòng, ngày sai 31/02, ngày trống, ngày dd/MM dễ nhầm, ô `=`, `+`, `-`, `@`, hai hàng giống nhau.
- `fakeHttp()` chỉ mock URL fixture; stray request bị chặn. Không nạp fixture vào `.env`/settings thật, không gửi Form thật.
- Dùng `google_layout.blade.php` hiện có cho trạng thái/danh sách/cài đặt. Các test kiểm tra render và liên kết menu. UAT desktop/mobile bằng trình duyệt chưa thực hiện.

## Bàn giao API riêng tư còn chờ

| Đầu việc | Người/đơn vị | Thời hạn | Trạng thái |
|---|---|---|---|
| Adapter API readonly, credential ngoài public/Git | Thành viên mục 1 — chưa có tên xác nhận | Chưa xác nhận | Người dùng xác nhận bạn phụ trách chưa làm adapter |
| Assignment/source ID, mapping trường/nhóm, trạng thái mở | Thành viên mục 2 — chưa có tên xác nhận | Chưa xác nhận | Hợp đồng dự thảo |
| Chấp nhận ReadResult và cảnh báo dữ liệu thiếu cho báo cáo | Thành viên mục 3 — chưa có tên xác nhận | Chưa xác nhận | Giữ API summarize cũ, test hồi quy đạt |
| Form/Sheet thử, quyền đọc, format ngày/timezone, header/range | Thành viên tích hợp và người phụ trách khảo sát | Chưa xác nhận | Chưa có nguồn thử được chỉ định |

Gói API cần bàn giao: implementation interface; cấu hình spreadsheet ID/tab/range/header và assignment IDs; định dạng timestamp/timezone; hướng dẫn cấp quyền service account chỉ đọc; fake test ánh xạ lỗi/quota/schema; hướng dẫn credential và nguồn thử. Chốt `google/auth` bằng Composer khi triển khai adapter, chưa thêm dependency không sử dụng.

Không yêu cầu công khai Sheet dữ liệu thật. Sau khi nhận adapter, thay binding bằng adapter/dispatcher có transport tường minh, chạy lại test hợp đồng và hồi quy, rồi đối chiếu phản hồi giả trên nguồn thử. Điều kiện ra Sprint 0 về người phụ trách/thời hạn vẫn **chưa đạt** cho đến khi có xác nhận.
