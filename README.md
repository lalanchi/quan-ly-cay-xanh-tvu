# Quản lý cây xanh Đại học Trà Vinh

Ứng dụng PHP 8.3 và Apache chạy bằng Docker Compose, kết nối tới database MySQL có sẵn trong XAMPP. Ứng dụng không tự tạo database hoặc thêm dữ liệu mẫu khi khởi động.

## Khởi chạy

1. Mở XAMPP Control Panel và khởi động **MySQL**.
2. Mở phpMyAdmin của XAMPP tại <http://localhost/phpmyadmin> và xác nhận database `cayxanh_db` cùng các bảng/dữ liệu cần thiết đã có.
3. Trong thư mục dự án, sao chép `.env.example` thành `.env`. Chỉnh `DB_USER`, `DB_PASSWORD` nếu tài khoản MySQL XAMPP của bạn không phải `root` không mật khẩu; chỉnh `DB_PORT` nếu MySQL dùng cổng khác `3306`.
4. Mở Docker Desktop, sau đó chạy `docker compose up --build -d`.
5. Truy cập ứng dụng tại `http://localhost:<WEB_PORT>` và đăng nhập bằng tài khoản đã có trong database. `WEB_PORT` mặc định là `8080`; nếu cổng này đang được chương trình khác sử dụng, đổi `WEB_PORT` trong `.env` (máy phát triển hiện tại dùng `8081`).

Trong Docker Desktop trên Windows, `host.docker.internal` trỏ từ container về máy Windows đang chạy XAMPP. phpMyAdmin chỉ là giao diện quản lý; ứng dụng kết nối trực tiếp tới MySQL, không kết nối tới giao diện phpMyAdmin.

Database cần có schema tương thích với ứng dụng, gồm các bảng `nguoi_dung`, `cay_xanh`, `loai_cay`, `khu_vuc` và `lich_su_cham_soc`. Các thông tin kết nối được cấu hình trong `.env`; file này không đưa lên mã nguồn.

## Triển khai production

- Đặt `APP_ENV=production` và `SESSION_COOKIE_SECURE=true` trong môi trường chạy ứng dụng. Ứng dụng kiểm tra HTTPS trên từng request; nếu TLS kết thúc tại reverse proxy, cấu hình `TRUSTED_PROXIES` thành danh sách IP chính xác của proxy (phân tách bằng dấu phẩy). Proxy phải ghi đè `X-Forwarded-Proto` do client gửi, không chuyển tiếp nguyên giá trị do client tự đặt.
- Dùng tài khoản MySQL riêng, không dùng `root`, đặt mật khẩu mạnh và chỉ cấp quyền cần thiết trên database `cayxanh_db`.
- Production bắt buộc cấu hình rõ `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` và `DB_PORT` trong secret/environment của nền tảng triển khai; không đưa thông tin thật vào `.env.example` hoặc mã nguồn.
- Production tắt hiển thị lỗi PHP ra trình duyệt và yêu cầu HTTPS. Hãy xem log của PHP/Apache để chẩn đoán lỗi kết nối hoặc lỗi ứng dụng; không bật `display_errors` trên website công khai.
- Phiên đăng nhập tự hết hạn sau 30 phút không hoạt động hoặc tối đa 8 giờ. ID phiên được đổi sau đăng nhập; tránh đổi ID theo chu kỳ trong lúc người dùng đang thao tác vì có thể làm mất phiên trên các request đồng thời.
- Docker chỉ mở cổng web trên `127.0.0.1` để reverse proxy HTTPS trên máy chủ có thể chuyển tiếp tới ứng dụng mà không công khai trực tiếp cổng ứng dụng. Cấu hình proxy giới hạn kích thước request, timeout hợp lý và giới hạn tốc độ đăng nhập; nếu chạy nhiều bản sao, dùng session store dùng chung thay vì session file cục bộ.
- Bật sao lưu database tự động, kiểm tra khôi phục định kỳ và chỉ cho phép kết nối MySQL từ máy chủ ứng dụng hoặc mạng riêng.

## Chức năng và quyền

- Cả hai vai trò có thể xem danh sách cây, bản đồ, thống kê và ghi lịch sử chăm sóc.
- Chỉ quản trị viên được thêm, sửa hoặc xóa hồ sơ cây.
- Khi có mặt tại vị trí cây, quản trị viên có thể dùng nút **Lấy vị trí hiện tại** trong biểu mẫu cây để ghi tọa độ thiết bị, hoặc chọn/kéo ghim trên bản đồ để chỉnh vị trí; cần cấp quyền vị trí cho trình duyệt và dùng HTTPS hoặc `localhost`. Độ chính xác tùy thiết bị/môi trường, nên cần kiểm tra kết quả trước khi lưu; không tự điền tọa độ ước lượng cho cây chưa đo.
- Mọi thay đổi dữ liệu qua biểu mẫu đều kiểm tra phiên đăng nhập, quyền truy cập và CSRF token.
- Bản đồ dùng OpenStreetMap để hiển thị đồng thời các vị trí cây có tọa độ và tự căn khung theo dữ liệu. Chọn cây trong danh sách để phóng to; trên biểu mẫu cây, có thể nhấn bản đồ hoặc kéo ghim để chọn tọa độ. Bản đồ cần kết nối Internet để tải thư viện và ô bản đồ. Tọa độ cần được đo và xác minh tại thực địa trước khi dùng làm dữ liệu chính thức.

## Tài khoản local

Các tài khoản thử nghiệm đã được tạo trong database MySQL local:

| Vai trò | Tên đăng nhập |
|---|---|
| Quản trị viên | `admin_tvu` |
| Nhân viên chăm sóc | `user_tvu` |

Mật khẩu ban đầu đã được cung cấp riêng trong cuộc trò chuyện, không lưu trong README hoặc Git. Hãy đổi mật khẩu trước khi dùng lâu dài; không sử dụng các tài khoản local này trên production.

## Dữ liệu minh họa trong database local

Database local có 12 hồ sơ nhân viên chăm sóc (`cs_demo_01` đến `cs_demo_12`) được phân công đều cho 360 cây (mỗi người phụ trách 30 cây), cùng 30 tài khoản demo bổ sung (`cs_demo_13` đến `cs_demo_42`) chưa được phân công cây. Mật khẩu ngẫu nhiên của 30 tài khoản mới chỉ được cung cấp trong cuộc trò chuyện, không lưu trong README hoặc Git; các tài khoản này chỉ dùng thử trên local. Số lịch chăm sóc hiện có được phân bổ khác nhau theo 6 mức: 60, 80, 100, 140, 160 hoặc 180 lần mỗi cây (60 cây ở mỗi mức), tổng cộng 43.200 lịch. Phần lớn lịch là dữ liệu minh họa được thêm để danh sách và lịch sử chăm sóc có nhiều dữ liệu.

Toàn bộ 360 hồ sơ cây local hiện có tọa độ để hiển thị trên bản đồ; trong đó 297 tọa độ được ước lượng theo khu vực và ghi rõ trong ghi chú, chưa đo GPS thực địa. Các vị trí còn lại cũng chưa được xác minh. Ngày trồng và lịch chăm sóc minh họa không phải dữ liệu đã xác minh ngoài thực tế. Hãy đo và xác nhận vị trí tại chỗ trước khi dùng làm dữ liệu chính thức. Những thay đổi này chỉ áp dụng cho database local hiện tại, không tự chạy khi tạo database mới và không nên sao chép sang production.

## Lệnh thường dùng

- Xem trạng thái: `docker compose ps`
- Xem log: `docker compose logs -f web`
- Dừng ứng dụng: `docker compose down`
- Khởi động lại: `docker compose up -d`

Với database cũ có cột mật khẩu ngắn hơn 255 ký tự, chạy `ALTER TABLE nguoi_dung MODIFY mat_khau VARCHAR(255) NOT NULL;` để hỗ trợ hash mật khẩu.

## Cấu trúc

```text
.
|-- .env.example
|-- Dockerfile
|-- docker-compose.yml
|-- README.md
`-- src/
    |-- care.php
    |-- config.php
    |-- footer.php
    |-- header.php
    |-- index.php
    |-- login.php
    |-- logout.php
    |-- map.php
    |-- stats.php
    |-- style.css
    |-- tree_delete.php
    |-- tree_form.php
    `-- trees.php
```

## Ghi chú triển khai

Không dùng tài khoản MySQL quyền cao hoặc để trống mật khẩu khi triển khai thật. Hãy cấu hình tài khoản có quyền tối thiểu cần thiết, bật HTTPS và không công khai file `.env`.
