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
    public function landing_co_ban_thu_tuong_tac_khong_can_tai_du_lieu_len(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="try"', false)
            ->assertSee('data-try-demo', false)
            ->assertSee('data-demo-screen', false)
            ->assertSee('data-demo-form', false)
            ->assertSee('data-meaning-title=', false)
            ->assertSee('data-demo-answer-title', false)
            ->assertSee('Câu trả lời mô phỏng')
            ->assertSee('Không có dữ liệu nào được tải lên');

        $this->get('/en')
            ->assertOk()
            ->assertSee('Try the workflow yourself.')
            ->assertSee('Sample answer')
            ->assertSee('Nothing is uploaded and no account is required.');
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

        $this->get('/download')
            ->assertOk()
            ->assertSee('Bản này chưa phát hành', false);
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
}
