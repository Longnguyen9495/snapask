<?php

use App\Http\Controllers\Web\ConnectorPageController;
use App\Http\Controllers\Web\ConversationPageController;
use App\Http\Controllers\Web\DownloadFileController;
use App\Http\Controllers\Web\DownloadPageController;
use App\Http\Controllers\Web\LandingController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\ProviderPageController;
use App\Http\Controllers\Web\RegisterPageController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

/*
 * Trang công khai.
 *
 * Ngôn ngữ nằm trong đường dẫn chứ không trong cookie: Google lập chỉ mục riêng
 * từng bản, và link chia sẻ đi đâu cũng giữ đúng thứ tiếng người gửi đang đọc.
 * Tiếng Việt không mang tiền tố vì đó là bản mặc định.
 */
Route::get('/', LandingController::class)->name('home');
Route::get('download', DownloadPageController::class)->name('download');

Route::prefix('en')->group(function (): void {
    Route::get('/', LandingController::class)->name('home.en');
    Route::get('download', DownloadPageController::class)->name('download.en');
});

/*
 * File cài đặt dùng chung một đường dẫn cho mọi ngôn ngữ: đây là file nhị phân,
 * không phải trang để đọc.
 */
Route::get('download/{platform}', DownloadFileController::class)
    ->whereIn('platform', ['windows', 'mac'])
    ->name('download.file');

/*
 * Đổi ngôn ngữ. POST vì mỗi lần bấm là ghi cookie và ghi vào tài khoản; dùng GET
 * thì một trình duyệt tải trước link là đổi luôn ngôn ngữ của người ta.
 */
Route::post('locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['vi', 'en'])
    ->name('locale.switch');

/*
 * Trang quản trị cho khách: nơi khai báo các dịch vụ mà AI được phép tra cứu.
 * Ứng dụng desktop chỉ chụp và hỏi, mọi việc cấu hình nằm ở đây.
 *
 * Không có tiền tố ngôn ngữ: những trang này sau lớp đăng nhập nên không cần
 * Google lập chỉ mục, và `users.locale` đã đủ để nhớ lựa chọn của từng người.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('login', [SessionController::class, 'create'])->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('register', [RegisterPageController::class, 'create'])->name('register');
    Route::post('register', [RegisterPageController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('providers', [ProviderPageController::class, 'index'])->name('web.providers.index');
    Route::post('providers', [ProviderPageController::class, 'store'])->name('web.providers.store');
    Route::put('providers/{provider}', [ProviderPageController::class, 'update'])->name('web.providers.update');
    Route::post('providers/{provider}/select', [ProviderPageController::class, 'select'])->name('web.providers.select');
    Route::post('providers/default', [ProviderPageController::class, 'useDefault'])->name('web.providers.default');
    Route::delete('providers/{provider}', [ProviderPageController::class, 'destroy'])->name('web.providers.destroy');

    /*
     * Lịch sử hội thoại. Ảnh đi qua controller chứ không nằm trong public/:
     * ảnh màn hình của khách hay chứa thứ nhạy cảm nên mỗi lần xem đều phải
     * qua cửa kiểm tra chủ sở hữu.
     */
    Route::get('conversations', [ConversationPageController::class, 'index'])->name('web.conversations.index');
    Route::get('conversations/{conversation}', [ConversationPageController::class, 'show'])->name('web.conversations.show');
    Route::get('conversations/{conversation}/image', [ConversationPageController::class, 'image'])->name('web.conversations.image');
    Route::delete('conversations/{conversation}', [ConversationPageController::class, 'destroy'])->name('web.conversations.destroy');

    Route::get('connectors', [ConnectorPageController::class, 'index'])->name('web.connectors.index');
    Route::post('connectors', [ConnectorPageController::class, 'store'])->name('web.connectors.store');
    Route::put('connectors/{connector}', [ConnectorPageController::class, 'update'])->name('web.connectors.update');
    Route::post('connectors/{connector}/sync', [ConnectorPageController::class, 'resync'])->name('web.connectors.resync');
    Route::delete('connectors/{connector}', [ConnectorPageController::class, 'destroy'])->name('web.connectors.destroy');
});
