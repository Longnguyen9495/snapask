<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiLocaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function me_tra_ve_ngon_ngu_cua_tai_khoan(): void
    {
        // Ứng dụng desktop đọc trường này để mở lên đúng thứ tiếng người dùng
        // đã chọn, kể cả khi họ chọn trên trang web.
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.locale', 'en');
    }

    #[Test]
    public function doi_duoc_ngon_ngu_qua_api(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', ['locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('user.locale', 'en');

        $this->assertSame('en', $user->refresh()->locale);
    }

    #[Test]
    public function ngon_ngu_la_bi_tu_choi(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', ['locale' => 'fr'])
            ->assertStatus(422);

        $this->assertSame('vi', $user->refresh()->locale);
    }

    #[Test]
    public function khach_chua_dang_nhap_khong_doi_duoc(): void
    {
        $this->patchJson('/api/me', ['locale' => 'en'])->assertUnauthorized();
    }

    #[Test]
    public function loi_api_duoc_dich_theo_accept_language(): void
    {
        // Ứng dụng desktop gửi Accept-Language ở mọi lời gọi, nên câu báo lỗi
        // quay về đã đúng thứ tiếng người dùng đang xem.
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Accept-Language', 'en')
            ->postJson('/api/ask', ['question' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.question.0', 'Enter a question.');

        $this->actingAs($user, 'sanctum')
            ->withHeader('Accept-Language', 'vi')
            ->postJson('/api/ask', ['question' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.question.0', 'Hãy nhập câu hỏi.');
    }
}
