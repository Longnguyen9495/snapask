<?php

namespace App\Services\Providers;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Phần dùng chung của mọi giao thức: dựng client HTTP và đọc luồng SSE.
 *
 * Mỗi lớp con chỉ còn phải lo hình dạng request và cách bóc tách sự kiện riêng
 * của nhà cung cấp mình nói chuyện.
 */
abstract class ModelDriver
{
    /**
     * Gửi một lượt hỏi và nhả từng mẩu chữ ra ngay khi mô hình trả về.
     *
     * @param  array<int, array<string, mixed>>  $messages  Tin nhắn theo hình dạng của OpenAI.
     * @param  array<int, array<string, mixed>>  $tools  Công cụ theo hình dạng của OpenAI.
     * @return Generator<int, string, void, array{content: string, tool_calls: array<int, array<string, mixed>>, prompt_tokens: ?int, completion_tokens: ?int}>
     */
    abstract public function stream(array $messages, ResolvedProvider $provider, array $tools = []): Generator;

    /** @param array<string, string> $headers */
    protected function client(ResolvedProvider $provider, array $headers): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->connectTimeout((int) config('snapask.connect_timeout'))
            ->timeout((int) config('snapask.timeout'))
            // Một lần thử lại là đủ cho lỗi tạm thời. Nhiều hơn, cộng với timeout
            // dài, sẽ đẩy tổng thời gian chờ vượt quá giới hạn chạy của PHP và
            // người dùng nhận một luồng trống thay vì câu trả lời.
            ->retry(1, 300, fn (Throwable $exception): bool => ! method_exists($exception, 'response')
                || $exception->response?->serverError() === true, throw: false);
    }

    /**
     * Đọc một luồng SSE và trả về từng sự kiện đã giải mã.
     *
     * @return Generator<int, array<string, mixed>>
     */
    protected function readEvents(Response $response): Generator
    {
        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(8192);

            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            // SSE ngắt theo dòng; phần đuôi dở dang phải giữ lại cho vòng đọc
            // sau, nếu không một sự kiện bị cắt đôi sẽ hỏng JSON.
            while (($position = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $position));
                $buffer = substr($buffer, $position + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));

                if ($data === '[DONE]') {
                    break 2;
                }

                $event = json_decode($data, true);

                if (is_array($event)) {
                    yield $event;
                }
            }
        }

        $body->close();
    }

    protected function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
