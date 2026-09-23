<?php

namespace App\Services\Providers;

use App\Enums\ApiFormat;
use App\Exceptions\ProviderNotConfigured;
use App\Models\User;

class ProviderResolver
{
    /**
     * Chọn nhà cung cấp cho một người dùng.
     *
     * Khách đã khai cấu hình riêng thì dùng cấu hình đó; chưa khai thì dùng nhà
     * cung cấp mặc định của hệ thống, kèm hạn mức theo gói.
     */
    public function for(User $user): ResolvedProvider
    {
        $provider = $user->activeProvider;

        if ($provider !== null) {
            $model = $user->active_model;

            // Mô hình đang chọn có thể đã bị gỡ khỏi danh sách; lùi về mô hình
            // đầu tiên còn lại thay vì gửi một mã mà nhà cung cấp không biết.
            if (! in_array($model, $provider->models, true)) {
                $model = $provider->models[0] ?? null;
            }

            if ($model === null) {
                throw new ProviderNotConfigured('Nhà cung cấp đang chọn chưa khai mô hình nào.');
            }

            return new ResolvedProvider(
                baseUrl: $provider->endpointBase(),
                apiKey: (string) $provider->api_key,
                model: $model,
                format: $provider->api_format,
                isCustom: true,
            );
        }

        $this->guardDefaultConfigured();

        return new ResolvedProvider(
            baseUrl: rtrim((string) config('snapask.base_url'), '/'),
            apiKey: (string) config('snapask.api_key'),
            model: (string) config('snapask.model'),
            format: ApiFormat::from((string) config('snapask.api_format')),
            isCustom: false,
        );
    }

    private function guardDefaultConfigured(): void
    {
        if (blank(config('snapask.api_key'))) {
            throw new ProviderNotConfigured(
                'Chưa có nhà cung cấp mô hình nào. Hãy mở trang quản lý và thêm một nhà cung cấp.',
            );
        }

        if (blank(config('snapask.base_url')) || blank(config('snapask.model'))) {
            throw new ProviderNotConfigured('Cấu hình nhà cung cấp mặc định chưa đầy đủ.');
        }
    }
}
