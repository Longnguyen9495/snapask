<?php

namespace Tests\Feature;

use App\Enums\ApiFormat;
use App\Models\ModelProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderPageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'OpenAI cua toi',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-cua-khach',
            'api_format' => ApiFormat::OpenAiChat->value,
            'models' => "gpt-4o-mini\ngpt-4o",
            ...$overrides,
        ];
    }

    #[Test]
    public function khach_chua_dang_nhap_bi_day_ve_trang_dang_nhap(): void
    {
        $this->get(route('web.providers.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function them_provider_dau_tien_thi_duoc_bat_luon(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('web.providers.store'), $this->payload())
            ->assertRedirect(route('web.providers.index'));

        $provider = ModelProvider::sole();
        $this->assertSame(['gpt-4o-mini', 'gpt-4o'], $provider->models);

        // Thêm vào là để dùng; bắt bấm thêm một nút nữa chỉ tổ gây bối rối.
        $this->assertSame($provider->id, $user->refresh()->active_provider_id);
        $this->assertSame('gpt-4o-mini', $user->active_model);
    }

    #[Test]
    public function khoa_api_duoc_ma_hoa_va_khong_hien_lai_tren_trang(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('web.providers.store'), $this->payload());

        $this->assertSame('sk-cua-khach', ModelProvider::sole()->api_key);

        // Giá trị trong bảng phải là bản đã mã hoá, không phải khoá trần.
        $stored = $this->getConnection()->table('model_providers')->value('api_key');
        $this->assertStringNotContainsString('sk-cua-khach', $stored);

        $this->actingAs($user)
            ->get(route('web.providers.index'))
            ->assertOk()
            ->assertDontSee('sk-cua-khach');
    }

    #[Test]
    public function chi_nhan_dia_chi_https(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('web.providers.store'), $this->payload(['base_url' => 'http://api.openai.com/v1']))
            ->assertSessionHasErrors('base_url');

        $this->assertSame(0, ModelProvider::count());
    }

    #[Test]
    public function danh_sach_mo_hinh_rong_thi_bi_tu_choi(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('web.providers.store'), $this->payload(['models' => "  \n \n"]))
            ->assertSessionHasErrors('models');

        $this->assertSame(0, ModelProvider::count());
    }

    #[Test]
    public function doi_duoc_mo_hinh_dang_dung(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('web.providers.store'), $this->payload());
        $provider = ModelProvider::sole();

        $this->actingAs($user)
            ->post(route('web.providers.select', $provider), ['model' => 'gpt-4o'])
            ->assertRedirect(route('web.providers.index'));

        $this->assertSame('gpt-4o', $user->refresh()->active_model);
    }

    #[Test]
    public function khong_chon_duoc_mo_hinh_ngoai_danh_sach(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('web.providers.store'), $this->payload());
        $provider = ModelProvider::sole();

        $this->actingAs($user)->post(route('web.providers.select', $provider), ['model' => 'khong-co-that']);

        $this->assertSame('gpt-4o-mini', $user->refresh()->active_model);
    }

    #[Test]
    public function quay_ve_mac_dinh_duoc(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('web.providers.store'), $this->payload());

        $this->actingAs($user)->post(route('web.providers.default'));

        $user->refresh();
        $this->assertNull($user->active_provider_id);
        $this->assertNull($user->active_model);
    }

    #[Test]
    public function xoa_provider_dang_dung_thi_don_luon_mo_hinh_mo_coi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('web.providers.store'), $this->payload());
        $provider = ModelProvider::sole();

        $this->actingAs($user)->delete(route('web.providers.destroy', $provider));

        $user->refresh();
        $this->assertNull($user->active_provider_id);
        // Bỏ sót chỗ này thì lượt hỏi sau gửi một mã mô hình mồ côi lên nhà
        // cung cấp mặc định.
        $this->assertNull($user->active_model);
    }

    #[Test]
    public function khong_dung_duoc_provider_cua_nguoi_khac(): void
    {
        $stranger = ModelProvider::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('web.providers.destroy', $stranger))
            ->assertNotFound();

        $this->assertDatabaseHas('model_providers', ['id' => $stranger->id]);
    }

    #[Test]
    public function trung_ten_trong_cung_tai_khoan_bi_tu_choi(): void
    {
        $user = User::factory()->create();
        ModelProvider::factory()->for($user)->create(['name' => 'OpenAI cua toi']);

        $this->actingAs($user)
            ->post(route('web.providers.store'), $this->payload())
            ->assertSessionHasErrors('name');
    }
}
