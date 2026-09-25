<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Một hội thoại như ứng dụng desktop và trang web cùng nhìn thấy.
 *
 * Không bao giờ trả `image_path`: đó là đường dẫn trong kho của máy chủ, lộ ra
 * chỉ giúp kẻ dò tìm, không giúp gì người dùng. Ảnh đi qua tuyến riêng có kiểm
 * tra chủ sở hữu.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /** Độ dài đoạn xem trước tin nhắn cuối trong danh sách. */
    public const PREVIEW_LENGTH = 140;

    /** Kiểm tra file ảnh còn trên disk — chỉ bật ở trang chi tiết, vì mỗi lần là một lời gọi tới kho. */
    private bool $checkImageFile = false;

    public function withImageFileCheck(): static
    {
        $this->checkImageFile = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'model' => $this->model,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'last_activity_at' => $this->updated_at?->toJSON(),
            'messages_count' => $this->whenCounted('messages'),
            'last_message_preview' => $this->when(
                array_key_exists('last_message_content', $this->resource->getAttributes()),
                fn (): ?string => self::preview($this->resource->getAttribute('last_message_content')),
            ),
            'has_image' => $this->startedWithImage(),
            'image' => $this->startedWithImage() ? $this->image() : null,
        ];
    }

    /**
     * Một dòng ngắn, không xuống dòng, để danh sách không bị một tin nhắn dài kéo giãn.
     *
     * Bỏ ký hiệu markdown (`**`, dấu ``` của khối mã, dấu đầu dòng…): đoạn xem
     * trước hiện dạng chữ thường, để nguyên ký hiệu thì đọc như lỗi hiển thị.
     */
    public static function preview(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $plain = preg_replace([
            '/```[\w-]*\n?/u',
            '/`([^`]*)`/u',
            '/!?\[([^\]]*)\]\([^)]*\)/u',
            '/(\*\*|__|~~)(.+?)\1/u',
            '/^\s{0,3}(#{1,6}\s+|>\s?|[-*+]\s+|\d+[.)]\s+)/mu',
        ], ['', '$1', '$1', '$2', ''], $content) ?? $content;

        $plain = Str::squish($plain);

        return $plain === '' ? null : Str::limit($plain, self::PREVIEW_LENGTH);
    }

    /** @return array{available: bool, width: ?int, height: ?int, expires_at: ?string} */
    private function image(): array
    {
        $available = $this->imageIsRetained();

        if ($available && $this->checkImageFile) {
            $available = Storage::disk(config('snapask.image.disk'))->exists($this->image_path);
        }

        return [
            'available' => $available,
            'width' => $this->image_width,
            'height' => $this->image_height,
            'expires_at' => $this->image_expires_at?->toJSON(),
        ];
    }
}
