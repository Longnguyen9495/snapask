<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Một thông báo chung cho cả email sai lẫn mật khẩu sai, để không
            // biến biểu mẫu đăng nhập thành công cụ dò xem email nào có thật.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Phiên cũ có thể đã bị kẻ khác nhìn thấy trước lúc đăng nhập; cấp id
        // mới để một phiên bị cố định từ trước không dùng lại được.
        $request->session()->regenerate();

        return redirect()->intended(route('web.connectors.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
