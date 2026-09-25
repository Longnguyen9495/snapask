<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * Mỗi tin nhắn mới đẩy `updated_at` của hội thoại lên, để lịch sử xếp theo
     * lần hoạt động gần nhất chứ không theo lúc tạo.
     *
     * @var array<int, string>
     */
    protected $touches = ['conversation'];

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tools_used',
        'prompt_tokens',
        'completion_tokens',
        'latency_ms',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tools_used' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
