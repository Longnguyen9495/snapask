<?php

namespace App\Models;

use App\Enums\ApiFormat;
use Database\Factories\ModelProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['api_key'])]
class ModelProvider extends Model
{
    /** @use HasFactory<ModelProviderFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'base_url', 'api_key', 'api_format', 'models'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'api_format' => ApiFormat::class,
            'models' => 'array',
            // Khoá của khách không bao giờ nằm dạng trần trong bảng.
            'api_key' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Địa chỉ đã bỏ dấu gạch chéo thừa ở cuối. */
    public function endpointBase(): string
    {
        return rtrim($this->base_url, '/');
    }
}
