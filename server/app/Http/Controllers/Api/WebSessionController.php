<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\SessionHandoffController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebSessionController extends Controller
{
    /**
     * Đổi token của app lấy một link đăng nhập web dùng một lần.
     *
     * App mở trang quản lý trong cửa sổ riêng của nó; không có bước này thì
     * người dùng phải gõ lại mật khẩu dù app đã đăng nhập sẵn. Link chỉ sống
     * vài chục giây và bị xoá ngay lần mở đầu tiên, nên lọt vào lịch sử hay
     * nhật ký proxy cũng không dùng lại được.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Chỉ đường dẫn tương đối trong chính trang này: không có `//`,
            // không có `..`, để link không thành cửa chuyển hướng ra ngoài.
            'path' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:[\/_-][a-z0-9]+)*$/'],
        ]);

        $token = Str::random(64);

        Cache::put(SessionHandoffController::cacheKey($token), [
            'user_id' => $request->user()->id,
            'path' => $validated['path'] ?? 'dashboard',
        ], now()->addSeconds(SessionHandoffController::TTL_SECONDS));

        return response()->json([
            'url' => route('session.handoff', ['token' => $token]),
            'expires_in' => SessionHandoffController::TTL_SECONDS,
        ]);
    }
}
