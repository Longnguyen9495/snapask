<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function yeu_cau_dat_lai_mat_khau_thi_gui_thu(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'quen@example.com']);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'quen@example.com'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function email_khong_co_tai_khoan_van_nhan_cung_mot_cau_tra_loi(): void
    {
        Notification::fake();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'khongcoai@example.com'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    #[Test]
    public function bam_link_trong_thu_thi_doi_duoc_mat_khau(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'quen@example.com']);

        $this->post('/forgot-password', ['email' => 'quen@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $mail) use ($user): bool {
            $this->get('/reset-password/'.$mail->token.'?email='.urlencode($user->email))
                ->assertOk()
                ->assertSee($user->email);

            $this->post('/reset-password', [
                'token' => $mail->token,
                'email' => $user->email,
                'password' => 'matkhaumoi123',
                'password_confirmation' => 'matkhaumoi123',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('matkhaumoi123', $user->fresh()->password));
    }

    #[Test]
    public function token_sai_thi_khong_doi_duoc_mat_khau(): void
    {
        $user = User::factory()->create(['email' => 'quen@example.com', 'password' => 'matkhaucu123']);

        $this->from('/reset-password/sai')
            ->post('/reset-password', [
                'token' => 'sai',
                'email' => 'quen@example.com',
                'password' => 'matkhaumoi123',
                'password_confirmation' => 'matkhaumoi123',
            ])
            ->assertRedirect('/reset-password/sai')
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('matkhaucu123', $user->fresh()->password));
    }

    #[Test]
    public function hai_lan_nhap_mat_khau_khac_nhau_thi_bao_loi(): void
    {
        $user = User::factory()->create(['email' => 'quen@example.com', 'password' => 'matkhaucu123']);

        $this->from('/reset-password/token')
            ->post('/reset-password', [
                'token' => 'token',
                'email' => 'quen@example.com',
                'password' => 'matkhaumoi123',
                'password_confirmation' => 'khacmot123',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('matkhaucu123', $user->fresh()->password));
    }

    #[Test]
    public function doi_xong_thi_dang_nhap_duoc_bang_mat_khau_moi(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'quen@example.com']);

        $this->post('/forgot-password', ['email' => 'quen@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'quen@example.com',
            'password' => 'matkhaumoi123',
            'password_confirmation' => 'matkhaumoi123',
        ]);

        $this->post('/login', ['email' => 'quen@example.com', 'password' => 'matkhaumoi123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    public function trang_quen_mat_khau_mo_duoc_va_co_link_tu_trang_dang_nhap(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk();
    }

    /**
     * Laravel đổi locale sang `preferredLocale()` của người nhận trước khi dựng
     * nội dung; ở đây gọi thẳng `toMail()` nên phải tự đặt, rồi trả lại.
     */
    #[Test]
    public function thu_dat_lai_mat_khau_viet_bang_thu_tieng_cua_nguoi_nhan(): void
    {
        $user = User::factory()->create(['email' => 'quen@example.com', 'locale' => 'vi']);

        $this->app->setLocale('vi');
        $mail = (new ResetPassword('token-thu'))->toMail($user);

        $this->assertSame('Đặt lại mật khẩu SnapAsk', $mail->subject);
        $this->assertStringContainsString('reset-password/token-thu', $mail->actionUrl);
        $this->assertStringContainsString('Xin chào', $mail->greeting);

        $this->app->setLocale('en');
        $english = (new ResetPassword('token-thu'))->toMail($user);
        $this->assertSame('Reset your SnapAsk password', $english->subject);
    }
}
