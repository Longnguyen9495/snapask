<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProviderNotConfigured;
use App\Http\Controllers\Controller;
use App\Http\Requests\AskRequest;
use App\Models\Conversation;
use App\Services\AskService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AskController extends Controller
{
    /**
     * Trả lời một lượt hỏi và phát chữ về máy khách theo SSE.
     */
    public function __invoke(AskRequest $request, AskService $service): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        // Chặn theo hạn mức TRƯỚC khi gọi mô hình. Kiểm tra sau khi gọi thì tiền
        // token đã mất rồi, và một người giữ phím tắt là đủ thủng cả gói.
        if (! $user->hasAsksRemaining()) {
            return response()->json([
                'message' => 'Đã dùng hết lượt hỏi của tháng này.',
                'quota' => $user->quotaSummary(),
            ], 429);
        }

        $conversation = $request->integer('conversation_id') === 0
            ? null
            : Conversation::find($request->integer('conversation_id'));

        return response()->eventStream(function () use ($request, $service, $user, $conversation) {
            try {
                $stream = $service->ask(
                    $user,
                    $conversation,
                    (string) $request->string('question'),
                    $request->filled('image') ? (string) $request->string('image') : null,
                );

                foreach ($stream as $event) {
                    yield json_encode($event, JSON_UNESCAPED_UNICODE);
                }

                yield json_encode([
                    'type' => 'done',
                    'conversation_id' => $stream->getReturn()->id,
                ], JSON_UNESCAPED_UNICODE);
            } catch (ProviderNotConfigured|InvalidArgumentException $exception) {
                yield json_encode(['type' => 'error', 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
            } catch (Throwable $exception) {
                report($exception);

                // Thông điệp thật của provider có thể chứa mảnh khoá API hoặc
                // chi tiết hạ tầng, nên máy khách chỉ nhận một câu chung.
                yield json_encode([
                    'type' => 'error',
                    'message' => 'Không lấy được câu trả lời. Hãy thử lại sau ít phút.',
                ], JSON_UNESCAPED_UNICODE);
            }
        }, endStreamWith: '[DONE]');
    }
}
