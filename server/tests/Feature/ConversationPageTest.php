<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function khach_chua_dang_nhap_bi_day_ve_trang_dang_nhap(): void
    {
        $this->get('/conversations')->assertRedirect(route('login'));
    }

    #[Test]
    public function danh_sach_hien_hoi_thoai_cua_chinh_minh(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Đọc hoá đơn']);
        Message::factory()->for($conversation)->count(3)->create();

        $this->actingAs($user)
            ->get('/conversations')
            ->assertOk()
            ->assertSee('Đọc hoá đơn', false)
            ->assertSee('3 lượt', false);
    }

    #[Test]
    public function khong_thay_hoi_thoai_cua_nguoi_khac(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);
        Conversation::factory()->create(['title' => 'Bí mật của người khác']);

        $this->actingAs($user)
            ->get('/conversations')
            ->assertOk()
            ->assertDontSee('Bí mật của người khác', false)
            ->assertSee('Chưa có gì ở đây', false);
    }

    #[Test]
    public function xem_duoc_noi_dung_mot_hoi_thoai(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);
        $conversation = Conversation::factory()->for($user)->create();

        Message::factory()->for($conversation)->create([
            'role' => 'user',
            'content' => 'Dòng thứ ba ghi gì?',
        ]);
        Message::factory()->for($conversation)->create([
            'role' => 'assistant',
            'content' => 'Ghi là tổng cộng 1.250.000 đồng.',
        ]);

        $this->actingAs($user)
            ->get(route('web.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Dòng thứ ba ghi gì?', false)
            ->assertSee('Ghi là tổng cộng 1.250.000 đồng.', false)
            ->assertSee('Bạn', false);
    }

    #[Test]
    public function khong_mo_duoc_hoi_thoai_cua_nguoi_khac(): void
    {
        // Đoán id là đọc được ảnh màn hình của người khác, nên phải chặn ở đây.
        $user = User::factory()->create();
        $other = Conversation::factory()->create();

        $this->actingAs($user)
            ->get(route('web.conversations.show', $other))
            ->assertNotFound();
    }

    #[Test]
    public function anh_da_qua_han_thi_noi_thang(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);
        $conversation = Conversation::factory()->for($user)->create([
            'image_path' => 'snapask/1/cu.png',
            'image_expires_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('web.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Ảnh chụp đã bị xoá.', false);
    }

    #[Test]
    public function xem_duoc_anh_khi_con_han(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('snapask/1/capture.png', 'gia-lam-anh-png');

        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create([
            'image_path' => 'snapask/1/capture.png',
            'image_expires_at' => now()->addDays(14),
        ]);

        $this->actingAs($user)
            ->get(route('web.conversations.image', $conversation))
            ->assertOk();
    }

    #[Test]
    public function khong_tai_duoc_anh_cua_nguoi_khac(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('snapask/9/rieng-tu.png', 'gia-lam-anh-png');

        $user = User::factory()->create();
        $other = Conversation::factory()->create([
            'image_path' => 'snapask/9/rieng-tu.png',
            'image_expires_at' => now()->addDays(14),
        ]);

        $this->actingAs($user)
            ->get(route('web.conversations.image', $other))
            ->assertNotFound();
    }

    #[Test]
    public function xoa_hoi_thoai_thi_xoa_luon_anh(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('snapask/1/capture.png', 'gia-lam-anh-png');

        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create([
            'image_path' => 'snapask/1/capture.png',
            'image_expires_at' => now()->addDays(14),
        ]);

        $this->actingAs($user)
            ->delete(route('web.conversations.destroy', $conversation))
            ->assertRedirect(route('web.conversations.index'));

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        Storage::disk('local')->assertMissing('snapask/1/capture.png');
    }

    #[Test]
    public function khong_xoa_duoc_hoi_thoai_cua_nguoi_khac(): void
    {
        $user = User::factory()->create();
        $other = Conversation::factory()->create();

        $this->actingAs($user)
            ->delete(route('web.conversations.destroy', $other))
            ->assertNotFound();

        $this->assertDatabaseHas('conversations', ['id' => $other->id]);
    }

    #[Test]
    public function trang_lich_su_co_ban_tieng_anh(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get('/conversations')
            ->assertOk()
            ->assertSee('Conversation history', false)
            ->assertSee('Nothing here yet', false);
    }
}
