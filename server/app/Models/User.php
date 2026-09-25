<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
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
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Đã xác thực email chưa.
     *
     * Tắt `snapask.verify_email` thì ai cũng tính là đã xác thực: Laravel cũng
     * dựa vào đây để quyết định có gửi email xác thực lúc đăng ký hay không, nên
     * máy dev không gửi thư nào và không ai bị chặn.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! config('snapask.verify_email') || $this->email_verified_at !== null;
    }

    /** Email gửi cho người dùng viết bằng thứ tiếng họ đã chọn. */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

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

    /** Hai chữ cái đầu cho avatar: chữ đầu của từ đầu và từ cuối trong tên. */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return mb_strtoupper(mb_substr((string) $this->email, 0, 1));
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * Hồ sơ gửi cho ứng dụng desktop và các trang quản lý.
     *
     * `email_verified` là trạng thái đã tính theo `snapask.verify_email`, không
     * phải cột thô: máy dev tắt xác thực thì ai cũng tính là đã xác thực.
     *
     * @return array{id: int, name: string, email: string, locale: ?string, email_verified: bool}
     */
    public function profileSummary(): array
    {
        return [
            ...$this->only(['id', 'name', 'email', 'locale']),
            'email_verified' => $this->hasVerifiedEmail(),
        ];
    }

    /**
     * Nhà cung cấp và mô hình đang trả lời, để hiện ở tổng quan và trong app.
     *
     * Không kèm địa chỉ hay khoá: chỉ đủ để người dùng biết ai đang trả lời.
     *
     * @return array{source: string, provider: ?string, model: ?string}
     */
    public function activeSetup(): array
    {
        $provider = $this->activeProvider;

        if ($provider === null) {
            return [
                'source' => 'default',
                'provider' => null,
                'model' => filled(config('snapask.model')) ? (string) config('snapask.model') : null,
            ];
        }

        $model = in_array($this->active_model, $provider->models, true)
            ? $this->active_model
            : ($provider->models[0] ?? null);

        return ['source' => 'custom', 'provider' => $provider->name, 'model' => $model];
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
