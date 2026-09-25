<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'plan', 'monthly_ask_limit', 'active_provider_id', 'active_model', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'monthly_ask_limit' => 'integer',
        ];
    }

    /** @return BelongsTo<ModelProvider, $this> */
    public function activeProvider(): BelongsTo
    {
        return $this->belongsTo(ModelProvider::class, 'active_provider_id');
    }

    /** @return HasMany<ModelProvider, $this> */
    public function modelProviders(): HasMany
    {
        return $this->hasMany(ModelProvider::class);
    }

    /** @return HasMany<McpConnector, $this> */
    public function mcpConnectors(): HasMany
    {
        return $this->hasMany(McpConnector::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Khách dùng nhà cung cấp của chính mình thì tự trả tiền token, nên không
     * bị chặn theo hạn mức gói.
     */
    public function bringsOwnProviderKey(): bool
    {
        return $this->active_provider_id !== null;
    }

    /** Số lượt hỏi đã dùng trong tháng dương lịch hiện tại. */
    public function asksUsedThisMonth(): int
    {
        return Message::query()
            ->where('role', 'user')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->whereIn('conversation_id', $this->conversations()->select('id'))
            ->count();
    }

    public function hasAsksRemaining(): bool
    {
        return $this->bringsOwnProviderKey() || $this->asksUsedThisMonth() < $this->monthly_ask_limit;
    }

    /** @return array{plan: string, limit: int, used: int, remaining: int, own_key: bool} */
    public function quotaSummary(): array
    {
        $used = $this->asksUsedThisMonth();

        return [
            'plan' => $this->plan,
            'limit' => $this->monthly_ask_limit,
            'used' => $used,
            'remaining' => max(0, $this->monthly_ask_limit - $used),
            'own_key' => $this->bringsOwnProviderKey(),
        ];
    }
}
