<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DemoScenes;
use App\Services\Providers\ProviderResolver;
use App\Services\VisionProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Hỏi AI thật ngay trên trang chủ, không cần tài khoản.
 *
 * Một câu trả lời thật thuyết phục hơn mọi lời quảng cáo — khách thấy sản phẩm
 * chạy được trước khi phải khai email. Nhưng đây cũng là cửa duy nhất mở ra mô
 * hình mà không qua đăng nhập, nên có ba lớp gác:
 *
 *  1. Trần theo IP mỗi giờ — một người không ngồi dùng trang chủ thay tài khoản.
 *  2. Trần toàn hệ thống mỗi ngày — bị dội thì hoá đơn vẫn có đỉnh.
 *  3. Câu hỏi ngắn, câu trả lời ngắn, và không có lịch sử hội thoại.
 *
 * Không ghi gì vào cơ sở dữ liệu: không tài khoản, không hội thoại, không ảnh.
 * Câu hỏi của khách đi thẳng tới mô hình rồi thôi.
 */
class DemoAskController extends Controller
{
    /** Đếm số lượt của cả hệ thống trong ngày; khoá đổi theo ngày nên tự hết hạn. */
    private const DAILY_KEY = 'demo-ask:daily:';

    public function __construct(
        private DemoScenes $scenes,
        private ProviderResolver $providers,
        private VisionProvider $vision,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! config('snapask.demo.enabled')) {
            return $this->refuse(__('The live demo is closed right now. Create an account to keep asking.'), 503);
        }

        $data = $request->validate([
            'scene' => ['required', 'string', Rule::in($this->scenes->keys())],
            'question' => ['required', 'string', 'max:'.config('snapask.demo.max_question_length')],
        ]);

        $scene = $this->scenes->find($data['scene']);
        $question = trim($data['question']);

        if ($question === '') {
            return $this->refuse(__('Type a question first.'), 422);
        }

        if ($limited = $this->guardLimits($request)) {
            return $limited;
        }

        try {
            $answer = $this->ask($scene, $question);
        } catch (Throwable $exception) {
            // Chi tiết lỗi của nhà cung cấp không ra tới trang công khai: nó lộ
            // cấu hình mà chẳng giúp khách điều gì.
            report($exception);

            return $this->refuse(__('The demo could not answer just now. Please try again in a moment.'), 503);
        }

        if (trim($answer) === '') {
            return $this->refuse(__('The demo could not answer just now. Please try again in a moment.'), 503);
        }

        $this->countUsage($request);

        return response()->json([
            'answer' => $answer,
            'remaining' => $this->remainingForIp($request),
        ]);
    }

    /** Gọi mô hình đúng một lượt, không kèm lịch sử và không kèm ảnh. */
    private function ask(array $scene, string $question): string
    {
        $provider = $this->providers->default();

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $scene['context']."\n\nCâu hỏi: ".$question],
        ];

        $stream = $this->vision->stream($messages, $provider);

        $text = '';

        foreach ($stream as $chunk) {
            $text .= $chunk;
        }

        $final = $stream->getReturn();

        return trim($final['content'] ?? $text);
    }

    /**
     * Hướng dẫn riêng cho bản dùng thử.
     *
     * Khác system prompt của ứng dụng thật ở hai chỗ: bắt trả lời thật ngắn, và
     * cho phép dựng bảng hay biểu đồ — đó chính là thứ đáng khoe với người đang
     * cân nhắc dùng sản phẩm.
     */
    private function systemPrompt(): string
    {
        return <<<'TEXT'
        Bạn là trợ lý SnapAsk, đang trả lời trong bản dùng thử trên trang chủ.

        - Trả lời bằng ngôn ngữ của câu hỏi. Không rõ tiếng nào thì dùng tiếng Việt.
        - Rất ngắn: nhiều nhất 4 câu, hoặc một bảng gọn.
        - Chỉ dựa vào nội dung ảnh được mô tả. Không bịa thêm số hay chi tiết.
        - Có số liệu so sánh được thì trình bày thành bảng markdown, cột số căn phải bằng |---:|.
        - Người dùng hỏi về biểu đồ, hoặc dữ liệu có xu hướng đáng nhìn, thì thêm
          một khối ```chart chứa đúng một JSON:
          {"type":"bar","title":"...","labels":["..."],"datasets":[{"label":"...","data":[1,2]}]}
          Chỉ ghi dữ liệu, không ghi màu sắc.
        - Không nhắc tới việc đây là bản dùng thử, không mời chào đăng ký.
        TEXT;
    }

    /** Ba lớp trần. Trả về response khi chạm trần, null khi còn lượt. */
    private function guardLimits(Request $request): ?JsonResponse
    {
        $daily = (int) Cache::get(self::DAILY_KEY.now()->toDateString(), 0);

        if ($daily >= config('snapask.demo.daily_total')) {
            return $this->refuse(__('The demo has had a busy day. Create a free account to keep asking.'), 429);
        }

        if (RateLimiter::tooManyAttempts($this->ipKey($request), config('snapask.demo.per_ip_hourly'))) {
            return $this->refuse(
                __('That is all the demo questions for now. Create a free account to keep going.'),
                429,
            );
        }

        return null;
    }

    /**
     * Chỉ đếm sau khi mô hình trả lời được.
     *
     * Đếm trước thì một lỗi phía nhà cung cấp cũng ăn mất lượt của khách, và
     * người đầu tiên thử sản phẩm lại là người chịu.
     */
    private function countUsage(Request $request): void
    {
        RateLimiter::hit($this->ipKey($request), 3600);

        $key = self::DAILY_KEY.now()->toDateString();

        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addDay());
    }

    private function remainingForIp(Request $request): int
    {
        return RateLimiter::remaining($this->ipKey($request), config('snapask.demo.per_ip_hourly'));
    }

    /** Băm địa chỉ IP: không cần biết ai, chỉ cần phân biệt được người này với người kia. */
    private function ipKey(Request $request): string
    {
        return 'demo-ask:ip:'.sha1((string) $request->ip());
    }

    private function refuse(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
