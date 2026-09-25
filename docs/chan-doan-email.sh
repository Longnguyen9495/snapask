#!/usr/bin/env bash
# Chẩn đoán vì sao thư xác thực / đặt lại mật khẩu không tới.
# Chạy trên máy chủ, trong /var/www/snapask-app/server, bằng user rexllm.
#
#   sudo -u rexllm bash docs/chan-doan-email.sh
#
# Chỉ đọc, không sửa gì. In ra cấu hình đang thực sự có hiệu lực.
set -u

cd "$(dirname "$0")/../server" || exit 1

echo "=== 1. Cấu hình mail đang có hiệu lực (sau config:cache) ==="
php artisan tinker --execute='
  echo "APP_ENV      = " . config("app.env") . PHP_EOL;
  echo "APP_URL      = " . config("app.url") . PHP_EOL;
  echo "mailer       = " . config("mail.default") . PHP_EOL;
  echo "host         = " . config("mail.mailers.smtp.host") . PHP_EOL;
  echo "port         = " . config("mail.mailers.smtp.port") . PHP_EOL;
  echo "username     = " . config("mail.mailers.smtp.username") . PHP_EOL;
  echo "password     = " . (config("mail.mailers.smtp.password") ? "[đã khai]" : "[TRỐNG]") . PHP_EOL;
  echo "from         = " . config("mail.from.address") . PHP_EOL;
  echo "verify_email = " . var_export(config("snapask.verify_email"), true) . PHP_EOL;
'

echo
echo "=== 2. Kết luận nhanh ==="
php artisan tinker --execute='
  $loi = [];
  if (config("mail.default") === "log") {
      $loi[] = "MAIL_MAILER vẫn là log — thư chỉ ghi vào storage/logs, không gửi đi đâu cả.";
  }
  if (! config("mail.mailers.smtp.password") && config("mail.default") === "smtp") {
      $loi[] = "MAIL_PASSWORD trống — SMTP sẽ bị Gmail từ chối.";
  }
  if (! config("snapask.verify_email")) {
      $loi[] = "snapask.verify_email = false — đăng ký xong KHÔNG gửi thư xác thực nào.";
  }
  if (str_contains((string) config("app.url"), ".local")) {
      $loi[] = "APP_URL trỏ .local — thư có gửi được thì link trong thư cũng hỏng.";
  }
  echo $loi ? implode(PHP_EOL, array_map(fn($l) => "  [!] " . $l, $loi)) . PHP_EOL
            : "  Cấu hình trông đã đúng; xem mục 3 và 4." . PHP_EOL;
'

echo
echo "=== 3. Tài khoản kenzjkudo trên production ==="
php artisan tinker --execute='
  $u = App\Models\User::where("email", "kenzjkudo@gmail.com")->first();
  echo $u
      ? "có, id={$u->id}, email_verified_at=" . var_export($u->email_verified_at?->toDateTimeString(), true) . PHP_EOL
      : "KHÔNG có tài khoản nào — nghĩa là lần đăng ký đó chưa hề chạm tới máy chủ này." . PHP_EOL;
'

echo
echo "=== 4. Log lỗi gửi thư gần đây ==="
grep -iE "swift|symfony.*mailer|smtp|Failed to authenticate|Connection could not" \
     storage/logs/laravel.log 2>/dev/null | tail -15 \
  || echo "  (không thấy dòng nào liên quan tới mail)"
