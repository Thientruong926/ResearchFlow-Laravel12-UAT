# Checklist Nghiệm thu UAT — Cổng khảo sát Google Forms & Google Sheets

Ngày lập: 30/10/2026. Dự án: `ResearchFlow-Laravel12-UAT`.  
Kiến trúc: **Không sử dụng Database (No-DB)**. Dữ liệu khảo sát gửi trực tiếp đến Google Forms và đọc báo cáo chỉ đọc từ Google Sheets.

---

## 1. Thông tin đợt kiểm thử UAT

- **Người thực hiện kiểm thử:** ___________________________
- **Thiết bị / Môi trường:**
  - [ ] Desktop / Laptop (1366 × 768 px hoặc lớn hơn): Chrome / Edge / Safari
  - [ ] Mobile (390 × 844 px — iPhone / Android): Safari iOS / Chrome Mobile
- **Nguồn khảo sát kiểm thử:** Form và Sheet thử nghiệm riêng (không dùng Form đang thu thập thật)
- **Phiên bản mã nguồn:** ResearchFlow Laravel 12 — Sprint 4 hoàn tất

---

## 2. Danh mục kiểm thử nghiệm thu (UAT Items)

### Nhóm A: Luồng người tham gia khảo sát (Participant Flow)

| Mã | Nội dung kiểm thử | Thao tác thực hiện | Kết quả kỳ vọng | Kết quả |
|---|---|---|---|:---:|
| A01 | Mở trang chủ giới thiệu | Truy cập `/` trên desktop và mobile | Hiển thị tiêu đề nghiên cứu, thông tin liên hệ, mục đích và bản đồng thuận. Giao diện responsive, không tràn màn hình. | [ ] PASS |
| A02 | Chọn trường và nhóm đối tượng | Chọn trường trong dropdown và nhóm (CBQL / Giảng viên / Sinh viên) -> bấm Tiếp tục | Chuyển hướng đúng đến `/survey?school=...&group=...`. Nếu nhóm chưa được gán phiếu hoạt động: báo lỗi rõ ràng. | [ ] PASS |
| A03 | Hiển thị bảng câu hỏi & rẽ nhánh | Xem trang khảo sát `/survey` | Câu 1 (Đã sử dụng công cụ số): nếu chọn "Chưa sử dụng", câu 2 (mức độ) và câu 4 (số buổi dùng) tự động ẩn và disabled. Nếu chọn "Đã sử dụng", hiển thị đầy đủ. | [ ] PASS |
| A04 | Lưu nháp tạm trên thiết bị | Nhập dở câu hỏi -> tải lại trang | Dữ liệu nháp được tự động khôi phục từ `localStorage` của trình duyệt. Không gửi nháp lên máy chủ. | [ ] PASS |
| A05 | Ràng buộc kiểm tra tính hợp lệ | Thử bỏ chọn đồng thuận; nhập tổng số buổi < 0 hoặc > 100; nhập số buổi dùng số > tổng số buổi | Bị chặn cả ở JavaScript trình duyệt và validator máy chủ. Báo lỗi tiếng Việt cụ thể, không mất dữ liệu đã nhập. | [ ] PASS |
| A06 | Nộp khảo sát thành công | Nhập dữ liệu hợp lệ kèm mã kiểm thử (ví dụ: `PILOT-TEST-001`) -> bấm Gửi câu trả lời | Chuyển hướng đến `/thank-you`. Dữ liệu được gửi thành công đến Google Forms. Bản nháp cục bộ được dọn dẹp. | [ ] PASS |
| A07 | Không lưu câu trả lời vào Database/Session | Kiểm tra máy chủ sau khi gửi | Không có database connection; session máy chủ và log không chứa nội dung câu trả lời. | [ ] PASS |

---

### Nhóm B: Theo dõi kết nối & Đối chiếu luồng Form → Sheet (Mục 4)

| Mã | Nội dung kiểm thử | Thao tác thực hiện | Kết quả kỳ vọng | Kết quả |
|---|---|---|---|:---:|
| B01 | Xem danh sách nguồn kết nối | Đăng nhập quản lý -> vào `/admin/connections` | Hiển thị tổng số nguồn, số nguồn đọc tốt, số nguồn lỗi. Mỗi thẻ nguồn hiển thị rõ trạng thái Form, trạng thái Sheet và đối chiếu luồng. | [ ] PASS |
| B02 | Kiểm tra từng nguồn đơn lẻ | Bấm "Kiểm tra nguồn" tại một thẻ | Hiển thị trạng thái "Đang kiểm tra...", gọi API kiểm tra độc lập, cập nhật thời gian phản hồi (ms) và trạng thái không cần tải lại toàn trang. | [ ] PASS |
| B03 | Kiểm tra tất cả các nguồn | Bấm "Kiểm tra tất cả" | Xử lý song song tối đa 2 request frontend, hiển thị thanh tiến độ, không dồn request kéo dài gây timeout. | [ ] PASS |
| B04 | Cooldown chống quá tải | Bấm kiểm tra liên tiếp cùng 1 nguồn trong vòng 30 giây | Bị chặn với thông báo cooldown 30 giây, bảo vệ quota Google Sheets API. | [ ] PASS |
| B05 | Nhãn kết quả cũ (Stale) | Đợi quá 5 phút sau lần kiểm tra gần nhất | Trạng thái hiển thị kèm nhãn "Kết quả đã cũ", không tự động đổi thành lỗi. | [ ] PASS |
| B06 | Xác minh đối chiếu luồng thủ công | Sau khi gửi phản hồi thử A06, đối chiếu mã `PILOT-TEST-001` xuất hiện trong Sheet -> vào "Ghi nhận đối chiếu thủ công" -> chọn "Đã xác minh" -> tick xác nhận -> bấm Lưu | Ghi nhận metadata xác minh thành công kèm timestamp và revision. Nút kiểm tra không tự động gửi phản hồi giả vào Form thật. | [ ] PASS |
| B07 | Nhận diện thu hồi quyền (403) | Giả lập/thu hồi quyền truy cập Sheet | Báo lỗi `FORBIDDEN`, hiển thị màu đỏ và hướng dẫn cấp lại quyền. Thời điểm thành công trong lịch sử được giữ nguyên. | [ ] PASS |

---

### Nhóm C: Tra cứu, Xem chi tiết, Phân trang & Xuất CSV (Mục 5)

| Mã | Nội dung kiểm thử | Thao tác thực hiện | Kết quả kỳ vọng | Kết quả |
|---|---|---|---|:---:|
| C01 | Tra cứu và lọc kết hợp | Vào `/admin/responses`, lọc theo từ khóa `PILOT-TEST-001`, chọn trường, nhóm, khoảng ngày | Bộ lọc kết hợp AND chính xác. Tìm không phân biệt hoa/thường, phân biệt dấu tiếng Việt. | [ ] PASS |
| C02 | Phân trang bảng phản hồi | Chọn hiển thị 25/50/100 dòng | Phân trang mượt mà, liên kết trang trước/sau giữ nguyên toàn bộ tham số bộ lọc. Bảng có thanh cuộn ngang trên mobile. | [ ] PASS |
| C03 | Xem chi tiết phản hồi bằng Token HMAC | Bấm "Xem chi tiết" tại dòng khảo sát vừa gửi | Token mã hóa (TTL 10 phút, không chứa câu trả lời) được giải mã và đối chiếu HMAC với Sheet. Hiển thị đầy đủ mọi câu hỏi và câu trả lời, text được escape an toàn chống XSS. | [ ] PASS |
| C04 | Bảo vệ token chi tiết khi dòng bị đổi | Thử sửa hoặc xóa dòng khảo sát trên Sheet rồi tải lại trang chi tiết | Bị từ chối với mã HTTP 409 và thông báo nhắc tải lại danh sách, không bao giờ hiển thị nhầm câu trả lời của người khác. | [ ] PASS |
| C05 | Xuất CSV tất cả kết quả thỏa bộ lọc | Bấm "Xuất tất cả kết quả CSV" | Tải xuống file CSV chứa toàn bộ dòng thỏa bộ lọc (không chỉ trang đang xem). File có UTF-8 BOM, mở trực tiếp trong Excel hiển thị đúng tiếng Việt có dấu. | [ ] PASS |
| C06 | Chống tấn công Formula Injection (CSV) | Kiểm tra ô có dấu `=`, `+`, `-`, `@` hoặc khoảng trắng điều khiển | Ký tự nguy hiểm được trung hòa tự động bằng dấu nháy đơn `'` ở đầu ô, không làm hỏng dữ liệu gốc trên Google Sheets. | [ ] PASS |
| C07 | Chặn xuất dữ liệu khi nguồn bị lỗi | Giả lập 1 nguồn bị lỗi trong phạm vi lọc | Bị chặn với cảnh báo HTTP 503 "Dữ liệu chưa đầy đủ", không âm thầm xuất thiếu dữ liệu. Quản lý có thể chọn nguồn khỏe để xuất lại. | [ ] PASS |

---

### Nhóm D: Cài đặt thông tin nghiên cứu & Quản lý cấu hình (Mục 6)

| Mã | Nội dung kiểm thử | Thao tác thực hiện | Kết quả kỳ vọng | Kết quả |
|---|---|---|---|:---:|
| D01 | Trang cài đặt nghiên cứu riêng | Vào `/admin/settings/research` | Form độc lập cho tiêu đề, giới thiệu, đồng thuận, người liên hệ, email, điện thoại, địa chỉ. Không đòi hỏi cấu hình cột Sheet. | [ ] PASS |
| D02 | Xem trước (Preview) không ghi đĩa | Sửa tiêu đề và bấm "Xem trước" | Render xem trước bằng đúng partial của trang công khai, chưa ghi file cấu hình JSON. | [ ] PASS |
| D03 | Lưu cấu hình an toàn & Khóa file | Bấm "Lưu thay đổi" | Ghi nguyên tử qua file tạm, cập nhật `research-settings.json`, tạo backup tự động xoay vòng tối đa 5 bản trong thư mục riêng tư. | [ ] PASS |
| D04 | Cập nhật tức thì ra trang công khai | Mở trang chủ `/` trong tab ẩn danh | Tiêu đề và thông tin nghiên cứu mới xuất hiện ngay lập tức mà không cần xóa cache hay khởi động lại server. | [ ] PASS |
| D05 | Chống xung đột ghi đè đồng thời (409) | Mở 2 tab cùng form sửa -> Lưu ở tab 1 -> bấm Lưu ở tab 2 | Tab 2 bị từ chối với HTTP 409 do revision cũ; nội dung vừa nhập ở tab 2 được giữ nguyên trên màn hình để quản lý sao chép, không bị ghi đè âm thầm. | [ ] PASS |
| D06 | Bảo toàn cấu hình gán phiếu | Kiểm tra danh sách trường và gán phiếu sau khi lưu nội dung | Danh sách trường, gán phiếu, mapping cột được bảo toàn 100%, không bị reset về giá trị mặc định. | [ ] PASS |

---

### Nhóm E: Bảo mật, Truy cập & Vận hành (Security & Ops)

| Mã | Nội dung kiểm thử | Thao tác thực hiện | Kết quả kỳ vọng | Kết quả |
|---|---|---|---|:---:|
| E01 | Chặn truy cập trái phép khu vực quản trị | Mở các URL `/admin/*` khi chưa đăng nhập | Chuyển hướng 302 về `/login`. Không lộ dữ liệu phản hồi hay metadata cấu hình. | [ ] PASS |
| E02 | Kiểm tra bảo vệ CSRF thực tế | Gửi request POST không có token CSRF | Bị từ chối với mã HTTP 419. | [ ] PASS |
| E03 | Header bảo mật & Chống lưu cache | Kiểm tra HTTP headers trên các route quản lý và khảo sát | Có `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Cache-Control: private, no-store, max-age=0`. | [ ] PASS |
| E04 | Rà soát không lưu phản hồi trên đĩa | Kiểm tra thư mục `storage/` và session | Không có file CSV tạm, không có dump dữ liệu câu trả lời trong session hoặc log. | [ ] PASS |
| E05 | Lệnh đối chiếu tự động CLI | Chạy `php artisan research:verify-flow --check-code=PILOT-TEST-001` | Quét và phát hiện chính xác mã đối chiếu tại dòng cụ thể của Sheet, in báo cáo rõ ràng. | [ ] PASS |

---

## 3. Kết luận nghiệm thu UAT

- **Tổng số mục kiểm thử:** 27 mục (A01–A07, B01–B07, C01–C07, D01–D06, E01–E05)
- **Số mục ĐẠT (PASS):** _____ / 27
- **Số mục CHƯA ĐẠT (FAIL):** _____ / 27
- **Ý kiến đánh giá của người kiểm thử / Quản lý nghiên cứu:**
  ____________________________________________________________________________________
  ____________________________________________________________________________________

**Chữ ký xác nhận người kiểm thử:** ____________________ Ngày: ____/____/2026
