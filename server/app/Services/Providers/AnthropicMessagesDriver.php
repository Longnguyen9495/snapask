<?php

namespace App\Services\Providers;

use Generator;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Giao thức /v1/messages của Anthropic.
 *
 * Phần còn lại của ứng dụng chỉ nói một hình dạng tin nhắn duy nhất — hình dạng
 * của OpenAI. Lớp này dịch xuôi lúc gửi và dịch ngược lúc nhận, để chỗ khác
 * không phải biết đang nói chuyện với nhà cung cấp nào.
 */
class AnthropicMessagesDriver extends ModelDriver
{
    /** Phiên bản giao thức, bắt buộc phải gửi kèm mọi request. */
    private const VERSION = '2023-06-01';

    public function stream(array $messages, ResolvedProvider $provider, array $tools = []): Generator
    {
        [$system, $turns] = $this->translateMessages($messages);

        $response = $this->client($provider, [
            'x-api-key' => $provider->apiKey,
            'anthropic-version' => self::VERSION,
        ])
            ->withOptions(['stream' => true])
            ->post($provider->baseUrl.'/v1/messages', array_filter([
                'model' => $provider->model,
                // Ở giao thức này `max_tokens` là bắt buộc, không phải tuỳ chọn.
                'max_tokens' => (int) config('snapask.max_output_tokens'),
                'system' => $system ?: null,
                'messages' => $turns,
                'stream' => true,
                'tools' => $tools ? $this->translateTools($tools) : null,
            ]))
            ->throw();

        $content = '';
        $blocks = [];
        $promptTokens = null;
        $completionTokens = null;

        foreach ($this->readEvents($response) as $event) {
            $type = $event['type'] ?? '';

            if ($type === 'message_start') {
                $promptTokens = $this->nullableInt(Arr::get($event, 'message.usage.input_tokens')) ?? $promptTokens;
            }

            if ($type === 'content_block_start') {
                // Khối tool_use mở ra với tên và id; phần tham số nhỏ giọt sau.
                $blocks[(int) ($event['index'] ?? 0)] = [
                    'type' => (string) Arr::get($event, 'content_block.type', ''),
                    'id' => (string) Arr::get($event, 'content_block.id', ''),
                    'name' => (string) Arr::get($event, 'content_block.name', ''),
                    'json' => '',
                ];
            }

            if ($type === 'content_block_delta') {
                $delta = $event['delta'] ?? [];
                $index = (int) ($event['index'] ?? 0);

                if (($delta['type'] ?? '') === 'text_delta' && is_string($delta['text'] ?? null)) {
                    $content .= $delta['text'];

                    yield $delta['text'];
                }

                if (($delta['type'] ?? '') === 'input_json_delta' && isset($blocks[$index])) {
                    $blocks[$index]['json'] .= (string) ($delta['partial_json'] ?? '');
                }
            }

            if ($type === 'message_delta') {
                $completionTokens = $this->nullableInt(Arr::get($event, 'usage.output_tokens')) ?? $completionTokens;
            }

            if ($type === 'error') {
                throw new RuntimeException((string) Arr::get($event, 'error.message', 'Nhà cung cấp báo lỗi.'));
            }
        }

        $toolCalls = $this->collectToolCalls($blocks);

        if (trim($content) === '' && $toolCalls === []) {
            throw new RuntimeException('Mô hình không trả về nội dung nào.');
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ];
    }

    /**
     * Dịch chuỗi tin nhắn kiểu OpenAI sang kiểu Anthropic.
     *
     * Ba khác biệt phải xử lý: lời dẫn hệ thống nằm ở tham số riêng chứ không
     * phải một tin nhắn; ảnh đi trong khối `image` với `source.base64` chứ không
     * phải một data URL; và kết quả công cụ là khối `tool_result` trong tin nhắn
     * của người dùng chứ không phải một vai `tool` riêng.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{0: string, 1: array<int, array<string, mixed>>}
     */
    private function translateMessages(array $messages): array
    {
        $system = '';
        $turns = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';

            if ($role === 'system') {
                $system .= (is_string($message['content'] ?? null) ? $message['content'] : '')."\n";

                continue;
            }

            if ($role === 'tool') {
                $block = [
                    'type' => 'tool_result',
                    'tool_use_id' => (string) ($message['tool_call_id'] ?? ''),
                    'content' => (string) ($message['content'] ?? ''),
                ];

                // Nhiều kết quả công cụ liên tiếp phải gộp vào một tin nhắn
                // người dùng duy nhất, đúng như giao thức yêu cầu.
                $last = count($turns) - 1;

                if ($last >= 0 && $turns[$last]['role'] === 'user' && is_array($turns[$last]['content'])) {
                    $turns[$last]['content'][] = $block;
                } else {
                    $turns[] = ['role' => 'user', 'content' => [$block]];
                }

                continue;
            }

            if ($role === 'assistant' && ! empty($message['tool_calls'])) {
                $turns[] = [
                    'role' => 'assistant',
                    'content' => $this->assistantToolBlocks($message),
                ];

                continue;
            }

            $turns[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $this->translateContent($message['content'] ?? ''),
            ];
        }

        return [trim($system), $turns];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array<string, mixed>>
     */
    private function assistantToolBlocks(array $message): array
    {
        $blocks = [];
        $text = is_string($message['content'] ?? null) ? trim($message['content']) : '';

        if ($text !== '') {
            $blocks[] = ['type' => 'text', 'text' => $text];
        }

        foreach ($message['tool_calls'] as $call) {
            $arguments = json_decode((string) Arr::get($call, 'function.arguments', '{}'), true);

            $blocks[] = [
                'type' => 'tool_use',
                'id' => (string) Arr::get($call, 'id', ''),
                'name' => (string) Arr::get($call, 'function.name', ''),
                'input' => (object) (is_array($arguments) ? $arguments : []),
            ];
        }

        return $blocks;
    }

    /**
     * @return string|array<int, array<string, mixed>>
     */
    private function translateContent(mixed $content): string|array
    {
        if (! is_array($content)) {
            return (string) $content;
        }

        $blocks = [];

        foreach ($content as $part) {
            if (($part['type'] ?? '') === 'text') {
                $blocks[] = ['type' => 'text', 'text' => (string) ($part['text'] ?? '')];

                continue;
            }

            if (($part['type'] ?? '') !== 'image_url') {
                continue;
            }

            $url = (string) Arr::get($part, 'image_url.url', '');

            if (preg_match('/^data:([\w\/+.-]+);base64,(.+)$/s', $url, $matches) !== 1) {
                continue;
            }

            $blocks[] = [
                'type' => 'image',
                'source' => ['type' => 'base64', 'media_type' => $matches[1], 'data' => $matches[2]],
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<int, array<string, mixed>>
     */
    private function translateTools(array $tools): array
    {
        return array_values(array_map(fn (array $tool): array => [
            'name' => (string) Arr::get($tool, 'function.name', ''),
            'description' => (string) Arr::get($tool, 'function.description', ''),
            'input_schema' => Arr::get($tool, 'function.parameters', ['type' => 'object']),
        ], $tools));
    }

    /**
     * Gói các khối tool_use lại theo hình dạng mà phần còn lại của ứng dụng đọc.
     *
     * @param  array<int, array<string, string>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function collectToolCalls(array $blocks): array
    {
        $calls = [];

        foreach ($blocks as $block) {
            if ($block['type'] !== 'tool_use') {
                continue;
            }

            $calls[] = [
                'id' => $block['id'],
                'type' => 'function',
                'function' => [
                    'name' => $block['name'],
                    // Khối không có tham số nào thì phần json rỗng; trả về `{}`
                    // để bên gọi lúc nào cũng giải mã được.
                    'arguments' => $block['json'] !== '' ? $block['json'] : '{}',
                ],
            ];
        }

        return $calls;
    }
}
