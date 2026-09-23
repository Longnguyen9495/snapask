<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Mcp\ConnectorTools;
use App\Services\Providers\ProviderResolver;
use Generator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AskService
{
    private const SYSTEM_PROMPT = <<<'TEXT'
    Bạn là trợ lý đọc ảnh chụp màn hình. Người dùng khoanh một vùng trên màn hình
    của họ rồi hỏi về đúng vùng đó.

    Quy tắc:
    - Trả lời bằng ngôn ngữ của câu hỏi.
    - Bám vào những gì thật sự nhìn thấy trong ảnh. Không đoán thêm chi tiết.
    - Nếu ảnh mờ hoặc thiếu phần cần thiết, nói thẳng là không đọc được thay vì suy diễn.
    - Trả lời gọn, đi thẳng vào việc. Dùng markdown khi thật sự giúp dễ đọc.
    - Nếu ảnh chứa thông tin nhạy cảm (mật khẩu, số thẻ), trả lời câu hỏi nhưng không chép lại nguyên văn các giá trị đó.
    TEXT;

    private const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * Trần số vòng gọi công cụ trong một lượt trả lời.
     *
     * Không có trần thì một mô hình bối rối có thể gọi đi gọi lại mãi và giữ
     * kết nối tới khi PHP hết thời gian chạy.
     */
    private const MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private VisionProvider $provider,
        private ConnectorTools $connectors,
        private ProviderResolver $providers,
    ) {}

    /**
     * Chạy trọn một lượt hỏi, nhả tiến trình ra ngoài rồi ghi lại kết quả.
     *
     * Mỗi giá trị nhả ra là một sự kiện: `delta` cho chữ mới, `tool` cho bước
     * đang gọi dịch vụ của khách.
     *
     * @return Generator<int, array{type: string, text?: string, label?: string}, void, Conversation>
     */
    public function ask(
        User $user,
        ?Conversation $conversation,
        string $question,
        ?string $imageDataUrl,
    ): Generator {
        $conversation ??= $this->startConversation($user, $question, $imageDataUrl);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        $resolved = $this->providers->for($user);
        $conversationMessages = $this->buildMessages($conversation, $question);
        $tools = $this->connectors->schemasFor($user);
        $toolsUsed = [];
        $answer = '';
        $promptTokens = 0;
        $completionTokens = 0;
        $model = $resolved->model;

        // Đo trọn cả lượt, kể cả các vòng gọi công cụ xen giữa: đó mới là khoảng
        // thời gian người dùng thật sự ngồi chờ.
        $startedAt = hrtime(true);

        for ($round = 0; $round <= self::MAX_TOOL_ROUNDS; $round++) {
            // Vòng cuối rút công cụ đi để mô hình buộc phải chốt câu trả lời,
            // thay vì gọi thêm một công cụ nữa rồi bị cắt ngang.
            $stream = $this->provider->stream(
                $conversationMessages,
                $resolved,
                $round < self::MAX_TOOL_ROUNDS ? $tools : [],
            );

            foreach ($stream as $delta) {
                $answer .= $delta;

                yield ['type' => 'delta', 'text' => $delta];
            }

            $result = $stream->getReturn();
            $promptTokens += $result['prompt_tokens'] ?? 0;
            $completionTokens += $result['completion_tokens'] ?? 0;

            if ($result['tool_calls'] === []) {
                break;
            }

            $conversationMessages[] = [
                'role' => 'assistant',
                'content' => $result['content'],
                'tool_calls' => $result['tool_calls'],
            ];

            foreach ($result['tool_calls'] as $call) {
                $name = (string) ($call['function']['name'] ?? '');
                $arguments = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
                $arguments = is_array($arguments) ? $arguments : [];

                yield ['type' => 'tool', 'label' => $this->connectors->label($user, $name)];

                $conversationMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($call['id'] ?? ''),
                    'content' => $this->connectors->run($user, $name, $arguments),
                ];

                $toolsUsed[] = ['tool' => $name, 'arguments' => $arguments];
            }
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $answer,
            'tools_used' => $toolsUsed ?: null,
            'prompt_tokens' => $promptTokens ?: null,
            'completion_tokens' => $completionTokens ?: null,
            // Sàn ở 1ms để một lượt nhanh dưới nửa mili giây không bị làm tròn
            // thành 0 — số 0 trong nhật ký đọc như "chưa đo được".
            'latency_ms' => max(1, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
        ]);

        if ($conversation->model !== $model) {
            $conversation->update(['model' => $model]);
        }

        return $conversation;
    }

    private function startConversation(User $user, string $question, ?string $imageDataUrl): Conversation
    {
        $path = $imageDataUrl === null ? null : $this->storeImage($user, $imageDataUrl);

        return $user->conversations()->create([
            'title' => Str::limit(trim($question), 80),
            'image_path' => $path,
            'image_expires_at' => $path === null
                ? null
                : now()->addDays((int) config('snapask.image.retention_days')),
        ]);
    }

    /**
     * Giải mã data URL và cất ảnh lên disk.
     */
    private function storeImage(User $user, string $dataUrl): string
    {
        if (preg_match('/^data:([\w\/+.-]+);base64,(.+)$/s', $dataUrl, $matches) !== 1) {
            throw new InvalidArgumentException('Ảnh gửi lên không đúng định dạng data URL.');
        }

        [, $mime, $encoded] = $matches;

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Chỉ nhận ảnh PNG, JPEG hoặc WebP.');
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('Không giải mã được ảnh gửi lên.');
        }

        if (strlen($binary) > (int) config('snapask.image.max_bytes')) {
            throw new InvalidArgumentException('Ảnh vượt quá dung lượng cho phép.');
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            default => 'webp',
        };

        $path = sprintf('snapask/%d/%s.%s', $user->id, Str::uuid(), $extension);
        Storage::disk(config('snapask.image.disk'))->put($path, $binary);

        return $path;
    }

    /**
     * Ghép chuỗi tin nhắn gửi lên mô hình.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(Conversation $conversation, string $question): array
    {
        $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

        $history = $conversation->messages()
            ->latest('id')
            ->limit((int) config('snapask.history_limit'))
            ->get()
            ->reverse()
            ->values()
            // Lượt vừa ghi chính là câu hỏi hiện tại; nó được dựng lại bên dưới
            // kèm ảnh, nên phải bỏ ra đây để không gửi hai lần.
            ->slice(0, -1);

        foreach ($history as $message) {
            $messages[] = ['role' => $message->role, 'content' => $message->content];
        }

        $messages[] = ['role' => 'user', 'content' => $this->userContent($conversation, $question)];

        return $messages;
    }

    /**
     * Phần nội dung của lượt hỏi hiện tại, kèm ảnh khi còn đính kèm được.
     *
     * Ảnh được gắn lại ở mọi lượt chứ không chỉ lượt đầu: mô hình không nhớ gì
     * giữa các request, nên bỏ ảnh đi thì câu hỏi tiếp theo kiểu "dòng thứ ba
     * ghi gì" sẽ được trả lời bằng suy đoán. Đây cũng là khoản token đắt nhất
     * của sản phẩm — tắt bằng `snapask.resend_image` nếu cần ưu tiên chi phí.
     *
     * @return string|array<int, array<string, mixed>>
     */
    private function userContent(Conversation $conversation, string $question): string|array
    {
        if ($conversation->image_path === null) {
            return $question;
        }

        $isFirstTurn = $conversation->messages()->where('role', 'assistant')->doesntExist();

        if (! $isFirstTurn && ! config('snapask.resend_image')) {
            return $question;
        }

        $disk = Storage::disk(config('snapask.image.disk'));

        // Ảnh có thể đã bị lệnh dọn dẹp xoá trước khi khách hỏi tiếp. Khi đó vẫn
        // trả lời được dựa trên lịch sử chữ, còn hơn ném lỗi vào mặt người dùng.
        if (! $disk->exists($conversation->image_path)) {
            return $question;
        }

        return [
            ['type' => 'text', 'text' => $question],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => sprintf(
                        'data:%s;base64,%s',
                        $disk->mimeType($conversation->image_path) ?: 'image/png',
                        base64_encode($disk->get($conversation->image_path)),
                    ),
                ],
            ],
        ];
    }
}
