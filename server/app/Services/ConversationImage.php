<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trả ảnh chụp của một hội thoại, dùng chung cho trang web và API desktop.
 *
 * Ảnh màn hình của khách hay chứa thứ nhạy cảm, nên:
 *   - chỉ trả khi còn trong hạn lưu trữ và file còn trên disk, bằng không 404
 *     để giao diện nói "ảnh đã bị xoá" thay vì một khung ảnh vỡ;
 *   - cấm mọi bộ đệm giữ lại bản sao (`no-store`);
 *   - hiển thị tại chỗ, không ép tải về, và không cho trình duyệt đoán lại MIME.
 *
 * Kiểm tra chủ sở hữu là việc của controller gọi tới đây.
 */
class ConversationImage
{
    public function response(Conversation $conversation): StreamedResponse
    {
        abort_unless($conversation->imageIsRetained(), 404);

        $disk = Storage::disk(config('snapask.image.disk'));

        abort_unless($disk->exists($conversation->image_path), 404);

        return $disk->response(
            $conversation->image_path,
            'snapask-'.$conversation->id.'.'.pathinfo($conversation->image_path, PATHINFO_EXTENSION),
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
