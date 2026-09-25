<?php

namespace App\Providers;

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
