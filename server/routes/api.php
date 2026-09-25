<?php

use App\Http\Controllers\Api\AskController;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\ConnectorController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/*
 * Ứng dụng desktop nói chuyện với máy chủ qua đúng các tuyến này. Khoá của nhà
 * cung cấp mô hình không bao giờ rời khỏi máy chủ: gói Electron giải nén được
 * trong vài giây nên mọi khoá nhúng vào đó coi như đã công khai.
 */
Route::post('auth/register', RegisterController::class)
    ->middleware('throttle:5,1')
    ->name('auth.register');

Route::post('auth/token', [AuthTokenController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('auth.token.store');

// Đăng xuất nằm ngoài `verified`: ai cầm token cũng phải thu hồi được nó.
Route::delete('auth/token', [AuthTokenController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('auth.token.destroy');

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('me', function (Request $request) {
        return response()->json([
            // `locale` đi kèm để ứng dụng desktop mở lên đúng thứ tiếng người
            // dùng đã chọn, kể cả khi họ chọn trên trang web.
            'user' => $request->user()->profileSummary(),
            'quota' => $request->user()->quotaSummary(),
            // Thêm vào cho workspace 0.3.0; bản cũ bỏ qua trường lạ.
            'setup' => $request->user()->activeSetup(),
        ]);
    })->name('me');

    /*
     * Ghi lựa chọn ngôn ngữ từ ứng dụng desktop.
     *
     * Chỉ nhận đúng một trường: đây là chỗ để đổi ngôn ngữ, không phải một cửa
     * chung cho mọi thứ thuộc về hồ sơ người dùng.
     */
    Route::patch('me', function (Request $request) {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('snapask.locales')))],
        ]);

        $request->user()->forceFill($data)->save();

        return response()->json([
            'user' => $request->user()->profileSummary(),
            'quota' => $request->user()->quotaSummary(),
            'setup' => $request->user()->activeSetup(),
        ]);
    })->name('me.update');

    Route::post('ask', AskController::class)
        ->middleware('throttle:20,1')
        ->name('ask');

    Route::get('connectors', [ConnectorController::class, 'index'])->name('connectors.index');
    Route::post('connectors', [ConnectorController::class, 'store'])->name('connectors.store');
    Route::put('connectors/{connector}', [ConnectorController::class, 'update'])->name('connectors.update');
    Route::delete('connectors/{connector}', [ConnectorController::class, 'destroy'])->name('connectors.destroy');
    Route::post('connectors/{connector}/sync', [ConnectorController::class, 'resync'])->name('connectors.sync');

    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update');

    // Ảnh đi qua token Sanctum trong header, không bao giờ qua tham số URL: link
    // có token dễ lọt vào nhật ký máy chủ, lịch sử trình duyệt và ảnh chụp màn hình.
    Route::get('conversations/{conversation}/image', [ConversationController::class, 'image'])->name('conversations.image');
    Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
});
