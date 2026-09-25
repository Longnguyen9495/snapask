<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('snapask.image.disk', 'captures');
        Storage::fake('captures');
    }

    #[Test]
    public function khong_co_token_thi_bi_tu_choi(): void
    {
        $conversation = Conversation::factory()->create();

        $this->getJson(route('conversations.index'))->assertUnauthorized();
        $this->getJson(route('conversations.show', $conversation))->assertUnauthorized();
        $this->getJson(route('conversations.image', $conversation))->assertUnauthorized();
        $this->patchJson(route('conversations.update', $conversation), ['title' => 'x'])->assertUnauthorized();
        $this->deleteJson(route('conversations.destroy', $conversation))->assertUnauthorized();
    }

    #[Test]
    public function danh_sach_chi_co_hoi_thoai_cua_minh_kem_du_truong_moi(): void
    {
        $user = User::factory()->create();
        $mine = Conversation::factory()->for($user)->create(['title' => 'Hoá đơn tháng 9']);
        Message::factory()->for($mine)->create(['content' => 'Tổng là bao nhiêu?']);
        Message::factory()->for($mine)->fromAssistant()->create(['content' => "**Tổng cộng**\n\n- `1.250.000` đồng."]);
        Conversation::factory()->create(['title' => 'Của người khác']);

        $response = $this->actingAs($user)->getJson(route('conversations.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.messages_count', 2)
            ->assertJsonPath('data.0.last_message_preview', 'Tổng cộng 1.250.000 đồng.')
            ->assertJsonPath('data.0.has_image', false)
            ->assertJsonPath('data.0.image', null)
            // Khung phân trang cũ vẫn giữ nguyên cho bản desktop trước.
            ->assertJsonStructure([
                'data' => [['id', 'title', 'model', 'created_at', 'updated_at', 'last_activity_at']],
                'current_page', 'last_page', 'per_page', 'total',
            ])
            ->assertJsonMissingPath('data.0.image_path');
    }

    #[Test]
    public function danh_sach_xep_theo_lan_hoat_dong_gan_nhat(): void
    {
        $user = User::factory()->create();

        $this->travelTo(now()->subDays(3));
        $older = Conversation::factory()->for($user)->create(['title' => 'Cũ']);
        $this->travelBack();

        $this->travelTo(now()->subDay());
        $newer = Conversation::factory()->for($user)->create(['title' => 'Mới hơn']);
        $this->travelBack();

        // Hỏi tiếp hội thoại cũ thì nó phải nổi lên đầu.
        Message::factory()->for($older)->create();

        $this->actingAs($user)
            ->getJson(route('conversations.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);
    }

    #[Test]
    public function phan_trang_hai_muoi_hoi_thoai_moi_trang(): void
    {
        $user = User::factory()->create();
        Conversation::factory()->for($user)->count(23)->create();

        $this->actingAs($user)
            ->getJson(route('conversations.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 23);
    }

    #[Test]
    public function tim_theo_tieu_de_va_noi_dung_tin_nhan(): void
    {
        $user = User::factory()->create();
        $byTitle = Conversation::factory()->for($user)->create(['title' => 'Lỗi đăng nhập ngân hàng']);
        $byContent = Conversation::factory()->for($user)->create(['title' => 'Không tiêu đề']);
        Message::factory()->for($byContent)->create(['content' => 'Màn hình báo lỗi đăng nhập sai']);
        Conversation::factory()->for($user)->create(['title' => 'Chuyện khác']);
        $stranger = Conversation::factory()->create(['title' => 'đăng nhập của người khác']);

        $ids = collect(
            $this->actingAs($user)
                ->getJson(route('conversations.index', ['search' => 'đăng nhập']))
                ->assertOk()
                ->json('data'),
        )->pluck('id');

        $this->assertEqualsCanonicalizing([$byTitle->id, $byContent->id], $ids->all());
        $this->assertNotContains($stranger->id, $ids->all());
    }

    #[Test]
    public function ky_tu_dai_dien_trong_o_tim_bi_thoat(): void
    {
        $user = User::factory()->create();
        $exact = Conversation::factory()->for($user)->create(['title' => 'Giảm 50% phí']);
        Conversation::factory()->for($user)->create(['title' => 'Giảm 500 nghìn']);

        $this->actingAs($user)
            ->getJson(route('conversations.index', ['search' => '50%']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $exact->id);
    }

    #[Test]
    public function o_tim_qua_dai_bi_tu_choi(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('conversations.index', ['search' => str_repeat('a', Conversation::SEARCH_MAX_LENGTH + 1)]))
            ->assertJsonValidationErrorFor('search');
    }

    #[Test]
    public function loc_theo_hoi_thoai_co_anh(): void
    {
        $user = User::factory()->create();
        $withImage = Conversation::factory()->for($user)->withImage()->create();
        Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson(route('conversations.index', ['screenshot' => 'with']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withImage->id)
            ->assertJsonPath('data.0.has_image', true)
            ->assertJsonPath('data.0.image.available', true);
    }

    #[Test]
    public function chi_tiet_kem_cong_cu_va_thong_tin_anh(): void
    {
        $user = User::factory()->create();
        Storage::disk('captures')->put('snapask/1/capture.png', 'png');
        $conversation = Conversation::factory()->for($user)->withImage()->create([
            'image_width' => 800,
            'image_height' => 600,
        ]);
        Message::factory()->for($conversation)->create(['content' => 'Còn hàng không?']);
        Message::factory()->for($conversation)->fromAssistant()->create([
            'content' => 'Còn 12 cái.',
            'tools_used' => [['tool' => 'kho__ton_kho', 'arguments' => ['sku' => 'A1']]],
        ]);

        $this->actingAs($user)
            ->getJson(route('conversations.show', $conversation))
            ->assertOk()
            ->assertJsonPath('conversation.id', $conversation->id)
            ->assertJsonPath('conversation.messages_count', 2)
            ->assertJsonPath('conversation.image.available', true)
            ->assertJsonPath('conversation.image.width', 800)
            ->assertJsonPath('messages.0.role', 'user')
            ->assertJsonPath('messages.0.tools_used', [])
            ->assertJsonPath('messages.1.tools_used.0.tool', 'kho__ton_kho')
            ->assertJsonPath('messages.1.tools_used.0.arguments.sku', 'A1')
            ->assertJsonMissingPath('conversation.image_path');
    }

    #[Test]
    public function chi_tiet_bao_anh_khong_con_khi_file_da_mat(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->withImage('snapask/1/mat.png')->create();

        $this->actingAs($user)
            ->getJson(route('conversations.show', $conversation))
            ->assertOk()
            ->assertJsonPath('conversation.has_image', true)
            ->assertJsonPath('conversation.image.available', false);
    }

    #[Test]
    public function khong_xem_duoc_hoi_thoai_cua_nguoi_khac(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('conversations.show', Conversation::factory()->create()))
            ->assertNotFound();
    }

    #[Test]
    public function tai_duoc_anh_cua_minh_khi_con_han(): void
    {
        $user = User::factory()->create();
        Storage::disk('captures')->put('snapask/1/capture.png', 'png');
        $conversation = Conversation::factory()->for($user)->withImage()->create();

        $response = $this->actingAs($user)->get(route('conversations.image', $conversation));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function anh_qua_han_mat_file_hoac_cua_nguoi_khac_tra_404(): void
    {
        $user = User::factory()->create();
        Storage::disk('captures')->put('snapask/1/expired.png', 'png');
        Storage::disk('captures')->put('snapask/9/rieng-tu.png', 'png');

        $expired = Conversation::factory()->for($user)->expired()->create();
        $missing = Conversation::factory()->for($user)->withImage('snapask/1/khong-co.png')->create();
        $noImage = Conversation::factory()->for($user)->create();
        $stranger = Conversation::factory()->withImage('snapask/9/rieng-tu.png')->create();

        $this->actingAs($user);

        $this->getJson(route('conversations.image', $expired))->assertNotFound();
        $this->getJson(route('conversations.image', $missing))->assertNotFound();
        $this->getJson(route('conversations.image', $noImage))->assertNotFound();
        $this->getJson(route('conversations.image', $stranger))->assertNotFound();
    }

    #[Test]
    public function doi_ten_duoc_cat_khoang_trang_va_khong_day_len_dau(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->subDay());
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Cũ']);
        $this->travelBack();
        $before = $conversation->updated_at->toJSON();

        $this->actingAs($user)
            ->patchJson(route('conversations.update', $conversation), ['title' => '  Hoá đơn điện  '])
            ->assertOk()
            ->assertJsonPath('conversation.title', 'Hoá đơn điện')
            ->assertJsonPath('conversation.updated_at', $before);

        $this->assertSame('Hoá đơn điện', $conversation->fresh()->title);
    }

    #[Test]
    public function doi_ten_kiem_tra_do_dai_va_chu_so_huu(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Giữ nguyên']);
        $stranger = Conversation::factory()->create(['title' => 'Của người khác']);

        $this->actingAs($user);

        $this->patchJson(route('conversations.update', $conversation), ['title' => '   '])
            ->assertJsonValidationErrorFor('title');
        $this->patchJson(route('conversations.update', $conversation), ['title' => str_repeat('a', Conversation::TITLE_MAX_LENGTH + 1)])
            ->assertJsonValidationErrorFor('title');
        $this->patchJson(route('conversations.update', $stranger), ['title' => 'Chiếm'])
            ->assertNotFound();

        $this->assertSame('Giữ nguyên', $conversation->fresh()->title);
        $this->assertSame('Của người khác', $stranger->fresh()->title);
    }

    #[Test]
    public function xoa_hoi_thoai_xoa_ca_tin_nhan_va_anh(): void
    {
        $user = User::factory()->create();
        Storage::disk('captures')->put('snapask/1/capture.png', 'png');
        $conversation = Conversation::factory()->for($user)->withImage()->create();
        Message::factory()->for($conversation)->count(2)->create();

        $this->actingAs($user)
            ->deleteJson(route('conversations.destroy', $conversation))
            ->assertOk()
            ->assertExactJson(['deleted' => true, 'id' => $conversation->id]);

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id]);
        Storage::disk('captures')->assertMissing('snapask/1/capture.png');
    }

    #[Test]
    public function khong_xoa_duoc_hoi_thoai_cua_nguoi_khac(): void
    {
        $stranger = Conversation::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('conversations.destroy', $stranger))
            ->assertNotFound();

        $this->assertDatabaseHas('conversations', ['id' => $stranger->id]);
    }

    #[Test]
    public function ho_so_kem_trang_thai_xac_thuc_va_mo_hinh_dang_dung(): void
    {
        config()->set('snapask.model', 'qwen-vl-plus');

        $this->actingAs(User::factory()->create())
            ->getJson(route('me'))
            ->assertOk()
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonPath('setup.source', 'default')
            ->assertJsonPath('setup.model', 'qwen-vl-plus')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'locale'], 'quota' => ['plan', 'limit', 'used', 'remaining', 'own_key']]);
    }
}
