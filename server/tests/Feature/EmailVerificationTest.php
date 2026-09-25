<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function register(): array
    {
        return [
            'name' => 'Kiểm thử',
            'email' => 'moi@example.com',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
        ];
    }

    #[Test]
    public function tat_xac_thuc_thi_dang_ky_xong_dung_ngay_va_khong_gui_thu(): void
    {
        config(['snapask.verify_email' => false]);
        Notification::fake();

        $this->post('/register', $this->register())->assertRedirect(route('dashboard'));
        $this->get('/connectors')->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function bat_xac_thuc_thi_dang_ky_web_gui_thu_va_chan_trang_quan_tri(): void
    {
        config(['snapask.verify_email' => true]);
        Notification::fake();

        $this->post('/register', $this->register());

        $user = User::where('email', 'moi@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get('/connectors')->assertRedirect(route('verification.notice'));
        $this->get('/email/verify')->assertOk()->assertSee('moi@example.com');
    }

    #[Test]
    public function bam_link_trong_thu_thi_xac_thuc_xong(): void
    {
        config(['snapask.verify_email' => true]);
        $user = User::factory()->unverified()->create();

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($link)->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user->fresh())->get('/connectors')->assertOk();
    }

    #[Test]
    public function gui_lai_link_xac_thuc(): void
    {
        config(['snapask.verify_email' => true]);
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from('/email/verify')
            ->post('/email/verification-notification')
            ->assertRedirect('/email/verify');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function dang_ky_qua_api_chua_cap_token_khi_can_xac_thuc(): void
    {
        config(['snapask.verify_email' => true]);
        Notification::fake();

        $this->postJson('/api/auth/register', [...$this->register(), 'device_name' => 'Máy thử'])
            ->assertCreated()
            ->assertJsonPath('verification_required', true)
            ->assertJsonMissingPath('token');
    }

    #[Test]
    public function chua_xac_thuc_thi_khong_lay_duoc_token_va_duoc_gui_lai_link(): void
    {
        config(['snapask.verify_email' => true]);
        Notification::fake();
        $user = User::factory()->unverified()->create(['password' => 'matkhau123']);

        $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'matkhau123',
            'device_name' => 'Máy thử',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function token_cua_tai_khoan_chua_xac_thuc_khong_goi_duoc_api(): void
    {
        config(['snapask.verify_email' => true]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertForbidden();
    }
}
