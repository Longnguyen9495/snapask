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
}
