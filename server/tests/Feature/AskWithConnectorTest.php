<?php

namespace Tests\Feature;

use App\Models\McpConnector;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AskWithConnectorTest extends TestCase
{
    use RefreshDatabase;

    private const PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('snapask.api_key', 'khoa-he-thong');
        config()->set('snapask.image.disk', 'captures');
        Storage::fake('captures');
    }

    /** @param array<int, string> $lines */
    private function sse(array $lines): string
    {
        return implode("\n\n", array_map(fn (string $line): string => 'data: '.$line, $lines))."\n\ndata: [DONE]\n\n";
    }

    /** Lượt đầu mô hình gọi công cụ, lượt sau nó chốt câu trả lời. */
    private function fakeModelThatCallsTool(): void
    {
        $toolRound = $this->sse([
            json_encode(['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_1',
                'function' => ['name' => 'kho__ton_kho', 'arguments' => '{"ma"'],
            ]]]]]]),
            // Phần arguments về nhỏ giọt qua nhiều chunk, phải ghép lại đúng.
            json_encode(['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'function' => ['arguments' => ':"SP01"}'],
            ]]]]]]),
        ]);

        $finalRound = $this->sse([
            json_encode(['choices' => [['delta' => ['content' => 'Còn 12 cái.']]]]),
            json_encode(['choices' => [[]], 'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 8]]),
        ]);

        Http::fake([
            'dashscope*' => Http::sequence()->push($toolRound)->push($finalRound),
            'mcp.vidu.com/*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []], 200, ['Mcp-Session-Id' => 'p1'])
                ->push('', 202)
                ->push(['jsonrpc' => '2.0', 'id' => 2, 'result' => [
                    'content' => [['type' => 'text', 'text' => 'SP01: còn 12']],
                ]]),
        ]);
    }

    private function userWithConnector(): User
    {
        $user = User::factory()->create();

        McpConnector::factory()->for($user)->synced()->create([
            'slug' => 'kho',
            'name' => 'Kho hàng',
            'url' => 'https://mcp.vidu.com/mcp',
        ]);

        return $user;
    }

    #[Test]
    public function mo_hinh_goi_duoc_cong_cu_cua_dich_vu_da_noi(): void
    {
        $this->fakeModelThatCallsTool();

        $content = $this->actingAs($this->userWithConnector())
            ->postJson(route('ask'), ['question' => 'Còn bao nhiêu SP01?', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        // Người dùng thấy được bước đang chạy, không phải ngồi nhìn màn hình trống.
        $this->assertStringContainsString('"type":"tool"', $content);
        $this->assertStringContainsString('Kho hàng', $content);
        $this->assertStringContainsString('Còn 12 cái.', $content);

        $answer = Message::where('role', 'assistant')->sole();
        $this->assertSame('Còn 12 cái.', $answer->content);
        $this->assertSame('kho__ton_kho', $answer->tools_used[0]['tool']);
        // Tham số bị cắt làm đôi qua hai chunk phải ghép lại thành JSON hợp lệ.
        $this->assertSame(['ma' => 'SP01'], $answer->tools_used[0]['arguments']);
    }

    #[Test]
    public function cong_cu_duoc_gui_len_mo_hinh_kem_tien_to_ten_dich_vu(): void
    {
        $this->fakeModelThatCallsTool();

        $this->actingAs($this->userWithConnector())
            ->postJson(route('ask'), ['question' => 'Còn bao nhiêu SP01?', 'image' => self::PIXEL])
            ->streamedContent();

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'chat/completions') || ! isset($request['tools'])) {
                return false;
            }

            return $request['tools'][0]['function']['name'] === 'kho__ton_kho';
        });
    }

    #[Test]
    public function dich_vu_chet_thi_van_tra_loi_duoc_bang_anh(): void
    {
        $this->fakeModelThatCallsTool();
        // Dịch vụ của khách sập giữa chừng.
        Http::fake(['mcp.vidu.com/*' => Http::response('', 503)]);

        $content = $this->actingAs($this->userWithConnector())
            ->postJson(route('ask'), ['question' => 'Còn bao nhiêu SP01?', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        // Lỗi của dịch vụ bên thứ ba không được làm hỏng cả lượt trả lời.
        $this->assertStringContainsString('Còn 12 cái.', $content);
        $this->assertStringNotContainsString('"type":"error"', $content);
    }

    #[Test]
    public function khach_chua_noi_dich_vu_nao_thi_khong_gui_cong_cu(): void
    {
        Http::fake(['*' => Http::response($this->sse([
            json_encode(['choices' => [['delta' => ['content' => 'Đây là màn hình lỗi.']]]]),
        ]))]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('ask'), ['question' => 'Lỗi gì đây?', 'image' => self::PIXEL])
            ->streamedContent();

        // Gửi mảng tools rỗng làm một số provider trả 400; phải bỏ hẳn khoá này.
        Http::assertSent(fn ($request): bool => ! isset($request['tools']) && ! isset($request['tool_choice']));
    }

    #[Test]
    public function ten_cong_cu_bia_ra_khong_lam_phien_dich_vu_cua_khach(): void
    {
        $toolRound = $this->sse([
            json_encode(['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_1',
                'function' => ['name' => 'kho__cong_cu_khong_co_that', 'arguments' => '{}'],
            ]]]]]]),
        ]);

        Http::fake([
            'dashscope*' => Http::sequence()
                ->push($toolRound)
                ->push($this->sse([json_encode(['choices' => [['delta' => ['content' => 'Không tra được.']]]])])),
            'mcp.vidu.com/*' => Http::response('', 500),
        ]);

        $this->actingAs($this->userWithConnector())
            ->postJson(route('ask'), ['question' => 'Hỏi linh tinh', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        // Mô hình bịa tên công cụ thì chặn ngay tại chỗ, không đẩy một lời gọi
        // vô nghĩa sang hệ thống của khách.
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'mcp.vidu.com'));
    }

    #[Test]
    public function vong_goi_cong_cu_co_tran_khong_chay_mai(): void
    {
        $alwaysCallsTool = $this->sse([
            json_encode(['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_'.Str::random(4),
                'function' => ['name' => 'kho__ton_kho', 'arguments' => '{}'],
            ]]]]]]),
        ]);

        // Closure chứ không phải một Http::response dùng chung: thân phản hồi là
        // luồng đọc một lần, tái dùng thì vòng thứ hai nhận được chuỗi rỗng.
        Http::fake([
            'dashscope*' => fn () => Http::response($alwaysCallsTool),
            'mcp.vidu.com/*' => fn () => Http::response(['jsonrpc' => '2.0', 'id' => 9, 'result' => [
                'content' => [['type' => 'text', 'text' => 'x']],
            ]]),
        ]);

        $this->actingAs($this->userWithConnector())
            ->postJson(route('ask'), ['question' => 'Hỏi vòng vo', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        // Một mô hình bối rối gọi công cụ mãi sẽ giữ kết nối tới khi PHP hết giờ
        // chạy; trần 4 vòng cộng lượt chốt là 5 lời gọi lên mô hình.
        $calls = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'chat/completions'))
            ->count();

        $this->assertSame(5, $calls);
    }
}
