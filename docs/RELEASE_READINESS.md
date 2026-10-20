# ĐÁNH GIÁ BÀN GIAO RESEARCHFLOW — 2026-10-18

**Phiên bản kỹ thuật:** v0.2.0 — **chưa được chứng nhận sẵn sàng thu thập dữ liệu nghiên cứu thật**.

Nguồn kiểm tra: 5 phiếu giao việc A–E trong `docs/source_assignments`, mã nguồn gốc được cung cấp và các kiểm thử tĩnh tại môi trường xây dựng. Không có dữ liệu hoặc giả định nào thay thế được phê duyệt của NCS/chủ nhiệm đề tài.

## Những thay đổi đã được thực hiện trên mã nguồn

- A3: dừng lưu/nộp khi cổng thu thập bị đóng, tuần tự hóa autosave trình duyệt và tránh ghi đè sau nộp bằng khóa phiên DB.
- A4: chức năng sao chép survey đã phát hành thành bản nháp mới, chỉnh sửa/xóa câu hỏi của bản nháp (chặn xóa câu còn có nhánh phụ thuộc); chống mã lựa chọn trùng và điều kiện rẽ nhánh không hợp lệ.
- B/Security: thêm `Referrer-Policy: no-referrer`, `Cache-Control: private, no-store`, `X-Frame-Options: DENY` trên trang phù hợp; giảm hiển thị số liệu phân nhóm với tài khoản viewer.
- D5/E2: trạng thái `withdrawal_requested` tạm giữ dữ liệu khỏi clean export; xử lý yêu cầu rút lui yêu cầu quyết định `exclude` hoặc `retain` kèm lý do theo protocol. Raw được bảo toàn, không tự xóa.
- E1: xuất codebook CSV UTF-8 (mapping mã biến, nguồn, missing, điều kiện, version).
- E2: SPSS Syntax sử dụng định dạng số cho câu hỏi số và choices numeric hợp lệ; mã có số 0 đứng đầu vẫn được giữ là chuỗi; hỗ trợ value labels/missing code/variable levels. **Chưa test nhập thực tế bằng SPSS**.
- Bổ sung ca kiểm thử Laravel và kiểm thử unit cú pháp SPSS, cập nhật SOP.

## Đã kiểm tra trong môi trường xây dựng

- Giải nén ZIP và kiểm tra mã nguồn thành công.
- Kiểm tra cú pháp tất cả file PHP: PASS (`php -l`).
- Kiểm tra cú pháp JavaScript của autosave: PASS (Node.js syntax parser).
- Tạo thử SPSS Syntax với biến số và nhãn Unicode: PASS về cấu trúc chuỗi.
- Quét nội dung và đường dẫn ZIP để loại `.env`, log, tệp backup, vendor, data thật: PASS theo quy tắc đóng gói.

**Giới hạn:** môi trường xây dựng không có Composer/Laravel dependencies và các PHP extensions cần thiết, nên các Laravel feature tests bổ sung **CHƯA CHẠY**. Kết quả 11/11 tests (58 assertions) trước đó chỉ áp dụng **phiên bản v0.1.0 trước khi sửa** do người dùng chạy ở Windows; không được tính cho bản ZIP mới.

## Các điều kiện BẮT BUỘC trước khi giảng viên sử dụng trong nghiên cứu thật

1. **Đề cương và phê duyệt:** NCS cung cấp/duyệt 3 bộ bảng hỏi theo nhóm CBQL/giảng viên/sinh viên, logic nhánh, mapping item→variable, required/missing, bản thông tin và đồng thuận, cơ sở/đợt/quota đã duyệt. Không được tự điền câu hỏi khoa học hay quota giả thành chính thức.
2. **Nghiệm thu A1–A5:** pilot đa thiết bị, tất cả nhánh, mất mạng/refresh và thời gian, đối chiếu consent/survey version. Không được đánh dấu UAT PASS chỉ từ test unit.
3. **B4, C2, C3:** chủ nhiệm xác nhận rule chống trùng không xâm lấn, ngưỡng QC, logic mâu thuẫn, điều kiện loại mẫu, cách review theo đúng protocol. Không suy diễn trùng người từ trùng thiết bị.
4. **D4:** luồng trẻ vị thành niên là prototype, không xác minh guardian. Giữ `RESEARCH_MINOR_FLOW=false` tới khi có phê duyệt và triển khai quy trình xác minh độc lập. Giữ `RESEARCH_AUDIO_APPROVED=false` tới khi có consent/quy trình lưu/xóa đúng yêu cầu.
5. **D5:** quy định rút lui, retention và cách xử lý dữ liệu đã xuất phải được NCS và cơ sở chịu trách nhiệm dữ liệu phê duyệt. Xử lý `retain` chỉ hợp lệ nếu protocol cho phép; bảo toàn raw cho audit không có nghĩa được tiếp tục sử dụng tự do.
6. **E2–E4:** nhập dữ liệu giả có dấu tiếng Việt, missing, value labels và số thập phân vào SPSS thật; đối soát số dòng, ID, syntax, export hash và xác minh reverse/scoring theo kế hoạch đã ký. Cleaning log hiện là nhật ký chú giải, không tự chạy pipeline transform được phê duyệt.
7. **Hạ tầng sản xuất:** HTTPS, domain hợp lệ, server riêng đủ quyền, firewall, quyền DB tối thiểu, mã hóa bản sao lưu, lịch restore drill, giám sát, chính sách secrets, cập nhật phụ thuộc, rà soát quyền người dùng, kiểm thử xâm nhập và log truy cập tải/xem dữ liệu.
8. **Tích hợp/triển khai:** chạy `composer install`, `php artisan migrate`, `php artisan test` trên bản giải nén mới với DB thử riêng; kiểm tra ứng dụng thực tế trên server (không đưa `php artisan serve` ra Internet).
9. **UAT/NCS ký duyệt:** ghi kết quả từng mục A1–E5 tại `docs/UAT_CHECKLIST.md`, kết luận điều kiện đưa vào thu thập thực tế và người chịu trách nhiệm.

## Quyết định bàn giao

Có thể bàn giao **mã nguồn để kiểm thử/pilot kỹ thuật**; **KHÔNG thể cam kết hoàn thành 100% điều kiện triển khai nghiên cứu thật** khi các phê duyệt, đặc tả khoa học và nghiệm thu thực tế chưa có. Các flag thu thập, trẻ vị thành niên và âm thanh mặc định đều `false`.

## Quản lý quyền truy cập (bổ sung v0.2.0)

- Bản nâng cấp thêm migration `2026_10_18_000002_add_user_active_flag.php`. Người nhận cần chạy `php artisan migrate` trên DB mới/đã sao lưu trước khi sử dụng mã nguồn mới.
- Chủ tài khoản owner có thể khóa/mở tài khoản khác trong trang Users; không được khóa chính mình. Tài khoản khóa bị từ chối đăng nhập và không thể tiếp tục dùng trang `/admin` bằng phiên đăng nhập sẵn có.
- Không thay thế việc quản lý vòng đời tài khoản, phân chia nhiệm vụ, MFA/SSO hoặc audit hệ thống ở mức cơ sở triển khai; yêu cầu đánh giá trước production.
