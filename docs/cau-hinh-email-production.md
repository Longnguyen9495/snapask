# Bật gửi email trên production

> **Đã cấu hình xong ngày 26/09/2026.** Cả thư xác thực lẫn thư đặt lại mật
> khẩu đã gửi thật từ production, không lỗi SMTP. Phần dưới giữ lại để tra khi
> dựng máy chủ mới hoặc khi thư ngừng tới.

Trạng thái trước khi sửa:

| Biến | Trước khi sửa | Đã đặt thành |
|---|---|---|
| `MAIL_MAILER` | `log` | `smtp` |
| `MAIL_SCHEME` | *(thiếu)* | `smtps` |
| `MAIL_HOST` | *(thiếu)* | `smtp.gmail.com` |
| `MAIL_PORT` | *(thiếu)* | `465` |
| `MAIL_USERNAME` | *(thiếu)* | địa chỉ gửi |
| `MAIL_PASSWORD` | *(thiếu)* | app password 16 ký tự |
| `MAIL_FROM_ADDRESS` | có | trùng `MAIL_USERNAME` |
| `SNAPASK_VERIFY_EMAIL` | *(thiếu)* | `true` |

`MAIL_MAILER=log` chính là lý do thư không tới: Laravel vẫn dựng thư đầy đủ rồi
ghi vào `storage/logs/laravel.log` thay vì gửi đi.

`APP_URL` đã là `https://snapask.221-121-1-68.sslip.io`, nên link trong thư sẽ
mở đúng. `.env` đang `640 rexllm:www-data` — giữ nguyên quyền đó sau khi sửa.

## Các bước

Bản sao lưu trước khi sửa: `.env.bak-20260925-184351`, cùng thư mục với `.env`.

1. Mở `.env` bằng đúng user, không dùng root:

```bash
sudo -u rexllm nano /var/www/snapask-app/server/.env
```

2. Sửa `MAIL_MAILER=log` thành `smtp`, `MAIL_FROM_ADDRESS` thành địa chỉ gửi,
   rồi thêm các dòng còn thiếu:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=<địa chỉ gửi>
MAIL_PASSWORD="<app password 16 ký tự>"
MAIL_FROM_ADDRESS=<trùng MAIL_USERNAME>
MAIL_FROM_NAME="SnapAsk"

SNAPASK_VERIFY_EMAIL=true
```

3. Nạp lại config — **bỏ bước này thì giá trị cũ vẫn có hiệu lực**:

```bash
cd /var/www/snapask-app/server
sudo -u rexllm php artisan config:cache
```

4. Kiểm tra cấu hình đã ăn:

```bash
sudo -u rexllm bash docs/chan-doan-email.sh
```

5. Gửi thử một lá thư thật:

```bash
cd /var/www/snapask-app/server
sudo -u rexllm php artisan tinker --execute='
  $u = App\Models\User::where("email", "kenzjkudo@gmail.com")->first();
  if (! $u) { echo "khong co tai khoan nay tren production" . PHP_EOL; exit; }
  $u->sendEmailVerificationNotification();
  echo "da gui toi " . $u->email . PHP_EOL;
'
```

Nếu tài khoản chưa tồn tại trên production thì đăng ký mới tại
`https://snapask.221-121-1-68.sslip.io/register` — thư sẽ tự gửi.

## Khi vẫn không tới

- `Failed to authenticate` trong `storage/logs/laravel.log` → `MAIL_PASSWORD`
  không phải App Password, hoặc đã bị thu hồi.
- Thư vào spam → `MAIL_FROM_ADDRESS` khác `MAIL_USERNAME`.
- Không có dòng lỗi nào và cũng không có thư → gần như chắc là quên
  `config:cache` ở bước 3.

## Về app password

App password dùng trong lượt trao đổi trước là của hộp thư cá nhân
`kenzjkudo@gmail.com` và đã đi qua lịch sử chat lẫn `.env` trên máy dev. Nên thu
hồi nó trong phần bảo mật tài khoản Google và cấp một app password riêng cho
production, tốt nhất gắn với một địa chỉ gửi riêng thay vì hộp thư cá nhân.
