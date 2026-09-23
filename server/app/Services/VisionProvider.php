<?php

namespace App\Services;

use App\Enums\ApiFormat;
use App\Services\Providers\AnthropicMessagesDriver;
use App\Services\Providers\ModelDriver;
use App\Services\Providers\OpenAiChatDriver;
use App\Services\Providers\ResolvedProvider;
use Generator;

/**
 * Chọn đúng giao thức cho nhà cung cấp rồi chuyển lời gọi sang đó.
 */
class VisionProvider
{
    public function __construct(
        private OpenAiChatDriver $openAi,
        private AnthropicMessagesDriver $anthropic,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return Generator<int, string, void, array{content: string, tool_calls: array<int, array<string, mixed>>, prompt_tokens: ?int, completion_tokens: ?int}>
     */
    public function stream(array $messages, ResolvedProvider $provider, array $tools = []): Generator
    {
        return $this->driverFor($provider->format)->stream($messages, $provider, $tools);
    }

    private function driverFor(ApiFormat $format): ModelDriver
    {
        return match ($format) {
            ApiFormat::OpenAiChat => $this->openAi,
            ApiFormat::AnthropicMessages => $this->anthropic,
        };
    }
}
