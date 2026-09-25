<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ConversationImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    /** Số hội thoại mỗi trang. */
    private const PER_PAGE = 20;

    /**
     * Danh sách hội thoại của người đang đăng nhập, hoạt động gần nhất trước.
     *
     * Giữ nguyên khung phân trang cũ của Laravel (`data`, `current_page`,
     * `last_page`…) thay vì bọc lại kiểu resource collection: bản desktop cũ
     * và mọi thứ đang đọc các trường đó tiếp tục chạy. Trường mới chỉ được thêm
     * vào từng phần tử.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:'.Conversation::SEARCH_MAX_LENGTH],
            'period' => ['nullable', 'string', Rule::in(array_keys(Conversation::PERIODS))],
            'screenshot' => ['nullable', 'string', Rule::in(['with', 'without'])],
            'model' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $conversations = $request->user()
            ->conversations()
            ->select('conversations.*')
            ->withListing()
            ->search($filters['search'] ?? null)
            ->filter($filters)
            ->byActivity()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Conversation $conversation): array => (new ConversationResource($conversation))->resolve($request));

        return response()->json($conversations);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        $conversation->loadCount('messages');

        return response()->json([
            'conversation' => (new ConversationResource($conversation))->withImageFileCheck()->resolve($request),
            'messages' => MessageResource::collection(
                $conversation->messages()->orderBy('id')->get(),
            )->resolve($request),
        ]);
    }

    /**
     * Đổi tên hội thoại.
     *
     * Không chạm vào `updated_at`: đổi tên không phải là hỏi tiếp, nên hội thoại
     * giữ nguyên chỗ trong lịch sử.
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        $request->merge(['title' => trim((string) $request->input('title'))]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:'.Conversation::TITLE_MAX_LENGTH],
        ], [
            'title.required' => __('Enter a title.'),
            'title.max' => __('The title may not be longer than :max characters.', ['max' => Conversation::TITLE_MAX_LENGTH]),
        ]);

        Conversation::withoutTimestamps(fn () => $conversation->update($data));

        return response()->json([
            'conversation' => (new ConversationResource($conversation->loadCount('messages')))->resolve($request),
        ]);
    }

    /** Ảnh chụp của hội thoại, qua token Sanctum — không bao giờ qua tham số URL. */
    public function image(Request $request, Conversation $conversation, ConversationImage $images): StreamedResponse
    {
        $this->authorizeOwner($request, $conversation);

        return $images->response($conversation);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        $conversation->delete();

        return response()->json(['deleted' => true, 'id' => $conversation->id]);
    }

    /** Hội thoại của người khác trả 404 chứ không 403, để không xác nhận là id đó có tồn tại. */
    private function authorizeOwner(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }
}
