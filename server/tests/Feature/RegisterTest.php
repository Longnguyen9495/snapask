<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Long',
            'email' => 'long@snapask.test',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
            ...$overrides,
        ];
    }

    #[Test]
    public function dang_ky_tu_app_thi_nhan_luon_token(): void
    {
        $this->postJson(route('auth.register'), $this->payload(['device_name' => 'May cua Long']))
            ->assertCreated()
            ->assertJsonPath('user.email', 'long@snapask.test')
            ->assertJsonStructure(['token']);

        // Cấp token ngay để người vừa tạo tài khoản không phải gõ lại mật khẩu.
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'May cua Long']);
    }

    #[Test]
    public function tai_khoan_moi_nhan_dung_goi_mac_dinh(): void
    {
        config()->set('snapask.plans.default', ['name' => 'trial', 'monthly_ask_limit' => 30]);

        $this->postJson(route('auth.register'), $this->payload());

        $user = User::sole();
        $this->assertSame('trial', $user->plan);
        $this->assertSame(30, $user->monthly_ask_limit);
    }

    #[Test]
    public function mat_khau_duoc_bam_chu_khong_luu_tran(): void
    {
        $this->postJson(route('auth.register'), $this->payload());

        $this->assertNotSame('matkhau123', User::sole()->password);
    }

    #[Test]
    public function email_da_co_tai_khoan_thi_bi_tu_choi(): void
    {
        User::factory()->create(['email' => 'long@snapask.test']);

        $this->postJson(route('auth.register'), $this->payload())
            ->assertJsonValidationErrorFor('email');

        $this->assertSame(1, User::count());
    }

    #[Test]
    public function hai_lan_nhap_mat_khau_lech_nhau_thi_bi_tu_choi(): void
    {
        $this->postJson(route('auth.register'), $this->payload(['password_confirmation' => 'khac-han']))
            ->assertJsonValidationErrorFor('password');

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function mat_khau_qua_ngan_thi_bi_tu_choi(): void
    {
        $this->postJson(route('auth.register'), $this->payload([
            'password' => 'ngan',
            'password_confirmation' => 'ngan',
        ]))->assertJsonValidationErrorFor('password');
    }

    #[Test]
    public function dang_ky_tren_web_thi_vao_thang_trang_tong_quan(): void
    {
        $this->post(route('register.store'), $this->payload())
            ->assertRedirect(route('dashboard'));

        // Đăng nhập luôn sau khi tạo, không bắt quay lại màn hình đăng nhập.
        $this->assertAuthenticated();
    }
}
