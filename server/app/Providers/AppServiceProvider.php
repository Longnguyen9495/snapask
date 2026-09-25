<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->sharePublicUrls();
        $this->sharePortalShell();
        $this->registerAccountMail();
    }

    /**
     * Nội dung hai lá thư mà tài khoản nào cũng gặp: xác thực email và đặt lại
     * mật khẩu.
     *
     * Bản mặc định của Laravel chỉ có tiếng Anh và ký tên bằng APP_NAME. Người
     * dùng ở đây chọn ngôn ngữ cho tài khoản mình, và `User::preferredLocale()`
     * đã khiến Laravel dịch sẵn thư sang thứ tiếng đó trước khi dựng nội dung —
     * nên chỉ cần viết qua `__()` là thư tự đi đúng tiếng người nhận.
     */
    private function registerAccountMail(): void
    {
        VerifyEmail::toMailUsing(fn (User $user, string $url): MailMessage => (new MailMessage)
            ->subject(__('Confirm your SnapAsk email'))
            ->greeting($this->greeting($user))
            ->line(__('Confirm this address to finish setting up your SnapAsk account.'))
            ->action(__('Confirm email'), $url)
            ->line(__('The link expires in :minutes minutes.', [
                'minutes' => config('auth.verification.expire', 60),
            ]))
            ->line(__('If you did not create this account, you can ignore this email.'))
            ->salutation(__('Thanks, :app', ['app' => 'SnapAsk'])));

        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            // Đường dẫn tự dựng thay vì dùng mặc định: email phải đi theo link,
            // vì broker đối chiếu token với đúng địa chỉ đã yêu cầu.
            $url = URL::route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);

            return (new MailMessage)
                ->subject(__('Reset your SnapAsk password'))
                ->greeting($this->greeting($user))
                ->line(__('We received a request to reset the password for your SnapAsk account.'))
                ->action(__('Reset password'), $url)
                ->line(__('The link expires in :minutes minutes.', [
                    'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]))
                ->line(__('If you did not ask for this, ignore this email — your password stays as it is.'))
                ->salutation(__('Thanks, :app', ['app' => 'SnapAsk']));
        });
    }

    /** Lời chào mở đầu thư, có tên nếu tài khoản đã điền. */
    private function greeting(User $user): string
    {
        $name = trim((string) $user->name);

        return $name === ''
            ? __('Hello,')
            : __('Hello :name,', ['name' => $name]);
    }

    /**
     * Người dùng và hạn mức cho khung trang quản lý.
     *
     * Tính một lần cho layout thay vì để từng controller truyền vào: thanh trên
     * cùng và sidebar hiện ở mọi trang, quên truyền ở một trang là vỡ khung.
     */
    private function sharePortalShell(): void
    {
        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            $view->with([
                'shellUser' => $user,
                'shellQuota' => $user?->quotaSummary(),
            ]);
        });
    }

    /**
     * Đường dẫn mà mọi trang công khai đều cần.
     *
     * Gom vào một chỗ vì cả layout lẫn từng trang đều dựng link theo ngôn ngữ
     * đang xem; để mỗi controller tự tính thì sớm muộn cũng có chỗ quên.
     */
    private function sharePublicUrls(): void
    {
        // Cả layout lẫn view con: `@extends` dựng view con trước, nên chia sẻ
        // riêng cho layout thì trong thân trang các biến này chưa tồn tại.
        View::composer(['layouts.public', 'landing', 'download'], function ($view): void {
            $locale = app()->getLocale();

            $view->with([
                'homeUrl' => $locale === 'vi' ? route('home') : route('home.en'),
                'downloadUrl' => $locale === 'vi' ? route('download') : route('download.en'),

                // Bản song song của chính trang đang xem, cho thẻ hreflang.
                'alternates' => $this->alternates(),
            ]);
        });
    }

    /**
     * Địa chỉ của trang hiện tại trong từng thứ tiếng.
     *
     * @return array<string, string>
     */
    private function alternates(): array
    {
        $isDownload = request()->routeIs('download', 'download.en');

        return [
            'vi' => $isDownload ? route('download') : route('home'),
            'en' => $isDownload ? route('download.en') : route('home.en'),
        ];
    }
}
