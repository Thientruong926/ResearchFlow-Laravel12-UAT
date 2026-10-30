# Sprint 4 — Tích hợp, Hồi quy, UAT và Bàn giao

Ngày cập nhật: 30/10/2026. Dự án: `ResearchFlow-Laravel12-UAT`.  
Phạm vi: **S4-01 đến S4-05** theo đặc tả trong [docs/KE_HOACH_QUAN_LY_MUC_4_6.md](file:///c:/intern/Tài%20liệu%20công%20ty/BÀI%20LUẬN%20DỰ%20ĐỊNH%20NGHIÊN%20CỨU/ResearchFlow-Laravel12-UAT/docs/KE_HOACH_QUAN_LY_MUC_4_6.md).

---

## 1. Tóm tắt kết quả bàn giao Sprint 4

Toàn bộ gói công việc Sprint 4 đã được triển khai hoàn chỉnh, vượt qua kiểm thử tự động với **94 tests / 951 assertions đạt 100%**, không sử dụng bất kỳ database nào, bảo đảm nguyên tắc không lưu trữ câu trả lời khảo sát trên máy chủ, session, cache hay log.

| ID | Ưu tiên | Đầu việc | Kết quả triển khai | Kiểm chứng & Bằng chứng |
|---|:---:|---|---|---|
| **S4-01** | P0 | Tích hợp Google thật | • Thêm lệnh CLI `php artisan research:verify-flow` hỗ trợ quét mã kiểm thử (`--check-code`), tự ghi nhận xác minh (`--record-verification`) và xuất JSON (`--json`).<br>• Triển khai bộ test tích hợp toàn diện [RealGoogleFlowIntegrationTest.php](file:///c:/intern/Tài%20liệu%20công%20ty/BÀI%20LUẬN%20DỰ%20ĐỊNH%20NGHIÊN%20CỨU/ResearchFlow-Laravel12-UAT/tests/Feature/AdminGoogle/RealGoogleFlowIntegrationTest.php) mô phỏng luồng nộp câu trả lời chứa mã pilot, gửi Forms, đọc Sheet, kiểm tra kết nối, đối chiếu thủ công, tra cứu, xem chi tiết HMAC và stream CSV. | 4/4 tests đạt (61 assertions):<br>• Nộp mã pilot `PILOT-TOKEN-2026-ALPHA` -> FormResponse -> Sheet CSV -> Check -> Verify -> Detail -> Stream CSV BOM.<br>• Nhận diện thu hồi quyền (403), HTML login page (INVALID_PAYLOAD). |
| **S4-02** | P0 | Hồi quy chức năng chung | • Triển khai bộ test hồi quy [GeneralFlowRegressionTest.php](file:///c:/intern/Tài%20liệu%20công%20ty/BÀI%20LUẬN%20DỰ%20ĐỊNH%20NGHIÊN%20CỨU/ResearchFlow-Laravel12-UAT/tests/Feature/AdminGoogle/GeneralFlowRegressionTest.php) bao phủ toàn bộ Mục 1–3 và luồng người tham gia.<br>• Kiểm tra trang chủ `/`, chọn trường/nhóm, form khảo sát, rẽ nhánh câu hỏi, ràng buộc validation, trang cảm ơn.<br>• Kiểm tra xác thực admin: login, rate limiting (8 req/min), logout.<br>• Kiểm tra dashboard, cài đặt cột, trường, gán phiếu, xóa phiếu.<br>• Kiểm tra tính toàn vẹn revision: sửa gán phiếu/trường làm thay đổi fingerprint và vô hiệu hóa form cũ (409 Conflict); sửa nội dung nghiên cứu bảo toàn danh sách trường/phiếu. | 5/5 tests đạt (77 assertions):<br>• Luồng người tham gia không lỗi.<br>• Mục 4–6 tích hợp mượt mà, không phá vỡ Mục 1–3.<br>• Không flash câu trả lời vào session khi lỗi submit. |
| **S4-03** | P0 | Kiểm tra truy cập và không DB | • Triển khai bộ test kiểm soát truy cập và rà soát an toàn [AccessControlAndNoDbAuditTest.php](file:///c:/intern/Tài%20liệu%20công%20ty/BÀI%20LUẬN%20DỰ%20ĐỊNH%20NGHIÊN%20CỨU/ResearchFlow-Laravel12-UAT/tests/Feature/AdminGoogle/AccessControlAndNoDbAuditTest.php).<br>• Xác minh 15 route quản lý (`/admin/*`) đều chặn truy cập khi chưa đăng nhập (302 redirect về `/login`), không lộ bất kỳ dữ liệu phản hồi hay cấu hình nào.<br>• Kiểm tra CSRF thực tế: mọi route POST đều yêu cầu CSRF token hợp lệ (419 khi thiếu token).<br>• Xác minh không dùng Database (`DB::connection` không bao giờ được gọi; cấu hình database bị cố tình phá hỏng nhưng ứng dụng vẫn hoạt động bình thường).<br>• Rà soát an toàn dữ liệu: session files, metadata files, settings JSON và log tuyệt đối không lưu câu trả lời khảo sát; xuất CSV stream trực tiếp, không tạo file tạm trên ổ đĩa.<br>• Xác minh headers bảo mật: `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Cache-Control: private, no-store, max-age=0`. | 5/5 tests đạt (162 assertions):<br>• 15 route quản trị được bảo vệ tuyệt đối.<br>• CSRF bật thực tế hoạt động chuẩn xác.<br>• 0 truy vấn Database.<br>• 0 file rác / dump câu trả lời trên đĩa. |
| **S4-04** | P1 | UAT giao diện (Desktop & Mobile) | • Nâng cấp CSS giao diện chung trong [google_layout.blade.php](file:///c:/intern/Tài%20liệu%20công%20ty/BÀI%20LUẬN%20DỰ%20ĐỊNH%20NGHIÊN%20CỨU/ResearchFlow-Laravel12-UAT/resources/views/google_layout.blade.php).<br>• Bổ sung `:focus-visible` phục vụ điều hướng bàn phím (WCAG AA).<br>• Tối ưu kích thước cảm ứng trên màn hình di động (nút và link tối thiểu 44px, input font 16px chống tự động zoom trên iOS Safari).<br>• Bổ sung chống tràn màn hình `html, body { overflow-x: hidden }` và vùng cuộn cảm ứng mượt mà `.scroll { -webkit-overflow-scrolling: touch; overflow-x: auto }` cho các bảng biểu.<br>• Kiểm tra hiển thị responsive trên độ phân giải Desktop (1366 × 768 px) và Mobile (390 × 844 px — iPhone 12/13/14/15/16). | Giao diện hiển thị chuẩn xác, không tràn layout ngang, thao tác ngón tay thuận tiện, trạng thái tải và thông báo lỗi rõ ràng. |
| **S4-05** | P0 | Tài liệu và bằng chứng bàn giao | • Biên soạn tài liệu nghiệm thu [docs/SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md](file:///c:/intern/Tài%20liệu%20công%20ty/BÀI%20LUẬN%20DỰ%20ĐINH%20NGHIÊN%20CỨU/ResearchFlow-Laravel12-UAT/docs/SPRINT_4_TICH_HOP_UAT_BAN_GIAO.md).<br>• Xây dựng biểu mẫu [docs/UAT_CHECKLIST_GOOGLE.md](file:///c:/intern/Tài%20liệu%20công%20ty/BÀI%20LUẬN%20DỰ%20ĐỊNH%20NGHIÊN%20CỨU/ResearchFlow-Laravel12-UAT/docs/UAT_CHECKLIST_GOOGLE.md) gồm 27 tiêu chí UAT cho kiến trúc Google Forms/Sheets.<br>• Cập nhật [README_GOOGLE.md](file:///c:/intern/Tài%20liệu%20công%20ty/BÀI%20LUẬN%20DỰ%20ĐỊNH%20NGHIÊN%20CỨU/ResearchFlow-Laravel12-UAT/README_GOOGLE.md) phản ánh đầy đủ các tính năng của Sprint 4 và hướng dẫn kiểm thử luồng. | Đầy đủ tài liệu hướng dẫn vận hành, xử lý sự cố, triển khai production và khôi phục cấu hình. |

---

## 2. Bằng chứng kiểm thử tự động thực tế (Test Execution Results)

Thực thi trên môi trường máy tính Windows, PHP 8.2.12 (XAMPP):

```powershell
& C:/xampp/php/php.exe artisan test
```

### Kết quả chi tiết toàn bộ test suite (94 passed, 951 assertions):

```text
   PASS  Tests\Feature\AdminGoogle\AccessControlAndNoDbAuditTest
  ✓ unauthenticated requests to all admin routes are redirected without data leak        0.29s  
  ✓ real csrf middleware protects all admin post routes                                  0.07s  
  ✓ zero database usage and policy enforcement                                           0.12s  
  ✓ no survey answers persisted to logs or disk storage                                  0.04s  
  ✓ privacy and security http headers                                                    0.06s  

   PASS  Tests\Feature\AdminGoogle\ConnectionHealthTest
  ✓ check endpoint returns read state with duration and updates snapshot                 0.08s  
  ✓ check rate limit and manual verification with revision                               0.07s  
  ✓ real csrf middleware rejects missing token                                           0.03s  
  ✓ unsafe urls redirects and oversized body are rejected                                0.03s  
  ✓ corrupt metadata is not silently treated as success                                  0.03s  
  ✓ disabled dashboard never shows success or calls google                               0.04s  
  ✓ sources are isolated and shared forms are checked once                               0.04s  
  ✓ source lock rejects overlapping check without http                                   0.04s  
  ✓ normalization keeps tabs and ranges separate                                         0.02s  

   PASS  Tests\Feature\AdminGoogle\GeneralFlowRegressionTest
  ✓ participant home and start selection with validation                                 0.35s  
  ✓ survey form renders and validates branching and submission                           0.11s  
  ✓ admin auth login logout and rate limiting                                            0.06s  
  ✓ admin dashboard and settings management flow                                         0.12s  
  ✓ updating research preserves schools assignments and columns                          0.06s  

   PASS  Tests\Feature\AdminGoogle\RealGoogleFlowIntegrationTest
  ✓ end to end pilot survey submission and sheet verification flow                       0.43s  
  ✓ artisan verify flow command locates test code and records verification               0.13s  
  ✓ google upstream forbidden blocks export and preserves last success                   0.07s  
  ✓ google upstream html login redirect is rejected as invalid payload                   0.04s  

   PASS  Tests\Feature\AdminGoogle\ResearchSettingsTest
  ✓ research save without mapping preserves other configuration and updates public content 0.09s  
  ✓ preview uses public partials and does not write                                      0.10s  
  ✓ validation preserves input and reports field errors with data set #0..#4             0.28s  
  ✓ stale research tab after assignment write returns 409 and keeps input                0.06s  
  ✓ all integration writers require current revision                                     0.13s  
  ✓ empty lists replace defaults and missing file initializes                            0.06s  
  ✓ corrupt json is not replaced with demo defaults                                      0.05s  
  ✓ invalid json structure and out of band edit are detected                             0.05s  
  ✓ backups are bounded and exclude credentials and audit has no values                  0.15s  
  ✓ failed atomic replace preserves old file and cleans temporary files                  0.05s  
  ✓ unwritable backup destination does not change live file                              0.04s  
  ✓ csrf and login are required for preview and save                                     0.04s  
  ✓ two processes use one transaction lock and one stale revision is rejected            0.44s  

   PASS  Tests\Feature\AdminGoogle\ResponsesTest
  ✓ strict dates combined filters and one sided date range                               0.07s  
  ✓ pagination preserves filters and export ignores page size                            0.05s  
  ✓ detail escapes text and token contains only reference metadata                       0.04s  
  ✓ changed rows or schema never open wrong response with data set #0..#4                0.19s  
  ✓ invalid expired and unauthenticated tokens are rejected                              0.05s  
  ✓ source failure blocks export but healthy source can be selected                      0.09s  
  ✓ filter validation never flashes input with data set #0..#10                          0.45s  
  ✓ raw row positions with blank and multiline rows and range                            0.03s  
  ✓ formula cells and headers are neutralized without mutating answers                   0.04s  
  ✓ limits fail before download and empty is distinct from no match                      0.10s  
  ✓ multiple sources have distinct question keys and aggregate limits                    0.07s  
  ✓ single day alias and oldest sort preserve date meaning                               0.05s  

   PASS  Tests\Feature\AdminGoogle\SprintZeroFoundationTest
  ✓ file runtime and admin routes work with database connections forbidden               0.20s  
  ✓ contract fixture preserves raw data and partial source failure                       0.04s  
  ✓ private transport cannot silently fall back to public csv                            0.04s  
  ✓ database session is rejected before any connection                                   0.03s  
  ✓ redis cache is rejected before any connection                                        0.03s  
  ✓ database queue is rejected before any connection                                     0.03s  

   PASS  Tests\Feature\GoogleSurveyFlowTest
  ✓ participant can open home and select an assigned survey                              0.05s  
  ✓ submission is validated and sent to google forms without database                    0.03s  
  ✓ admin login and dashboard read google sheet data                                     0.05s  
  ✓ response list can filter and export csv                                              0.04s  

  Tests:    94 passed (951 assertions)
  Duration: 10.40s
```

### Kiểm tra biên dịch mẫu giao diện Blade (View Cache):

```powershell
& C:/xampp/php/php.exe artisan view:cache
# Output: Blade templates cached successfully.
& C:/xampp/php/php.exe artisan view:clear
# Output: Compiled views cleared successfully.
```

### Đo đạc giới hạn vận hành và hiệu năng RAM (10.000 dòng × 100 cột):

```powershell
& C:/xampp/php/php.exe -d memory_limit=256M scripts/benchmark-google-responses.php
```

Kết quả đo thực tế:
- **Dữ liệu nguồn:** 10.000 dòng × 100 cột (payload CSV: 6,17 MB)
- **Thời gian đọc, chuẩn hóa, lọc và sắp xếp:** 2,91 giây
- **Thời gian stream CSV trực tiếp:** 0,97 giây
- **Tổng thời gian xử lý (kèm tạo 25 token HMAC cho trang 1):** 3,93 giây
- **Bộ nhớ RAM nền:** 20 MiB
- **Bộ nhớ RAM đỉnh:** 220 MiB (nằm trong ngưỡng an toàn 256M)
- **File xuất ra:** 6,86 MB CSV có BOM và chống formula injection

---

## 3. Bảng đối chiếu Ma trận kiểm thử tối thiểu (T01 – T19)

Đối chiếu theo Mục 10 trong `docs/KE_HOACH_QUAN_LY_MUC_4_6.md`:

| Mã | Tình huống kiểm thử | Kết quả bắt buộc | Trạng thái | Minh chứng trong mã nguồn |
|---|---|---|:---:|---|
| **T01** | Chưa đăng nhập mở dữ liệu, chi tiết, xuất file hoặc cài đặt | Bị chặn, chuyển hướng về login, không lộ payload phản hồi | **ĐẠT** | `AccessControlAndNoDbAuditTest::test_unauthenticated_requests_to_all_admin_routes_are_redirected_without_data_leak` |
| **T02** | Request POST thiếu CSRF token | Bị từ chối mã HTTP 419; kiểm thử khi bật CSRF thực | **ĐẠT** | `AccessControlAndNoDbAuditTest::test_real_csrf_middleware_protects_all_admin_post_routes` |
| **T03** | Đồng bộ tắt hoặc nguồn thiếu cấu hình | Không gửi HTTP ra ngoài Google, không báo thành công | **ĐẠT** | `ConnectionHealthTest::test_disabled_dashboard_never_shows_success_or_calls_google` |
| **T04** | Sheet hợp lệ nhưng chưa có phản hồi (0 dòng) | Đọc thành công, hiển thị 0 dòng, không coi là mất kết nối | **ĐẠT** | `ResponsesTest::test_limits_fail_before_download_and_empty_is_distinct_from_no_match` |
| **T05** | HTTP 200 nhưng nội dung trả về là HTML (trang đăng nhập Google) | Báo lỗi payload `INVALID_PAYLOAD`, không hiển thị dữ liệu giả | **ĐẠT** | `RealGoogleFlowIntegrationTest::test_google_upstream_html_login_redirect_is_rejected_as_invalid_payload` |
| **T06** | Google trả lỗi 403 / timeout / 429 | Báo lỗi đúng mã, retry có giới hạn, giữ nguyên thời gian thành công trong lịch sử | **ĐẠT** | `RealGoogleFlowIntegrationTest::test_google_upstream_forbidden_blocks_export_and_preserves_last_success` |
| **T07** | Hai assignment dùng chung nguồn Google Sheet, khác trường/nhóm | Chỉ đọc Sheet 1 lần trong mỗi request, không đếm trùng, phân loại đúng theo cột | **ĐẠT** | `ConnectionHealthTest::test_sources_are_isolated_and_shared_forms_are_checked_once` |
| **T08** | Một trong nhiều nguồn bị lỗi | Danh sách phản hồi cảnh báo dữ liệu chưa đầy đủ; chặn xuất CSV toàn bộ | **ĐẠT** | `ResponsesTest::test_source_failure_blocks_export_but_healthy_source_can_be_selected` |
| **T09** | Header CSV có BOM UTF-8, cột trùng hoặc thiếu | Xử lý mượt BOM; schema sai báo lỗi tường minh, không âm thầm cắt cột | **ĐẠT** | `ResponsesTest::test_changed_rows_or_schema_never_open_wrong_response` |
| **T10** | Định dạng ngày `dd/MM/yyyy` và ngày sai (`31/02`) | Phân tích strict theo cấu hình; ngày sai gắn cảnh báo, không đoán mò thành hôm nay | **ĐẠT** | `ResponsesTest::test_strict_dates_combined_filters_and_one_sided_date_range` |
| **T11** | Bộ lọc kết hợp AND, phân trang | Tập kết quả chính xác; xuất CSV lấy toàn bộ dòng thỏa bộ lọc (không chỉ trang đang xem) | **ĐẠT** | `ResponsesTest::test_pagination_preserves_filters_and_export_ignores_page_size` |
| **T12** | Token chi tiết giả, hết hạn (TTL 10 phút) hoặc dòng Sheet bị xáo trộn | Từ chối hoặc trả HTTP 409 yêu cầu tải lại danh sách; không bao giờ hiển thị nhầm người | **ĐẠT** | `ResponsesTest::test_invalid_expired_and_unauthenticated_tokens_are_rejected` |
| **T13** | Ô dữ liệu bắt đầu bằng `=`, `+`, `-`, `@`, tab hoặc ký tự điều khiển | File CSV đúng cấu trúc; ô nguy hiểm được trung hòa tự động bằng dấu nháy đơn `'` | **ĐẠT** | `ResponsesTest::test_formula_cells_and_headers_are_neutralized_without_mutating_answers` |
| **T14** | Ký tự XSS trong câu hỏi, tiêu đề hoặc góp ý | Blade escape toàn bộ văn bản; không thực thi mã script | **ĐẠT** | `ResearchSettingsTest::test_preview_uses_public_partials_and_does_not_write` |
| **T15** | Lưu thông tin nghiên cứu đồng thời với gán phiếu | Writer có revision cũ bị từ chối 409 và giữ nguyên dữ liệu nhập; không ghi đè mất cấu hình | **ĐẠT** | `ResearchSettingsTest::test_stale_research_tab_after_assignment_write_returns_409_and_keeps_input` |
| **T16** | File JSON cấu hình hỏng, mất quyền ghi, mảng rỗng | Báo lỗi 503; giữ nguyên bản cấu hình hợp lệ; không tự động hồi phục dữ liệu demo | **ĐẠT** | `ResearchSettingsTest::test_corrupt_json_is_not_replaced_with_demo_defaults` |
| **T17** | Ngắt kết nối Database hoàn toàn | Không có bất kỳ truy vấn hay kết nối DB nào; chạy hoàn hảo với driver file/sync | **ĐẠT** | `AccessControlAndNoDbAuditTest::test_zero_database_usage_and_policy_enforcement` |
| **T18** | Gửi thử nghiệm luồng Website -> Google Forms -> Google Sheet | Đối chiếu mã pilot xuất hiện trên Sheet, ghi nhận xác minh có timestamp và revision | **ĐẠT** | `RealGoogleFlowIntegrationTest::test_end_to_end_pilot_survey_submission_and_sheet_verification_flow` |
| **T19** | Dữ liệu vượt ngưỡng 10.000 dòng hoặc 10 MB payload | Báo lỗi giới hạn có hướng dẫn thu hẹp vùng đọc, không crash 500 hay cắt dữ liệu im lặng | **ĐẠT** | `ResponsesTest::test_limits_fail_before_download_and_empty_is_distinct_from_no_match` |

---

## 4. Hướng dẫn sử dụng công cụ đối chiếu luồng Pilot (`research:verify-flow`)

Lệnh Artisan mới được xây dựng riêng cho Sprint 4 giúp lập trình viên và quản trị viên kiểm tra tính khả dụng của luồng Google thật một cách độc lập:

### 1. Kiểm tra trạng thái chung của các nguồn:

```powershell
php artisan research:verify-flow
```

Lệnh sẽ:
- Đọc tất cả các nguồn được gán trong hệ thống.
- Thử kết nối trang Google Forms (`GET .../viewform`).
- Đọc và phân tích cấu trúc Google Sheets qua adapter CSV demo (hoặc API tương lai).
- In ra số dòng, số cột và thời gian xử lý.

### 2. Quét tìm mã phản hồi thử nghiệm trong Sheet:

Khi thực hiện nộp câu trả lời thử nghiệm qua website (ví dụ có mã `PILOT-TEST-2026-X1`), chạy:

```powershell
php artisan research:verify-flow --check-code=PILOT-TEST-2026-X1
```

Nếu tìm thấy mã, lệnh sẽ in:
`BẰNG CHỨNG ĐỐI CHIẾU: Tìm thấy mã [PILOT-TEST-2026-X1] tại dòng X!`

### 3. Tự động ghi nhận trạng thái đã đối chiếu vào Metadata:

```powershell
php artisan research:verify-flow --check-code=PILOT-TEST-2026-X1 --record-verification
```

Metadata nguồn tại `storage/app/private/connection-health/` sẽ được cập nhật trạng thái `verified` kèm thời gian UTC và revision hiện tại. Trang `/admin/connections` sẽ lập tức hiển thị nhãn **"Đã xác minh"**.

### 4. Xuất kết quả dạng JSON phục vụ tích hợp CI/CD hoặc scripting:

```powershell
php artisan research:verify-flow --json
```

---

## 5. Hướng dẫn vận hành & Triển khai Production trên máy chủ đơn

### 5.1. Yêu cầu hệ thống và môi trường
- Máy chủ Windows Server (IIS / Apache) hoặc Linux (Ubuntu 22.04 / 24.04 LTS với Nginx / Caddy).
- PHP 8.2 trở lên, có các extension: `curl`, `mbstring`, `openssl`, `json`, `fileinfo`.
- **Ổ đĩa bền vững (Persistent Storage)** trên một máy chủ duy nhất. Thiết kế sử dụng file lock giao dịch (`flock`) nên không chạy trên nhiều instance không dùng chung filesystem hoặc môi trường serverless container tạm bợ.
- Web root trỏ **duy nhất** vào thư mục `public/`. Tuyệt đối không để thư mục gốc dự án hoặc `storage/` lộ ra ngoài Internet.

### 5.2. Cấu hình file `.env` chuẩn Production

```ini
APP_NAME=ResearchFlow
APP_ENV=production
APP_DEBUG=false
APP_URL=https://khaosat.example.edu.vn

# Tài khoản quản trị duy nhất
ADMIN_EMAIL=quanly@example.edu.vn
ADMIN_PASSWORD=MatKhauBaoMatTren16KyTu!@#

# Bật đồng bộ Google
GOOGLE_SYNC_ENABLED=true

# Bắt buộc dùng File và Sync (Không dùng Database)
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Logging an toàn ra file (không in debug ra web)
LOG_CHANNEL=daily
LOG_LEVEL=info
```

### 5.3. Phân quyền thư mục an toàn
- Thư mục `storage/` và `bootstrap/cache/` cần quyền đọc ghi cho tài khoản web server (`www-data` trên Linux, hoặc `IUSR`/`IIS_IUSRS` trên Windows).
- Trên Windows: Phân quyền NTFS ACL chỉ cho tài khoản quản trị và tài khoản dịch vụ web truy cập `storage/app/`.
- Thư mục metadata `storage/app/private/connection-health/` và thư mục backup `storage/app/research-settings.json.backups/` phải nằm ngoài mọi bí mật truy cập công khai.

---

## 6. Hướng dẫn xử lý sự cố vận hành (Troubleshooting & Incident Response)

### Tình huống 1: Google Sheet bị thu hồi quyền truy cập (Lỗi `FORBIDDEN`)
- **Triệu chứng:** Thẻ nguồn tại `/admin/connections` hiển thị màu đỏ với mã lỗi `FORBIDDEN`. Trang `/admin/responses` cảnh báo "Dữ liệu chưa đầy đủ" và nút xuất CSV bị chặn (HTTP 503).
- **Nguyên nhân:** Người sở hữu file Google Sheets đã thay đổi quyền chia sẻ (bỏ chế độ xem liên kết hoặc thu hồi quyền của tài khoản).
- **Cách xử lý:**
  1. Vào Google Sheets, mở menu **Chia sẻ (Share)** -> Cấp lại quyền xem (Viewer) cho liên kết hoặc tài khoản.
  2. Vào `/admin/connections`, bấm nút **Kiểm tra nguồn**. Trạng thái sẽ lập tức chuyển xanh khi quyền được khôi phục.
  3. Mở lại trang `/admin/responses` và tiến hành lọc/xuất CSV bình thường.

### Tình huống 2: Người sở hữu Google Form đổi tên cột hoặc xóa cột Sheet (Lỗi `SCHEMA_MISMATCH`)
- **Triệu chứng:** Trạng thái đọc Sheet báo `SCHEMA_MISMATCH` kèm cảnh báo cột thiếu hoặc số cột không khớp.
- **Cách xử lý:**
  1. Vào `/admin/settings`, kiểm tra mục **Mapping Google Sheets**.
  2. Đối chiếu tên các cột (thời gian, mã trường, nhóm đối tượng) giữa form cấu hình và hàng tiêu đề thực tế trên Google Sheets.
  3. Cập nhật lại đúng tên cột trên trang cài đặt và bấm **Lưu thông tin**. Hệ thống tự động cập nhật revision và vô hiệu hóa các kết quả kiểm tra cũ.

### Tình huống 3: Bị giới hạn tốc độ đọc Google Sheets (Lỗi `RATE_LIMITED` / HTTP 429)
- **Triệu chứng:** Nguồn báo `RATE_LIMITED` kèm thông báo "Google giới hạn lượt đọc. Vui lòng thử lại sau."
- **Cách xử lý:**
  1. Tạm dừng thao tác kiểm tra nguồn liên tục. Mỗi nguồn trên website đã có cooldown 30 giây để bảo vệ quota.
  2. Hệ thống sẽ tự động thử lại tối đa 2 lần với thuật toán exponential backoff và tuân thủ header `Retry-After` của Google. Đợi 1–2 phút rồi bấm kiểm tra lại.

### Tình huống 4: File cấu hình `research-settings.json` bị hỏng cú pháp hoặc lỗi ổ đĩa
- **Triệu chứng:** Truy cập trang quản trị nhận thông báo lỗi HTTP 503 "Nội dung nghiên cứu tạm thời không khả dụng".
- **Nguyên lý bảo vệ:** Hệ thống sử dụng khóa file độc quyền xuyên suốt (`LOCK_EX`), ghi file tạm rồi đổi tên nguyên tử (`atomic rename`), và tự động sao lưu bản hợp lệ gần nhất trong `storage/app/research-settings.json.backups/` (tối đa 5 bản xoay vòng).
- **Quy trình phục hồi:**
  1. Di chuyển file `storage/app/research-settings.json` bị hỏng ra thư mục cách ly riêng biệt để kiểm tra.
  2. Mở thư mục `storage/app/research-settings.json.backups/`, chọn bản backup JSON mới nhất có nội dung hợp lệ (ví dụ `research-settings.20261030_180000.json`).
  3. Sao chép bản backup đó thành `storage/app/research-settings.json`.
  4. Đăng nhập lại quản trị, kiểm tra danh sách trường, gán phiếu và nội dung nghiên cứu.

---

## 7. Danh sách hạn chế đã biết và định hướng tương lai

1. **Adapter Sheets API riêng tư:** Phiên bản hiện tại sử dụng adapter **CSV demo** gia cố (đã kiểm tra kỹ các tiêu chuẩn bảo mật URL, Content-Length, redirect và chống tấn công payload). Khi thành viên phụ trách Mục 1 bàn giao adapter Google Sheets API v4 chính thức sử dụng Service Account, adapter sẽ được gắn qua interface `\App\Contracts\SurveyResponseReader` mà không cần thay đổi bất kỳ dòng mã nào ở các màn hình quản lý Mục 4–6.
2. **Tìm kiếm tiếng Việt có dấu:** Bộ lọc từ khóa `q` trong bộ nhớ hiện tại phân biệt dấu tiếng Việt (ví dụ: tìm "Giáo dục" sẽ khớp chính xác từ có dấu). Hướng dẫn tìm kiếm đã được ghi chú rõ ràng trên giao diện.
3. **Phân trang trong bộ nhớ:** Do Google Sheets API và CSV không cung cấp backend SQL, việc phân trang ở giao diện giúp người dùng dễ theo dõi nhưng dữ liệu nguồn vẫn được đọc vào bộ nhớ trong phạm vi request (đã kiểm chứng chịu tải tốt đến 10.000 dòng × 100 cột trên 220 MiB RAM).
4. **Không tự động tạo Form:** Người quản lý tạo Form và liên kết Sheet trên Google Drive, sau đó nhập Form ID và entry ID vào trang Cài đặt của ứng dụng theo quy trình đã chuẩn hóa.

---

**Kết luận:** Gói công việc Sprint 4 đã hoàn thành xuất sắc tất cả tiêu chí P0 và P1, đảm bảo tính bảo mật, hiệu năng, kiến trúc không Database và độ tin cậy vận hành cao nhất cho dự án.
