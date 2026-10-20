# Lịch sử thay đổi phiên bản mã nguồn

## 2026-10-18 — Bản kỹ thuật v0.2.0 (chưa được xác nhận production)
- Sao chép phiên bản bảng hỏi đã công bố thành bản nháp mới để giữ lịch sử và dữ liệu đã thu.
- Kiểm tra chặt mã lựa chọn, điều kiện rẽ nhánh phải tham chiếu mã chọn hợp lệ.
- Giới hạn ghi vào câu trả lời khi cổng thu thập đóng; khóa hàng DB khi lưu nhằm tránh ghi đè do yêu cầu đồng thời.
- Hàng đợi tự lưu nháp được tuần tự hóa; nếu mất kết nối hiển thị trạng thái thay vì tuyên bố đã lưu.
- Thêm HTTP headers giảm rò rỉ token qua referrer/cache và giới hạn hiển thị thống kê chi tiết cho viewer.
- Khi tiếp nhận rút lui, đưa trạng thái vào `withdrawal_requested`; clean export loại bản ghi đang chờ/đã loại. Khi giải quyết phải ghi rõ retain/exclude theo protocol đã duyệt.
- Thêm CSV codebook UTF-8, test kiểm chứng tính năng mới, checklist nghiệm thu và tài liệu rủi ro trước production.
- Kiểm tra cú pháp PHP và cấu trúc ZIP trong môi trường xây dựng; chưa thực thi Laravel feature suite hoặc SPSS trên môi trường xây dựng này do thiếu thư viện runtime/PHP extensions.

# Changelog dự án

## 2026-10-17 — Prototype v0.1.0
- Tạo khung mã nguồn Laravel 12, MySQL schema và giao diện responsive.
- Dựng luồng A (portal/ba nhóm/branch/draft/version), B (Research ID/site/QR/schema/duplicate-token), C (QC rules/queue/dashboard/quota alerts/snapshot), D (quota/info/consent/minor-disabled/support), E (codebook/raw-clean/CSV-XLSX-SPSS syntax/log/transcript registry).
- Bổ sung role middleware, ghi audit, test feature source, hướng dẫn Windows và backup/restore SOP.
- Chưa chạy Composer, Laravel feature tests, mobile UAT, restore pilot, SPSS round-trip hoặc production security review. Không có nội dung nghiên cứu/đạo đức hay quota tự đặt.

## Quản lý quyền truy cập (bổ sung v0.2.0)

- Bản nâng cấp thêm migration `2026_10_18_000002_add_user_active_flag.php`. Người nhận cần chạy `php artisan migrate` trên DB mới/đã sao lưu trước khi sử dụng mã nguồn mới.
- Chủ tài khoản owner có thể khóa/mở tài khoản khác trong trang Users; không được khóa chính mình. Tài khoản khóa bị từ chối đăng nhập và không thể tiếp tục dùng trang `/admin` bằng phiên đăng nhập sẵn có.
- Không thay thế việc quản lý vòng đời tài khoản, phân chia nhiệm vụ, MFA/SSO hoặc audit hệ thống ở mức cơ sở triển khai; yêu cầu đánh giá trước production.

## 2026-10-19 — v0.3 candidate (không phải production release)

- A3: Tự thử lại auto-save khi online, nhắc rời trang nếu còn thay đổi chưa lưu; không lưu dữ liệu nghiên cứu trong localStorage.
- B5: Ghi access audit cho đọc dữ liệu/QC/yêu cầu; chặn link thuộc site/wave bị vô hiệu hóa.
- C1/C2/C5: Ẩn ô nhỏ trên dashboard; `logic_conflict` JSON do NCS quy định/duyệt; snapshot private scheduler opt-in và trang download có xác nhận hash.
- D5: Khóa nộp sau withdrawal, idempotent pending request, đối soát pending withdrawal trong clean export.
- E2/E3: Workflow recode draft/approve/retire và log áp dụng theo export/session/rule, không sửa raw.
- E5: Transcript review events, coding-ready export, tạm giữ dữ liệu rút lui.
- Thêm migration 000003–000005, feature tests và tài liệu nâng cấp/UAT. **Test suite mới chưa chạy trong môi trường phát triển này.**
