<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneCapturedImages extends Command
{
    protected $signature = 'snapask:prune';

    protected $description = 'Xoá ảnh chụp màn hình đã quá hạn lưu trữ.';

    /**
     * Ảnh chụp màn hình của khách thường chứa dữ liệu nhạy cảm, nên chỉ giữ
     * trong thời hạn đã công bố. Lịch sử chữ vẫn được giữ lại.
     */
    public function handle(): int
    {
        $disk = Storage::disk(config('snapask.image.disk'));
        $removed = 0;

        Conversation::query()
            ->whereNotNull('image_path')
            ->where('image_expires_at', '<=', now())
            ->chunkById(200, function ($conversations) use ($disk, &$removed): void {
                foreach ($conversations as $conversation) {
                    $disk->delete($conversation->image_path);
                    // Dọn ảnh không phải hoạt động của người dùng: giữ nguyên
                    // `updated_at` để hội thoại không nhảy lên đầu lịch sử.
                    Conversation::withoutTimestamps(fn () => $conversation->update(['image_path' => null, 'image_expires_at' => null]));
                    $removed++;
                }
            });

        $this->info("Đã xoá {$removed} ảnh quá hạn.");

        return self::SUCCESS;
    }
}
