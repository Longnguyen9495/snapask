<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationPageController extends Controller
{
    /** Danh sách hội thoại của người đang đăng nhập, mới nhất trước. */
    public function index(Request $request): View
    {
        return view('conversations.index', [
            'conversations' => $request->user()
                ->conversations()
                ->withCount('messages')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        return view('conversations.show', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->orderBy('id')->get(),
        ]);
    }

    /**
     * Ảnh chụp của một hội thoại.
     *
     * Đi qua đây chứ không nằm trong public/: ảnh màn hình của khách hay chứa
     * thứ nhạy cảm, nên mỗi lần xem đều phải qua cửa kiểm tra chủ sở hữu.
     *
     * Ảnh quá hạn đã bị `snapask:prune` xoá; lúc đó trả 404 thay vì một khung
     * ảnh vỡ.
     */
    public function image(Request $request, Conversation $conversation): StreamedResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        abort_if($conversation->image_path === null, 404);

        $disk = Storage::disk(config('snapask.image.disk'));

        abort_unless($disk->exists($conversation->image_path), 404);

        return $disk->response($conversation->image_path);
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        /*
         * Xoá luôn ảnh chứ không đợi `snapask:prune`: người dùng vừa nói rõ là
         * không muốn giữ hội thoại này nữa, để ảnh nằm lại thêm vài ngày là đi
         * ngược ý họ.
         */
        if ($conversation->image_path !== null) {
            Storage::disk(config('snapask.image.disk'))->delete($conversation->image_path);
        }

        $conversation->delete();

        return redirect()->route('web.conversations.index')
            ->with('status', __('Conversation deleted.'));
    }
}
