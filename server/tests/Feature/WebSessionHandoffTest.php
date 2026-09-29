<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebSessionHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function handoffUrl(User $user, ?string $path = null): string
    {
        $url = $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson(route('auth.web-session.store'), array_filter(['path' => $path]))
            ->assertOk()
            ->json('url');

        // Bỏ token và người dùng đã nạp, để lượt mở link chạy đúng như một cửa
        // sổ trình duyệt chưa đăng nhập.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $url;
    }

    #[Test]
    public function mo_link_thi_dang_nhap_va_toi_trang_duoc_xin(): void
    {
        $user = User::factory()->create();
        $url = $this->handoffUrl($user, 'providers');

        $this->get($url)->assertRedirect(url('providers'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    #[Test]
    public function khong_ghi_duong_dan_thi_toi_tong_quan(): void
    {
        $url = $this->handoffUrl(User::factory()->create());

        $this->get($url)->assertRedirect(url('dashboard'));
    }

    #[Test]
    public function link_chi_dung_duoc_mot_lan(): void
    {
        $url = $this->handoffUrl(User::factory()->create());

        $this->get($url)->assertRedirect(url('dashboard'));

        // Mở lại từ một cửa sổ khác, không mang theo phiên vừa tạo.
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    #[Test]
    public function link_het_han_sau_mot_phut(): void
    {
        $url = $this->handoffUrl(User::factory()->create());

        $this->travel(61)->seconds();

        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    #[Test]
    public function thay_phien_cu_cua_tai_khoan_khac(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->create();
        $url = $this->handoffUrl($user);

        $this->actingAs($other)->get($url)->assertRedirect(url('dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    #[Test]
    public function tu_choi_duong_dan_tro_ra_ngoai(): void
    {
        $this->withToken(User::factory()->create()->createToken('test')->plainTextToken);

        foreach (['//evil.test', 'https://evil.test', '../admin', 'dashboard?next=x'] as $path) {
            $this->postJson(route('auth.web-session.store'), ['path' => $path])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('path');
        }
    }

    #[Test]
    public function can_token_moi_xin_duoc_link(): void
    {
        $this->postJson(route('auth.web-session.store'))->assertUnauthorized();
    }

    #[Test]
    public function token_la_thi_ve_trang_dang_nhap(): void
    {
        $this->get(route('session.handoff', ['token' => str_repeat('a', 64)]))->assertRedirect(route('login'));
    }
}
