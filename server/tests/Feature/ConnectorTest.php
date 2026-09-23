<?php

namespace Tests\Feature;

use App\Models\McpConnector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConnectorTest extends TestCase
{
    use RefreshDatabase;

    /** Máy chủ MCP đáp lại đúng thứ tự: initialize, thông báo, rồi tools/list. */
    private function fakeMcpServer(): void
    {
        Http::fake(['mcp.vidu.com/*' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
                'protocolVersion' => '2025-06-18',
                'serverInfo' => ['name' => 'Kho hàng', 'version' => '1.0'],
            ]], 200, ['Mcp-Session-Id' => 'phien-123'])
            ->push('', 202)
            ->push(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => [[
                'name' => 'ton_kho',
                'description' => 'Tra số lượng còn lại.',
                'inputSchema' => ['type' => 'object', 'properties' => ['ma' => ['type' => 'string']]],
            ]]]]),
        ]);
    }

    #[Test]
    public function them_dich_vu_thi_doc_luon_danh_sach_cong_cu(): void
    {
        $this->fakeMcpServer();

        $this->actingAs(User::factory()->create())
            ->postJson(route('connectors.store'), [
                'name' => 'Kho hàng',
                'slug' => 'kho',
                'url' => 'https://mcp.vidu.com/mcp',
                'auth_token' => 'token-cua-khach',
            ])
            ->assertCreated()
            ->assertJsonPath('connector.tools.0.name', 'ton_kho')
            ->assertJsonPath('connector.has_token', true)
            // Token của khách không được quay ngược ra ngoài.
            ->assertJsonMissingPath('connector.auth_token');

        $this->assertNotNull(McpConnector::sole()->synced_at);
    }

    #[Test]
    public function ma_phien_duoc_mang_theo_o_cac_loi_goi_sau(): void
    {
        $this->fakeMcpServer();

        $this->actingAs(User::factory()->create())->postJson(route('connectors.store'), [
            'name' => 'Kho hàng',
            'slug' => 'kho',
            'url' => 'https://mcp.vidu.com/mcp',
        ]);

        // Lời gọi đầu chưa có mã phiên; từ lời gọi thứ hai trở đi bắt buộc phải có,
        // nếu không máy chủ MCP có trạng thái sẽ từ chối.
        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => $request['method'] === 'tools/list'
            && $request->hasHeader('Mcp-Session-Id', 'phien-123'));
    }

    #[Test]
    public function dich_vu_khong_ket_noi_duoc_van_luu_lai_kem_loi(): void
    {
        Http::fake(['mcp.vidu.com/*' => Http::response(['error' => 'down'], 500)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('connectors.store'), [
                'name' => 'Kho hàng',
                'slug' => 'kho',
                'url' => 'https://mcp.vidu.com/mcp',
            ])
            ->assertCreated()
            ->assertJsonPath('connector.tools', []);

        // Vẫn còn bản ghi để khách sửa địa chỉ hoặc token, kèm lý do hỏng.
        $connector = McpConnector::sole();
        $this->assertNotNull($connector->last_error);
        $this->assertNull($connector->tools);
    }

    #[Test]
    public function chi_nhan_dia_chi_https(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->postJson(route('connectors.store'), [
                'name' => 'Kho hàng',
                'slug' => 'kho',
                'url' => 'http://mcp.vidu.com/mcp',
            ])
            ->assertJsonValidationErrorFor('url');

        Http::assertNothingSent();
    }

    #[Test]
    public function khong_dung_duoc_connector_cua_nguoi_khac(): void
    {
        Http::fake();
        $stranger = McpConnector::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('connectors.destroy', $stranger))
            ->assertNotFound();

        $this->assertDatabaseHas('mcp_connectors', ['id' => $stranger->id]);
    }

    #[Test]
    public function slug_trung_trong_cung_tai_khoan_bi_tu_choi(): void
    {
        Http::fake();
        $user = User::factory()->create();
        McpConnector::factory()->for($user)->create(['slug' => 'kho']);

        $this->actingAs($user)
            ->postJson(route('connectors.store'), [
                'name' => 'Kho khác',
                'slug' => 'kho',
                'url' => 'https://mcp.vidu.com/mcp',
            ])
            ->assertJsonValidationErrorFor('slug');
    }
}
