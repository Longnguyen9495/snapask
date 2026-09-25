<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadFileController extends Controller
{
    /**
     * Trả bộ cài cho một nền tảng.
     *
     * Hai nơi file có thể nằm, xét theo thứ tự:
     *
     *   1. `url` trong manifest — bản đã lên GitHub Releases hay CDN. Chuyển
     *      hướng sang đó, để băng thông không đi qua máy chủ này.
     *   2. đĩa `releases` — bản tự dựng, dùng lúc chưa có CDN.
     *
     * Chưa có bản nào cho nền tảng đó thì 404, chứ không trả về một trang trắng.
     */
    public function __invoke(string $platform): RedirectResponse|StreamedResponse
    {
        $build = config("snapask.releases.builds.{$platform}");

        abort_if($build === null || ($build['file'] ?? null) === null, 404);

        // Đếm lượt tải theo nền tảng. Ghi log thay vì thêm một bảng: con số này
        // chỉ để biết bản nào được dùng, không phải số liệu tính tiền.
        Log::info('snapask.download', ['platform' => $platform, 'version' => config('snapask.releases.version')]);

        if (filled($build['url'] ?? null)) {
            return redirect()->away($build['url']);
        }

        $disk = Storage::disk(config('snapask.releases.disk'));

        abort_unless($disk->exists($build['file']), 404);

        return $disk->download($build['file']);
    }
}
