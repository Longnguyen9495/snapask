<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectorRequest;
use App\Models\McpConnector;
use App\Services\Mcp\ConnectorSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectorPageController extends Controller
{
    public function index(Request $request): View
    {
        $connectors = $request->user()->mcpConnectors()->orderBy('name')->get();
        $statuses = $connectors->map(fn (McpConnector $connector): string => $connector->healthStatus());

        return view('connectors.index', [
            'connectors' => $connectors,
            'quota' => $request->user()->quotaSummary(),
            'summary' => [
                'total' => $connectors->count(),
                'ok' => $statuses->filter(fn (string $status) => $status === 'ok')->count(),
                'error' => $statuses->filter(fn (string $status) => $status === 'error')->count(),
                'disabled' => $statuses->filter(fn (string $status) => $status === 'disabled')->count(),
            ],
        ]);
    }

    public function store(ConnectorRequest $request, ConnectorSynchronizer $synchronizer): RedirectResponse
    {
        $connector = $request->user()->mcpConnectors()->create($request->safe()->except('clear_token'));

        $synchronizer->sync($connector);

        return $this->backWithOutcome($connector);
    }

    /**
     * Sửa một dịch vụ.
     *
     * Ô token bỏ trống nghĩa là giữ token cũ — token không bao giờ được đổ
     * ngược ra biểu mẫu, nên không thể bắt khách gõ lại mỗi lần sửa tên. Muốn
     * gỡ token thì đánh dấu ô "Gỡ token".
     */
    public function update(
        ConnectorRequest $request,
        McpConnector $connector,
        ConnectorSynchronizer $synchronizer,
    ): RedirectResponse {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $data = $request->safe()->only(['name', 'slug', 'url']);

        if ($request->boolean('clear_token')) {
            $data['auth_token'] = null;
        } elseif ($request->filled('auth_token')) {
            $data['auth_token'] = $request->validated('auth_token');
        }

        $connector->update($data);
        $synchronizer->sync($connector);

        return $this->backWithOutcome($connector);
    }

    public function resync(
        Request $request,
        McpConnector $connector,
        ConnectorSynchronizer $synchronizer,
    ): RedirectResponse {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $synchronizer->sync($connector);

        return $this->backWithOutcome($connector);
    }

    /** Bật hoặc tắt dịch vụ mà không phải xoá: tắt đi thì AI thôi gọi nó, cấu hình vẫn còn. */
    public function toggle(Request $request, McpConnector $connector): RedirectResponse
    {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $connector->update(['enabled' => ! $connector->enabled]);

        return redirect()
            ->route('web.connectors.index')
            ->with('status', $connector->enabled
                ? __(':name is on. The AI can look it up again.', ['name' => $connector->name])
                : __(':name is off. The AI will not call it until you turn it back on.', ['name' => $connector->name]));
    }

    public function destroy(Request $request, McpConnector $connector): RedirectResponse
    {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $connector->delete();

        return redirect()
            ->route('web.connectors.index')
            ->with('status', __('Service deleted.'));
    }

    /**
     * Câu báo sau khi lưu: nói thẳng đã đọc được bao nhiêu công cụ, vì đó mới
     * là thứ quyết định AI dùng được dịch vụ hay không. Lưu được mà không kết
     * nối được thì báo bằng giọng cảnh báo, kèm hướng sửa.
     */
    private function backWithOutcome(McpConnector $connector): RedirectResponse
    {
        $redirect = redirect()->route('web.connectors.index');

        if ($connector->last_error !== null) {
            return $redirect
                ->with('status', __('Saved, but could not connect: :error', ['error' => $connector->last_error]))
                ->with('status_tone', 'error')
                ->with('status_hint', __('Check that the address is reachable over HTTPS and that the token is still valid, then sync again.'));
        }

        $count = count($connector->tools ?? []);

        return $redirect->with('status', trans_choice(
            'Connected :name, read :count tool.|Connected :name, read :count tools.',
            $count,
            ['name' => $connector->name, 'count' => $count],
        ));
    }
}
