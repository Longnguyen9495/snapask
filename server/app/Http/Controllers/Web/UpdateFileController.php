<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UpdateFileController extends Controller
{
    /**
     * Phục vụ manifest và gói cập nhật cho electron-updater.
     *
     * Chỉ cho đọc tên file ngay trong thư mục release, không chấp nhận đường dẫn
     * con để tránh directory traversal. Các file hợp lệ do electron-builder tạo.
     */
    public function __invoke(string $file): StreamedResponse
    {
        abort_if($file !== basename($file), 404);
        abort_unless(preg_match('/\A(?:latest(?:-mac)?\.yml|SnapAsk-Setup-[A-Za-z0-9._-]+(?:\.exe|\.dmg|\.zip|\.blockmap))\z/', $file) === 1, 404);

        $disk = Storage::disk(config('snapask.releases.disk'));

        abort_unless($disk->exists($file), 404);

        return $disk->download($file, $file, [
            'Cache-Control' => str_starts_with($file, 'latest')
                ? 'no-cache, no-store, must-revalidate'
                : 'public, max-age=31536000, immutable',
        ]);
    }
}
