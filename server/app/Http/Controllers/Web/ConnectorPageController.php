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
        return view('connectors.index', [
            'connectors' => $request->user()->mcpConnectors()->orderBy('name')->get(),
            'quota' => $request->user()->quotaSummary(),
        ]);
    }

    public function store(ConnectorRequest $request, ConnectorSynchronizer $synchronizer): RedirectResponse
    {
        $connector = $request->user()->mcpConnectors()->create($request->validated());

        $synchronizer->sync($connector);

        return redirect()
            ->route('web.connectors.index')
            ->with('status', $this->outcome($connector));
    }

    public function update(
        ConnectorRequest $request,
        McpConnector $connector,
        ConnectorSynchronizer $synchronizer,
    ): RedirectResponse {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $connector->update($request->validated());
        $synchronizer->sync($connector);

        return redirect()
            ->route('web.connectors.index')
            ->with('status', $this->outcome($connector));
    }

    public function resync(
        Request $request,
        McpConnector $connector,
        ConnectorSynchronizer $synchronizer,
    ): RedirectResponse {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $synchronizer->sync($connector);

        return redirect()
            ->route('web.connectors.index')
            ->with('status', $this->outcome($connector));
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
     * là thứ quyết định AI dùng được dịch vụ hay không.
     */
    private function outcome(McpConnector $connector): string
    {
        if ($connector->last_error !== null) {
            return __('Saved, but could not connect: :error', ['error' => $connector->last_error]);
        }

        $count = count($connector->tools ?? []);

        return trans_choice('Connected :name, read :count tool.|Connected :name, read :count tools.', $count, ['name' => $connector->name, 'count' => $count]);
    }
}
