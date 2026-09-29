<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SessionHandoffController extends Controller
{
    /** Đủ để app mở cửa sổ và tải trang, không đủ để ai nhặt được link mà dùng. */
    public const TTL_SECONDS = 60;

    /** Khoá cache băm từ token, để kho cache không giữ nguyên văn thứ dùng để đăng nhập. */
    public static function cacheKey(string $token): string
    {
        return 'web-handoff:'.hash('sha256', $token);
    }

    /** Mở link do app xin ở WebSessionController: đăng nhập rồi đưa tới trang cần xem. */
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        // `pull` đọc và xoá trong một bước: link chỉ mở được đúng một lần.
        $handoff = Cache::pull(self::cacheKey($token));
        $user = $handoff ? User::find($handoff['user_id']) : null;

        if (! $user) {
            return redirect()->route('login');
        }

        // Cửa sổ có thể đang giữ phiên của một tài khoản khác từ lần trước;
        // bỏ hẳn phiên đó thay vì trộn hai người vào một phiên.
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to(url($handoff['path']));
    }
}
