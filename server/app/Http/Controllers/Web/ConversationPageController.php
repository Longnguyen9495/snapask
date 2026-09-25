<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ConversationImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationPageController extends Controller
{
    /** Số hội thoại mỗi trang; cùng con số với API để hai bên phân trang giống nhau. */
    private const PER_PAGE = 20;

    /**
     * Danh sách hội thoại của người đang đăng nhập, hoạt động gần nhất trước.
     *
     * Dùng đúng các scope mà API desktop dùng, để một câu tìm cho ra cùng kết
     * quả dù gõ trên web hay trong ứng dụng.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $conversations = $request->user()
            ->conversations()
            ->select('conversations.*')
            ->withListing()
            ->search($filters['search'])
            ->filter($filters)
            ->byActivity()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('conversations.index', [
            'conversations' => $conversations,
            'filters' => $filters,
            'filtering' => collect($filters)->filter()->isNotEmpty(),
            'hasAny' => $request->user()->conversations()->exists(),
            'models' => $request->user()->conversations()
                ->whereNotNull('model')
                ->distinct()
                ->orderBy('model')
                ->pluck('model'),
            'periods' => array_keys(Conversation::PERIODS),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorizeOwner($request, $conversation);

        $conversation->loadCount('messages');

        return view('conversations.show', [
            'conversation' => $conversation,
            // Còn hạn nhưng file đã mất (xoá tay, đổi disk) thì cũng coi như đã xoá,
            // để trang nói thẳng thay vì hiện một khung ảnh vỡ.
            'imageAvailable' => $conversation->imageIsRetained()
                && Storage::disk(config('snapask.image.disk'))->exists($conversation->image_path),
            'messages' => $conversation->messages()->orderBy('id')->get(),
        ]);
    }

    /**
     * Ảnh chụp của một hội thoại.
     *
     * Đi qua đây chứ không nằm trong public/: ảnh màn hình của khách hay chứa
     * thứ nhạy cảm, nên mỗi lần xem đều phải qua cửa kiểm tra chủ sở hữu.
     */
    public function image(Request $request, Conversation $conversation, ConversationImage $images): StreamedResponse
    {
        $this->authorizeOwner($request, $conversation);

        return $images->response($conversation);
    }

    /** Đổi tên, giữ nguyên chỗ của hội thoại trong lịch sử. */
    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeOwner($request, $conversation);

        $request->merge(['title' => trim((string) $request->input('title'))]);

        $data = $request->validateWithBag('rename', [
            'title' => ['required', 'string', 'max:'.Conversation::TITLE_MAX_LENGTH],
        ], [
            'title.required' => __('Enter a title.'),
            'title.max' => __('The title may not be longer than :max characters.', ['max' => Conversation::TITLE_MAX_LENGTH]),
        ]);

        Conversation::withoutTimestamps(fn () => $conversation->update($data));

        return redirect()->route('web.conversations.show', $conversation)
            ->with('status', __('Conversation renamed.'));
    }

    /** Ảnh đi theo hội thoại: Conversation::booted() xoá file ngay lúc này. */
    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeOwner($request, $conversation);

        $conversation->delete();

        return redirect()->route('web.conversations.index')
            ->with('status', __('Conversation deleted.'));
    }

    /**
     * Bộ lọc đọc từ thanh địa chỉ.
     *
     * Giá trị lạ bị bỏ qua chứ không báo lỗi: sửa tay đường dẫn sai một chữ
     * không đáng bị một trang lỗi.
     *
     * @return array{search: ?string, period: ?string, screenshot: ?string, model: ?string}
     */
    private function filters(Request $request): array
    {
        $search = Str::limit(trim((string) $request->query('search', '')), Conversation::SEARCH_MAX_LENGTH, '');
        $period = (string) $request->query('period', '');
        $screenshot = (string) $request->query('screenshot', '');
        $model = trim((string) $request->query('model', ''));

        return [
            'search' => $search !== '' ? $search : null,
            'period' => array_key_exists($period, Conversation::PERIODS) ? $period : null,
            'screenshot' => in_array($screenshot, ['with', 'without'], true) ? $screenshot : null,
            'model' => $model !== '' ? Str::limit($model, 255, '') : null,
        ];
    }

    private function authorizeOwner(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }
}
