<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DownloadPageController extends Controller
{
    /** Trang tải về, liệt kê từng nền tảng kèm dung lượng và mã băm. */
    public function __invoke(): View
    {
        return view('download', [
            'release' => config('snapask.releases'),
        ]);
    }
}
