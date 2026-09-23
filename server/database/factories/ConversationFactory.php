<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'image_path' => null,
            'image_expires_at' => null,
            'model' => 'qwen-vl-plus',
        ];
    }

    /** Hội thoại có ảnh đính kèm, dùng cho các kiểm thử về dọn dẹp ảnh. */
    public function withImage(string $path = 'snapask/1/capture.png'): static
    {
        return $this->state(fn (): array => [
            'image_path' => $path,
            'image_expires_at' => now()->addDays(14),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'image_path' => 'snapask/1/expired.png',
            'image_expires_at' => now()->subDay(),
        ]);
    }
}
