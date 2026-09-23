<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /** Danh sách hội thoại của người đang đăng nhập, mới nhất trước. */
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->withCount('messages')
            ->latest('id')
            ->paginate(20, ['id', 'title', 'model', 'created_at']);

        return response()->json($conversations);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        return response()->json([
            'conversation' => $conversation->only(['id', 'title', 'model', 'created_at']),
            'messages' => $conversation->messages()
                ->orderBy('id')
                ->get(['id', 'role', 'content', 'created_at']),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $conversation->delete();

        return response()->json(['deleted' => true]);
    }
}
