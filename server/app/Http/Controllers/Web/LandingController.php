<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * Trang chủ quảng cáo.
     *
     * Cùng một controller phục vụ cả `/` lẫn `/en`: ngôn ngữ do middleware
     * SetLocale đọc từ tiền tố, nên ở đây không phải phân nhánh gì.
     */
    public function __invoke(): View
    {
        return view('landing', [
            'release' => config('snapask.releases'),
        ]);
    }
}
