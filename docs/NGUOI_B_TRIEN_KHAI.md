# Triển khai người B theo hồ sơ thiết kế hạ tầng khảo sát số

Căn cứ chính là tài liệu người dùng chỉ định: `Hồ sơ thiết kế và bàn giao hạ tầng khảo sát số.docx`, các phần F01–F12, B1–B5 và quyết định D01–D14. Phiếu `B_PHAN_CONG_IT_RESEARCH.docx` xác định ranh giới công việc. Ngày cập nhật: 22/10/2026.

Phần B đã được tích hợp vào dự án Laravel hiện tại. Bộ kiểm thử tự động dùng dữ liệu giả và SQLite riêng; không thay đổi CSDL khảo sát thật. Chưa chạy migration trên MySQL của người dùng vì dự án hiện không có `.env` kết nối. Không dùng kết quả kiểm thử này thay cho nghiệm thu pilot thực địa hoặc lệnh NCS mở thu thập.

## Đối chiếu thiết kế với mã nguồn

| Thiết kế | Triển khai |
|---|---|
| B1, F01 | `ResearchId`: 16 byte ngẫu nhiên mật mã → R + 26 Base32. Ràng buộc UNIQUE có sẵn; thử lại tối đa 5 lần khi xung đột đúng Research ID; ghi audit collision. UUID cũ giữ nguyên để bảo toàn liên kết. |
| B2, F02 | Sinh mã S-/D-/W- ngẫu nhiên; bảng `departments` có FK cơ sở; mã lớp tùy chọn, mã điểm riêng. Giữ mã cơ sở cũ đang tồn tại. |
| F03–F04 | Link mới gắn điểm/cơ sở/khoa/nhóm/đợt/phiếu cố định, người quản lý, người tạo/duyệt, hạn mở/đóng. Token 192 bit; CSDL chỉ lưu SHA-256. URL/QR hiển thị một lần ngay khi tạo, không lưu vào flash session. |
| F04, B2-T01–T04 | DRAFT → ACTIVE → PAUSED; REVOKED không mở lại. EXPIRED tính từ hạn cuối. Thay link bằng bản ghi mới và ghi `replaced_by` khi thu hồi link cũ. Không có chức năng sửa ánh xạ link. |
| F05 | Giữ luồng thông tin/consent phiên bản hiện có; cấp ID sau khi đồng thuận. Form consent gửi đến token gốc, không gửi đến mã băm. |
| F06–F07 | `participants.sample_link_id` lưu nguồn link; session trỏ FK người tham gia và phiếu. `SamplingRoute` chọn phiếu gắn với link, kể cả khi bản mới đã công bố và bản cũ trở thành superseded. Khóa câu trả lời vẫn theo session + question_id; question_id thuộc riêng survey/version. |
| OBS2/OBS4 | Nhóm mới BGH/KHOA/GV/SV; OBS2 chỉ BGH/KHOA. Mỗi đợt gắn instrument, thời hạn và kỳ tham chiếu. Giữ nhóm manager/lecturer/student cho dữ liệu cũ; không tự đoán manager là BGH hay KHOA. |
| F08 | Giữ 97, 98, BLANK ở `answers.missing_code` cho OBS2/OBS4; giá trị JSON gốc giữ để tương thích bộ xuất cũ. Xuất dài để answer_value trống khi có missing_code. QC không tính mã thiếu vào straight-lining; clean recode không đổi mã thiếu thành điểm; SPSS syntax khai báo 97/98 là missing. |
| F09 | `technical_duplicate_flags`: SESSION_REPLAY, RESEARCH_REPEAT; collision ID ghi audit và cấp lại. Quét lại QR chung không tạo cờ. Rà soát OPEN/REVIEWED có người, giờ, giải trình; không xóa phiên hoặc tự quyết Exclude. Resume token UNIQUE tiếp tục ngăn token gán sang hai session. |
| F10 | CSV raw dạng dài 19 cột theo mẫu prototype; codebook và bộ xuất rộng hiện có bổ sung instrument/form/source item. Không xuất tên cơ sở, token, resume URL. Năm trường nền trong mẫu dài để trống vì chưa có dữ liệu/đặc tả thu được duyệt. |
| F11 | Giữ tài khoản/vai trò Laravel hiện có; registry/QR có log đọc, export và thay đổi trạng thái có audit. Bằng chứng cờ là SHA-256, không ghi IP/fingerprint/câu trả lời. |
| F12 | Giữ script MySQL backup/restore có sẵn; bổ sung lệnh chỉ đọc đối chiếu mọi bảng, số dòng, digest nội dung sau restore vào `researchflow_restore`. Đã có bài thử sao lưu/khôi phục SQLite độc lập và phát hiện dữ liệu sai dù số dòng bằng nhau. |
| D11 | Khi tạo link mới bắt buộc chọn theo quyết định NCS: `continue` cho phiên đang mở tiếp tục hoặc `stop` dừng khi link/đợt/cơ sở ngừng/hết hạn. Công tắc đóng thu thập chung và yêu cầu rút lui vẫn chặn lưu. |

Đã bổ sung vai trò theo thiết kế: IT_ADMIN tạo/cấu hình/tạm dừng/thu hồi link và cấp tài khoản, nhưng không duyệt mở hoặc xuất dữ liệu; NCS duyệt mở/thu hồi, rà cờ, xem log và xuất dữ liệu; ANALYST xuất bản phân tích dạng dài chỉ gồm phiên complete có quyết định QC valid mới nhất và không đang rút lui, tải codebook, không đọc registry/raw; SITE_COORDINATOR chỉ xem trạng thái link thuộc assigned_site_id, không thấy token, tên thật hoặc câu trả lời; AUDITOR chỉ xem nhật ký. Các quyền đọc được ghi audit. Owner/researcher cũ giữ quyền A–E để tương thích; khi triển khai thật nên cấp vai trò chuyên trách mới thay vì dùng tài khoản rộng quyền. Phân công người thật, MFA và quyền backup ở máy chủ vẫn do đơn vị vận hành phê duyệt.

## Cài phần bổ sung

Thực hiện trong thư mục dự án, với đúng PHP đang cài đủ extensions. Trên máy hiện tại, PHP chạy được ở:

```powershell
C:\xampp\php-8.5.7\php-8.5.7-nts-Win32-vs17-x64\php.exe
```

Sau khi cấu hình `.env` và sao lưu DB hiện có, áp dụng migration theo README của dự án:

```powershell
php artisan migrate
```

Không chạy migrate:fresh lên DB đang có dữ liệu. Bốn migration mới ngày 22/10/2026 bổ sung provenance, danh mục/metadata registry và cờ kỹ thuật/mã thiếu. Các FK link của bản ghi cũ để NULL vì không đủ bằng chứng suy ngược người tham gia đã dùng link nào.

Trên DB thử riêng, nạp ngân hàng biến từ prototype của người B:

```powershell
php artisan research:import-b-design
```

Lệnh nạp 6 phiếu nháp, 227 biến: OBS2 39 BGH + 34 KHOA; OBS4 39 BGH + 33 KHOA + 46 GV + 36 SV. Nguồn là hai CSV DEMO trong `docs/b_design`. Nhãn thang và nguyên văn câu hỏi đi theo instrument/form_version. Mã nguồn như BGH-NT01 được giữ ở `source_item_code`; mã biến dùng cho SPSS là BGH_NT01, không nhập ký tự gạch ngang vào tên biến SPSS. Nạp lại cùng phiên bản bị từ chối và rollback, không ghi đè ngân hàng cũ. Lệnh không công bố phiếu, không tạo người tham gia, không mở thu thập.

## Thử trên giao diện

1. Đăng nhập owner/researcher hoặc NCS cho phần công cụ; vào Khảo sát để kiểm tra phiếu nháp DEMO hoặc nhập bản đã duyệt. Khi sao chép phiếu, điền mã phiên bản mới trước khi công bố.
2. Owner/researcher hoặc IT_ADMIN vào Điểm mẫu: tạo cơ sở, khoa/ngành và đợt đúng OBS2/OBS4; điền thời hạn và kỳ tham chiếu.
3. Tạo link với phiếu đã công bố đúng nhóm, người phụ trách, mã điểm, thời hạn nằm trong đợt và lựa chọn D11.
4. Lưu URL và tải QR tại trang cấp link một lần. Link mặc định DRAFT; nếu mất URL, tạo link mới rồi thu hồi/thay thế link cũ. Registry CSV không chứa token/hash của link mới.
5. NCS (hoặc tài khoản owner/researcher cũ) duyệt bật link khi công tắc thu thập mở, có consent và quota được duyệt. Dùng dữ liệu giả trong UAT; production phải theo lệnh NCS.
6. Mở đúng URL/QR; đọc consent rồi điền phiếu. Hoàn tất hai lượt qua cùng QR phải tạo hai Research ID riêng và không bị coi là trùng chỉ vì dùng chung QR.
7. Thử gửi lại phiên đã nộp: câu trả lời gốc giữ nguyên, có cờ SESSION_REPLAY tại Cờ trùng kỹ thuật.
8. Thử link tạm dừng/thu hồi/hết hạn và kiểm tra chính sách D11. Phiên mới bị chặn; dữ liệu đã thu được giữ nguyên.
9. Xuất raw dạng dài từ trang Điểm mẫu; xuất codebook CSV từ Data/SPSS. Đối chiếu instrument, form_version, source_item_code và missing_code.

Để quét các phiên cùng Research ID/đợt/bộ phiếu và mở cờ rà soát:

```powershell
php artisan research:scan-duplicates
```

Đây là cờ kỹ thuật, không chứng minh trùng người; không tự chạy xóa hoặc chọn mẫu.

## Sao lưu và xác minh phục hồi

Làm theo `docs/OPERATIONS.md` và hai script `scripts/backup_mysql.cmd`, `scripts/restore_mysql.cmd`. Restore chỉ vào DB thử `researchflow_restore` mới, không dùng DB production. Trước đối chiếu, ngừng ghi ở nguồn và dùng bản backup vừa chụp; dữ liệu nguồn tiếp tục thay đổi sẽ làm digest khác.

```powershell
php artisan research:verify-mysql-restore
```

Lệnh không restore hoặc sửa hai DB. Nó kết nối DB nguồn từ cấu hình hiện tại và DB thử cùng máy chủ/tài khoản, so sánh tên bảng, số dòng và SHA-256 của nội dung từng bảng, lưu JSON riêng tư trong local disk dưới `restore_checks/`, trả mã 0 khi khớp, 1 khi lệch. Không chứa mật khẩu hoặc dữ liệu từng dòng trong báo cáo. Cần đối chiếu schema, khóa ngoại, file audio/đính kèm, quyền, mã hóa, lịch/retention và phục hồi dịch vụ riêng theo SOP. Chưa chạy thử MySQL thực tế trong phiên làm việc này.

## Kiểm thử và phạm vi nghiệm thu

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --log-junit uat-evidence/B_design_phpunit.xml
```

Kết quả cuối ngày 22/10/2026: **57/57 bài kiểm thử đạt, 20.405 assertions**, PHP 8.5.7 và SQLite; danh sách 62 đường dẫn quản trị tải thành công. Các ca bổ sung nằm trong `ResearchDesignBUatTest`, `RestoreVerificationUatTest` và kiểm tra missing codes của `SpssSyntaxTest`. Bao gồm 10.000 ID, retry collision, link sai/trạng thái/thời hạn, đúng phiên bản, QR phát hành một lần, thu hồi không mất dữ liệu, mã thiếu, raw export riêng tư, import đủ 227 biến, nộp lại, phát hiện lặp session, D11, phân vai trò/giới hạn điểm và restore nội dung giả. Nhật ký JUnit là bằng chứng tự động, không phải chữ ký nghiệm thu NCS.

Chưa có dữ liệu Facebook trong luồng này. Facebook nếu được nhóm dùng để đăng link chỉ là kênh mời khảo sát; hệ thống không có chức năng lấy bài viết/bình luận/danh tính từ Facebook.
