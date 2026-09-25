<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function trang_chu_mo_duoc_khi_chua_dang_nhap(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Chụp bất kỳ đâu. Hỏi ngay tại đó.', false)
            ->assertSee('Đăng nhập', false);
    }

    #[Test]
    public function landing_co_ban_thu_tuong_tac_voi_ba_man_hinh_mau(): void
    {
        /*
         * Ba cảnh cho ba kiểu người. Bản trước chỉ có một đoạn mã lỗi, nên ai
         * không lập trình nhìn vào không thấy mình trong đó — mà phần lớn khách
         * của SnapAsk không lập trình.
         */
        $this->get('/')
            ->assertOk()
            ->assertSee('id="try"', false)
            ->assertSee('data-try-demo', false)
            ->assertSee('data-demo-screen', false)
            ->assertSee('data-demo-form', false)
            ->assertSee('data-scene-tab="invoice"', false)
            ->assertSee('data-scene-tab="report"', false)
            ->assertSee('data-scene-tab="code"', false)
            ->assertSee('Một hoá đơn')
            ->assertSee('Một báo cáo doanh thu')
            ->assertSee('Một thông báo lỗi')
            // Gợi ý câu hỏi và địa chỉ gửi câu hỏi phải có mặt trong trang.
            ->assertSee('data-demo-chips', false)
            ->assertSee(route('demo.ask'), false)
            ->assertSee('id="demo-data"', false);

        $this->get('/en')
            ->assertOk()
            ->assertSee('Try it right here. No account needed.')
            ->assertSee('An invoice')
            ->assertSee('A sales report');
    }

    #[Test]
    public function tat_ban_dung_thu_thi_trang_van_chay_va_khong_moi_hoi(): void
    {
        config(['snapask.demo.enabled' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-live="0"', false)
            // Không hứa có AI thật khi bản dùng thử đang đóng.
            ->assertDontSee('AI thật trả lời');
    }

    #[Test]
    public function nguoi_da_dang_nhap_van_xem_duoc_trang_chu(): void
    {
        // Trước đây `/` đẩy thẳng vào trang quản trị, nên người đã đăng nhập
        // không còn đường nào xem lại trang giới thiệu.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Chụp bất kỳ đâu. Hỏi ngay tại đó.', false)
            ->assertSee(route('dashboard'), false)
            ->assertSee('Mở trang quản lý', false)
            ->assertDontSee('href="'.route('login').'" class="btn', false);
    }

    #[Test]
    public function trang_chu_co_du_cac_phan_chinh(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="features"', false)
            ->assertSee('id="privacy"', false)
            ->assertSee('id="get"', false)
            ->assertSee('id="faq"', false);
    }

    #[Test]
    public function trang_tai_ve_mo_duoc_bang_hai_thu_tieng(): void
    {
        $this->get('/download')->assertOk()->assertSee('Tải SnapAsk', false);
        $this->get('/en/download')->assertOk()->assertSee('Get SnapAsk', false);
    }

    #[Test]
    public function chua_co_ban_dung_thi_trang_tai_ve_noi_ro(): void
    {
        config(['snapask.releases.builds.windows.file' => null]);

        // Câu này nói cho người tải biết làm gì tiếp, không nhắc tới chuyện
        // dựng từ mã nguồn — người dùng cuối không có mã nguồn để mà dựng.
        $this->get('/download')
            ->assertOk()
            ->assertSee('Bản này chưa có', false)
            ->assertDontSee('mã nguồn', false);
    }

    #[Test]
    public function tai_file_tu_dia_cuc_bo(): void
    {
        Storage::fake('releases');
        Storage::disk('releases')->put('SnapAsk-0.1.0-win-x64.exe', 'noi-dung-gia');

        config(['snapask.releases.builds.windows.file' => 'SnapAsk-0.1.0-win-x64.exe']);

        $this->get('/download/windows')
            ->assertOk()
            ->assertDownload('SnapAsk-0.1.0-win-x64.exe');
    }

    #[Test]
    public function co_url_thi_chuyen_thang_sang_do(): void
    {
        // Bản đã lên GitHub Releases hoặc CDN: băng thông không đi qua máy chủ này.
        config([
            'snapask.releases.builds.mac.file' => 'SnapAsk-0.1.0-mac-universal.dmg',
            'snapask.releases.builds.mac.url' => 'https://github.com/vidu/snapask/releases/tai-ve.dmg',
        ]);

        $this->get('/download/mac')
            ->assertRedirect('https://github.com/vidu/snapask/releases/tai-ve.dmg');
    }

    #[Test]
    public function chua_dung_ban_nao_thi_tai_ve_bao_404(): void
    {
        config(['snapask.releases.builds.windows.file' => null]);

        $this->get('/download/windows')->assertNotFound();
    }

    #[Test]
    public function file_khai_trong_manifest_nhung_khong_co_that_thi_404(): void
    {
        Storage::fake('releases');

        config(['snapask.releases.builds.windows.file' => 'khong-ton-tai.exe']);

        $this->get('/download/windows')->assertNotFound();
    }

    #[Test]
    public function nen_tang_la_bi_tu_choi(): void
    {
        $this->get('/download/linux')->assertNotFound();
    }

    #[Test]
    public function thu_tu_heading_khong_nhay_coc(): void
    {
        /*
         * Trình đọc màn hình dựng mục lục trang theo bậc heading, nên nhảy từ
         * h1 thẳng xuống h3 là mất một tầng và người dùng nghe ra một cấu trúc
         * không có thật.
         *
         * Lỗi này đã xảy ra một lần ở khối "Ba bước", nên có test canh.
         */
        foreach (['/', '/en', '/download', '/en/download'] as $path) {
            preg_match_all('/<h([1-6])[\s>]/', $this->get($path)->getContent(), $matches);

            $levels = array_map('intval', $matches[1]);

            $this->assertNotSame([], $levels, "Trang {$path} không có heading nào.");
            $this->assertSame(1, $levels[0], "Trang {$path} không mở đầu bằng h1.");

            foreach ($levels as $i => $level) {
                if ($i === 0) {
                    continue;
                }

                $this->assertLessThanOrEqual(
                    $levels[$i - 1] + 1,
                    $level,
                    "Trang {$path} nhảy từ h{$levels[$i - 1]} xuống h{$level}.",
                );
            }
        }
    }

    #[Test]
    public function chi_co_mot_h1_tren_moi_trang(): void
    {
        foreach (['/', '/en', '/download', '/en/download'] as $path) {
            $count = preg_match_all('/<h1[\s>]/', $this->get($path)->getContent());

            $this->assertSame(1, $count, "Trang {$path} có {$count} thẻ h1.");
        }
    }

    #[Test]
    public function ma_bam_hien_len_cho_khach_tu_doi_chieu(): void
    {
        // Bộ cài chưa ký số, nên đây là cách duy nhất khách biết file còn nguyên.
        config([
            'snapask.releases.builds.windows.file' => 'SnapAsk-0.1.0-win-x64.exe',
            'snapask.releases.builds.windows.sha256' => str_repeat('ab', 32),
        ]);

        $this->get('/download')
            ->assertOk()
            ->assertSee(str_repeat('ab', 32), false);
    }

    #[Test]
    public function trang_cong_khai_khong_noi_ngon_ngu_ky_thuat(): void
    {
        /*
         * Người tải SnapAsk về không phải lập trình viên. Những chữ dưới đây
         * từng nằm ngay giữa trang: chúng không giúp họ quyết định gì, chỉ làm
         * sản phẩm trông như một bản thử nội bộ.
         */
        $jargon = ['ký số', 'code-signed', 'mã nguồn', 'MCP', 'checksum'];

        foreach (['/', '/en', '/download'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            // Chỉ soát chữ người dùng thật sự đọc được: bỏ thẻ, bỏ script và
            // style đi. Tên class như `feature-card--mcp` nằm trong mã nguồn
            // trang chứ không nằm trước mắt ai.
            $body = substr($html, (int) strpos($html, '<body'));
            $body = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', ' ', $body) ?? $body;
            $visible = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            foreach ($jargon as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $visible, "Trang {$path} còn chữ \"{$word}\".");
            }
        }
    }
}
