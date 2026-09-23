<?php

namespace Tests\Feature;

use App\Models\McpConnector;
use App\Models\Message;
use App\Models\ModelProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnthropicProviderTest extends TestCase
{
    use RefreshDatabase;

    private const PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('snapask.image.disk', 'captures');
        Storage::fake('captures');
    }

    /** @param array<int, array<string, mixed>> $events */
    private function sse(array $events): string
    {
        return collect($events)
            ->map(fn (array $event): string => 'event: '.$event['type']."\ndata: ".json_encode($event))
            ->implode("\n\n")."\n\n";
    }

    private function userWithAnthropic(): User
    {
        $user = User::factory()->create();
        $provider = ModelProvider::factory()->for($user)->anthropic()->create();
        $user->update(['active_provider_id' => $provider->id, 'active_model' => 'claude-sonnet-5']);

        return $user->refresh();
    }

    #[Test]
    public function tra_loi_duoc_qua_giao_thuc_anthropic(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->sse([
            ['type' => 'message_start', 'message' => ['usage' => ['input_tokens' => 700]]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Đây là ']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'màn hình lỗi.']],
            ['type' => 'message_delta', 'usage' => ['output_tokens' => 9]],
            ['type' => 'message_stop'],
        ]))]);

        $content = $this->actingAs($this->userWithAnthropic())
            ->postJson(route('ask'), ['question' => 'Lỗi gì đây?', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Đây là ', $content);

        $answer = Message::where('role', 'assistant')->sole();
        $this->assertSame('Đây là màn hình lỗi.', $answer->content);
        $this->assertSame(700, $answer->prompt_tokens);
        $this->assertSame(9, $answer->completion_tokens);
    }

    #[Test]
    public function gui_dung_header_va_hinh_dang_cua_anthropic(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->sse([
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'ok']],
        ]))]);

        $this->actingAs($this->userWithAnthropic())
            ->postJson(route('ask'), ['question' => 'Đọc giúp dòng đầu', 'image' => self::PIXEL])
            ->streamedContent();

        Http::assertSent(function ($request): bool {
            // Xác thực bằng x-api-key, không phải Bearer; và phiên bản giao thức
            // là bắt buộc, thiếu là bị từ chối.
            if (! $request->hasHeader('x-api-key', 'sk-cua-khach')
                || ! $request->hasHeader('anthropic-version', '2023-06-01')
                || $request->hasHeader('Authorization')) {
                return false;
            }

            if (! str_ends_with($request->url(), '/v1/messages')) {
                return false;
            }

            // Lời dẫn hệ thống nằm ở tham số riêng, không phải một tin nhắn.
            if (! is_string($request['system']) || $request['system'] === '') {
                return false;
            }

            foreach ($request['messages'] as $message) {
                if ($message['role'] === 'system') {
                    return false;
                }
            }

            // Ảnh đi trong khối image/base64, không phải một data URL.
            $content = collect($request['messages'])->last()['content'];
            $image = collect($content)->firstWhere('type', 'image');

            return $image !== null
                && $image['source']['type'] === 'base64'
                && $image['source']['media_type'] === 'image/png'
                && ! str_contains($image['source']['data'], 'data:');
        });
    }

    #[Test]
    public function goi_duoc_cong_cu_qua_giao_thuc_anthropic(): void
    {
        $toolRound = $this->sse([
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => [
                'type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'kho__ton_kho',
            ]],
            // Tham số về nhỏ giọt qua nhiều sự kiện, phải ghép lại đúng.
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"ma"']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => ':"SP01"}']],
            ['type' => 'message_stop'],
        ]);

        $finalRound = $this->sse([
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Còn 12 cái.']],
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()->push($toolRound)->push($finalRound),
            'mcp.vidu.com/*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []], 200, ['Mcp-Session-Id' => 'p1'])
                ->push('', 202)
                ->push(['jsonrpc' => '2.0', 'id' => 2, 'result' => [
                    'content' => [['type' => 'text', 'text' => 'SP01: còn 12']],
                ]]),
        ]);

        $user = $this->userWithAnthropic();
        McpConnector::factory()->for($user)->synced()->create([
            'slug' => 'kho',
            'name' => 'Kho hàng',
            'url' => 'https://mcp.vidu.com/mcp',
        ]);

        $content = $this->actingAs($user)
            ->postJson(route('ask'), ['question' => 'Còn bao nhiêu SP01?', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Còn 12 cái.', $content);

        $answer = Message::where('role', 'assistant')->sole();
        $this->assertSame(['ma' => 'SP01'], $answer->tools_used[0]['arguments']);

        // Công cụ phải phẳng (name/input_schema), không bọc trong "function".
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'anthropic') || ! isset($request['tools'])) {
                return false;
            }

            return $request['tools'][0]['name'] === 'kho__ton_kho'
                && isset($request['tools'][0]['input_schema'])
                && ! isset($request['tools'][0]['function']);
        });

        // Kết quả công cụ quay lại dưới dạng khối tool_result trong tin nhắn người dùng.
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'anthropic')) {
                return false;
            }

            return collect($request['messages'])->contains(
                fn (array $message): bool => $message['role'] === 'user'
                    && is_array($message['content'])
                    && collect($message['content'])->contains(
                        fn (array $block): bool => ($block['type'] ?? '') === 'tool_result'
                            && ($block['tool_use_id'] ?? '') === 'toolu_1',
                    ),
            );
        });
    }

    #[Test]
    public function khach_dung_provider_rieng_thi_khong_bi_han_muc_chan(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->sse([
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'ok']],
        ]))]);

        $user = $this->userWithAnthropic();
        $user->update(['monthly_ask_limit' => 0]);

        $this->actingAs($user->refresh())
            ->postJson(route('ask'), ['question' => 'Vẫn hỏi được chứ?', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();
    }

    #[Test]
    public function khoa_he_thong_khong_bao_gio_di_theo_provider_cua_khach(): void
    {
        config()->set('snapask.api_key', 'khoa-cua-he-thong');

        Http::fake(['api.anthropic.com/*' => Http::response($this->sse([
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'ok']],
        ]))]);

        $this->actingAs($this->userWithAnthropic())
            ->postJson(route('ask'), ['question' => 'Lỗi gì đây?', 'image' => self::PIXEL])
            ->streamedContent();

        Http::assertSent(fn ($request): bool => ! str_contains(
            json_encode($request->headers()).$request->body(),
            'khoa-cua-he-thong',
        ));
    }
}
