<?php

namespace App\Services\Providers;

use Generator;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Giao thức /chat/completions của OpenAI, cũng là giao thức mà hầu hết các dịch
 * vụ khác nhái theo.
 */
class OpenAiChatDriver extends ModelDriver
{
    public function stream(array $messages, ResolvedProvider $provider, array $tools = []): Generator
    {
        $response = $this->client($provider, ['Authorization' => 'Bearer '.$provider->apiKey])
            ->withOptions(['stream' => true])
            ->post($provider->baseUrl.'/chat/completions', $this->withTemperature(array_filter([
                'model' => $provider->model,
                'messages' => $messages,
                'max_tokens' => (int) config('snapask.max_output_tokens'),
                'stream' => true,
                'stream_options' => ['include_usage' => true],
                'tools' => $tools ?: null,
                'tool_choice' => $tools ? 'auto' : null,
            ])))
            ->throw();

        $content = '';
        $toolCalls = [];
        $promptTokens = null;
        $completionTokens = null;

        foreach ($this->readEvents($response) as $event) {
            $promptTokens = $this->nullableInt(Arr::get($event, 'usage.prompt_tokens')) ?? $promptTokens;
            $completionTokens = $this->nullableInt(Arr::get($event, 'usage.completion_tokens')) ?? $completionTokens;

            $delta = Arr::get($event, 'choices.0.delta.content');

            if (is_string($delta) && $delta !== '') {
                $content .= $delta;

                yield $delta;
            }

            foreach (Arr::get($event, 'choices.0.delta.tool_calls', []) ?? [] as $partial) {
                $this->mergeToolCall($toolCalls, $partial);
            }
        }

        // Lượt mà mô hình chỉ gọi công cụ thì không có chữ nào, và đó là bình
        // thường; chỉ lượt vừa không chữ vừa không công cụ mới là hỏng.
        if (trim($content) === '' && $toolCalls === []) {
            throw new RuntimeException('Mô hình không trả về nội dung nào.');
        }

        return [
            'content' => $content,
            'tool_calls' => array_values($toolCalls),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ];
    }

    /**
     * Ghép các mảnh tool call rải rác qua nhiều chunk.
     *
     * Provider gửi tên ở chunk đầu rồi nhỏ giọt phần arguments qua các chunk
     * sau, nên phải cộng dồn theo chỉ số thay vì ghi đè.
     *
     * @param  array<int, array<string, mixed>>  $toolCalls
     */
    private function mergeToolCall(array &$toolCalls, mixed $partial): void
    {
        if (! is_array($partial)) {
            return;
        }

        $index = (int) ($partial['index'] ?? 0);

        $toolCalls[$index] ??= [
            'id' => '',
            'type' => 'function',
            'function' => ['name' => '', 'arguments' => ''],
        ];

        if (is_string($partial['id'] ?? null) && $partial['id'] !== '') {
            $toolCalls[$index]['id'] = $partial['id'];
        }

        $name = Arr::get($partial, 'function.name');

        if (is_string($name) && $name !== '') {
            $toolCalls[$index]['function']['name'] = $name;
        }

        $arguments = Arr::get($partial, 'function.arguments');

        if (is_string($arguments) && $arguments !== '') {
            $toolCalls[$index]['function']['arguments'] .= $arguments;
        }
    }

    /**
     * Chỉ đính `temperature` khi nó thật sự được cấu hình.
     *
     * Gửi tham số này tới một model không nhận nó làm hỏng cả request chứ không
     * bị bỏ qua — có model trả về 400 kèm "`temperature` is deprecated for this
     * model". Để trống là cách an toàn cho mọi nhà cung cấp.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withTemperature(array $payload): array
    {
        $temperature = config('snapask.temperature');

        if ($temperature !== null && $temperature !== '') {
            $payload['temperature'] = (float) $temperature;
        }

        return $payload;
    }
}
