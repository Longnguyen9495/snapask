<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountPageController extends Controller
{
    /** Thông tin tài khoản, gói và hạn mức của người đang đăng nhập. */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'quota' => $user->quotaSummary(),
            'quotaResetsAt' => now()->startOfMonth()->addMonth(),
            'setup' => $user->activeSetup(),
            'desktopSessions' => $user->tokens()->count(),
        ]);
    }
}
