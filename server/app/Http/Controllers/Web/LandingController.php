<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DemoScenes;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * Trang chủ quảng cáo.
     *
     * Cùng một controller phục vụ cả `/` lẫn `/en`: ngôn ngữ do middleware
     * SetLocale đọc từ tiền tố, nên ở đây không phải phân nhánh gì.
     */
    public function __invoke(DemoScenes $scenes): View
    {
        return view('landing', [
            'release' => config('snapask.releases'),
            'scenes' => $scenes->all(),
            // Tắt bản dùng thử thì phần đó biến mất khỏi trang, chứ không để
            // khách bấm vào một nút chỉ trả về lỗi.
            'demoLive' => (bool) config('snapask.demo.enabled'),
        ]);
    }
}
