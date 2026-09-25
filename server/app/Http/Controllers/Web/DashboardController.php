<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\McpConnector;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Số hội thoại gần nhất hiện ở tổng quan. */
    private const RECENT_LIMIT = 6;

    /**
     * Trang tổng quan sau đăng nhập.
     *
     * Chỉ đọc những gì đã có trong cơ sở dữ liệu — hạn mức, mô hình đang dùng,
     * kết quả đồng bộ gần nhất của từng dịch vụ — nên trang mở nhanh dù dịch vụ
     * ngoài của khách đang chậm hay hỏng.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $connectors = $user->mcpConnectors()->get();
        $statuses = $connectors->map(fn (McpConnector $connector): string => $connector->healthStatus());
        $setup = $user->activeSetup();

        $defaultReady = filled(config('snapask.api_key')) && filled(config('snapask.base_url')) && filled(config('snapask.model'));

        $checklist = [
            'desktop' => $user->tokens()->exists(),
            'model' => $setup['source'] === 'custom' || $defaultReady,
            'first_question' => $user->conversations()->exists(),
            'service' => $connectors->isNotEmpty(),
        ];

        return view('dashboard', [
            'user' => $user,
            'quota' => $user->quotaSummary(),
            'quotaResetsAt' => now()->startOfMonth()->addMonth(),
            'setup' => $setup,
            'modelReady' => $checklist['model'],
            'services' => [
                'total' => $connectors->count(),
                'ok' => $statuses->filter(fn (string $status) => $status === 'ok')->count(),
                'error' => $statuses->filter(fn (string $status) => $status === 'error')->count(),
                'disabled' => $statuses->filter(fn (string $status) => $status === 'disabled')->count(),
                'last_synced_at' => $connectors->max('synced_at'),
            ],
            'recent' => $user->conversations()
                ->select('conversations.*')
                ->withListing()
                ->byActivity()
                ->limit(self::RECENT_LIMIT)
                ->get(),
            'checklist' => $checklist,
            // Dịch vụ là tuỳ chọn: thiếu nó không giữ danh sách việc cần làm ở lại.
            'showChecklist' => ! ($checklist['desktop'] && $checklist['model'] && $checklist['first_question']),
        ]);
    }
}
