<?php

use App\Http\Controllers\Web\AccountPageController;
use App\Http\Controllers\Web\ConnectorPageController;
use App\Http\Controllers\Web\ConversationPageController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DownloadFileController;
use App\Http\Controllers\Web\DownloadPageController;
use App\Http\Controllers\Web\EmailVerificationController;
use App\Http\Controllers\Web\LandingController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\ProviderPageController;
use App\Http\Controllers\Web\RegisterPageController;
use App\Http\Controllers\Web\SessionController;
use App\Http\Controllers\Web\SettingsPageController;
use App\Http\Controllers\Web\UpdateFileController;
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

// Kênh generic cho electron-updater: manifest latest*.yml và gói cài đặt đi
// chung release disk, nhưng có đường dẫn ổn định để app tự kiểm tra phiên bản.
Route::get('updates/{file}', UpdateFileController::class)
    ->where('file', '[A-Za-z0-9._-]+')
    ->name('updates.file');

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

    /*
     * Xác thực email. Nằm ngoài nhóm `verified` bên dưới, vì đây chính là chỗ
     * người chưa xác thực được đẩy về.
     */
    Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    /*
     * Tổng quan là trang mặc định sau đăng nhập. Tên `dashboard` cũng là chỗ
     * middleware `guest` đẩy người đã đăng nhập về khi họ mở lại /login.
     */
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('account', AccountPageController::class)->name('web.account');
    Route::get('settings', SettingsPageController::class)->name('web.settings');

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
    Route::patch('conversations/{conversation}', [ConversationPageController::class, 'update'])->name('web.conversations.update');
    Route::delete('conversations/{conversation}', [ConversationPageController::class, 'destroy'])->name('web.conversations.destroy');

    /*
     * Thêm, sửa và đồng bộ đều gọi ra dịch vụ của khách, nên chịu chung một
     * trần tần suất: bấm liên tục không biến máy chủ này thành công cụ dội
     * request vào địa chỉ người khác.
     */
    Route::get('connectors', [ConnectorPageController::class, 'index'])->name('web.connectors.index');
    Route::post('connectors', [ConnectorPageController::class, 'store'])->middleware('throttle:20,1')->name('web.connectors.store');
    Route::put('connectors/{connector}', [ConnectorPageController::class, 'update'])->middleware('throttle:20,1')->name('web.connectors.update');
    Route::post('connectors/{connector}/sync', [ConnectorPageController::class, 'resync'])->middleware('throttle:20,1')->name('web.connectors.resync');
    Route::post('connectors/{connector}/toggle', [ConnectorPageController::class, 'toggle'])->name('web.connectors.toggle');
    Route::delete('connectors/{connector}', [ConnectorPageController::class, 'destroy'])->name('web.connectors.destroy');
});
