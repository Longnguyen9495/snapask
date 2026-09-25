<?php

namespace App\Models;

use Database\Factories\McpConnectorFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['auth_token'])]
class McpConnector extends Model
{
    /** @use HasFactory<McpConnectorFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'url',
        'auth_token',
        'enabled',
        'tools',
        'synced_at',
        'last_error',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'tools' => 'array',
            'synced_at' => 'datetime',
            // Token của khách không bao giờ nằm dạng trần trong bảng.
            'auth_token' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Connector chỉ được đem ra dùng khi đang bật và đã đọc được công cụ.
     */
    public function isUsable(): bool
    {
        return $this->enabled && filled($this->tools);
    }

    /**
     * Trạng thái để hiện lên trang quản lý, theo lần đồng bộ gần nhất.
     *
     * Không gọi thử dịch vụ lúc tải trang: một dịch vụ chậm sẽ kéo cả trang
     * chậm theo. Muốn biết tình hình mới nhất thì bấm đồng bộ lại.
     *
     * @return 'disabled'|'error'|'ok'|'pending'
     */
    public function healthStatus(): string
    {
        return match (true) {
            ! $this->enabled => 'disabled',
            $this->last_error !== null => 'error',
            filled($this->tools) => 'ok',
            default => 'pending',
        };
    }
}
