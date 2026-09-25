<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /** Trần độ dài ô tìm kiếm: đủ cho một cụm từ, không đủ để dựng câu LIKE nặng. */
    public const SEARCH_MAX_LENGTH = 100;

    /** Độ dài tối đa của tiêu đề khi người dùng tự đặt. */
    public const TITLE_MAX_LENGTH = 120;

    /** Các mốc thời gian bộ lọc hiểu được, tính bằng ngày. */
    public const PERIODS = ['7d' => 7, '30d' => 30, '90d' => 90];

    protected $fillable = [
        'user_id',
        'title',
        'image_path',
        'image_width',
        'image_height',
        'image_expires_at',
        'model',
    ];

    /**
     * Xoá hội thoại thì xoá luôn ảnh trên disk, dù đi đường web hay API.
     *
     * Người dùng vừa nói rõ không muốn giữ hội thoại này nữa; để ảnh nằm lại
     * chờ `snapask:prune` là đi ngược ý họ.
     */
    protected static function booted(): void
    {
        static::deleting(function (Conversation $conversation): void {
            if ($conversation->image_path !== null) {
                Storage::disk(config('snapask.image.disk'))->delete($conversation->image_path);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'image_expires_at' => 'datetime',
            'image_width' => 'integer',
            'image_height' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Hội thoại này bắt đầu từ một ảnh chụp, kể cả khi ảnh đã bị dọn. */
    public function startedWithImage(): bool
    {
        return $this->image_path !== null || $this->image_width !== null;
    }

    /** Ảnh còn trong hạn lưu trữ, chưa bị `snapask:prune` xoá. */
    public function imageIsRetained(): bool
    {
        return $this->image_path !== null
            && ($this->image_expires_at === null || $this->image_expires_at->isFuture());
    }

    /**
     * Xếp theo lần hoạt động gần nhất.
     *
     * Mỗi tin nhắn mới chạm vào `updated_at` của hội thoại (xem Message::$touches),
     * nên hội thoại vừa hỏi tiếp tự nổi lên đầu. `id` phá thế hoà để phân trang
     * không lặp hay sót khi hai hội thoại cùng một giây.
     *
     * @param  Builder<Conversation>  $query
     */
    #[Scope]
    protected function byActivity(Builder $query): void
    {
        $query->orderByDesc('updated_at')->orderByDesc('id');
    }

    /**
     * Kèm số tin nhắn và đoạn đầu của tin nhắn cuối, cho danh sách lịch sử.
     *
     * @param  Builder<Conversation>  $query
     */
    #[Scope]
    protected function withListing(Builder $query): void
    {
        $query->withCount('messages')->addSelect([
            'last_message_content' => Message::query()
                ->select('content')
                ->whereColumn('conversation_id', 'conversations.id')
                ->latest('id')
                ->limit(1),
        ]);
    }

    /**
     * Tìm theo tiêu đề và nội dung tin nhắn.
     *
     * Ký tự đại diện của LIKE được thoát, để người gõ "50%" tìm đúng chuỗi đó
     * chứ không khớp mọi thứ bắt đầu bằng 50.
     *
     * @param  Builder<Conversation>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $pattern = '%'.addcslashes(mb_substr($term, 0, self::SEARCH_MAX_LENGTH), '\\%_').'%';

        $query->where(function (Builder $query) use ($pattern): void {
            $query->whereRaw("title LIKE ? ESCAPE '\\'", [$pattern])
                ->orWhereHas('messages', fn (Builder $messages) => $messages->whereRaw("content LIKE ? ESCAPE '\\'", [$pattern]));
        });
    }

    /**
     * Bộ lọc của trang lịch sử: khoảng thời gian, có ảnh hay không, mô hình.
     *
     * Giá trị lạ bị bỏ qua thay vì báo lỗi: đây là tham số trên thanh địa chỉ,
     * sửa tay sai một chữ không đáng bị một trang lỗi.
     *
     * @param  Builder<Conversation>  $query
     * @param  array{period?: ?string, screenshot?: ?string, model?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $days = self::PERIODS[$filters['period'] ?? ''] ?? null;

        if ($days !== null) {
            $query->where('updated_at', '>=', now()->subDays($days));
        }

        match ($filters['screenshot'] ?? null) {
            'with' => $query->where(fn (Builder $q) => $q->whereNotNull('image_path')->orWhereNotNull('image_width')),
            'without' => $query->whereNull('image_path')->whereNull('image_width'),
            default => null,
        };

        if (filled($filters['model'] ?? null)) {
            $query->where('model', (string) $filters['model']);
        }
    }
}
