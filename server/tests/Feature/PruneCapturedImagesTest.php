<?php

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PruneCapturedImagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chi_xoa_anh_da_qua_han_va_giu_lai_lich_su_chu(): void
    {
        config()->set('snapask.image.disk', 'captures');
        Storage::fake('captures');
        Storage::disk('captures')->put('snapask/1/expired.png', 'cu');
        Storage::disk('captures')->put('snapask/1/capture.png', 'moi');

        $expired = Conversation::factory()->expired()->create();
        $fresh = Conversation::factory()->withImage()->create();

        $this->artisan('snapask:prune')->assertSuccessful();

        Storage::disk('captures')->assertMissing('snapask/1/expired.png');
        Storage::disk('captures')->assertExists('snapask/1/capture.png');

        // Hội thoại vẫn còn, chỉ phần ảnh bị gỡ.
        $this->assertNull($expired->refresh()->image_path);
        $this->assertDatabaseHas('conversations', ['id' => $expired->id]);
        $this->assertNotNull($fresh->refresh()->image_path);
    }
}
