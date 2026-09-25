<?php

namespace Tests\Feature;

use App\Services\DemoScenes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bản dùng thử trên trang chủ: khách hỏi AI thật mà chưa có tài khoản.
 *
 * Đây là cửa duy nhất mở ra mô hình mà không qua đăng nhập, nên phần lớn bài
 * kiểm ở đây là về chuyện gác cửa chứ không phải về câu trả lời.
 */
class DemoAskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'snapask.demo.enabled' => true,
            'snapask.demo.per_ip_hourly' => 3,
            'snapask.demo.daily_total' => 50,
            'snapask.base_url' => 'https://mo-hinh.test/v1',
            'snapask.api_key' => 'sk-test',
            'snapask.model' => 'test-model',
            'snapask.api_format' => 'openai-chat',
        ]);

        RateLimiter::clear('demo-ask:ip:'.sha1('127.0.0.1'));
        Cache::flush();
    }

    /**
     * Giả một lượt trả lời dạng SSE, đúng hình dạng nhà cung cấp thật trả về.
     *
     * Dựng response mới cho mỗi lần gọi: luồng chỉ đọc được một lần, nên dùng
     * lại một đối tượng cho nhiều lượt sẽ ném "Stream is detached" ở lượt thứ hai.
     */
    private function fakeModel(string $answer): void
    {
        $body = 'data: '.json_encode(['choices' => [['delta' => ['content' => $answer]]]])."\n\n"
            ."data: [DONE]\n\n";

        Http::fake(fn () => Http::response($body, 200, ['Content-Type' => 'text/event-stream']));
    }

    #[Test]
    public function khach_chua_dang_nhap_hoi_duoc_va_nhan_cau_tra_loi(): void
    {
        $this->fakeModel('Tổng thanh toán là 21.217.680 đồng.');

        $this->postJson(route('demo.ask'), [
            'scene' => 'invoice',
            'question' => 'Tổng bao nhiêu?',
        ])
            ->assertOk()
            ->assertJsonStructure(['answer', 'remaining'])
            ->assertJsonPath('answer', 'Tổng thanh toán là 21.217.680 đồng.');
    }

    #[Test]
    public function man_hinh_mau_la_bi_tu_choi(): void
    {
        // Mã cảnh do người gửi quyết định, nên phải nằm trong danh sách cho phép:
        // nếu không, nó thành một ô nhập tuỳ ý đi thẳng vào prompt.
        $this->postJson(route('demo.ask'), ['scene' => 'bia-ra', 'question' => 'x'])
            ->assertStatus(422);
    }

    #[Test]
    public function cau_hoi_rong_hoac_qua_dai_bi_tu_choi(): void
    {
        $this->postJson(route('demo.ask'), ['scene' => 'code', 'question' => ''])
            ->assertStatus(422);

        $this->postJson(route('demo.ask'), [
            'scene' => 'code',
            'question' => str_repeat('a', (int) config('snapask.demo.max_question_length') + 1),
        ])->assertStatus(422);
    }

    #[Test]
    public function het_luot_theo_dia_chi_thi_bi_chan_va_duoc_moi_tao_tai_khoan(): void
    {
        $this->fakeModel('xong');

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('demo.ask'), ['scene' => 'code', 'question' => 'vì sao lỗi?'])->assertOk();
        }

        $this->postJson(route('demo.ask'), ['scene' => 'code', 'question' => 'vì sao lỗi?'])
            ->assertStatus(429)
            ->assertJsonPath('message', __('That is all the demo questions for now. Create a free account to keep going.'));
    }

    #[Test]
    public function tran_toan_he_thong_moi_ngay_chan_duoc_dot_dội(): void
    {
        $this->fakeModel('xong');

        Cache::put('demo-ask:daily:'.now()->toDateString(), 50, now()->addDay());

        $this->postJson(route('demo.ask'), ['scene' => 'code', 'question' => 'vì sao lỗi?'])
            ->assertStatus(429);
    }

    #[Test]
    public function loi_cua_nha_cung_cap_khong_lam_mat_luot_cua_khach(): void
    {
        /*
         * Đếm lượt sau khi mô hình trả lời được, không phải trước.
         *
         * Đếm trước thì một lỗi phía nhà cung cấp cũng ăn mất lượt, và người
         * đầu tiên thử sản phẩm lại là người chịu.
         */
        Http::fake(fn () => Http::response('lỗi máy chủ', 500));

        $this->postJson(route('demo.ask'), ['scene' => 'code', 'question' => 'vì sao lỗi?'])
            ->assertStatus(503);

        // Lượt hỏng không được tính, nên bộ đếm của địa chỉ này vẫn ở 0.
        $this->assertSame(
            0,
            RateLimiter::attempts('demo-ask:ip:'.sha1('127.0.0.1')),
            'Lượt hỏng vẫn bị tính vào trần của khách.',
        );
    }

    #[Test]
    public function loi_cua_nha_cung_cap_khong_lo_chi_tiet_ra_trang_cong_khai(): void
    {
        Http::fake(['*' => Http::response('Invalid api key sk-that-cua-he-thong', 401)]);

        $body = $this->postJson(route('demo.ask'), ['scene' => 'code', 'question' => 'vì sao?'])
            ->assertStatus(503)
            ->getContent();

        $this->assertStringNotContainsString('sk-that-cua-he-thong', $body);
        $this->assertStringNotContainsString('mo-hinh.test', $body);
    }

    #[Test]
    public function tat_ban_dung_thu_thi_endpoint_dong_lai(): void
    {
        config(['snapask.demo.enabled' => false]);

        $this->postJson(route('demo.ask'), ['scene' => 'code', 'question' => 'vì sao?'])
            ->assertStatus(503);
    }

    #[Test]
    public function khong_ghi_gi_vao_co_so_du_lieu(): void
    {
        $this->fakeModel('xong');

        $this->postJson(route('demo.ask'), ['scene' => 'invoice', 'question' => 'tổng bao nhiêu?'])->assertOk();

        // Bản dùng thử không tạo tài khoản, không tạo hội thoại, không lưu ảnh.
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    #[Test]
    public function noi_dung_man_hinh_mau_di_kem_cau_hoi_len_mo_hinh(): void
    {
        $this->fakeModel('xong');

        $this->postJson(route('demo.ask'), ['scene' => 'report', 'question' => 'vẽ biểu đồ'])->assertOk();

        // Mô hình phải nhìn thấy đúng thứ khách đang nhìn trên màn hình, nếu
        // không câu trả lời sẽ nói về một bảng số liệu không tồn tại.
        Http::assertSent(function ($request): bool {
            // Body là JSON với tiếng Việt đã escape thành \uXXXX, nên phải giải
            // mã rồi mới so chữ.
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'doanh thu quý 3')
                && str_contains($body, '1.240')
                && str_contains($body, 'Miền Bắc')
                && str_contains($body, 'vẽ biểu đồ');
        });
    }

    #[Test]
    public function ba_man_hinh_mau_deu_co_du_phan_can_thiet(): void
    {
        $scenes = app(DemoScenes::class)->all();

        $this->assertSame(['invoice', 'report', 'code'], array_keys($scenes));

        foreach ($scenes as $key => $scene) {
            $this->assertNotSame('', trim($scene['context']), "Cảnh {$key} thiếu mô tả gửi lên mô hình.");
            $this->assertNotSame([], $scene['questions'], "Cảnh {$key} không có câu hỏi gợi ý.");
            $this->assertNotSame([], $scene['lines'], "Cảnh {$key} không có dòng nào để hiện.");

            // Phải có phần được tô sẵn, vì người dùng bàn phím chọn bằng Enter.
            $this->assertNotSame(
                [],
                array_filter($scene['lines'], fn (array $line): bool => $line['target'] ?? false),
                "Cảnh {$key} không có dòng nào được đánh dấu để chọn bằng bàn phím.",
            );
        }
    }
}
