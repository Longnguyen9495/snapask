<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\McpConnector;
use App\Models\Message;
use App\Models\ModelProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalPageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function portalPages(): array
    {
        return [
            'tổng quan' => ['/dashboard'],
            'hội thoại' => ['/conversations'],
            'mô hình' => ['/providers'],
            'dịch vụ' => ['/connectors'],
            'tài khoản' => ['/account'],
            'cài đặt' => ['/settings'],
        ];
    }

    #[Test]
    #[DataProvider('portalPages')]
    public function khach_chua_dang_nhap_bi_day_ve_dang_nhap(string $path): void
    {
        $this->get($path)->assertRedirect(route('login'));
    }

    #[Test]
    #[DataProvider('portalPages')]
    public function moi_trang_deu_co_dieu_huong_dang_xuat_va_doi_ngon_ngu(string $path): void
    {
        $user = User::factory()->create(['locale' => 'vi']);

        $this->actingAs($user)
            ->get($path)
            ->assertOk()
            ->assertSee('id="portal-nav"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('action="'.route('locale.switch', 'en').'"', false)
            ->assertSee(route('download'), false)
            ->assertSee('Tổng quan', false);
    }

    #[Test]
    public function muc_dang_mo_duoc_danh_dau_trong_dieu_huong(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'en']))
            ->get('/providers')
            ->assertOk()
            ->assertSeeInOrder(['href="'.route('web.providers.index').'"', 'aria-current="page"', 'AI models'], false);
    }

    #[Test]
    public function nguoi_da_dang_nhap_mo_trang_dang_nhap_thi_ve_tong_quan(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function tong_quan_hien_han_muc_mo_hinh_dich_vu_va_hoi_thoai_gan_day(): void
    {
        config()->set('snapask.api_key', 'khoa-he-thong');
        config()->set('snapask.model', 'qwen-vl-plus');

        $user = User::factory()->create(['locale' => 'vi', 'name' => 'Lan Anh', 'monthly_ask_limit' => 50]);
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Đọc hoá đơn điện']);
        Message::factory()->for($conversation)->create(['content' => 'Tháng này bao nhiêu?']);
        McpConnector::factory()->for($user)->synced()->create(['slug' => 'kho']);
        McpConnector::factory()->for($user)->create(['slug' => 'loi', 'last_error' => 'Không kết nối được.']);
        Conversation::factory()->create(['title' => 'Của người khác']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Chào Lan Anh', false)
            ->assertSee('49', false)
            ->assertSee('qwen-vl-plus', false)
            ->assertSee('1 hoạt động tốt', false)
            ->assertSee('1 đang lỗi', false)
            ->assertSee('Đọc hoá đơn điện', false)
            ->assertSee('Tháng này bao nhiêu?', false)
            ->assertDontSee('Của người khác', false);
    }

    #[Test]
    public function danh_sach_viec_can_lam_bien_mat_khi_da_xong(): void
    {
        config()->set('snapask.api_key', 'khoa-he-thong');
        $user = User::factory()->create(['locale' => 'vi']);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Hoàn tất thiết lập', false);

        $user->createToken('SnapAsk · may-tinh');
        Conversation::factory()->for($user)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Hoàn tất thiết lập', false);
    }

    #[Test]
    public function trang_tai_khoan_hien_goi_han_muc_va_dang_xuat(): void
    {
        $user = User::factory()->create(['locale' => 'en', 'email' => 'lan@example.com', 'plan' => 'free', 'monthly_ask_limit' => 50]);

        $this->actingAs($user)
            ->get(route('web.account'))
            ->assertOk()
            ->assertSee('lan@example.com')
            ->assertSee('Verified')
            ->assertSee('50 of 50 left', false)
            ->assertSee('Sign out');
    }

    #[Test]
    public function trang_cai_dat_giai_thich_thoi_han_luu_anh(): void
    {
        config()->set('snapask.image.retention_days', 14);

        $this->actingAs(User::factory()->create(['locale' => 'en']))
            ->get(route('web.settings'))
            ->assertOk()
            ->assertSee('Deleted automatically 14 days after the question.', false)
            ->assertSee('English');
    }

    #[Test]
    public function tim_hoi_thoai_tren_web_va_giu_bo_loc_khi_phan_trang(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);
        Conversation::factory()->for($user)->count(21)->create(['title' => 'Hoá đơn điện']);
        Conversation::factory()->for($user)->create(['title' => 'Lịch họp']);

        $response = $this->actingAs($user)->get(route('web.conversations.index', ['search' => 'Hoá đơn']));

        $response->assertOk()
            ->assertSee('21 hội thoại khớp', false)
            ->assertDontSee('Lịch họp', false)
            ->assertSee('search=Ho%C3%A1', false);
    }

    #[Test]
    public function loc_hoi_thoai_chi_co_chu(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);
        Conversation::factory()->for($user)->withImage()->create(['title' => 'Có ảnh']);
        Conversation::factory()->for($user)->create(['title' => 'Chỉ có chữ thôi']);

        $this->actingAs($user)
            ->get(route('web.conversations.index', ['screenshot' => 'without']))
            ->assertOk()
            ->assertSee('Chỉ có chữ thôi', false)
            ->assertDontSee('Có ảnh</a>', false);
    }

    #[Test]
    public function khong_tim_thay_thi_co_huong_xoa_bo_loc(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);
        Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('web.conversations.index', ['search' => 'khong-co-gi-khop']))
            ->assertOk()
            ->assertSee('Không có hội thoại nào khớp', false)
            ->assertSee('Xoá bộ lọc', false);
    }

    #[Test]
    public function chi_tiet_hien_cong_cu_da_goi_va_markdown_an_toan(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);
        $conversation = Conversation::factory()->for($user)->create();
        Message::factory()->for($conversation)->fromAssistant()->create([
            'content' => "**Còn 12 cái.**\n\n<script>alert(1)</script>\n\n[bấm](javascript:alert(1))",
            'tools_used' => [['tool' => 'kho__ton_kho', 'arguments' => ['sku' => 'A1']]],
        ]);

        $this->actingAs($user)
            ->get(route('web.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('<strong>Còn 12 cái.</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false)
            ->assertSee('kho__ton_kho', false)
            ->assertSee('Đã tra 1 công cụ', false);
    }

    #[Test]
    public function chi_tiet_chi_hien_anh_khi_file_con_tren_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('snapask/1/giu.png', 'png');

        $user = User::factory()->create(['locale' => 'vi']);
        $kept = Conversation::factory()->for($user)->withImage('snapask/1/giu.png')->create();
        $lost = Conversation::factory()->for($user)->withImage('snapask/1/mat.png')->create();

        $this->actingAs($user)
            ->get(route('web.conversations.show', $kept))
            ->assertOk()
            ->assertSee('/conversations/'.$kept->id.'/image"', false);

        $this->actingAs($user)
            ->get(route('web.conversations.show', $lost))
            ->assertOk()
            ->assertDontSee('/conversations/'.$lost->id.'/image"', false)
            ->assertSee('Ảnh chụp đã bị xoá.', false);
    }

    #[Test]
    public function doi_ten_hoi_thoai_tren_web(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Cũ']);

        $this->actingAs($user)
            ->patch(route('web.conversations.update', $conversation), ['title' => '  Hoá đơn tháng 9 '])
            ->assertRedirect(route('web.conversations.show', $conversation));

        $this->assertSame('Hoá đơn tháng 9', $conversation->fresh()->title);
    }

    #[Test]
    public function doi_ten_rong_hoac_cua_nguoi_khac_bi_tu_choi(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Giữ nguyên']);
        $stranger = Conversation::factory()->create(['title' => 'Của người khác']);

        $this->actingAs($user)
            ->patch(route('web.conversations.update', $conversation), ['title' => ' '])
            ->assertSessionHasErrorsIn('rename', 'title');

        $this->actingAs($user)
            ->patch(route('web.conversations.update', $stranger), ['title' => 'Chiếm'])
            ->assertNotFound();

        $this->assertSame('Giữ nguyên', $conversation->fresh()->title);
        $this->assertSame('Của người khác', $stranger->fresh()->title);
    }

    #[Test]
    public function thao_tac_thay_doi_du_lieu_can_csrf(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        // Bật lại kiểm tra CSRF mà môi trường test vốn bỏ qua.
        $this->withMiddleware()->app->instance('env', 'local');

        $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', 'sai')
            ->call('DELETE', route('web.conversations.destroy', $conversation), [], [], [], ['HTTP_X_CSRF_TOKEN' => 'sai'])
            ->assertStatus(419);

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }

    #[Test]
    public function sua_provider_bo_trong_khoa_thi_giu_khoa_cu(): void
    {
        $user = User::factory()->create();
        $provider = ModelProvider::factory()->for($user)->create(['api_key' => 'sk-cu', 'models' => ['gpt-4o-mini']]);

        $this->actingAs($user)
            ->put(route('web.providers.update', $provider), [
                'name' => 'Đổi tên',
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => '',
                'api_format' => 'openai-chat',
                'models' => "gpt-4o-mini\ngpt-4o",
            ])
            ->assertRedirect(route('web.providers.index'));

        $provider->refresh();
        $this->assertSame('Đổi tên', $provider->name);
        $this->assertSame('sk-cu', $provider->api_key);
        $this->assertSame(['gpt-4o-mini', 'gpt-4o'], $provider->models);
    }

    #[Test]
    public function loi_khi_sua_provider_nam_trong_tui_loi_rieng(): void
    {
        $user = User::factory()->create();
        $provider = ModelProvider::factory()->for($user)->create();

        $this->actingAs($user)
            ->put(route('web.providers.update', $provider), [
                'name' => 'X',
                'base_url' => 'http://khong-an-toan.vn',
                'api_format' => 'openai-chat',
                'models' => 'gpt-4o',
            ])
            ->assertSessionHasErrorsIn('provider'.$provider->id, 'base_url');
    }

    #[Test]
    public function sua_dich_vu_bo_trong_token_thi_giu_token_cu_con_danh_dau_go_thi_xoa(): void
    {
        Http::fake(['*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['tools' => []]])]);
        $user = User::factory()->create();
        $connector = McpConnector::factory()->for($user)->create(['slug' => 'kho', 'auth_token' => 'token-cu']);

        $payload = ['name' => 'Kho mới', 'slug' => 'kho', 'url' => 'https://mcp.vidu.com/mcp', 'auth_token' => ''];

        $this->actingAs($user)->put(route('web.connectors.update', $connector), $payload)
            ->assertRedirect(route('web.connectors.index'));
        $this->assertSame('token-cu', $connector->fresh()->auth_token);
        $this->assertSame('Kho mới', $connector->fresh()->name);

        $this->actingAs($user)->put(route('web.connectors.update', $connector), [...$payload, 'clear_token' => '1']);
        $this->assertNull($connector->fresh()->auth_token);
    }

    #[Test]
    public function bat_tat_dich_vu_va_kiem_tra_chu_so_huu(): void
    {
        $user = User::factory()->create();
        $connector = McpConnector::factory()->for($user)->synced()->create(['slug' => 'kho']);
        $stranger = McpConnector::factory()->synced()->create(['slug' => 'khac']);

        $this->actingAs($user)->post(route('web.connectors.toggle', $connector))
            ->assertRedirect(route('web.connectors.index'));
        $this->assertFalse($connector->fresh()->enabled);

        $this->actingAs($user)->post(route('web.connectors.toggle', $connector));
        $this->assertTrue($connector->fresh()->enabled);

        $this->actingAs($user)->post(route('web.connectors.toggle', $stranger))->assertNotFound();
        $this->assertTrue($stranger->fresh()->enabled);
    }

    #[Test]
    public function trang_dich_vu_tom_tat_tinh_trang(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        McpConnector::factory()->for($user)->synced()->create(['slug' => 'a']);
        McpConnector::factory()->for($user)->create(['slug' => 'b', 'last_error' => 'The token was rejected.']);
        McpConnector::factory()->for($user)->synced()->create(['slug' => 'c', 'enabled' => false]);

        $this->actingAs($user)
            ->get(route('web.connectors.index'))
            ->assertOk()
            ->assertSeeInOrder(['Connected', '3', 'Healthy', '1', 'Failing', '1', 'Disabled', '1'])
            ->assertSee('The token was rejected.')
            ->assertSee('Check that the address is reachable over HTTPS', false);
    }

    #[Test]
    public function trang_chu_cho_khach_hien_dang_nhap_va_tai_ve(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('login'), false)
            ->assertDontSee(route('dashboard'), false);
    }
}
