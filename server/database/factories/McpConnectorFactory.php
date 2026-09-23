<?php

namespace Database\Factories;

use App\Models\McpConnector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<McpConnector> */
class McpConnectorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'slug' => fake()->unique()->lexify('kho???'),
            'name' => 'Kho hàng',
            'url' => 'https://mcp.vidu.com/mcp',
            'auth_token' => 'token-cua-khach',
            'enabled' => true,
            'tools' => null,
            'synced_at' => null,
            'last_error' => null,
        ];
    }

    /** Connector đã đồng bộ được danh sách công cụ, sẵn sàng dùng. */
    public function synced(?array $tools = null): static
    {
        return $this->state(fn (): array => [
            'tools' => $tools ?? [[
                'name' => 'ton_kho',
                'description' => 'Tra số lượng còn lại của một mã hàng.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['ma_hang' => ['type' => 'string']],
                    'required' => ['ma_hang'],
                ],
            ]],
            'synced_at' => now(),
        ]);
    }
}
