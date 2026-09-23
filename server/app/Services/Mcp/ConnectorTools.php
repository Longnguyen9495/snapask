<?php

namespace App\Services\Mcp;

use App\Models\McpConnector;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gom công cụ của mọi connector khách đã nối, và chạy lời gọi ngược trở lại.
 */
class ConnectorTools
{
    /**
     * Ký tự nối giữa tên connector và tên công cụ.
     *
     * Giao thức gọi công cụ chỉ nhận `[a-zA-Z0-9_-]`, nên không dùng được dấu
     * chấm hay dấu hai chấm để ngăn cách.
     */
    private const SEPARATOR = '__';

    /** @var array<int, Collection<int, McpConnector>> */
    private array $cache = [];

    public function __construct(private McpClient $client) {}

    /**
     * Mô tả công cụ theo đúng hình dạng mà mô hình chờ đợi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schemasFor(User $user): array
    {
        return $this->usableConnectors($user)
            ->flatMap(fn (McpConnector $connector): array => collect($connector->tools)
                ->map(fn (array $tool): array => [
                    'type' => 'function',
                    'function' => [
                        'name' => $this->qualify($connector, $tool['name']),
                        'description' => trim($connector->name.': '.($tool['description'] ?? '')),
                        'parameters' => $tool['inputSchema'] ?? ['type' => 'object', 'properties' => new \stdClass],
                    ],
                ])
                ->all())
            ->take(64)
            ->values()
            ->all();
    }

    /**
     * Chạy một lời gọi công cụ và trả về chuỗi kết quả cho mô hình đọc tiếp.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function run(User $user, string $qualifiedName, array $arguments): string
    {
        $match = $this->resolve($user, $qualifiedName);

        if ($match === null) {
            // Mô hình đôi khi bịa ra tên công cụ. Nói thẳng là không có, còn hơn
            // đẩy một lời gọi vô nghĩa sang dịch vụ của khách.
            return 'Không tìm thấy công cụ này trong các dịch vụ đã kết nối.';
        }

        [$connector, $tool] = $match;

        try {
            return Str::limit(
                $this->client->callTool($connector, $tool, $arguments),
                // Kết quả dài vô hạn sẽ nuốt hết cửa sổ ngữ cảnh và đẩy chi phí
                // token lên theo. Cắt bớt rồi để mô hình hỏi lại cụ thể hơn.
                (int) config('snapask.mcp.max_result_chars'),
            );
        } catch (Throwable $exception) {
            report($exception);

            // Trả lỗi vào mạch hội thoại thay vì ném ra ngoài: mô hình còn cơ
            // hội xoay sang cách khác hoặc nói thật với người dùng là không tra
            // được, thay vì cả lượt trả lời bị bỏ dở.
            return 'Không gọi được dịch vụ '.$connector->name.'. Hãy trả lời dựa trên những gì nhìn thấy trong ảnh.';
        }
    }

    /** Nhãn tiếng Việt hiện lên giao diện trong lúc công cụ đang chạy. */
    public function label(User $user, string $qualifiedName): string
    {
        $match = $this->resolve($user, $qualifiedName);

        if ($match === null) {
            return 'Đang gọi công cụ…';
        }

        [$connector, $tool] = $match;

        return sprintf('Đang hỏi %s: %s…', $connector->name, str_replace('_', ' ', $tool));
    }

    /**
     * Tìm lại connector và tên công cụ gốc từ cái tên đã ghép gửi lên mô hình.
     *
     * Dò ngược trong chính danh sách công cụ đã đồng bộ chứ không tách chuỗi
     * theo dấu phân cách: tên ghép có thể đã bị cắt cho vừa 64 ký tự, và tên
     * công cụ gốc có thể chứa ký tự đã bị thay thế khi ghép.
     *
     * @return array{0: McpConnector, 1: string}|null
     */
    private function resolve(User $user, string $qualifiedName): ?array
    {
        foreach ($this->usableConnectors($user) as $connector) {
            foreach ($connector->tools as $tool) {
                if ($this->qualify($connector, $tool['name']) === $qualifiedName) {
                    return [$connector, $tool['name']];
                }
            }
        }

        return null;
    }

    /** @return Collection<int, McpConnector> */
    private function usableConnectors(User $user): Collection
    {
        // Nạp một lần cho cả lượt trả lời: vòng lặp gọi công cụ hỏi lại danh
        // sách này sau mỗi vòng, và mỗi lần hỏi là một truy vấn nữa. Khoá theo
        // id người dùng chứ không dùng once(), vì một tiến trình có thể phục vụ
        // nhiều người và kết quả của người này không được rơi sang người kia.
        return $this->cache[$user->id] ??= $user->mcpConnectors()
            ->where('enabled', true)
            ->whereNotNull('tools')
            ->get()
            ->filter(fn (McpConnector $connector): bool => $connector->isUsable())
            ->values();
    }

    private function qualify(McpConnector $connector, string $tool): string
    {
        return substr($connector->slug.self::SEPARATOR.preg_replace('/[^a-zA-Z0-9_-]/', '_', $tool), 0, 64);
    }
}
