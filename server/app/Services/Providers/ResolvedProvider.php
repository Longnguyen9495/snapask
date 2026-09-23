<?php

namespace App\Services\Providers;

use App\Enums\ApiFormat;

/**
 * Nhà cung cấp thật sự dùng cho một lượt hỏi, sau khi đã chọn giữa cấu hình
 * riêng của khách và nhà cung cấp mặc định của hệ thống.
 */
class ResolvedProvider
{
    public function __construct(
        public string $baseUrl,
        public string $apiKey,
        public string $model,
        public ApiFormat $format,
        /** Khách đang dùng khoá của chính mình, nên không bị hạn mức gói chặn. */
        public bool $isCustom,
    ) {}
}
