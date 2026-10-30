# Sprint 2 — Mốc dừng để tiếp tục

Cập nhật: 30/10/2026, khoảng 10:29 giờ Việt Nam.

**Mốc dừng này đã được tiếp tục theo yêu cầu người dùng ngày 30/10/2026. Trạng thái mới xem [bàn giao Sprint 2](SPRINT_2_TRA_CUU_XUAT_DU_LIEU.md): code đã nối route/view, 63 test đạt; Google thật/UAT còn chờ.** Nội dung bên dưới giữ lại làm lịch sử trước lần tiếp tục, không phải danh sách việc còn thiếu hiện tại. Code nằm trong working tree, chưa commit; không reset/clean/stage tất cả thay đổi có sẵn.

## Bối cảnh đã xác nhận

- Sprint 0: nền tảng/fixture/hợp đồng có test; người phụ trách adapter Google Sheets API riêng tư **chưa làm adapter**, thời hạn chưa xác nhận. Không cần chờ adapter để phát triển UI bằng fake.
- Sprint 1: đã có trang kết nối và test; chưa nghiệm thu Google thật.
- Đọc yêu cầu Sprint 2 ở mục 6 và bảng S2-01…S2-06 trong `KE_HOACH_QUAN_LY_MUC_4_6.md`.
- Không DB/Redis; không lưu câu trả lời vào file/cache/log/session; giữ Laravel/Blade, routes/google.php. Không tự gửi Form thật hoặc công khai Sheet.

## Code Sprint 2 đã được ghi thành công

Các service mới hiện **chưa được nối vào route/controller/view**:

| File | Phần đã viết, còn cần test riêng |
|---|---|
| `app/Services/ResponseNormalizer.php` | Strict parse ngày theo format/timezone; ngày lỗi null + warning; answer key theo source/cột; nhận rowNumbers từ reader |
| `app/Services/ResponseQueryService.php` | Đọc nguồn qua interface; lỗi từng nguồn; giới hạn tổng rows/columns/bytes; lọc AND và sắp xếp với tie-break nguồn/dòng |
| `app/Services/ResponseReference.php` | Token Crypt có hạn 600 giây, source/row/revision/HMAC; không chứa câu trả lời; kiểm tra token giả/hết hạn |
| `app/Services/SurveyCsvExporter.php` | Stream trực tiếp php://output, BOM, fputcsv; bảo vệ ô/header công thức; cột nguồn và union key theo nguồn |

Đã sửa thành phần dùng chung:

- `ConnectionReadResult`: thêm `rowNumbers` và `payloadBytes`, đều không đưa vào metadata().
- `ConnectionSources`: suy ra header_row từ range A1 khi có hàng bắt đầu.
- `GoogleSheetsService`: CSV parser giữ số dòng, kể cả dòng trống; không mất vị trí do xuống dòng trong ô; dùng escape CSV rỗng; xử lý BOM riêng; từ chối header `_source_*`; giới hạn 10.000 hàng/100 cột; trả rowNumbers/payloadBytes.
- `config/research.php`: thêm `responses.max_rows=10000`, `max_columns=100`, `max_bytes=10 MiB`, `reference_ttl=600`; tăng `connections.max_bytes` từ 5 lên 10 MiB. Cần cập nhật tài liệu Sprint 1 đang ghi 5 MiB khi hoàn tất Sprint 2.

## Chưa được ghi / chưa triển khai

Một patch gộp controller/request/views bị từ chối vì chứa Delete + Add cùng đường dẫn `google_responses.blade.php`. **Không có phần nào của patch đó được áp dụng.** Đã kiểm tra: chưa có `AdminResponseController.php` và `ResponseFilterRequest.php`.

- `/admin/responses` và `/admin/responses/export` vẫn dùng `GoogleAdminController` cũ.
- Chưa có route detail, bộ lọc validation, phân trang hoặc giao diện mới.
- Chưa tạo `resources/views/admin/response_detail.blade.php` và `response_error.blade.php`.
- `ResponseQueryService::limit()` đang tham chiếu view `admin.response_error` **chưa tồn tại**; phải tạo view trước khi nối service vào route.
- Chưa chặn export cũ khi nguồn lỗi, chưa nối exporter mới; không coi export hiện hành đã an toàn.
- Chưa có test riêng Sprint 2 hoặc số đo 10.000 hàng × 100 cột.

## Kiểm chứng tại mốc dừng

Đã chạy sau phần code được ghi:

```powershell
& C:/xampp/php/php.exe artisan test
# 37 passed (289 assertions), khoảng 4.92 giây
```

Đây là suite hồi quy Sprint 0/1 và 4 test baseline, **không chứng minh service mới Sprint 2 đạt nghiệm thu**. `git diff --check` không báo lỗi. PHP chưa có trong PATH; dùng đường dẫn trên. Công cụ shell trong sandbox từng lỗi khởi tạo, các lệnh đã chạy bằng escalation được tự động duyệt.

## Thứ tự tiếp tục đề xuất

1. Đọc mốc dừng này, git status và các file mới; không làm lại Sprint 0/1.
2. Test S2-01 cho strict date, rowNumbers với range/dòng trống/multiline, nguồn chung, schema lỗi. Kiểm tra hợp đồng API tương lai phải cung cấp rowNumbers/payloadBytes chính xác.
3. Tạo ResponseFilterRequest: q≤200, enum trường/nhóm/nguồn, khoảng ngày hợp lệ (chỉ date_to cũng phải dùng được), sort/newest-oldest, page≥1, per_page 25/50/100. GET lỗi không flash input vào session. Export bỏ qua page/per_page. Nếu giữ alias date cũ phải kiểm tra khoảng ngày sau khi hợp nhất.
4. Tạo AdminResponseController với index/export/detail. Nối route giữ name cũ; detail đọc lại đúng nguồn, đối chiếu row/revision/HMAC, trả 409 khi dữ liệu đổi, 410 hết hạn, không lộ payload trên lỗi.
5. Tạo views bằng **Update File** với file đã tồn tại. Danh sách chỉ thời gian/trường/nhóm/nguồn/link chi tiết, giữ filter khi chuyển trang, phân biệt rỗng/không khớp/nguồn lỗi. Escape mọi dữ liệu, giữ xuống dòng chi tiết; private/no-store.
6. Export dùng cùng query/filter; chặn khi bất kỳ nguồn trong phạm vi lỗi; cho chọn nguồn khỏe. Tải lại khi xuất, stream toàn kết quả. Rà formula injection cả header/ô có whitespace/tab/CR/LF và số âm chưa có numeric schema.
7. Bổ sung test AdminGoogle và điều chỉnh baseline hợp lý: test cũ assert nội dung `Tốt` trên danh sách sẽ cần chuyển sang detail vì danh sách mới không hiển thị toàn câu trả lời. StreamedResponse phải đọc streamedContent() trong test. Test Sprint 0 hiện mong export 200 dù fixture có nguồn 403: đổi thành kiểm tra chặn 503, rồi export riêng nguồn khỏe.
8. Test token sửa/hết hạn/revision/sắp xếp/xóa; quyền truy cập; query giả/ngày đảo; nhiều trang với export toàn bộ; giới hạn trước stream. Chặn DB/Redis/stray HTTP bằng GoogleTestCase.
9. Đo memory/time fixture 10.000 × 100; ghi số đo thực, không tự nhận đạt hiệu năng. Xem tổng union cột theo nguồn có thể vượt 100; giới hạn cần báo rõ, không cắt.
10. Cập nhật tiến độ S2-01…S2-06 và tài liệu bàn giao; chạy suite/route/view checks. Ghi Google API và UAT thật vẫn chưa thực hiện.

Lưu ý rà soát: nguồn quá 10 MiB hiện đi qua lỗi INVALID_PAYLOAD của ConnectionHttpClient, trong khi giới hạn tổng QueryService trả 413; cần thống nhất thông báo/HTTP và test. Dashboard summarize vẫn có logic ngày cũ; giữ tương thích hoặc bổ sung test hồi quy nếu chuyển sang chuẩn hóa chung.
