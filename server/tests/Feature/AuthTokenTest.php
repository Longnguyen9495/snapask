<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dang_nhap_dung_thi_nhan_duoc_token(): void
    {
        $user = User::factory()->create(['email' => 'demo@snapask.test']);

        $this->postJson(route('auth.token.store'), [
            'email' => 'demo@snapask.test',
            'password' => 'password',
            'device_name' => 'SnapAsk · May-cua-Long',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'demo@snapask.test')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'SnapAsk · May-cua-Long',
        ]);
    }

    #[Test]
    public function dang_nhap_lai_tren_cung_may_thu_hoi_token_cu(): void
    {
        User::factory()->create(['email' => 'demo@snapask.test']);
        $payload = [
            'email' => 'demo@snapask.test',
            'password' => 'password',
            'device_name' => 'May-cua-Long',
        ];

        $first = $this->postJson(route('auth.token.store'), $payload)->json('token');
        $second = $this->postJson(route('auth.token.store'), $payload)->json('token');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function sai_mat_khau_thi_bi_tu_choi(): void
    {
        User::factory()->create(['email' => 'demo@snapask.test']);

        $this->postJson(route('auth.token.store'), [
            'email' => 'demo@snapask.test',
            'password' => 'sai-mat-khau',
            'device_name' => 'May-cua-Long',
        ])->assertJsonValidationErrorFor('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function token_bi_thu_hoi_thi_khong_goi_tiep_duoc(): void
    {
        User::factory()->create(['email' => 'demo@snapask.test']);
        $token = $this->postJson(route('auth.token.store'), [
            'email' => 'demo@snapask.test',
            'password' => 'password',
            'device_name' => 'May-cua-Long',
        ])->json('token');

        $this->withToken($token)->deleteJson(route('auth.token.destroy'))->assertOk();

        // Guard giữ lại người dùng đã giải mã trong cùng một tiến trình kiểm thử;
        // không quên đi thì lượt gọi sau đậu nhờ bộ nhớ chứ không nhờ token.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson(route('me'))->assertUnauthorized();
    }
}
