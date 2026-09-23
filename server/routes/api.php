<?php

use App\Http\Controllers\Api\AskController;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\ConnectorController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->group(function (): void {
    Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');

    Route::get('me', function (Request $request) {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email']),
            'quota' => $request->user()->quotaSummary(),
        ]);
    })->name('me');

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
    Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
});
