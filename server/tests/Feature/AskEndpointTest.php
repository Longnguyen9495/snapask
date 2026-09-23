<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ModelProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AskEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('snapask.api_key', 'test-key');
        config()->set('snapask.model', 'qwen-vl-plus');
        config()->set('snapask.image.disk', 'captures');
        Storage::fake('captures');
    }

    /** Một luồng SSE hợp lệ của nhà cung cấp, đủ hai mẩu chữ và phần thống kê. */
    private function fakeProviderStream(): void
    {
        $lines = [
            'data: '.json_encode(['id' => 'x1', 'choices' => [['delta' => ['content' => 'Đây là ']]]]),
            'data: '.json_encode(['id' => 'x1', 'choices' => [['delta' => ['content' => 'màn hình lỗi.']]]]),
            'data: '.json_encode(['id' => 'x1', 'choices' => [[]], 'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 20]]),
            'data: [DONE]',
        ];

        Http::fake(['*' => Http::response(implode("\n\n", $lines)."\n\n", 200)]);
    }

    private const PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    #[Test]
    public function khach_chua_dang_nhap_khong_hoi_duoc(): void
    {
        $this->postJson(route('ask'), ['question' => 'Lỗi gì đây?'])
            ->assertUnauthorized();
    }

    #[Test]
    public function luot_hoi_dau_tien_luu_anh_hoi_thoai_va_phat_cau_tra_loi(): void
    {
        $this->fakeProviderStream();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('ask'), [
            'question' => 'Lỗi gì đây?',
            'image' => self::PIXEL,
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('"type":"delta"', $content);
        $this->assertStringContainsString('"type":"done"', $content);
        $this->assertStringContainsString('[DONE]', $content);

        $conversation = Conversation::sole();
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertNotNull($conversation->image_path);
        Storage::disk('captures')->assertExists($conversation->image_path);

        // Hạn xoá ảnh phải được đặt ngay lúc lưu, nếu không ảnh sẽ nằm lại mãi.
        $this->assertNotNull($conversation->image_expires_at);

        $answer = Message::where('role', 'assistant')->sole();
        $this->assertSame('Đây là màn hình lỗi.', $answer->content);
        $this->assertSame(900, $answer->prompt_tokens);
    }

    #[Test]
    public function anh_duoc_gui_kem_theo_dinh_dang_mo_hinh_doc_duoc(): void
    {
        $this->fakeProviderStream();

        $this->actingAs(User::factory()->create())->postJson(route('ask'), [
            'question' => 'Đọc giúp dòng đầu',
            'image' => self::PIXEL,
        ])->streamedContent();

        Http::assertSent(function ($request): bool {
            $content = collect($request['messages'])->firstWhere('role', 'user')['content'];

            return is_array($content)
                && $content[1]['type'] === 'image_url'
                && str_starts_with($content[1]['image_url']['url'], 'data:image/png;base64,');
        });
    }

    #[Test]
    public function het_han_muc_thi_bi_chan_truoc_khi_goi_mo_hinh(): void
    {
        Http::fake();
        $user = User::factory()->create(['monthly_ask_limit' => 1]);
        Message::factory()->for(Conversation::factory()->for($user))->create();

        $this->actingAs($user)
            ->postJson(route('ask'), ['question' => 'Còn hỏi được không?'])
            ->assertStatus(429)
            ->assertJsonPath('quota.remaining', 0);

        // Chặn sau khi gọi thì tiền token đã mất; phải chặn trước.
        Http::assertNothingSent();
    }

    #[Test]
    public function khach_mang_khoa_rieng_thi_khong_bi_han_muc_chan(): void
    {
        $this->fakeProviderStream();
        $user = User::factory()->create(['monthly_ask_limit' => 1]);
        $provider = ModelProvider::factory()->for($user)->create();
        $user->update(['active_provider_id' => $provider->id, 'active_model' => 'gpt-4o-mini']);
        Message::factory()->for(Conversation::factory()->for($user))->create();

        $this->actingAs($user)
            ->postJson(route('ask'), ['question' => 'Vẫn hỏi được chứ?', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk-cua-khach'));
    }

    #[Test]
    public function khong_noi_tiep_duoc_hoi_thoai_cua_nguoi_khac(): void
    {
        Http::fake();
        $stranger = Conversation::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('ask'), [
                'question' => 'Ảnh này là gì?',
                'conversation_id' => $stranger->id,
            ])
            ->assertJsonValidationErrorFor('conversation_id');

        Http::assertNothingSent();
    }

    #[Test]
    public function chua_cau_hinh_provider_thi_noi_ro_phai_lam_gi(): void
    {
        Http::fake();
        config()->set('snapask.api_key', null);

        $content = $this->actingAs(User::factory()->create())
            ->postJson(route('ask'), ['question' => 'Lỗi gì đây?', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        // Đây là thứ người dùng tự sửa được, không được thay bằng câu chung chung.
        $this->assertStringContainsString('trang quản lý', $content);
        $this->assertStringNotContainsString('thử lại sau', $content);
        Http::assertNothingSent();
    }

    #[Test]
    public function loi_cua_nha_cung_cap_khong_lo_chi_tiet_ra_may_khach(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Invalid api key sk-that-cua-he-thong'], 500)]);
        $user = User::factory()->create();

        $content = $this->actingAs($user)
            ->postJson(route('ask'), ['question' => 'Lỗi gì đây?', 'image' => self::PIXEL])
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"type":"error"', $content);
        $this->assertStringNotContainsString('sk-that-cua-he-thong', $content);
    }
}
