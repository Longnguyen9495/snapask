# Triển khai SnapAsk lên VPS PageSeed

Cùng máy với Cái Tiệm Neo và PageSeed. Ba site chạy song song, mỗi site một vhost
riêng — sửa site này không được đụng vhost của site kia.

## Thông tin kết nối

| Thuộc tính | Giá trị |
|---|---|
| Máy chủ | `221.121.1.68` |
| Tài khoản | `root` |
| Private key trên máy Windows | `%USERPROFILE%\.ssh\pageseed_bizfly` |
| Hostname đã kiểm chứng | `Pageseed` |

```powershell
ssh -i "$env:USERPROFILE\.ssh\pageseed_bizfly" root@221.121.1.68
```

> Máy được dựng lại ngày 23/09/2026 nên host key SSH đã đổi một lần. Gặp cảnh báo
> `REMOTE HOST IDENTIFICATION HAS CHANGED` mà **không** phải do rebuild thì dừng
> lại và xác minh vân tay qua console của nhà cung cấp trước khi gỡ key cũ.

## Bản đồ triển khai

| Thành phần | Giá trị |
|---|---|
| Website | `https://snapask.221-121-1-68.sslip.io` |
| Thư mục project (git checkout + bản chạy) | `/var/www/snapask-app` |
| Web root | `/var/www/snapask-app/server/public` |
| SQLite | `/var/www/snapask-app/server/database/database.sqlite` |
| Nginx vhost | `/etc/nginx/sites-available/snapask` |
| PHP-FPM socket | `/run/php/php8.5-fpm.sock` |
| Scheduler | `snapask-scheduler.timer` (systemd) |
| Bộ cài desktop | `/var/www/snapask-app/server/storage/app/releases/` |
| Commit triển khai đầu tiên | `69d462f` |

### Khác Cái Tiệm Neo ở ba chỗ

1. **Laravel nằm trong `server/`**, không ở gốc repo — web root phải có đuôi
   `/server/public`. Thư mục `desktop/` cũng theo về nhưng không được phục vụ.
2. **Không cần build frontend.** CSS/JS là file tĩnh trong `public/`, không qua
   Vite, nên deploy không có bước `npm`.
3. **Có scheduler.** `snapask:prune` xoá ảnh chụp màn hình quá hạn — đây là cam
   kết riêng tư với người dùng, không phải việc tuỳ chọn.

## Scheduler

Máy này **không có cron daemon**, nên lịch chạy bằng systemd timer:

- `/etc/systemd/system/snapask-scheduler.service` — gọi `php artisan schedule:run`
- `/etc/systemd/system/snapask-scheduler.timer` — mỗi phút một lần

Laravel tự quyết định lệnh nào tới giờ; hiện chỉ có `snapask:prune` lúc 03:10.

```bash
systemctl status snapask-scheduler.timer
journalctl -u snapask-scheduler.service -n 20
```

## Quy trình deploy

Chỉ thực hiện sau khi người dùng phê duyệt và code đã được push.

1. Quality gate ở máy local: `php artisan test`, `./vendor/bin/pint --test`, và
   `npm run check` trong `desktop/` khi có đụng phần desktop.
2. Sao lưu SQLite trước khi có migration:

```bash
mkdir -p /var/backups/snapask
cp -p /var/www/snapask-app/server/database/database.sqlite \
      /var/backups/snapask/database-$(date +%Y%m%d-%H%M%S).sqlite
```

3. Pull thẳng trong thư mục đang chạy, bằng user `rexllm`:

```bash
sudo -u rexllm git -C /var/www/snapask-app pull --ff-only origin main
```

4. Trong `/var/www/snapask-app/server`, đều bằng `sudo -u rexllm`:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

5. `.env` **không** đi theo `git pull`. Mỗi biến mới phải thêm tay.
6. Quyền sau khi pull, nếu git có tạo file mới:

```bash
chown -R rexllm:www-data storage bootstrap/cache database
chmod 2770 database && chmod 660 database/database.sqlite
```

7. Không đụng Nginx khi chỉ deploy code — `root` đã cố định.
8. Xác minh `/`, `/en`, `/login`, `/dashboard` (guest phải 302), `/api/me` (401),
   rồi kiểm tra **caitiemneo và pageseed vẫn 200**.

## Xuất file Excel và PowerPoint

Bảng trong câu trả lời tải về được dưới ba dạng: CSV (PHP thuần), XLSX
(`phpoffice/phpspreadsheet`) và PPTX (`phpoffice/phppresentation`).

Hai gói này **bắt buộc cần `ext-gd`**. Máy chủ có sẵn; máy dev chạy PHP không có
gd thì `composer install` sẽ dừng. Khi đó thêm cờ:

```bash
composer install --ignore-platform-req=ext-gd
```

Cờ này chỉ dành cho máy dev. **Không dùng trên máy chủ** — ở đó thiếu gd là dấu
hiệu môi trường sai, không phải thứ để bỏ qua.

> `phppresentation` 0.9.0 phun ra nhiều cảnh báo `Deprecated` trên PHP 8.5 vì
> chưa cập nhật cú pháp nullable. `LOG_DEPRECATIONS_CHANNEL=null` (mặc định) giữ
> chúng khỏi log. Nếu sau này bật ghi deprecation, nhớ lọc gói này ra, và kiểm
> lại trước khi nâng lên PHP 8.6.

## Phát hành bản desktop

Trang `/download` và cơ chế tự cập nhật đọc từ `storage/app/releases/`. Mỗi lần
phát hành phải chép đủ **cả cụm**:

- `SnapAsk-Setup-<ver>-win-x64.exe` và `.blockmap` của nó
- `SnapAsk-Setup-<ver>-mac-universal.dmg` và `.blockmap`
- `latest.yml` (Windows) và `latest-mac.yml` (macOS)

Thiếu `latest*.yml` thì tải tay vẫn được nhưng **app đang chạy không bao giờ thấy
bản mới**. Sau khi chép, khai trong `.env`:

```dotenv
SNAPASK_RELEASE_VERSION=0.3.0
SNAPASK_RELEASE_DATE=2026-09-25
SNAPASK_RELEASE_WIN_FILE=SnapAsk-Setup-0.3.0-win-x64.exe
SNAPASK_RELEASE_WIN_SIZE=<bytes>
SNAPASK_RELEASE_WIN_SHA256=<sha256>
```

Rồi chạy lại `php artisan config:cache` — sửa `.env` mà quên bước này thì cấu
hình cũ vẫn có hiệu lực.

## Nhà cung cấp mô hình

`.env` production trỏ `https://rexllm.xyz/v1` với `claude-sonnet-5`, dùng chung
endpoint với Cái Tiệm Neo. Khách nào tự cắm khoá riêng ở `/providers` thì không
bị hạn mức gói chặn.

## Kiểm tra nhanh, read-only

```bash
ssh -i "$env:USERPROFILE\.ssh\pageseed_bizfly" -o BatchMode=yes root@221.121.1.68 \
  "systemctl is-active nginx php8.5-fpm snapask-scheduler.timer; \
   for h in snapask caitiemneo; do curl -sS -o /dev/null -w \"\$h=%{http_code}\n\" https://\$h.221-121-1-68.sslip.io/; done; \
   curl -sS -o /dev/null -w 'pageseed=%{http_code}\n' https://221-121-1-68.sslip.io/"
```

## Lưu ý an toàn

- Mọi lệnh `git`, `composer`, `artisan` chạy bằng `sudo -u rexllm`. Chạy bằng
  root sẽ để lại file root-owned làm hỏng lần deploy sau.
- `.env` phải là `640 rexllm:www-data`; thư mục `database` phải `2770` vì SQLite
  cần ghi được cả file lẫn thư mục cha.
- Không commit `.env`, SQLite, `vendor`, `node_modules` hay bộ cài.
- Không đưa private key, mật khẩu hay token vào repo, log hoặc hội thoại.
