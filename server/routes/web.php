<?php

use App\Http\Controllers\Web\ConnectorPageController;
use App\Http\Controllers\Web\ProviderPageController;
use App\Http\Controllers\Web\RegisterPageController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

/*
 * Trang quản trị cho khách: nơi khai báo các dịch vụ mà AI được phép tra cứu.
 * Ứng dụng desktop chỉ chụp và hỏi, mọi việc cấu hình nằm ở đây.
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

    Route::get('connectors', [ConnectorPageController::class, 'index'])->name('web.connectors.index');
    Route::post('connectors', [ConnectorPageController::class, 'store'])->name('web.connectors.store');
    Route::put('connectors/{connector}', [ConnectorPageController::class, 'update'])->name('web.connectors.update');
    Route::post('connectors/{connector}/sync', [ConnectorPageController::class, 'resync'])->name('web.connectors.resync');
    Route::delete('connectors/{connector}', [ConnectorPageController::class, 'destroy'])->name('web.connectors.destroy');
});

Route::get('/', fn () => redirect()->route('web.connectors.index'));
