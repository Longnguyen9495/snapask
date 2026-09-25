<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /** Trang "kiểm tra hộp thư", nơi middleware `verified` đẩy người chưa xác thực về. */
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    /**
     * Link trong email trỏ về đây.
     *
     * `EmailVerificationRequest` đã đối chiếu id và mã băm email với người đang
     * đăng nhập, nên một link lọt sang tay người khác không xác thực hộ được ai.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('dashboard')
            ->with('status', __('Email verified. Welcome to SnapAsk.'));
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('We sent a new link to :email.', ['email' => $request->user()->email]));
    }
}
