<?php

namespace Tests\Feature;

use App\Models\McpConnector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConnectorPageTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMcpServer(): void
    {
        Http::fake(['mcp.vidu.com/*' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []], 200, ['Mcp-Session-Id' => 'phien-1'])
            ->push('', 202)
            ->push(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => [[
                'name' => 'ton_kho',
                'description' => 'Tra số lượng còn lại.',
                'inputSchema' => ['type' => 'object', 'properties' => ['ma' => ['type' => 'string']]],
            ]]]]),
        ]);
    }

    #[Test]
    public function khach_chua_dang_nhap_bi_day_ve_trang_dang_nhap(): void
    {
        $this->get(route('web.connectors.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function dang_nhap_bang_trinh_duyet_roi_vao_trang_dich_vu(): void
    {
        User::factory()->create(['email' => 'demo@snapask.test']);

        $this->post(route('login.store'), [
            'email' => 'demo@snapask.test',
            'password' => 'password',
        ])->assertRedirect(route('web.connectors.index'));

        $this->assertAuthenticated();
    }

    #[Test]
    public function sai_mat_khau_thi_khong_vao_duoc(): void
    {
        User::factory()->create(['email' => 'demo@snapask.test']);

        $this->post(route('login.store'), [
            'email' => 'demo@snapask.test',
            'password' => 'sai-roi',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function trang_chi_liet_ke_dich_vu_cua_chinh_minh(): void
    {
        $user = User::factory()->create();
        McpConnector::factory()->for($user)->synced()->create(['name' => 'Kho cua toi', 'slug' => 'kho']);
        McpConnector::factory()->synced()->create(['name' => 'Kho nguoi khac', 'slug' => 'khac']);

        $this->actingAs($user)
            ->get(route('web.connectors.index'))
            ->assertOk()
            ->assertSee('Kho cua toi')
            ->assertDontSee('Kho nguoi khac');
    }

    #[Test]
    public function them_dich_vu_tu_web_thi_doc_luon_cong_cu(): void
    {
        $this->fakeMcpServer();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('web.connectors.store'), [
                'name' => 'Kho hàng',
                'slug' => 'kho',
                'url' => 'https://mcp.vidu.com/mcp',
                'auth_token' => 'token-cua-khach',
            ])
            ->assertRedirect(route('web.connectors.index'))
            ->assertSessionHas('status');

        $connector = McpConnector::sole();
        $this->assertSame('ton_kho', $connector->tools[0]['name']);
        $this->assertSame('token-cua-khach', $connector->auth_token);
    }

    #[Test]
    public function loi_ket_noi_hien_thanh_cau_nguoi_dung_doc_duoc(): void
    {
        Http::fake(['mcp.vidu.com/*' => Http::response('', 401)]);

        $this->actingAs(User::factory()->create())->post(route('web.connectors.store'), [
            'name' => 'Kho hàng',
            'slug' => 'kho',
            'url' => 'https://mcp.vidu.com/mcp',
            'auth_token' => 'token-sai',
        ]);

        // Thông điệp thô của tầng HTTP kèm mã lỗi cURL và link tài liệu, đọc như
        // phần mềm hỏng chứ không như một chỉ dẫn sửa được.
        $error = McpConnector::sole()->last_error;
        $this->assertStringContainsString('token', $error);
        $this->assertStringNotContainsString('cURL', $error);
        $this->assertStringNotContainsString('http', mb_strtolower(str_replace('HTTP', '', $error)));
    }

    #[Test]
    public function token_khong_bao_gio_hien_lai_tren_trang(): void
    {
        $user = User::factory()->create();
        McpConnector::factory()->for($user)->synced()->create([
            'slug' => 'kho',
            'auth_token' => 'bi-mat-cua-khach',
        ]);

        $this->actingAs($user)
            ->get(route('web.connectors.index'))
            ->assertOk()
            ->assertDontSee('bi-mat-cua-khach');
    }

    #[Test]
    public function khong_xoa_duoc_dich_vu_cua_nguoi_khac(): void
    {
        $stranger = McpConnector::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('web.connectors.destroy', $stranger))
            ->assertNotFound();

        $this->assertDatabaseHas('mcp_connectors', ['id' => $stranger->id]);
    }

    #[Test]
    public function dang_xuat_thi_khong_vao_lai_duoc(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
