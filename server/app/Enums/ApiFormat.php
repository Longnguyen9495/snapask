<?php

namespace App\Enums;

/**
 * Giao thức mà một nhà cung cấp mô hình nói.
 *
 * Hai họ này phủ gần hết thị trường: phần lớn dịch vụ nhái theo OpenAI, còn
 * Anthropic đi đường riêng với /v1/messages.
 */
enum ApiFormat: string
{
    case OpenAiChat = 'openai-chat';
    case AnthropicMessages = 'anthropic-messages';

    public function label(): string
    {
        return match ($this) {
            self::OpenAiChat => 'OpenAI chat completions (/chat/completions)',
            self::AnthropicMessages => 'Anthropic messages (/v1/messages)',
        };
    }

    /** Địa chỉ gợi ý, điền sẵn vào biểu mẫu cho đỡ phải tra tài liệu. */
    public function baseUrlHint(): string
    {
        return match ($this) {
            self::OpenAiChat => 'https://api.openai.com/v1',
            self::AnthropicMessages => 'https://api.anthropic.com',
        };
    }
}
