<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectorRequest;
use App\Models\McpConnector;
use App\Services\Mcp\ConnectorSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'connectors' => $request->user()
                ->mcpConnectors()
                ->orderBy('name')
                ->get()
                ->map(fn (McpConnector $connector): array => $this->present($connector)),
        ]);
    }

    public function store(ConnectorRequest $request, ConnectorSynchronizer $synchronizer): JsonResponse
    {
        $connector = $request->user()->mcpConnectors()->create($request->safe()->except('clear_token'));

        $synchronizer->sync($connector);

        return response()->json(['connector' => $this->present($connector)], 201);
    }

    public function update(ConnectorRequest $request, McpConnector $connector, ConnectorSynchronizer $synchronizer): JsonResponse
    {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $connector->update($request->safe()->except('clear_token'));

        $synchronizer->sync($connector);

        return response()->json(['connector' => $this->present($connector)]);
    }

    public function destroy(Request $request, McpConnector $connector): JsonResponse
    {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $connector->delete();

        return response()->json(['deleted' => true]);
    }

    /** Đọc lại danh sách công cụ, dùng khi dịch vụ bên kia vừa đổi. */
    public function resync(Request $request, McpConnector $connector, ConnectorSynchronizer $synchronizer): JsonResponse
    {
        abort_unless($connector->user_id === $request->user()->id, 404);

        $synchronizer->sync($connector);

        return response()->json(['connector' => $this->present($connector)]);
    }

    /** @return array<string, mixed> */
    private function present(McpConnector $connector): array
    {
        return [
            ...$connector->only(['id', 'slug', 'name', 'url', 'enabled', 'synced_at', 'last_error']),
            // Không trả token ra ngoài; chỉ cho biết đã có hay chưa.
            'has_token' => filled($connector->auth_token),
            'tools' => collect($connector->tools ?? [])
                ->map(fn (array $tool): array => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                ])
                ->all(),
        ];
    }
}
