# SOP vận hành, sao lưu/khôi phục và an toàn dữ liệu

## Sao lưu MySQL (Windows CMD)

Đóng băng thay đổi schema, kiểm tra quyền. Trước khi chạy, tạo thư mục backup riêng **ngoài** `public`, không gửi lên dịch vụ chưa được phép.

```cmd
cd C:\Projects\ResearchFlow_Laravel12
scripts\backup_mysql.cmd researchflow root C:\Backups\researchflow_20261017.sql
```

`mysqldump` hỏi mật khẩu. Backup không tự mã hóa; phải mã hóa bản sao và kiểm soát quyền file theo quy định cơ sở. Không đặt mật khẩu MySQL trong command line hoặc commit lên Git. Thử restoration định kỳ tại **DB mới** (không dùng production):

```sql
CREATE DATABASE researchflow_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```cmd
scripts\restore_mysql.cmd researchflow_restore root C:\Backups\researchflow_20261017.sql
```

Đối chiếu từng bảng qua MySQL Workbench: `SELECT COUNT(*) FROM participants;`, `survey_sessions`, `answers`, `consents`, `qc_decisions`, `audit_events`; kiểm tra các bản ghi cố định đã biết, phiên bản và quan hệ. Lập biên bản kiểm tra checksum và thời gian khôi phục. **Bản ZIP này không kèm backup SQL.** Theo nhật ký kiểm tra tại môi trường Windows gốc, đã thử khôi phục thủ công sang DB riêng và đối chiếu 23 bảng; script cập nhật cần kiểm thử luồng thành công tại máy bàn giao, không lấy biên bản kiểm thử ngoài ZIP làm bằng chứng nghiệm thu production.

## Quy trình thay đổi và bàn giao

- M1: đóng băng requirements matrix, ERD/codebook/rules/version mapping.
- M2: demo với mã site/đợt/câu hỏi giả **được đánh dấu là dữ liệu thử**; không đưa vào dataset thực.
- M3: pilot, kiểm thử A–E, khắc phục lỗi có changelog, xin NCS ký duyệt.
- M4: production khi có hồ sơ đạo đức/cơ sở, HTTPS, bảo vệ kết nối DB, secrets, lịch backup, quyền và monitoring. Không dùng `php artisan serve` để công khai.
- M5: source ZIP, `.env.example` (không bí mật), ERD, test log, codebook phê duyệt, UAT, SOP, restore test, danh sách quyền bàn giao; **không chia sẻ mật khẩu**.

## Quy định lưu trữ và truy cập

- Quyền cấp phát cá nhân, nguyên tắc tối thiểu; đóng tài khoản hết vai trò thủ công trong DB/phần quản lý phân quyền trước sản xuất.
- `storage/app/private` không thể truy cập công khai qua webserver chỉ phục vụ thư mục `public/`.
- Không tự động đưa file audio hoặc transcript lên dịch vụ AI/Cloud nếu chưa có chấp thuận và DPA phù hợp.
- Chốt lịch retention và chính sách yêu cầu rút lui theo phiên bản consent với NCS; yêu cầu rút lui cần được xử lý theo quy trình, không cam kết xóa luôn.
- Sau khi đổi version và bật/tắt thu thập, ghi phiên bản và diễn giải trong changelog riêng của dự án.

## Bảo vệ yêu cầu rút lui trong tập clean (bổ sung)

- Khi người tham gia gửi yêu cầu `withdrawal`, `participants.state` chuyển thành `withdrawal_requested` trong cùng giao dịch ghi yêu cầu.
- Tập **clean** không xuất dữ liệu của `withdrawal_requested` và `withdrawn` kể cả khi QC trước đó đã đánh dấu `valid`. Dữ liệu **raw** không bị sửa tự động, chỉ dành cho người có quyền theo quy trình lưu trữ/kiểm toán.
- NCS xem xét và chọn `exclude` (trạng thái `withdrawn`) hoặc `retain` (trạng thái `retained_by_protocol`) với giải trình phù hợp thông tin đồng thuận. Quyết định và người xử lý ghi lại trong audit.
- Đây là chặn sử dụng phân tích ở cấp ứng dụng, **không phải** hệ thống tự xóa/ẩn danh toàn diện hay thay thế quy trình pháp lý. Phải xác nhận protocol, thời hạn lưu và biện pháp xử lý các bản sao export đã phát hành trước đó.

## Quản lý quyền truy cập (bổ sung v0.2.0)

- Bản nâng cấp thêm migration `2026_10_18_000002_add_user_active_flag.php`. Người nhận cần chạy `php artisan migrate` trên DB mới/đã sao lưu trước khi sử dụng mã nguồn mới.
- Chủ tài khoản owner có thể khóa/mở tài khoản khác trong trang Users; không được khóa chính mình. Tài khoản khóa bị từ chối đăng nhập và không thể tiếp tục dùng trang `/admin` bằng phiên đăng nhập sẵn có.
- Không thay thế việc quản lý vòng đời tài khoản, phân chia nhiệm vụ, MFA/SSO hoặc audit hệ thống ở mức cơ sở triển khai; yêu cầu đánh giá trước production.
