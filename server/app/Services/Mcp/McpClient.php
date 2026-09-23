<?php

namespace App\Services\Mcp;

use App\Models\McpConnector;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client MCP tối giản, nói chuyện qua Streamable HTTP.
 *
 * Chỉ dùng máy chủ MCP qua HTTP chứ không chạy tiến trình cục bộ như Claude
 * Desktop: ở đây mô hình được gọi từ máy chủ SnapAsk, nên một tiến trình nằm
 * trên máy khách thì SnapAsk không với tới được.
 */
class McpClient
{
    private const PROTOCOL_VERSION = '2025-06-18';

    private int $id = 0;

    /**
     * Bắt tay rồi đọc danh sách công cụ mà máy chủ MCP cung cấp.
     *
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function listTools(McpConnector $connector): array
    {
        $session = $this->initialize($connector);
        $result = $this->call($connector, 'tools/list', [], $session);

        return collect(Arr::get($result, 'tools', []))
            ->filter(fn (mixed $tool): bool => is_array($tool) && is_string($tool['name'] ?? null))
            ->map(fn (array $tool): array => [
                'name' => $tool['name'],
                'description' => (string) ($tool['description'] ?? ''),
                'inputSchema' => is_array($tool['inputSchema'] ?? null)
                    ? $tool['inputSchema']
                    : ['type' => 'object', 'properties' => new \stdClass],
            ])
            ->values()
            ->all();
    }

    /**
     * Gọi một công cụ và trả về phần chữ mà máy chủ MCP đáp lại.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function callTool(McpConnector $connector, string $tool, array $arguments): string
    {
        $session = $this->initialize($connector);
        $result = $this->call($connector, 'tools/call', [
            'name' => $tool,
            'arguments' => (object) $arguments,
        ], $session);

        $text = collect(Arr::get($result, 'content', []))
            ->filter(fn (mixed $part): bool => is_array($part) && ($part['type'] ?? null) === 'text')
            ->map(fn (array $part): string => (string) ($part['text'] ?? ''))
            ->implode("\n");

        if (Arr::get($result, 'isError') === true) {
            return 'Công cụ báo lỗi: '.($text !== '' ? $text : 'không rõ nguyên nhân.');
        }

        // Công cụ trả về ảnh hoặc tài nguyên khác thì chưa đọc được ở đây; nói
        // thẳng ra còn hơn đưa chuỗi rỗng để mô hình tự bịa nội dung.
        return $text !== '' ? $text : 'Công cụ chạy xong nhưng không trả về nội dung chữ nào.';
    }

    /**
     * Bắt tay phiên làm việc và trả về mã phiên nếu máy chủ cấp.
     */
    private function initialize(McpConnector $connector): ?string
    {
        $response = $this->send($connector, null, [
            'jsonrpc' => '2.0',
            'id' => ++$this->id,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'SnapAsk', 'version' => '0.1.0'],
            ],
        ]);

        $this->decode($response);

        // Máy chủ có trạng thái sẽ cấp mã phiên qua header và bắt buộc mọi lời
        // gọi sau phải mang theo; máy chủ không trạng thái thì bỏ trống.
        $session = $response->header('Mcp-Session-Id') ?: null;

        // Thông báo một chiều, không có `id` và không chờ phản hồi.
        $this->send($connector, $session, [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        return $session;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(McpConnector $connector, string $method, array $params, ?string $session): array
    {
        return $this->decode($this->send($connector, $session, [
            'jsonrpc' => '2.0',
            'id' => ++$this->id,
            'method' => $method,
            'params' => (object) $params,
        ]));
    }

    /** @param array<string, mixed> $payload */
    private function send(McpConnector $connector, ?string $session, array $payload): Response
    {
        return $this->client($connector, $session)
            ->post($connector->url, $payload)
            ->throw();
    }

    private function client(McpConnector $connector, ?string $session): PendingRequest
    {
        return Http::asJson()
            // Chuẩn Streamable HTTP cho phép máy chủ đáp bằng JSON hoặc SSE, nên
            // phải nhận cả hai; chỉ khai một kiểu là bị từ chối với 406.
            ->withHeaders(array_filter([
                'Accept' => 'application/json, text/event-stream',
                'MCP-Protocol-Version' => self::PROTOCOL_VERSION,
                'Mcp-Session-Id' => $session,
            ]))
            ->when(filled($connector->auth_token), fn (PendingRequest $request): PendingRequest => $request
                ->withToken($connector->auth_token))
            ->connectTimeout((int) config('snapask.mcp.connect_timeout'))
            ->timeout((int) config('snapask.mcp.timeout'));
    }

    /**
     * Đọc phần `result` của một phản hồi JSON-RPC, dù nó về dạng JSON hay SSE.
     *
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $body = trim($response->body());

        if ($body === '') {
            return [];
        }

        $message = str_contains((string) $response->header('Content-Type'), 'text/event-stream')
            ? $this->firstSseMessage($body)
            : json_decode($body, true);

        if (! is_array($message)) {
            throw new RuntimeException('Máy chủ MCP trả về nội dung không đọc được.');
        }

        if (isset($message['error'])) {
            throw new RuntimeException((string) Arr::get($message, 'error.message', 'Máy chủ MCP báo lỗi.'));
        }

        return is_array($message['result'] ?? null) ? $message['result'] : [];
    }

    /**
     * Lấy bản tin JSON-RPC đầu tiên trong một luồng SSE.
     *
     * @return array<string, mixed>|null
     */
    private function firstSseMessage(string $body): ?array
    {
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $decoded = json_decode(trim(substr($line, 5)), true);

            if (is_array($decoded) && (isset($decoded['result']) || isset($decoded['error']))) {
                return $decoded;
            }
        }

        return null;
    }
}
