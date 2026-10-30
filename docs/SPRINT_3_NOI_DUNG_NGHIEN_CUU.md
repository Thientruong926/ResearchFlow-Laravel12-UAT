# Sprint 3 — Nội dung nghiên cứu và liên hệ

Ngày cập nhật: 30/10/2026. Phạm vi S3-01 đến S3-05 trong kế hoạch mục 4–6.

**Đã triển khai code và kiểm thử tự động, gồm cạnh tranh giữa hai tiến trình PHP trên Windows. Chưa thực hiện UAT trực quan desktop/mobile hoặc thử cơ chế thay thế file trên máy chủ triển khai khác.** Phần này không cần adapter Google để sửa/xem trước/lưu nội dung; tích hợp Google thật vẫn là phần còn chờ của dự án.

## Kết quả theo đầu việc

| ID | Kết quả | Kiểm chứng |
|---|---|---|
| S3-01 | GET/POST `/admin/settings/research`, controller/request riêng; giữ route name POST cũ; mapping cột chuyển sang POST `/admin/settings/columns` | Lưu research không gửi tên cột vẫn đạt; trường/gán phiếu/mapping/khóa lạ còn nguyên |
| S3-02 | Validation tiếng Việt theo trường; giữ nội dung nhập; preview dùng chung partial với trang công khai; escape văn bản | Test email/phone/title/address sai, preview không ghi, script hiển thị dạng text |
| S3-03 | SettingsFileStore khóa đọc-sửa-ghi, revision fingerprint, file tạm cùng thư mục + fflush/fsync + rename; backup tối đa 5 bản | Test stale revision trả 409, ghi thay thế thất bại không phá JSON cũ, dọn file tạm, xoay vòng backup |
| S3-04 | Tiêu đề/giới thiệu/đồng thuận/liên hệ cập nhật qua JSON; điện thoại/địa chỉ tùy chọn; partial dùng ở trang đầu và phiếu | Test tải lại trang công khai/phiếu thấy nội dung mới, không cần config:clear |
| S3-05 | Mọi writer research/columns/schools/assignments dùng chung SettingsFileStore và revision; JSON hỏng báo 503; mảng rỗng thay defaults | Test hai tiến trình cùng revision: một OK, một CONFLICT; các writer HTTP cũ dùng revision cũ trả 409; kiểm thử hồi quy cả Sprint 0–2 |

## Cách dùng

1. Đăng nhập, vào **Cài đặt → Sửa nội dung / xem trước** (`/admin/settings/research`).
2. Nhập tiêu đề, giới thiệu, đồng thuận và liên hệ. Điện thoại/địa chỉ có thể để trống.
3. Bấm **Xem trước** để xem phần nội dung dùng đúng partial của trang người tham gia; chưa ghi cấu hình. Form bên dưới giữ nội dung để chỉnh tiếp hoặc lưu.
4. Bấm **Lưu thay đổi**; server đọc lại file sau khi thay thế, sau đó tải lại trang sửa và báo thành công. Trang công khai cập nhật ở lần tải tiếp theo.
5. Khi nhận 409, cấu hình đã đổi kể từ lúc mở form. Nội dung nghiên cứu vừa nhập vẫn nằm trên trang báo lỗi để sao chép. Tải bản mới nhất, đối chiếu rồi lưu lại; không tự ghi đè phiên bản mới của người khác.

Form cũ đang mở trước khi nâng cấp chưa có revision sẽ không được lưu: tải lại trang trước khi chỉnh. POST research cũ vẫn giữ URL/name nhưng chỉ nhận nội dung research; mapping có form riêng tại trang Cài đặt.

## Kho cấu hình và audit

Các thành phần chính:

- `app/Services/SettingsFileStore.php`: đọc có shared lock, ghi có exclusive lock xuyên suốt đọc/kiểm revision/merge/backup/thay file.
- `app/Services/ResearchSettings.php`: thao tác từng subtree qua store, không tự ghi file riêng nữa.
- `app/Http/Requests/UpdateResearchSettingsRequest.php`, `AdminResearchSettingsController.php`: validation, preview, lưu và giữ nội dung khi xung đột/lỗi I/O.
- `resources/views/admin/research_settings.blade.php`, `partials/research_intro`, `research_consent`, `research_contact`: giao diện dùng chung.

File chính vẫn là `storage/app/research-settings.json`. File lock cố định là `research-settings.json.lock`; **không xóa file lock khi ứng dụng đang chạy**. File tạm được tạo cùng thư mục, quyền 0600 trên hệ thống hỗ trợ chmod, rồi rename lên file đích; không dùng unlink file chính trước rename. Windows test đã xác nhận thay file đang tồn tại thành công và trường hợp rename thất bại giữ nguyên file cũ.

Revision gửi trong form là hash nội dung cấu hình có hiệu lực (không gồm audit), không phải số do client tự tăng. `_version` tăng mỗi lần có thay đổi; sửa file bên ngoài ứng dụng cũng đổi fingerprint và làm tab cũ bị từ chối. Bản ghi `_audit` giữ tối đa 100 sự kiện, mỗi sự kiện chỉ có tên trường thay đổi, email người quản lý, UTC timestamp, revision mới. Không có giá trị cũ/mới, token, password hay credential trong audit. Save không đổi nội dung là no-op.

File chưa tồn tại mới dùng defaults. JSON lỗi cú pháp hoặc cấu trúc sai báo lỗi; không tự đổi sang nội dung demo. `schools`/`assignments` trong file được thay toàn bộ, kể cả `[]`; không gộp danh sách theo index để làm sống lại phần tử mặc định. Sửa research chỉ sửa các trường được cho phép, giữ nguyên khóa chưa biết ở root và trong research.

## Backup và phục hồi

- Backup trước lần ghi được đặt trong `storage/app/research-settings.json.backups/`, tối đa 5 file JSON; cả backup/lock/temp đã thêm vào .gitignore.
- Backup có **các phần cấu hình được biết là không chứa credential**: research, schools, assignments, sheet_columns, source_defaults, version. Các trường lạ và khóa credential không được chép vào backup. URL nguồn được canonicalize để bỏ query ngoài phạm vi nguồn; Form URL được đưa về ID.
- File chính vẫn bảo toàn các trường chưa biết. Vì backup chủ động lọc credential và extension lạ, backup không phải bản sao byte-for-byte của toàn bộ file chính; khi phục hồi cần đối chiếu extension riêng với người phụ trách. Không đặt Google private key/access token vào JSON nghiên cứu; credential phải nằm ngoài public/Git theo kế hoạch adapter.
- Nếu không tạo được backup hoặc không xoay vòng được do quyền thư mục, lần lưu báo lỗi trước khi thay file chính. Nếu thay file chính thất bại sau khi tạo backup, backup cũ vẫn hợp lệ và nội dung chính không đổi.
- Thư mục phải riêng tư trên một máy chủ có ổ đĩa bền vững. Web root chỉ trỏ tới public/. Trên Windows cần ACL chỉ cho tài khoản ứng dụng/quản trị truy cập; chmod không thay thế ACL.

Quy trình phục hồi vận hành (chưa tự chạy trên dữ liệu thật):

1. Dừng thao tác ghi cấu hình, giữ bản file lỗi để kiểm tra trong vị trí riêng tư ngoài public/Git.
2. Chọn backup hợp lệ gần nhất; kiểm tra research, trường, gán phiếu và mapping, đối chiếu extension không nằm trong backup. Không phục hồi credential vào JSON này.
3. Trong thời gian bảo trì, thao tác phục hồi phải giữ cùng exclusive lock `.lock`, ghi file tạm cùng thư mục và rename; không ghi đè trực tiếp khi request khác còn đang chạy.
4. Mở lại trang quản lý, tải mới form, kiểm tra trang công khai và kết nối nguồn; không tiếp tục gửi form giữ revision cũ. Audit lịch sử không nằm trong backup đã lọc nên cần giữ file cũ riêng tư nếu phải tra cứu.

## Kiểm thử thực tế

```powershell
& C:/xampp/php/php.exe artisan test
# 80 passed (651 assertions), 6.37 giây

& C:/xampp/php/php.exe artisan route:list --path=admin/settings
# 8 route cài đặt, gồm edit/preview/save research và mapping riêng

& C:/xampp/php/php.exe artisan view:cache
# Blade templates cached successfully
```

`git diff --check` không có lỗi. Test mới ở `tests/Feature/AdminGoogle/ResearchSettingsTest.php`; worker cạnh tranh ở `tests/Fixtures/Google/settings-writer.php`. Tất cả dùng thư mục tạm riêng, không gọi Google hoặc DB, không sửa cấu hình thật.

Lỗi quyền ghi được kiểm tra bằng đích backup bị chặn và mô phỏng rename thất bại có kiểm soát. Chưa thay ACL thật của máy người dùng, chưa mô phỏng mất điện/ổ đĩa chết hoặc chạy trên nhiều instance. Cần kiểm tra quyền và atomic replace trên máy triển khai trong Sprint 4. UAT trực quan/keyboard còn chờ; test render không thay thế UAT.

Không tự commit, không deploy, không thay credential. Các thay đổi có sẵn của người dùng được giữ nguyên.
