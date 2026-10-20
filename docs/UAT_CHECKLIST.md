# Checklist nghiệm thu pilot/UAT (mẫu, CHƯA KIỂM THỬ THỰC TẾ)

Ghi người test, thiết bị/trình duyệt, ngày, dữ liệu giả, kỳ vọng/thực tế, kết quả PASS/FAIL, ảnh lỗi và mã commit. Không chuyển Production nếu chưa có phê duyệt.

- [ ] A1: Safari iOS, Chrome Android, Chrome/Edge desktop; giới thiệu → info → đồng thuận → khảo sát → hoàn tất; link không đúng → 404.
- [ ] A2: CBQL / giảng viên / sinh viên nhận đúng bảng hỏi, không nhìn thấy nhánh khác.
- [ ] A3: Required, số, lựa chọn không hợp lệ, câu ẩn và tự lưu/draft; mô phỏng mất kết nối, refresh trang.
- [ ] A4: Đổi version sau pilot không sửa bản cũ, bản trả lời giữ survey/version info đúng.
- [ ] B1: 100 Research ID ngẫu nhiên unique, không có MSSV/email.
- [ ] B2: QR SVG quét trên điện thoại đến đúng điểm/đợt/nhóm (cấu hình URL hosting chính xác).
- [ ] B3: Migration + FK, không xuất `private_label`, không xuất resume token/consent ID nhạy cảm.
- [ ] B4: POST nộp lại không ghi đè; kiểm tra cờ reused token khi rule đã duyệt.
- [ ] B5: Role deny 403, audit login/export, sao lưu riêng và **khôi phục tại DB thử**; đối chiếu số dòng.
- [ ] C1/C4/C5: Số hoàn tất/dashboard/target/snapshot đối chiếu SQL trực tiếp; thử quota thiếu/thừa.
- [ ] C2/C3: Các QC rule ở draft không tác dụng; sau người khác duyệt mới cắm cờ; decision cần reason, không auto-exclude.
- [ ] D1: Submitter không thể tự approve; quota mới supersede phiên bản cũ.
- [ ] D2/D3: Consent chứa đúng version, time, trạng thái. Không cho trả lời trước khi consent.
- [ ] D4: Mặc định dưới 18 bị chặn; không bật prototype khi chưa có hồ sơ phê duyệt và cơ chế xác minh thực sự.
- [ ] D5: Yêu cầu hỗ trợ/rút lui có created_at, định tuyến NCS, trạng thái/log tồn tại, không xóa câu trả lời tự động.
- [ ] E1–E4: CSV UTF-8 mở đúng tiếng Việt, XLSX mở được, import SPSS syntax, nhãn và missing/value labels đúng, row counts raw/clean đúng, compare SHA-256.
- [ ] E5: File âm thanh private không bị route public tải, transcript gắn Research ID, quyền và thời hạn lưu theo consent.
- [ ] Security: CSRF, brute-force login, role security, IDOR, XSS, file upload, resume token sharing, HTTPS, backup/restoration, audit integrity.

**Biên bản UAT:** chưa điền. Người nghiệm thu: __________ Ngày: __________ Thiết bị: __________ Kết quả: __________ Chấp thuận NCS: __________

## Bổ sung cho phiên bản v0.2.0 — chưa ký xác nhận UAT

- [ ] Kiểm thử clone/version mới và sửa câu hỏi trong bản nháp; bản published không thay đổi, condition choice không hợp lệ bị từ chối.
- [ ] Kiểm thử tạm ngừng cổng khi đã tạo phiên nháp: chặn POST lưu/nộp; tiếp tục hỗ trợ/rút lui theo protocol.
- [ ] Kiểm thử gửi withdrawal: `withdrawal_requested` và clean export không chứa bản ghi; quyết định `exclude/retain` bắt buộc ghi lý do; raw không tự xóa.
- [ ] Thử SPSS numeric Likert/single/number, missing values, mã chuỗi có số 0 đầu và nhãn tiếng Việt bằng dữ liệu giả, import SPSS thực tế.
- [ ] Xác nhận HTTP no-referrer/no-store, quyền viewer không nhìn thấy bảng phân nhóm chi tiết.
- [ ] Chạy lại PHPUnit/Feature trên bản ZIP mới (bộ 11 PASS trước sửa không tính cho bản này).
- [ ] Người duyệt nghiên cứu ký duyệt bảng hỏi/quota/QC/consent, dữ liệu, bảo mật và triển khai trước khi mở thu thập.

## Bổ sung v0.3 — PHẢI kiểm tra thực tế, chưa đánh dấu PASS

- [ ] A3: Mất mạng ngay khi sửa câu → cảnh báo; kết nối lại → tự lưu nháp; không có lưu câu trả lời bền vững vào trình duyệt; refresh ngoài ý muốn cảnh báo mất dữ liệu.
- [ ] B5: Site/wave tắt → link không cho tham gia; truy cập trang dữ liệu/QC được ghi `audit_events` với route name; không log token khảo sát.
- [ ] C1: Ô thống kê có 1–4 mẫu không lộ số liệu chi tiết với cấu hình min-cell 5; thử các vai trò.
- [ ] C2: `logic_conflict` JSON nhiều cặp, xác minh rule nháp không flag; sau reviewer và approver khác nhau mới flag; trường hợp nhánh ẩn không flag nhầm.
- [ ] C5: `php artisan research:snapshot` → `report_snapshots` có SHA-256 chính xác, quyền tải và kiểm tra file đúng; lệnh scheduler cron chạy thử trong staging.
- [ ] D5: Gửi withdrawal lặp không tạo yêu cầu trùng, nộp mới bị chặn và clean export giữ dữ liệu ngoài cho tới xử lý; rà soát tác động các bản export trước đó theo protocol.
- [ ] E2/E3: rule recode chỉ xử lý exact question_id/code đã duyệt, raw không đổi, clean có thay đổi và `cleaning_applications` chứa bằng chứng theo export; rule retired không còn áp dụng.
- [ ] E5: review transcript, coding_ready và export CSV; recheck minor/audio chưa được duyệt không thể truy cập.
- [ ] E4: số `QC Valid` có thể lớn hơn số `Clean export` khi có withdrawal; đối chiếu dữ liệu CSV thực.
- [ ] E5: audio-only received → nhập transcript → reviewed → coding_ready; kiểm tra chuyển trạng thái sai bị từ chối và audit luôn ghi đầy đủ.
