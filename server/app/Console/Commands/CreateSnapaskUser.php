<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSnapaskUser extends Command
{
    protected $signature = 'snapask:user
        {email : Email đăng nhập}
        {--name= : Tên hiển thị}
        {--password= : Mật khẩu; bỏ trống thì nhập kín ở bước sau}
        {--plan=free : Tên gói}
        {--limit=50 : Số lượt hỏi mỗi tháng}';

    protected $description = 'Tạo tài khoản đăng nhập cho ứng dụng desktop.';

    /**
     * Chưa có trang đăng ký công khai, nên tài khoản được mở bằng lệnh này.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?: $this->secret('Mật khẩu'));

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->option('name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => $password,
            'plan' => (string) $this->option('plan'),
            'monthly_ask_limit' => (int) $this->option('limit'),
        ]);

        $this->info("Đã tạo tài khoản #{$user->id} ({$user->email}), gói {$user->plan}, {$user->monthly_ask_limit} lượt/tháng.");

        return self::SUCCESS;
    }
}
