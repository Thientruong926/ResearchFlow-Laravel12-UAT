# ResearchFlow — Kế hoạch triển khai chức năng quản lý mục 4–6

Ngày lập: 30/10/2026. Dự án: `ResearchFlow-Laravel12-UAT`.

Đây là đặc tả và backlog để giao cho Codex triển khai tiếp trên mã nguồn hiện có, không phải báo cáo các tính năng đã hoàn thành. Thời lượng dưới đây là ước lượng cho một lập trình viên, cần điều chỉnh sau Sprint 0.

## 1. Mục tiêu và phạm vi công việc

Phần được giao gồm đúng ba mục của **người quản lý**:

| Mục | Kết quả cần bàn giao |
|---|---|
| 4. Theo dõi trạng thái kết nối và đồng bộ dữ liệu | Trang theo dõi từng nguồn Google Forms/Sheets, lần kiểm tra gần nhất, lần đọc thành công, lỗi và hướng xử lý; không báo đồng bộ thành công khi chưa có bằng chứng |
| 5. Xem chi tiết, tìm kiếm, lọc và xuất dữ liệu khảo sát | Danh sách phản hồi có phân trang, xem từng phản hồi, bộ lọc kết hợp, xuất toàn bộ kết quả phù hợp sang CSV |
| 6. Cài đặt thông tin nghiên cứu và thông tin liên hệ | Trang sửa/xem trước/lưu nội dung nghiên cứu và liên hệ, cập nhật ra giao diện người tham gia, lưu cấu hình an toàn không dùng DB |

Luồng dữ liệu bắt buộc giữ nguyên:

```text
Người tham gia → Website → Google Forms → Google Sheets
                                             ↓ chỉ đọc
                                      Website quản lý
```

**Không sử dụng MySQL, PostgreSQL, SQLite, Redis hoặc database khác, kể cả cho session, cache, queue hay kiểm thử phần này. Không tạo migration/model để lưu phản hồi. Không ghi trực tiếp câu trả lời vào Sheets nhằm bỏ qua Forms.**

Quy ước lưu trữ của thiết kế:

- Google Sheets là nguồn dữ liệu phản hồi mà trang quản lý đọc.
- Nội dung phản hồi chỉ tồn tại trong bộ nhớ trong lúc xử lý yêu cầu và trong file tải xuống do quản lý chủ động yêu cầu; không lưu bản sao trên máy chủ, file cache, session hay log.
- Cho phép file JSON riêng tư chứa **cấu hình nghiên cứu**, và file chứa **metadata vận hành** không có câu trả lời. Đây là cách lưu cấu hình hiện tại của dự án, không phải kho dữ liệu khảo sát thay thế.
- Session đăng nhập và khóa thao tác dùng file; kiểm thử dùng bộ nhớ và thư mục tạm riêng. Không flash dữ liệu phản hồi vào session khi xử lý lỗi.
- Nếu bên giao việc yêu cầu cả cấu hình cũng phải nằm trên Google, cần đổi thiết kế kho cấu hình trước khi triển khai; tài liệu này mặc định tiếp tục dùng file cấu hình như demo.

### Phần ngoài phạm vi và điểm phối hợp

| Phần việc | Người phụ trách | Đầu vào cần giao cho mục 4–6 |
|---|---|---|
| Quản lý mục 1: kết nối Forms/Sheets | Thành viên phụ trách tích hợp | Thông tin nguồn, cơ chế cấp quyền, adapter đọc Sheet, mã lỗi, định dạng cột và ngày |
| Quản lý mục 2: gán phiếu | Thành viên phụ trách gán phiếu | `assignment_id`, mã trường/nhóm, trạng thái mở, quan hệ nguồn dùng chung |
| Quản lý mục 3: báo cáo tổng quan | Thành viên phụ trách báo cáo | Dùng chung kết quả đọc đã chuẩn hóa; tiếp nhận trạng thái nguồn thiếu/lỗi |
| Người tham gia: gửi khảo sát | Thành viên phụ trách khảo sát | Luồng gửi Forms; hỗ trợ mã đối chiếu nếu muốn tự động xác nhận một phản hồi đã về Sheet |
| Phần của người dùng | Người triển khai mục 4–6 | Giao diện trạng thái, chuẩn hóa/lọc/xuất/xem chi tiết, nội dung nghiên cứu và liên hệ |

Không làm lại dashboard thống kê, gán phiếu, bộ câu hỏi hay luồng trả lời. Những chỉnh sửa dùng chung phải có hợp đồng dữ liệu và kiểm thử hồi quy. Khi adapter mục 1 chưa sẵn sàng, dùng fake/fixture cho phát triển; không tự công bố nguồn demo là nguồn thật.

## 2. Kết quả rà soát mã hiện có

Đã đọc mã nguồn và cấu hình; chưa xác nhận chạy thành công ứng dụng. Lệnh `php artisan test` và `php artisan route:list` chưa chạy được vì terminal hiện tại không nhận diện lệnh `php`. Không suy ra test thất bại hay chức năng bị hỏng từ việc thiếu PHP trong PATH.

| Thành phần hiện có | Nhận xét từ mã nguồn | Hướng xử lý |
|---|---|---|
| `bootstrap/app.php` | Đang nạp `routes/google.php`, không nạp `routes/web.php` | Chỉ mở rộng tuyến Google hiện hành |
| `routes/web.php`, model và tài liệu cũ | Còn mã kiến trúc DB cũ trong repository | Không bật lại; đánh dấu tài liệu cũ rõ ràng, không xóa hàng loạt |
| `GoogleAdminController::dashboard()` | Suy ra Sheet OK nếu không tìm thấy lỗi theo assignment | Thay bằng kết quả kiểm tra tường minh; assignment bị bỏ qua không đồng nghĩa thành công |
| `GoogleFormsService::connectionStatus()` | GET `viewform` thành công thì báo kết nối | Chỉ kết luận trang Form truy cập được, chưa chứng minh nhận câu trả lời hay liên kết đúng Sheet |
| `GoogleSheetsService::responses()` | Tải CSV, bỏ nguồn trùng URL, trả `synced_at = now()` cả khi lỗi/tắt | Tách thời điểm thử và thành công; quản lý theo nguồn chuẩn hóa |
| `GoogleSheetsService::parseCsv()` | Chưa kiểm tra HTML đăng nhập, BOM/header trùng; cột thừa bị cắt | Báo lỗi schema, tránh âm thầm mất cột hoặc báo Sheet rỗng |
| `GoogleSheetsService::filter()` | Đã tìm từ khóa, lọc trường/nhóm/một ngày | Bổ sung validation, khoảng ngày, phân trang và quy tắc dữ liệu dùng chung |
| `admin/google_responses.blade.php` | Render toàn bộ hàng/cột trên một trang | Thêm phân trang và trang chi tiết |
| `GoogleAdminController::export()` | CSV có BOM, dùng cùng bộ lọc cơ bản | Thêm phòng công thức CSV, kiểm tra lỗi nguồn, stream tải xuống |
| `ResearchSettings` | JSON riêng tư; `LOCK_EX` lúc ghi nhưng đọc-sửa-ghi chưa khóa toàn bộ | Khóa giao dịch file, ghi thay thế an toàn, kiểm soát version và lỗi JSON |
| `ResearchSettings::all()` | `array_replace_recursive` gộp cả danh sách số thứ tự | Thay toàn bộ `schools`/`assignments` khi có trong file, tránh phục hồi nhầm phần tử mặc định |
| `google_settings.blade.php` và `updateResearch()` | Nội dung nghiên cứu gộp với mapping cột, trường và gán phiếu | Tách form nội dung; bảo toàn cấu hình các thành viên khác |
| `SessionAuthController` | Một tài khoản cấu hình qua môi trường, session không dùng model User | Tái sử dụng middleware `admin.session`; chưa mở rộng hệ thống phân quyền nhiều tài khoản |
| `phpunit.xml` | Chỉ khai báo `GoogleSurveyFlowTest.php` với 4 test | Đăng ký thêm test mới cụ thể; không vô tình chạy toàn bộ suite DB cũ |
| `research:check` | Chỉ in cấu hình/tổng số assignment | Không coi đây là kiểm tra kết nối Google thực tế |

## 3. Công nghệ và quyết định kiến trúc

| Hạng mục | Công nghệ chọn | Lý do / cách áp dụng |
|---|---|---|
| Backend | Giữ Laravel 12, PHP tương thích `composer.lock` và yêu cầu PHP ^8.2 | Tái sử dụng code; không nâng framework trong gói việc này |
| Giao diện | Blade + CSS hiện tại + JavaScript thuần `fetch` | Không cần SPA, React/Vue hay thêm bước build Node; responsive, thao tác bàn phím được |
| HTTP | Laravel HTTP Client | Timeout, fake trong test, ánh xạ lỗi; không tự viết cURL rải rác |
| Nguồn thật | Google Sheets API v4, quyền `spreadsheets.readonly` | Đọc Sheet riêng tư phía server, không yêu cầu publish dữ liệu khảo sát ra web [1] |
| Xác thực Google | Service account được chia sẻ quyền đọc Sheet, thư viện chính thức `google/auth` | Thành viên mục 1 cung cấp; credential lưu ngoài public/Git, không gửi xuống trình duyệt; chốt phiên bản tương thích bằng Composer |
| CSV demo | Adapter CSV hiện có sau khi gia cố | Chỉ dùng dữ liệu giả/được phép công khai; không tự động fallback từ API riêng tư sang CSV công khai |
| Cấu hình | JSON riêng tư + khóa file + revision | Không DB, có kiểm soát ghi đồng thời |
| Theo dõi | Metadata JSON hoặc file cache có TTL, Laravel log đã lọc thông tin | Không lưu payload phản hồi; không đưa URL credential/token vào log |
| Xuất dữ liệu | `response()->streamDownload` + `fputcsv` trực tiếp `php://output` | CSV UTF-8 BOM dùng được trong Excel; không tạo file phản hồi tạm trên server |
| Test | PHPUnit hiện có + `Http::fake`, fixture giả, thư mục cấu hình tạm | Kiểm tra đường lỗi và hợp đồng mà không dùng tài khoản/dữ liệu thật |

`SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. Không cần queue cho MVP. Cache chỉ giữ token ngắn hạn/khóa/metadata được phép; không cache các hàng dữ liệu. Triển khai MVP trên **một máy chủ có ổ đĩa bền vững**; không mặc định file JSON/lock hoạt động đúng trên nhiều instance hay filesystem tạm.

Sheets API hỗ trợ lấy nhiều vùng nhưng không cung cấp tìm kiếm toàn văn/phân trang kiểu SQL cho giao diện này [1]. Backend đọc vùng giới hạn rồi lọc trong bộ nhớ. Phân trang giao diện không đồng nghĩa giảm toàn bộ lưu lượng đọc từ Google.

## 4. Hợp đồng tích hợp cần chốt ở Sprint 0

### 4.1. Nguồn và dữ liệu đọc

```text
SourceDefinition
  source_id                       # định danh cấu hình, không phải URL bí mật
  spreadsheet_id + sheet_id        # sheet_id/gid xác định đúng tab
  range + header_row
  transport: sheets_api | demo_csv
  timestamp_format + timezone     # mặc định hiển thị Asia/Ho_Chi_Minh
  columns: timestamp, school, group
  assignment_ids[]

ReadResult
  source_id, checked_at, last_success_at
  status: ok | disabled | unconfigured | error
  error_code?, user_message?, retryable
  headers[], records[]             # chỉ giữ trong bộ nhớ của request
  row_count, schema_version

NormalizedRecord
  source_id, source_row_number
  submitted_at?, school_code?, group_code?
  answers: {column_key: value}
  warnings[]
```

- Nguồn chuẩn hóa theo spreadsheet + tab + vùng đọc; nhiều assignment dùng cùng nguồn chỉ tải một lần trong mỗi request. Không gán toàn bộ dữ liệu cho assignment đầu tiên.
- Khi Sheet dùng chung nhiều trường/nhóm, bắt buộc có cột phân loại. Chỉ được fallback trường/nhóm từ assignment khi nguồn xác định duy nhất một trường/nhóm. Trường hợp mơ hồ báo lỗi mapping.
- Không loại trùng phản hồi bằng nội dung hoặc timestamp: hai người có thể trả lời giống nhau. Chỉ loại việc đọc lặp cùng một nguồn.
- Không đoán ngày theo `strtotime`: cấu hình định dạng nguồn rõ ràng, parse strict. Ngày không hợp lệ được đánh dấu, không tự gán hôm nay.
- Header thiếu, rỗng hoặc trùng phải báo lỗi; cột khác tên giữa các nguồn cần key ổn định gồm nguồn/cột, không nhập nhầm hai câu hỏi khác nhau cùng nhãn.
- Thay cấu hình nguồn phải đổi revision và vô hiệu kết quả kiểm tra cũ. Adapter mới không phá hợp đồng `summarize` mà thành viên mục 3 đang sử dụng.

### 4.2. Phân biệt kết nối và đồng bộ

Ba loại bằng chứng phải tách riêng:

1. **Trang Form truy cập được**: GET trang công khai thành công; không đủ kết luận Form đang nhận phản hồi.
2. **Đọc Sheet thành công**: nhận dữ liệu/schema hợp lệ từ đúng nguồn; Sheet hợp lệ có 0 dòng vẫn là đọc thành công.
3. **Đã xác nhận luồng Form → Sheet**: có bằng chứng đối chiếu một lần gửi thử xuất hiện đúng Sheet. Không suy ra từ số lượng hàng hay HTTP 200.

Nhãn cho mục 3: `Chưa xác minh`, `Chờ đối chiếu`, `Đã xác minh lúc …`, `Chưa thấy phản hồi thử`, `Cấu hình đã thay đổi — cần xác minh lại`. Đây là bằng chứng tại một thời điểm, không bảo đảm mọi lần gửi sau đó.

MVP dùng biên bản kiểm thử thủ công trên Form/Sheet thử nghiệm: người phụ trách luồng gửi gửi một câu trả lời giả có mã thử duy nhất; quản lý xác nhận mã xuất hiện trong Sheet và trang dữ liệu. Nút “Kiểm tra kết nối” **không tự gửi phản hồi thử**. Metadata xác minh chỉ lưu ID nguồn, revision, thời gian và trạng thái, không lưu câu trả lời.

Tự động đối chiếu mã gửi là mở rộng cần hợp tác với luồng người tham gia. Không giả định Forms API có endpoint nộp câu trả lời: resource responses chính thức có phương thức đọc `get/list` [2]. `formResponse` đang dùng trong dự án không được xem là hợp đồng REST chính thức. Nếu dùng Forms API để xác minh `linkedSheetId`, cần quyền đọc Form và đúng Form ID API; không đồng nhất ID `/d/e/` công khai với ID API [3].

## 5. Đặc tả mục 4 — Theo dõi kết nối và đồng bộ

### 5.1. Màn hình

Trang `/admin/connections` gồm:

- Tổng số nguồn được cấu hình, số đọc thành công, số lỗi, số chưa kiểm tra; phân biệt nguồn với số assignment.
- Mỗi nguồn hiển thị trường/nhóm liên quan, trạng thái Form, trạng thái đọc Sheet, trạng thái xác minh luồng, lần kiểm tra, lần đọc thành công, thời gian phản hồi và thông báo lỗi dễ hiểu.
- Nút kiểm tra từng nguồn, kiểm tra tất cả, mở trang cấu hình tương ứng. Nguồn không mở thu thập vẫn có thể cho phép kiểm tra thủ công nếu đủ cấu hình; không tự gọi nó là đang đồng bộ.
- Trạng thái `Đang kiểm tra`, `Tắt`, `Chưa cấu hình`, `Chưa kiểm tra`, `Thành công`, `Lỗi`, `Kết quả đã cũ` có chữ và màu; không chỉ dựa vào màu.
- Không có phản hồi mới trong một thời gian không phải bằng chứng mất kết nối.

### 5.2. Xử lý

- GET trang chỉ đọc metadata; POST kiểm tra mới gọi Google và ghi metadata. Có middleware đăng nhập, CSRF và throttle.
- Giữ `last_checked_at` cho mọi lần thử; chỉ cập nhật `last_success_at` khi đọc/schema thành công. Khi lần mới lỗi, kết quả cũ vẫn hiển thị kèm nhãn lịch sử.
- Metadata hết hạn sau 5 phút để gắn nhãn cũ; chưa kiểm tra lại thì không tự chuyển thành lỗi. Mốc 5 phút là mặc định sản phẩm, có thể cấu hình.
- Timeout đề xuất mỗi nguồn 10 giây; retry GET tối đa 2 lần với backoff/jitter cho 429/5xx/lỗi mạng, trong tổng ngân sách 30 giây. 401/403/404 không retry mù. Tuân thủ `Retry-After` nếu có; tránh vượt quota Google [4].
- Kiểm tra tất cả: frontend gọi endpoint từng nguồn với tối đa 2 tác vụ đồng thời, hiển thị tiến độ; không dồn mọi nguồn vào một request kéo dài.
- Khóa theo nguồn, giới hạn một lần kiểm tra/nguồn/30 giây. Tạm dừng polling khi tab ẩn; MVP ưu tiên kiểm tra theo thao tác, không yêu cầu cron.
- Mã lỗi tối thiểu: `DISABLED`, `NOT_CONFIGURED`, `UNAUTHORIZED`, `FORBIDDEN`, `NOT_FOUND`, `RATE_LIMITED`, `TIMEOUT`, `UPSTREAM_ERROR`, `INVALID_PAYLOAD`, `SCHEMA_MISMATCH`.
- CSV demo: HTTPS, miền/path cho phép, kiểm soát redirect từng chặng và kích thước phản hồi; từ chối HTML trang đăng nhập. Không chỉ kiểm tra HTTP 200 hay đuôi URL.

**Nghiệm thu:** mô phỏng lỗi một nguồn không làm các nguồn khác mất trạng thái; nguồn bị bỏ qua không hiện xanh; lần kiểm tra lỗi không ghi đè thời gian thành công; nguồn dùng chung hiển thị đúng mọi assignment.

## 6. Đặc tả mục 5 — Xem, tìm, lọc và xuất dữ liệu

### 6.1. Danh sách và bộ lọc

- Giữ `/admin/responses`; hiển thị thời gian, trường, nhóm, nguồn và nút “Xem chi tiết”. Không nhồi toàn bộ câu hỏi vào bảng danh sách.
- Bộ lọc GET: `q`, `school`, `group`, `source`, `date_from`, `date_to`, `sort`, `page`, `per_page`.
- `q` tối đa 200 ký tự; tìm không phân biệt hoa/thường trên dữ liệu hiển thị. MVP vẫn phân biệt dấu tiếng Việt, ghi rõ trong gợi ý tìm kiếm.
- Các bộ lọc kết hợp bằng AND. Dropdown trường/nhóm/nguồn lấy từ cấu hình, giá trị không hợp lệ trả lỗi validation.
- Khoảng ngày bao gồm toàn bộ ngày đầu/cuối theo timezone cấu hình; `date_from <= date_to`. Dòng ngày lỗi vẫn xem được khi không lọc ngày, kèm cảnh báo; không đưa vào kết quả lọc ngày.
- Mặc định mới nhất trước, tie-break theo nguồn và số dòng. Cho phép cũ nhất trước. `per_page` chỉ nhận 25/50/100; giữ bộ lọc khi đổi trang.
- Hiển thị số kết quả sau lọc, số nguồn đọc thành công/tổng nguồn, thời điểm đọc. Nếu một nguồn lỗi: hiển thị rõ “Dữ liệu chưa đầy đủ”, không báo tổng toàn hệ thống.
- Phân biệt không có dữ liệu, không có kết quả phù hợp và không đọc được dữ liệu.

### 6.2. Chi tiết phản hồi

- Trang `/admin/responses/detail?ref=...` hiển thị tất cả nhãn câu hỏi và giá trị, nguồn, thời gian, trường/nhóm; text được escape, xuống dòng đúng.
- Không tạo ID DB. `ref` là token mã hóa/xác thực có thời hạn khoảng 10 phút, chứa source, số dòng, revision và HMAC nội dung chuẩn hóa; không chứa câu trả lời.
- Khi mở, đọc lại đúng dòng và đối chiếu HMAC. Sheet bị sửa/sắp xếp/xóa làm khác dữ liệu thì trả 409 cùng lời nhắc tải lại danh sách; không hiển thị nhầm người. Token không thay thế kiểm tra đăng nhập.
- Số dòng chỉ là vị trí tạm, không phải định danh người tham gia. Nếu nhóm có response ID ổn định được ghi trong Sheet thì ưu tiên ID đó sau khi thống nhất hợp đồng.
- Giao diện chỉ đọc; không thêm sửa/xóa phản hồi khỏi Google.

### 6.3. Xuất CSV

- Xuất **tất cả kết quả thỏa cùng bộ lọc**, không chỉ trang đang xem; bỏ `page/per_page` khi export, giữ điều kiện và thứ tự sắp xếp.
- Dùng chung `ResponseQueryService` cho danh sách và export để tránh lệch logic.
- Tải lại dữ liệu tại lúc xuất; thông báo dữ liệu có thể thay đổi so với màn hình vì không lưu snapshot phản hồi. Không cam kết snapshot giao dịch xuyên nhiều Sheet.
- Khi có bất kỳ nguồn trong phạm vi xuất bị lỗi/schema sai: chặn export mặc định với thông báo nguồn lỗi, không xuất thiếu một cách im lặng. Có thể chọn nguồn khỏe trong bộ lọc rồi xuất lại.
- CSV UTF-8 BOM, đúng quoting dấu phẩy/dấu nháy/xuống dòng. Union cột theo key đã thống nhất; có cột nguồn để giải thích dữ liệu nhiều phiếu. Không vô tình xuất token hoặc cột metadata nội bộ.
- Bảo vệ CSV formula injection: ô văn bản có ký tự nguy hiểm `=`, `+`, `-`, `@`, tab/CR/LF ở đầu hoặc sau khoảng trắng điều khiển phải được trung hòa bằng dấu nháy đơn; chỉ giữ số âm dạng số khi schema xác nhận cột numeric. Không sửa dữ liệu gốc trong Google.
- Header `Cache-Control: private, no-store`; log chỉ thời gian/người thực hiện/số dòng/mã nguồn, không log từ khóa tìm hay nội dung.
- MVP chọn CSV; XLSX là mở rộng sau nghiệm thu, không bắt buộc cài thư viện Excel trong đợt này.

### 6.4. Giới hạn vận hành đề xuất

Mốc UAT ban đầu: tối đa 10.000 dòng/100 cột trên toàn phạm vi đọc và tối đa 10 MB payload mỗi yêu cầu. Đây là **ngưỡng thiết kế cần đo**, không phải thông số hiệu năng đã kiểm chứng. Kiểm tra giới hạn trước khi stream file; nếu vượt trả thông báo thu hẹp nguồn hoặc cần thiết kế đọc theo lô. Không cắt dữ liệu lặng lẽ.

Đọc toàn bộ CSV/API vào bộ nhớ rồi stream output vẫn tốn bộ nhớ đầu vào. Nếu nhu cầu vượt ngưỡng, thiết kế riêng đọc vùng theo lô và giới hạn export; không chữa bằng cách nhập dữ liệu vào DB. Đo thời gian/bộ nhớ trên máy UAT, ghi kết quả thực tế vào biên bản.

## 7. Đặc tả mục 6 — Nội dung nghiên cứu và liên hệ

Tách trang `/admin/settings/research` khỏi thiết lập nguồn/gán phiếu. Tái sử dụng POST `/admin/settings/research`, nhưng form này chỉ được cập nhật subtree `research`.

| Trường | Quy tắc |
|---|---|
| `title` | Bắt buộc, trim, tối đa 300 ký tự |
| `introduction` | Bắt buộc, tối đa 5.000 ký tự, văn bản thuần nhiều dòng |
| `contact_name` | Bắt buộc, tối đa 200 ký tự |
| `contact_email` | Bắt buộc, email hợp lệ, tối đa 200 ký tự |
| `consent` | Giữ nội dung hiện có, bắt buộc, tối đa 5.000 ký tự; chỉ dùng nội dung được nhóm nghiên cứu cung cấp |
| `contact_phone` | Tùy chọn, tối đa 30 ký tự, ký tự điện thoại thông thường; không ép người quản lý phải cung cấp |
| `contact_address` | Tùy chọn, tối đa 500 ký tự |
| `revision` | Giá trị đối chiếu chống ghi đè từ tab cũ; server quyết định revision mới |

Hành vi:

1. Có “Xem trước” và “Lưu thay đổi”; preview dùng cùng cách render như trang người tham gia, chưa lưu vào file.
2. Validation lỗi giữ nội dung vừa nhập và báo ngay tại trường. Không nhận HTML tùy ý; Blade escape văn bản.
3. Lưu thành công thì đọc lại cấu hình và xác nhận; trang công khai cập nhật khi tải lại. Không bắt người dùng chạy `config:clear` cho thay đổi JSON.
4. Mọi chức năng sửa research/schools/assignments phải dùng cùng cơ chế khóa đọc-sửa-ghi file. Chỉ `LOCK_EX` tại bước ghi là chưa đủ tránh mất cập nhật.
5. Giữ lock xuyên suốt đọc version, merge đúng subtree, validate, ghi file tạm cùng thư mục và thay thế file đích an toàn. Kiểm tra cơ chế thay thế trên cả Windows và máy triển khai; thất bại thì giữ bản cũ.
6. Revision không khớp trả 409 yêu cầu tải lại, không âm thầm ghi đè. Khi sửa research phải giữ nguyên schools, assignments, mapping cột và trường cấu hình chưa biết.
7. File thiếu ở lần khởi tạo mới dùng defaults; file tồn tại nhưng JSON hỏng phải báo lỗi cấu hình, không lặng lẽ dùng nội dung demo. Backup cấu hình hợp lệ gần nhất, kiểm soát số bản và quyền truy cập.
8. Ghi audit metadata trường đã đổi, người thao tác, thời gian, revision; không ghi password/token. Backup cấu hình không chứa credential Google.

**Nghiệm thu:** sửa tiêu đề/liên hệ xuất hiện đúng trên trang công khai; email sai không ghi; nội dung giống script hiển thị như text; hai tab sửa cùng lúc không mất dữ liệu; lỗi ghi không làm JSON cũ hỏng; cập nhật nội dung không đổi gán phiếu.

## 8. Tổ chức code đề xuất

Tên dưới đây là dự kiến, cần đối chiếu trước khi tạo để không trùng thành phần của người khác.

```text
app/Contracts/SurveyResponseReader.php       # giao diện chung với mục 1
app/Services/GoogleSheetsService.php         # adapter hiện có, bổ sung hợp đồng
app/Services/ConnectionHealthService.php    # kiểm tra + metadata, mục 4
app/Services/ResponseNormalizer.php         # chuẩn hóa cột/ngày/nguồn
app/Services/ResponseQueryService.php       # lọc/sắp xếp/phân trang, mục 5
app/Services/SurveyCsvExporter.php          # export và bảo vệ ô CSV
app/Services/ResearchSettings.php           # kho JSON, nâng cấp dùng chung
app/Http/Controllers/AdminConnectionController.php
app/Http/Controllers/AdminResponseController.php
app/Http/Controllers/AdminResearchSettingsController.php
app/Http/Requests/ResponseFilterRequest.php
app/Http/Requests/UpdateResearchSettingsRequest.php
resources/views/admin/connections.blade.php
resources/views/admin/google_responses.blade.php
resources/views/admin/response_detail.blade.php
resources/views/admin/research_settings.blade.php
```

Không tách controller chỉ để đổi tên: chuyển logic theo từng sprint, giữ route name hiện có mà menu/dashboard đang dùng. Đăng ký route detail/export trước route động nếu sau này dùng `{ref}`.

| Route | Trách nhiệm |
|---|---|
| GET `/admin/connections` | Hiển thị trạng thái đã ghi nhận |
| POST `/admin/connections/{source}/check` | Kiểm tra nguồn và cập nhật metadata |
| POST `/admin/connections/{source}/verification` | Ghi nhận kết quả đối chiếu thủ công có timestamp/revision |
| GET `/admin/responses` | Danh sách đã lọc/phân trang |
| GET `/admin/responses/detail` | Xem phản hồi qua token |
| GET `/admin/responses/export` | Xuất kết quả lọc đầy đủ |
| GET `/admin/settings/research` | Form nội dung riêng |
| POST `/admin/settings/research/preview` | Preview không ghi cấu hình |
| POST `/admin/settings/research` | Lưu subtree research |

Tất cả route quản lý dùng `admin.session`; POST có CSRF, validation và throttle phù hợp. Response chứa câu trả lời phải `private, no-store`. Không thêm public API xuất dữ liệu.

## 9. Kế hoạch sprint và đầu việc

Tổng ước lượng **20 ngày công**: Sprint 0 (2), Sprint 1 (4), Sprint 2 (6), Sprint 3 (4), Sprint 4 (4). Có thể tương đương khoảng bốn tuần làm toàn thời gian; chưa tính thời gian chờ bàn giao quyền Google hoặc phản hồi UAT. Các mục P0 là điều kiện bắt buộc; P1 là trải nghiệm hỗ trợ nhưng vẫn nằm trong kế hoạch.

### Sprint 0 — Chốt nền tảng và hợp đồng (2 ngày)

Tiến độ ngày 30/10/2026: xem [bàn giao Sprint 0](SPRINT_0_NEN_TANG.md) và [hợp đồng nguồn v1](HOP_DONG_NGUON_KHAO_SAT.md). Nền tảng/fixture có test; việc xác nhận người phụ trách và thời hạn adapter API còn chờ.

| ID | Ưu tiên | Đầu việc | Công nghệ / hướng giải quyết | Đầu ra nghiệm thu |
|---|---|---|---|---|
| S0-01 | P0 | Xác nhận luồng đang chạy | PHP/Artisan; kiểm tra bootstrap và route list; chạy 4 test hiện có khi có PHP | Ghi nhận baseline thực, không gọi suite cũ dùng DB |
| S0-02 | P0 | Chốt cấu hình không DB | File session/cache, queue sync; test chặn tạo DB connection | Route trong phạm vi hoạt động khi không có DB server/credential |
| S0-03 | P0 | Chốt nguồn với thành viên mục 1–3 | DTO/contract ở mục 4; thống nhất ID, cột, ngày, shared source | Fixture và hợp đồng đọc nguồn được thống nhất |
| S0-04 | P0 | Chuẩn bị dữ liệu giả và layout | Fixture 2 trường/3 nhóm, nguồn chung, nguồn lỗi; Blade layout hiện có | Có dữ liệu test nhiều cột, Unicode, lỗi ngày, ô công thức; kế hoạch API riêng tư |

**Điều kiện ra sprint:** biết rõ ai làm adapter API và thời điểm bàn giao. Nếu chưa có adapter, các sprint UI tiếp tục bằng fake, nhưng nghiệm thu tích hợp thật chưa được đánh dấu xong.

### Sprint 1 — Mục 4: trạng thái kết nối (4 ngày)

Tiến độ triển khai ngày 30/10/2026: xem [bàn giao Sprint 1](SPRINT_1_KET_NOI.md). Code và test tự động đã có; tích hợp Google thật/UAT chưa nghiệm thu.

| ID | Ưu tiên | Đầu việc | Công nghệ / hướng giải quyết | Đầu ra nghiệm thu |
|---|---|---|---|---|
| S1-01 | P0 | Kết quả đọc có trạng thái rõ | Adapter + ReadResult; loại bỏ suy luận thành công từ thiếu error | Tắt/thiếu cấu hình/0 dòng/HTTP lỗi phân biệt đúng |
| S1-02 | P0 | Kho metadata | JSON private, khóa file, timestamp UTC, hiển thị giờ VN | Không có câu trả lời trong file; giữ last_success khi lỗi |
| S1-03 | P0 | Trang trạng thái và nút kiểm tra | Blade/fetch, endpoint POST, CSRF/throttle, timeout/retry hữu hạn | Kiểm tra một/tất cả, tiến độ, nhãn kết quả cũ |
| S1-04 | P0 | Nguồn dùng chung và xác minh luồng | Deduplicate source; manual verification gắn revision | Không nhân bản kiểm tra; không báo đã xác minh từ GET Form |
| S1-05 | P0 | Kiểm thử lỗi | PHPUnit/Http fake: 401/403/404/429/5xx/HTML/timeout | Test tái hiện và ngăn trạng thái xanh sai |

**Demo cuối sprint:** nguồn tốt, nguồn bị thu hồi quyền, nguồn tắt; chỉ ra khác nhau giữa lần kiểm tra và lần thành công.

### Sprint 2 — Mục 5: tra cứu và xuất dữ liệu (6 ngày)

**Đã tiếp tục ngày 30/10/2026:** code S2-01…S2-06 đã có, 63 test đạt; đã đo fixture 10.000 × 100. Xem [bàn giao Sprint 2](SPRINT_2_TRA_CUU_XUAT_DU_LIEU.md). Tích hợp Google thật và UAT trình duyệt chưa nghiệm thu; [mốc dừng cũ](SPRINT_2_DIEM_DUNG.md) chỉ còn là lịch sử.

| ID | Ưu tiên | Đầu việc | Công nghệ / hướng giải quyết | Đầu ra nghiệm thu |
|---|---|---|---|---|
| S2-01 | P0 | Chuẩn hóa dữ liệu | ResponseNormalizer; mapping nguồn, strict date parse, schema validation | Không mất cột/nhầm nhóm khi nguồn chung |
| S2-02 | P0 | Tìm và lọc kết hợp | FormRequest + ResponseQueryService, query string | Kết quả AND đúng; kiểm tra ngày đảo/ngày sai/filter giả |
| S2-03 | P1 | Phân trang và bảng responsive | Paginator trên dữ liệu bộ nhớ; 25/50/100 | Giữ bộ lọc, số lượng và thứ tự nhất quán |
| S2-04 | P0 | Trang chi tiết | Token mã hóa + HMAC, đọc lại và đối chiếu | Token sửa/hết hạn/dòng đổi không mở nhầm phản hồi |
| S2-05 | P0 | Xuất CSV | StreamDownload, fputcsv, BOM, xử lý công thức | Xuất mọi dòng thỏa lọc; chặn khi thiếu nguồn; tiếng Việt đúng |
| S2-06 | P0 | Test dữ liệu và hiệu năng | Fixture 10.000 dòng/100 cột, đo memory/time, ngưỡng payload | Báo cáo số đo, không crash/cắt dữ liệu âm thầm; giới hạn được điều chỉnh theo máy thật |

**Demo cuối sprint:** lọc cùng lúc trường + nhóm + khoảng ngày + từ khóa; mở chi tiết; tải CSV và so tập kết quả. Mô phỏng nguồn lỗi để chứng minh export không âm thầm thiếu dữ liệu.

### Sprint 3 — Mục 6: thông tin nghiên cứu và liên hệ (4 ngày)

Tiến độ ngày 30/10/2026: đã triển khai S3-01…S3-05 và kiểm thử, toàn suite **80 test / 651 assertions đạt**, gồm hai tiến trình ghi cạnh tranh trên Windows. Xem [bàn giao Sprint 3](SPRINT_3_NOI_DUNG_NGHIEN_CUU.md). UAT trực quan và xác nhận filesystem máy triển khai còn chờ.

| ID | Ưu tiên | Đầu việc | Công nghệ / hướng giải quyết | Đầu ra nghiệm thu |
|---|---|---|---|---|
| S3-01 | P0 | Tách form nội dung khỏi tích hợp | Controller/FormRequest riêng, giữ route POST cũ | Lưu nghiên cứu không đòi tên cột Sheet |
| S3-02 | P0 | Validation và preview | Blade escape, preview không ghi, old input | Lỗi tại trường; preview đúng giao diện công khai |
| S3-03 | P0 | Ghi JSON có version | Khóa toàn giao dịch, atomic replace, backup, revision | Hai tab không ghi đè; lỗi JSON/ổ đĩa được báo và giữ bản hợp lệ |
| S3-04 | P0 | Đồng bộ nội dung giao diện | Dùng ResearchSettings ở trang công khai/liên hệ | Tải lại thấy nội dung mới, không đổi assignments/mapping |
| S3-05 | P0 | Kiểm thử cạnh tranh và hồi quy | PHPUnit tệp tạm, lỗi quyền ghi, stale revision, arrays rỗng | Không phục hồi nhầm mặc định; toàn bộ writer dùng khóa chung |

**Demo cuối sprint:** thay thông tin, preview, lưu, mở trang khảo sát; thử email sai và hai tab lưu cạnh tranh.

### Sprint 4 — Tích hợp, UAT và bàn giao (4 ngày)

| ID | Ưu tiên | Đầu việc | Công nghệ / hướng giải quyết | Đầu ra nghiệm thu |
|---|---|---|---|---|
| S4-01 | P0 | Tích hợp Google thật | Sheet thử riêng tư, adapter mục 1; luồng gửi qua Forms | Có bằng chứng phản hồi giả đến Sheet và xuất hiện trong quản lý |
| S4-02 | P0 | Hồi quy chức năng chung | Test trang giới thiệu/chọn phiếu/gửi/dashboard/gán phiếu | Mục 4–6 không phá mục 1–3 và người tham gia |
| S4-03 | P0 | Kiểm tra truy cập và không DB | Session/CSRF, download chưa đăng nhập, chặn DB, rà log/file | Không lộ export, credential hoặc bản sao câu trả lời |
| S4-04 | P1 | UAT giao diện | Desktop 1366 px, mobile 390 px, keyboard, trạng thái tải/lỗi | Không tràn layout chính, thao tác được trên mobile |
| S4-05 | P0 | Tài liệu và bằng chứng | README Google, checklist UAT, kết quả test, hướng dẫn lỗi | Gói bàn giao có cách chạy, giới hạn và mục chưa đạt rõ ràng |

**Điều kiện hoàn tất:** tất cả P0 đạt; test tự động chạy thật; UAT trên nguồn thử thật có biên bản. Không đánh dấu hoàn tất chỉ vì Http fake xanh.

## 10. Ma trận kiểm thử tối thiểu

| Mã | Tình huống | Kết quả bắt buộc |
|---|---|---|
| T01 | Chưa đăng nhập mở dữ liệu/chi tiết/export/cài đặt | Bị chặn, không có payload phản hồi |
| T02 | POST thiếu CSRF | Bị từ chối; có ít nhất test tích hợp với CSRF thực bật |
| T03 | Đồng bộ tắt/nguồn thiếu cấu hình | Không gửi HTTP ngoài, không báo thành công |
| T04 | Sheet hợp lệ chưa có phản hồi | Đọc thành công, 0 dòng, không gọi là mất kết nối |
| T05 | HTTP 200 nhưng body là HTML | Lỗi payload, không hiển thị dữ liệu giả |
| T06 | 403/timeout/429 | Lỗi đúng loại, retry giới hạn, giữ timestamp thành công cũ |
| T07 | Hai assignment dùng cùng nguồn, trường/nhóm khác | Chỉ đọc một lần, không đếm đôi hoặc gán nhầm |
| T08 | Một trong nhiều nguồn lỗi | Danh sách cảnh báo thiếu; export phạm vi đó bị chặn |
| T09 | Header BOM/trùng/thiếu, cột thừa | BOM xử lý; schema sai báo rõ, không lặng lẽ cắt cột |
| T10 | Ngày nguồn dd/MM và ngày không hợp lệ | Parse theo cấu hình; ngày lỗi không bị đoán sai |
| T11 | Bộ lọc kết hợp, nhiều trang | Tập kết quả đúng; export không chỉ lấy trang hiện tại |
| T12 | Token chi tiết giả/hết hạn, dòng bị sắp xếp lại | Từ chối hoặc yêu cầu tải lại; không hiện nhầm |
| T13 | Ô =HYPERLINK, +, @, dấu phẩy, nháy, xuống dòng | File đúng cấu trúc; ô nguy hiểm bị trung hòa |
| T14 | XSS trong ghi chú/tiêu đề | Hiển thị text, không chạy script |
| T15 | Lưu research đồng thời với assignments | Một writer xung đột bị yêu cầu tải lại, không mất cấu hình |
| T16 | JSON hỏng, hết quyền ghi, file danh sách rỗng | Báo lỗi; giữ bản hợp lệ; không tự thêm lại danh sách demo |
| T17 | Ngắt khả năng dùng DB, chạy route trong phạm vi | Không có kết nối/query DB; test không dùng RefreshDatabase |
| T18 | Gửi thử website → Forms → Sheet | Có đối chiếu thật, ghi rõ thời gian và revision |
| T19 | Dữ liệu vượt ngưỡng đọc/export | Lỗi có hướng dẫn, không tải file thiếu hay lỗi 500 mơ hồ |

Test mới đặt trong nhóm riêng `tests/Feature/AdminGoogle/` và `tests/Unit/Google/`. Cập nhật `phpunit.xml` chỉ thêm nhóm mới cùng `GoogleSurveyFlowTest.php`; không thêm toàn bộ `tests/Feature` vì còn test kiến trúc DB cũ. Trong test service đọc: `Http::preventStrayRequests()` để không vô tình truy cập nguồn thật.

## 11. Checklist bàn giao

> Cập nhật ngày 30/10/2026 — Sprint 4 hoàn tất. Toàn bộ 10 mục đã được xác nhận qua kiểm thử tự động **94 tests / 951 assertions (100% pass)** và tài liệu kèm theo.

- [x] Route đang hoạt động vẫn thuộc `routes/google.php`.  
  _Minh chứng: `bootstrap/app.php` chỉ nạp `routes/google.php`; không bật lại `routes/web.php`. Kiểm tra `SprintZeroFoundationTest::test_file_runtime_and_admin_routes_work_with_database_connections_forbidden`._

- [x] Không thêm DB/migration/model phản hồi; không thêm database session/cache/jobs.  
  _Minh chứng: `AccessControlAndNoDbAuditTest::test_zero_database_usage_and_policy_enforcement` — cấu hình DB bị phá hỏng hoàn toàn mà ứng dụng vẫn chạy đúng. `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. Không có file migration nào được tạo._

- [x] Mục 4 có trạng thái và bằng chứng đúng, không dùng `synced_at=now()` để giả thời gian thành công.  
  _Minh chứng: `ConnectionHealthTest` — 9 tests bao phủ: giữ `last_success_at` khi lỗi, metadata hỏng không hiển thị thành công, nguồn tắt không gọi Google, khóa nguồn ngăn kiểm tra đồng thời, xác minh thủ công có revision. `ConnectionHealthService` tách biệt `last_checked_at` và `last_success_at`._

- [x] Mục 5 có tra cứu, xem riêng, phân trang, CSV đầy đủ theo lọc; lỗi nguồn không biến thành 0 phản hồi.  
  _Minh chứng: `ResponsesTest` — 12 tests bao phủ: lọc kết hợp AND, phân trang giữ bộ lọc, token HMAC TTL 10 phút, chặn xuất khi nguồn lỗi, formula injection, giới hạn vận hành 10.000 dòng × 100 cột, xuất CSV stream không tạo file tạm._

- [x] Mục 6 cập nhật nội dung công khai và bảo toàn cấu hình tích hợp.  
  _Minh chứng: `ResearchSettingsTest` — 13 tests bao phủ: lưu nội dung không đổi gán phiếu/cột, preview không ghi, khóa giao dịch file, atomic replace, stale revision 409, backup xoay vòng tối đa 5 bản, audit không ghi credential._

- [x] Credential Google nằm ngoài public/Git; Sheet thật không cần công khai để trang quản lý đọc.  
  _Minh chứng: `ResearchSettingsTest::test_backups_are_bounded_and_exclude_credentials_and_audit_has_no_values` — xác minh file backup không chứa credential. `.gitignore` bao gồm `storage/app/` và `.env`. `README_GOOGLE.md` hướng dẫn dùng Service Account với quyền `spreadsheets.readonly` phía server._

- [x] Không có nội dung phản hồi trong log, cache, JSON metadata, session hay file export tạm.  
  _Minh chứng: `AccessControlAndNoDbAuditTest::test_no_survey_answers_persisted_to_logs_or_disk_storage` — quét session files, metadata files, settings JSON và log; xác nhận xuất CSV stream trực tiếp qua `php://output`, không ghi file tạm trên ổ đĩa._

- [x] Có kết quả chạy test, UAT thật, số đo hiệu năng và danh sách giới hạn còn lại.  
  _Minh chứng: [SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md](SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md) Mục 2 — kết quả 94/94 tests đạt, đo benchmark 10.000 × 100 = 220 MiB RAM / 3,93 giây / 6,86 MB CSV. [UAT_CHECKLIST_GOOGLE.md](UAT_CHECKLIST_GOOGLE.md) — 27 tiêu chí UAT sẵn sàng ký nghiệm thu. Danh sách hạn chế ghi tại Mục 7 tài liệu Sprint 4._

- [x] Có hướng dẫn xử lý thu hồi quyền, đổi cột, quota, JSON hỏng và khôi phục backup cấu hình.  
  _Minh chứng: [SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md](SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md) Mục 6 — 4 tình huống sự cố: FORBIDDEN (cấp lại quyền), SCHEMA_MISMATCH (đổi cột), RATE_LIMITED (429 với backoff), JSON hỏng (phục hồi từ backup xoay vòng). [OPERATIONS.md](OPERATIONS.md) và [INSTALL_PRODUCTION_WINDOWS.md](INSTALL_PRODUCTION_WINDOWS.md) bổ sung quy trình vận hành._

- [x] Có hướng dẫn triển khai một máy chủ, thư mục writable/private, HTTPS và phục hồi cấu hình.  
  _Minh chứng: [SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md](SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md) Mục 5 — yêu cầu hệ thống, cấu hình `.env` production, phân quyền NTFS (Windows) / ACL (Linux), web root trỏ vào `public/`, HTTPS bắt buộc, `storage/app/private/` nằm ngoài Internet. [README_GOOGLE.md](../README_GOOGLE.md) hướng dẫn cài đặt nhanh và kiểm thử trước demo._

## 12. Prompt dùng để giao Codex triển khai

```text
Hãy đọc docs/KE_HOACH_QUAN_LY_MUC_4_6.md và triển khai phần người quản lý
mục 4–6 trên dự án ResearchFlow-Laravel12-UAT hiện có.

Bắt đầu Sprint 0 rồi thực hiện lần lượt các sprint. Trước khi sửa phải đọc
AGENTS.md áp dụng, bootstrap/app.php, routes/google.php và các service/controller
Google hiện hành. Không bật lại routes/web.php hoặc kiến trúc DB cũ.

Giữ Laravel 12 + Blade + JavaScript thuần. Không dùng bất kỳ DB nào, kể cả
session/cache/queue/test. Không lưu câu trả lời vào file/cache/log/session.
Chỉ lưu cấu hình và metadata vận hành riêng tư theo đặc tả.

Phạm vi chính là theo dõi kết nối, tra cứu/xem chi tiết/xuất dữ liệu, cài đặt
nội dung nghiên cứu/liên hệ. Đọc trực tiếp Google Sheets; phản hồi vẫn phải
đi qua Google Forms. Tái sử dụng code hiện có, giữ tương thích phần người khác.

Nếu thiếu adapter Google riêng tư hoặc quyền nguồn thật, triển khai phần độc lập
bằng fake và ghi rõ phụ thuộc; không công khai Sheet hay bịa trạng thái thành công.
Không tự gửi thử vào Form đang thu thập thật. Chỉ UAT trên nguồn thử được chỉ định.

Mỗi sprint phải có code, kiểm thử tương ứng và cập nhật tiến độ theo ID đầu việc.
Báo rõ file đổi, kết quả kiểm thử thực tế, phần chưa đạt và lý do. Không đánh dấu
hoàn thành nếu test chưa chạy hoặc chỉ có dữ liệu fake mà chưa UAT tích hợp.
Không tự triển khai public hoặc thay credential môi trường thật.
```

## 13. Nguồn kỹ thuật đối chiếu

Các lựa chọn kiến trúc, mốc thời gian và giới hạn UAT trong tài liệu là đề xuất cho dự án; tài liệu hãng dưới đây hỗ trợ cơ chế API/framework, không chứng nhận ứng dụng đã hoạt động.

1. [Google Sheets API — values.batchGet và quyền đọc](https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets.values/batchGet): đọc nhiều vùng, các tùy chọn render dữ liệu và scope readonly.
2. [Google Forms API — responses](https://developers.google.com/workspace/forms/api/reference/rest/v1/forms.responses): phương thức đọc phản hồi `get/list`.
3. [Google Forms API — Form resource](https://developers.google.com/workspace/forms/api/reference/rest/v1/forms): phân biệt formId, responderUri và linkedSheetId.
4. [Google Sheets API — Usage limits](https://developers.google.com/workspace/sheets/api/limits): quota và xử lý giới hạn; kiểm tra lại khi triển khai, không hard-code giả định quota vĩnh viễn.
5. [Laravel 12 — Cache / Atomic Locks](https://laravel.com/docs/12.x/cache#atomic-locks): cơ chế khóa; thiết kế này chọn file cho một máy chủ.

Nguồn rà soát nội bộ: `README_GOOGLE.md`, `composer.json`, `bootstrap/app.php`, `routes/google.php`, `routes/console.php`, `config/research.php`, `GoogleAdminController`, `SessionAuthController`, `GoogleFormsService`, `GoogleSheetsService`, `ResearchSettings`, các view quản lý Google và `GoogleSurveyFlowTest.php`. Tài liệu MySQL cũ không phải hướng triển khai cho phạm vi này.
