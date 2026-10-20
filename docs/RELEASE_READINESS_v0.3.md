# ResearchFlow v0.3 — báo cáo bàn giao kỹ thuật và điều kiện triển khai

**Trạng thái:** BẢN ỨNG VIÊN KIỂM THỬ / CHƯA ĐƯỢC NGHIỆM THU DÙNG VỚI NGƯỜI THAM GIA THẬT.

Nguồn: 5 phiếu giao việc A–E trong `docs/source_assignments`, mã nguồn của phiên bản v0.2.0 do người dùng cung cấp. Nhóm phát triển không được tự thay thế quyết định khoa học/đạo đức của chủ nhiệm đề tài bằng nội dung giả.

## Đã bổ sung so với v0.2.0

| Phần | Thay đổi | Giới hạn cần xác nhận |
|---|---|---|
| A3 | Tự lưu thử lại khi kết nối trở lại, cảnh báo rời trang nếu có thay đổi chưa lưu; không lưu câu trả lời vào localStorage. | **Không phải hỗ trợ offline đầy đủ**. Phải pilot mất mạng/refresh. |
| B5 | Ghi audit cho thao tác đọc các trang dữ liệu/QC/yêu cầu có phân quyền; kiểm tra điểm và đợt hoạt động trước khi nhận khảo sát. | Chưa kiểm thử truy cập phá hoại, MFA, log cấp máy chủ. |
| C1 | Che ô thống kê nhỏ dưới ngưỡng bảo vệ quyền riêng tư mặc định 5 (cấu hình được). | Ngưỡng và trường hợp tổng hợp suy ngược danh tính cần được duyệt lại. |
| C2 | Cho phép quy tắc `logic_conflict` dưới dạng danh sách cặp mâu thuẫn JSON; chỉ có tác dụng sau duyệt. | Câu/mã giá trị và tiêu chí QC phải do NCS xác nhận. |
| C5 | Lệnh `php artisan research:snapshot` tạo CSV QC/quota trong storage riêng tư, SHA-256 và danh sách/tải qua giao diện có quyền. Tùy chọn scheduler 08:00. | Server cần cấu hình scheduler; chưa chạy cron/backup ngoài môi trường này. |
| D5 | Chặn nộp mới sau yêu cầu rút lui; hạn chế yêu cầu rút lui trùng; giữ bản ghi đang chờ rút lui ra khỏi clean dù trạng thái không đồng bộ. | Quyết định giữ/xóa và xử lý các bản đã xuất chờ protocol. |
| E2/E3 | Bảng quy tắc recode theo từng câu/cặp mã, hai người tạo-duyệt, chỉ áp dụng khi xuất clean. Log bản xuất/session/rule/giá trị trước-sau. Raw không thay đổi. | **Không suy diễn quy tắc đảo chiều/scoring**. Phải đối chiếu codebook/scoring từ NCS. |
| E5 | Review transcript có reason + history, trạng thái coding-ready, xuất CSV được duyệt/giữ riêng tư; giữ dữ liệu của người yêu cầu rút lui ngoài export. | Luồng audio vẫn khóa, cần consent, retention và duyệt đạo đức. |

## Kết quả kiểm tra và giới hạn

- PHP syntax lint trên mã nguồn: xem `docs/BUILD_VALIDATION_v0.3.json` được sinh khi đóng gói.
- Kiểm tra tham chiếu tên route trong Blade: xem cùng báo cáo.
- Các PHPUnit feature tests đã bổ sung trong `tests/Feature/CompletionHardeningTest.php` nhưng **không chạy được trong môi trường đóng gói vì thiếu vendor và extension PHP bắt buộc**. Kết quả 11/11 của người dùng chỉ áp dụng cho phiên bản trước.
- **Chưa nghiệm thu**: chạy Laravel trên DB MySQL/MariaDB mới; SPSS thật; Safari/Android/desktop; tải nhiều người dùng; kiểm tra an toàn ứng dụng; triển khai hosting HTTPS; phản hồi/chỉ tiêu/thang đo thật.

## Điều kiện PHẢI đạt trước khi mở thu thập thực tế

1. Chủ nhiệm đề tài phê duyệt bảng hỏi chính thức cho cả ba nhóm (CBQL, giảng viên, sinh viên), các lựa chọn/mã biến, điều kiện rẽ nhánh, tiêu chí bắt buộc và quota.
2. Hồ sơ đạo đức và bản thông tin/consent được phê duyệt; xác định rõ tính tự nguyện, rút lui, lưu giữ/xóa dữ liệu, đối tượng chưa thành niên và audio. `RESEARCH_MINOR_FLOW` và `RESEARCH_AUDIO_APPROVED` tiếp tục `false` khi chưa có quy trình đã thẩm định.
3. Mọi test ở `docs/UAT_CHECKLIST.md` được chạy và ghi người test/thiết bị/bằng chứng; test bất kỳ lỗi nào phải sửa và chạy lại.
4. Nghiệm thu QC/conflict/recode/scoring cùng NCS; xác nhận raw/clean đúng, đưa CSV + .sps vào IBM SPSS thật, đối soát số dòng, nhãn tiếng Việt, missing/value labels.
5. Phê duyệt các nguyên tắc bảo mật và tổ chức: quyền tối thiểu/MFA khi phù hợp, HTTPS, DB creds riêng, kiểm tra OWASP, tách staging/production, lịch backup **và thử restore**, server access logs, theo dõi và xử lý sự cố, dữ liệu nhạy cảm, thời hạn lưu.
6. Chạy `composer install`, `php artisan migrate`, `php artisan test`, UAT, kiểm tra báo cáo snapshot/route/permission trên bản ZIP đã giải nén với DB thử **riêng**.
7. Chủ nhiệm đề tài ký duyệt phiên bản, nội dung, dữ liệu và quyết định go-live.

**Đây là danh sách chặn triển khai thật, không phải bằng chứng các bước trên đã hoàn thành.** Giữ `RESEARCH_COLLECTION_OPEN=false` trước khi tất cả điều kiện đạt.

## Làm rõ phiên bản sửa cuối

- Màn hình E4 phân biệt riêng số `QC Valid` và số dòng **thật sự đủ điều kiện clean** sau khi loại trường hợp rút lui.
- Mục audio ở trạng thái `received` có thể được chép lời vào cùng bản ghi (khi luồng audio đã được phê duyệt), sau đó mới được review/coding-ready; không thể nhảy thẳng trạng thái.
- Cần kiểm thử phương án lưu tập tin, đếm bản ghi và phân quyền nhiều người trước khi kích hoạt.
