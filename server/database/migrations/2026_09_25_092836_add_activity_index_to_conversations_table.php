<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lịch sử hội thoại xếp theo lần hoạt động gần nhất, tức `updated_at`.
     *
     * Trước bản này thêm tin nhắn không chạm vào hội thoại, nên `updated_at` cũ
     * có thể đứng yên từ lượt đầu. Kéo nó lên bằng thời điểm tin nhắn cuối để
     * danh sách đúng thứ tự ngay sau khi triển khai — chỉ tiến lên, không xoá gì.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->index(['user_id', 'updated_at']);
        });

        DB::table('conversations')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('messages')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->whereColumn('messages.created_at', '>', 'conversations.updated_at'))
            ->update([
                'updated_at' => DB::raw('(select max(messages.created_at) from messages where messages.conversation_id = conversations.id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'updated_at']);
        });
    }
};
