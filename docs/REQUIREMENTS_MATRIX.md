# Ma trận yêu cầu A1–E5 và kiểm soát nghiệm thu

Các mô tả dưới đây tóm lược năm phiếu phân công do người dùng cung cấp. `Implemented` = có mã nguồn; `Partial` = có mã nguồn nhưng cần đặc tả, test/UAT hoặc mở rộng; `Blocked` = thiếu quyết định/điều kiện ngoài kỹ thuật. Đây **không phải** chứng nhận nghiệm thu.

| Mã | Yêu cầu gốc | Mã nguồn/đầu ra | Tình trạng và lưu ý |
|---|---|---|---|
| A1 | Portal giới thiệu→thông tin→consent→sàng lọc→bảng hỏi→hoàn tất | `PortalController`, `resources/views/portal`, `survey` | Partial: phân luồng bằng QR/link, sàng lọc tuổi; nội dung chờ NCS |
| A2 | CBQL/giảng viên/sinh viên, branching/skip | `surveys`, `questions`, sample link group | Implemented engine; chưa có câu hỏi chính thức |
| A3 | Range check, required, cảnh báo thiếu, autosave | `PortalController::save`, draft submit, Blade form | Partial: lưu nháp thủ công, autosave online tuần tự, kiểm tra range/choices/text; KHÔNG có offline queue (cần UAT mất mạng) |
| A4 | Version bảng hỏi, changelog, khóa | `surveys.version/status`, `audit_events` | Partial: có clone phiên bản đã công bố thành bản nháp mới, sửa/xóa có kiểm soát câu hỏi bản nháp và khóa phiên bản cũ; chưa có version diff/biên bản duyệt nội dung chính thức |
| A5 | Test đa thiết bị/nhánh/mất kết nối/UAT | `tests/Feature`, `docs/UAT_CHECKLIST.md` | Blocked: chưa chạy UAT thực tế và bài kiểm tra offline |
| B1 | Research ID duy nhất, không PII | UUID random trong `participants.research_id` | Implemented |
| B2 | Mã cơ sở/đợt/nhóm; QR/link registry | `sites`,`waves`,`sample_links`, `/links/{id}/qr` | Implemented SVG; mã dùng theo kế hoạch NCS |
| B3 | Participant/session/response/consent/version/site + PK/FK/time | migrations, `docs/ERD.md` | Implemented schema |
| B4 | Duplicate session/token flag, không fingerprint | `QualityService::reusedToken` | Partial: chỉ gắn cờ token gửi lại, không chứng minh trùng người |
| B5 | Backup/restore, ít quyền, log truy cập | scripts/backup/restore, role middleware, audit | Partial: có khóa/mở tài khoản và chặn phiên đã khóa; nhật ký môi trường Windows trước bàn giao ghi nhận restore thủ công/đối soát 23 bảng; chưa xác minh script luồng thành công trên máy nhận và log truy cập đọc dữ liệu đầy đủ |
| C1 | Dashboard theo nhóm/site/đợt/hoàn tất | `AdminController::dashboard`, view | Implemented aggregate, cần đối soát/cỡ mẫu tối thiểu để hạn chế re-identification |
| C2 | Too-fast, high missing, straight-line, logic, duplicate | QC rule table + `QualityService` | Partial: 4 quy tắc, logic contradiction chưa có đặc tả khoa học |
| C3 | Valid/Review/Exclude + lý do + người duyệt + audit | qc_decisions, audit | Implemented workflow (nhưng cần tiêu chí khoa học) |
| C4 | Theo dõi thiếu/vượt quota đã duyệt | dashboard quota alerts | Implemented cơ bản |
| C5 | Snapshot tiến độ/QC theo đợt và đối soát DB | `snapshot` CSV, export validation | Partial: snapshot quota/QC theo đợt CSV, chưa có tự động gửi theo lịch |
| D1 | Quota draft/submit/approve/lock | quota_configs versions/statuses | Implemented dựa trên chỉ tiêu ngoài hệ thống |
| D2 | Participant information sheet | information_versions | Implemented framework; thiếu nội dung được duyệt |
| D3 | Consent version, timestamp, status, log | consents | Implemented tối thiểu; chưa chứng nhận pháp lý |
| D4 | Under-18 guardian permission / assent | feature flag disabled, consent flags, intro | Prototype **only**; chưa có xác minh/nghiệm thu đạo đức |
| D5 | Support/withdrawal → NCS | participant_requests | Partial: tiếp nhận/routing, hold dữ liệu khỏi clean khi rút lui, bắt buộc disposition và audit; chính sách lưu/xóa/thu hồi bản export chờ NCS |
| E1 | Codebook, variable/value labels, missing, scale, source | questions metadata + codebook view | Partial: có hiển thị và xuất CSV codebook, nhưng thiếu biến/thang đo/mapping đã được NCS duyệt |
| E2 | CSV/XLSX/SPSS, clean derived, recode/reverse/scoring nếu duyệt | DataController, SimpleXlsx | Partial: CSV/XLSX/SPSS syntax (biến số và choice codes numeric được định dạng numeric); chưa có recode/reverse/scoring được duyệt, chưa chạy SPSS round-trip |
| E3 | Cleaning log mọi biến đổi, có thể tái lập | cleaning_logs | Partial: ghi metadata/log thủ công, chưa có pipeline recode thực thi |
| E4 | Đối soát dòng/ID/missing/encoding/decimal | DataController::validation | Partial: có báo cáo đếm, cần round-trip import SPSS để nghiệm thu |
| E5 | Audio→mã file→transcript→coding-ready registry/rights | transcripts, private storage | Partial: nhận và lưu thủ công, không có engine ASR; tắt khi chưa consent |

## Trách nhiệm NCS/đơn vị chủ trì phải cung cấp

- **A/D**: nội dung 3 bộ bảng hỏi (các mã biến, lựa chọn, rẽ nhánh, câu bắt buộc), thông tin tham gia và consent có version, quy trình dưới 18 tuổi (nếu có).
- **B/D**: danh sách mã cơ sở/đợt, thiết kế/quota đã phê duyệt, tiêu chí xác minh và chống trùng phù hợp đạo đức.
- **C**: ngưỡng too-fast/missing/straight-lining, điều kiện logic mâu thuẫn, tiêu chí Valid/Review/Exclude, quy trình phân xử độc lập.
- **E**: codebook chính thức, reverse/scoring/missing/recode, mẫu transcript và thời hạn lưu/xóa âm thanh.
- **Cả hệ thống**: chủ thể dữ liệu/hosting, kế hoạch bảo mật, pilot/UAT, backup/restore thực tế, phê duyệt đạo đức, quy định pháp lý và thời hạn lưu trữ.
