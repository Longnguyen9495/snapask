<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function trang_chu_khong_co_tien_to_ra_tieng_viet(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Chụp bất kỳ đâu. Hỏi ngay tại đó.', false)
            ->assertSee('<html lang="vi"', false);
    }

    #[Test]
    public function trang_chu_duoi_en_ra_tieng_anh(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('Capture anywhere. Ask right there.', false)
            ->assertSee('<html lang="en"', false);
    }

    #[Test]
    public function trang_cong_khai_khai_du_hreflang(): void
    {
        $response = $this->get('/');

        foreach (['vi', 'en', 'x-default'] as $hreflang) {
            $response->assertSee('hreflang="'.$hreflang.'"', false);
        }
    }

    #[Test]
    public function tien_to_url_thang_ca_lua_chon_da_luu(): void
    {
        // Người dùng chọn tiếng Việt từ trước, nhưng đang mở đúng link /en —
        // cái họ vừa bấm vào nói to hơn cái họ chọn hôm qua.
        $user = User::factory()->create(['locale' => 'vi']);

        $this->actingAs($user)
            ->get('/en')
            ->assertOk()
            ->assertSee('Capture anywhere. Ask right there.', false);
    }

    #[Test]
    public function doi_ngon_ngu_thi_ghi_cookie(): void
    {
        $this->post('/locale/en')
            ->assertRedirect()
            ->assertCookie(SetLocale::COOKIE, 'en');
    }

    #[Test]
    public function doi_ngon_ngu_thi_ghi_vao_tai_khoan_khi_da_dang_nhap(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)->post('/locale/en')->assertRedirect();

        $this->assertSame('en', $user->refresh()->locale);
    }

    #[Test]
    public function ngon_ngu_la_bi_tu_choi(): void
    {
        $this->post('/locale/fr')->assertNotFound();
    }

    #[Test]
    public function cookie_quyet_dinh_ngon_ngu_trang_quan_tri(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->withCookie(SetLocale::COOKIE, 'en')
            ->get('/connectors')
            ->assertOk()
            ->assertSee('Services for the AI to look up', false);
    }

    #[Test]
    public function lua_chon_cua_tai_khoan_thang_cookie(): void
    {
        $user = User::factory()->create(['locale' => 'vi']);

        $this->actingAs($user)
            ->withCookie(SetLocale::COOKIE, 'en')
            ->get('/connectors')
            ->assertOk()
            ->assertSee('Dịch vụ cho AI tra cứu', false);
    }

    #[Test]
    public function chua_chon_gi_thi_la_tieng_viet_du_trinh_duyet_khai_tieng_anh(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->get('/connectors')
            ->assertOk()
            ->assertSee('Dịch vụ cho AI tra cứu', false);
    }

    #[Test]
    public function doi_ngon_ngu_tu_trang_cong_khai_thi_doi_ca_tien_to(): void
    {
        // Referer dựng từ APP_URL chứ không viết cứng: LocaleController bỏ qua
        // referer trỏ ra ngoài máy chủ, nên một địa chỉ cố định ở đây sẽ hỏng
        // ngay khi ai đó đổi APP_URL.
        $this->from(config('app.url').'/download')
            ->post('/locale/en')
            ->assertRedirect('/en/download');
    }

    #[Test]
    public function doi_ve_tieng_viet_thi_bo_tien_to(): void
    {
        $this->from(config('app.url').'/en/download')
            ->post('/locale/vi')
            ->assertRedirect('/download');
    }

    #[Test]
    public function trang_quan_tri_giu_nguyen_duong_dan_khi_doi_ngon_ngu(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(config('app.url').'/providers')
            ->post('/locale/en')
            ->assertRedirect('/providers');
    }

    #[Test]
    public function hai_file_ngon_ngu_co_cung_bo_khoa(): void
    {
        $vi = json_decode(file_get_contents(lang_path('vi.json')), true, 512, JSON_THROW_ON_ERROR);
        $en = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);

        // Thiếu khoá thì chữ rơi về tiếng Anh giữa một trang tiếng Việt, mà
        // không ai thấy cho tới khi khách gặp đúng câu đó.
        $this->assertSame([], array_keys(array_diff_key($en, $vi)), 'Khoá có trong en.json nhưng thiếu ở vi.json');
        $this->assertSame([], array_keys(array_diff_key($vi, $en)), 'Khoá có trong vi.json nhưng thiếu ở en.json');
    }

    #[Test]
    public function khong_con_chuoi_tieng_viet_viet_cung_trong_view(): void
    {
        $offenders = [];

        $files = array_merge(
            glob(resource_path('views/*.blade.php')),
            glob(resource_path('views/*/*.blade.php')),
        );

        foreach ($files as $file) {
            $source = file_get_contents($file);

            /*
             * Bỏ mọi chỗ mà tiếng Việt là đúng chỗ của nó:
             *
             *   - chú thích Blade, HTML, CSS và JS — giải thích bằng tiếng Việt
             *     trong mã nguồn là phong cách của dự án này;
             *   - chính đối số của __() và trans_choice() — đó là bản dịch, chứ
             *     không phải chữ viết cứng.
             */
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source);
            $source = preg_replace('/<!--.*?-->/s', '', $source);
            $source = preg_replace('#/\*.*?\*/#s', '', $source);
            $source = preg_replace('#^\s*//.*$#m', '', $source);
            $source = preg_replace('/(?:__|trans_choice)\(\s*\'(?:[^\'\\\\]|\\\\.)*\'/s', '', $source);

            if (preg_match('/[ăâđêôơưàáạảãèéẹẻẽìíịỉĩòóọỏõùúụủũỳýỵỷỹ]/ui', $source, $m, PREG_OFFSET_CAPTURE)) {
                $offenders[] = basename($file).' ('.trim(substr($source, max(0, $m[0][1] - 40), 80)).')';
            }
        }

        $this->assertSame([], $offenders, 'Còn chữ tiếng Việt viết cứng trong: '.implode(', ', $offenders));
    }
}
