<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsPageController extends Controller
{
    /**
     * Cài đặt dùng chung cho web và desktop: ngôn ngữ, chính sách lưu trữ và
     * kênh cập nhật.
     *
     * Không đưa cài đặt riêng của ứng dụng desktop (phím tắt, địa chỉ máy chủ)
     * lên đây: chúng thuộc về từng máy, không theo tài khoản.
     */
    public function __invoke(Request $request): View
    {
        return view('settings.index', [
            'user' => $request->user(),
            'locales' => config('snapask.locales'),
            'retentionDays' => (int) config('snapask.image.retention_days'),
            'release' => config('snapask.releases'),
        ]);
    }
}
