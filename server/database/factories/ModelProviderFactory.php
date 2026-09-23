<?php

namespace Database\Factories;

use App\Enums\ApiFormat;
use App\Models\ModelProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ModelProvider> */
class ModelProviderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Khoa rieng',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-cua-khach',
            'api_format' => ApiFormat::OpenAiChat,
            'models' => ['gpt-4o-mini'],
        ];
    }

    public function anthropic(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Anthropic',
            'base_url' => 'https://api.anthropic.com',
            'api_format' => ApiFormat::AnthropicMessages,
            'models' => ['claude-sonnet-5'],
        ]);
    }
}
