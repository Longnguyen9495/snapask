<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Một lượt trong hội thoại.
 *
 * Số token và độ trễ là số liệu vận hành, không phải thứ người dùng cần xem,
 * nên không đi kèm.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'tools_used' => collect($this->tools_used ?? [])
                ->map(fn (array $call): array => [
                    'tool' => (string) ($call['tool'] ?? ''),
                    'arguments' => is_array($call['arguments'] ?? null) ? $call['arguments'] : [],
                ])
                ->values()
                ->all(),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
