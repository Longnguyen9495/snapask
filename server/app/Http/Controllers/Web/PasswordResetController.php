<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /** Biểu mẫu "quên mật khẩu": chỉ hỏi email. */
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Gửi link đặt lại mật khẩu.
     *
     * Email nào cũng nhận cùng một câu trả lời, kể cả khi không có tài khoản
     * nào mang địa chỉ đó: trả lời khác nhau là biến trang này thành công cụ
     * dò xem ai đã đăng ký. Riêng lỗi bị chặn vì gửi quá dày vẫn nói thật, vì
     * nó chỉ nói về chính người đang bấm.
     */
    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return back()->with('status', __('If :email has an account, a reset link is on its way.', [
            'email' => $request->string('email')->toString(),
        ]));
    }

    /**
     * Trang đặt mật khẩu mới.
     *
     * Token nằm trong đường dẫn còn email đi theo query, đúng như link trong
     * thư; cả hai được chuyển tiếp vào biểu mẫu dưới dạng input ẩn.
     */
    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    /** Nhận mật khẩu mới. */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'password.confirmed' => __('The two passwords do not match.'),
            'password.min' => __('Password needs at least 8 characters.'),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                $user->forceFill([
                    'password' => $request->string('password')->toString(),

                    // Đổi luôn remember_token: ai đó đang giữ cookie "nhớ tôi"
                    // của tài khoản này sẽ bị đá ra, và đó chính là lý do người
                    // dùng đặt lại mật khẩu.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __('Password changed. Sign in with the new one.'));
    }
}
