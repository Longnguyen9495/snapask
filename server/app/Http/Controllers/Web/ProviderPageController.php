<?php

namespace App\Http\Controllers\Web;

use App\Enums\ApiFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderRequest;
use App\Models\ModelProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderPageController extends Controller
{
    public function index(Request $request): View
    {
        return view('providers.index', [
            'providers' => $request->user()->modelProviders()->orderBy('name')->get(),
            'formats' => ApiFormat::cases(),
            'user' => $request->user(),
        ]);
    }

    public function store(ProviderRequest $request): RedirectResponse
    {
        $provider = $request->user()->modelProviders()->create([
            ...$request->safe()->only(['name', 'base_url', 'api_key', 'api_format']),
            'models' => $request->modelList(),
        ]);

        // Nhà cung cấp đầu tiên được bật luôn: khách thêm nó vào là để dùng, bắt
        // bấm thêm một nút nữa mới chạy thì chỉ tổ gây bối rối.
        if ($request->user()->active_provider_id === null) {
            $this->activate($request, $provider, $provider->models[0]);
        }

        return redirect()->route('web.providers.index')
            ->with('status', "Đã thêm {$provider->name}.");
    }

    public function update(ProviderRequest $request, ModelProvider $provider): RedirectResponse
    {
        abort_unless($provider->user_id === $request->user()->id, 404);

        $provider->update([
            ...$request->safe()->only(['name', 'base_url', 'api_key', 'api_format']),
            'models' => $request->modelList(),
        ]);

        return redirect()->route('web.providers.index')->with('status', 'Đã cập nhật.');
    }

    /** Chọn nhà cung cấp và mô hình dùng cho các lượt hỏi tiếp theo. */
    public function select(Request $request, ModelProvider $provider): RedirectResponse
    {
        abort_unless($provider->user_id === $request->user()->id, 404);

        $model = (string) $request->string('model');

        if (! in_array($model, $provider->models, true)) {
            return back()->with('status', 'Mô hình này không có trong danh sách.');
        }

        $this->activate($request, $provider, $model);

        return redirect()->route('web.providers.index')
            ->with('status', "Đang dùng {$model} qua {$provider->name}.");
    }

    /** Quay về nhà cung cấp mặc định của hệ thống, kèm hạn mức theo gói. */
    public function useDefault(Request $request): RedirectResponse
    {
        $request->user()->update(['active_provider_id' => null, 'active_model' => null]);

        return redirect()->route('web.providers.index')
            ->with('status', 'Đã quay về nhà cung cấp mặc định.');
    }

    public function destroy(Request $request, ModelProvider $provider): RedirectResponse
    {
        abort_unless($provider->user_id === $request->user()->id, 404);

        $provider->delete();

        // Khoá ngoại đặt null khi xoá, nhưng `active_model` thì không ai dọn —
        // để lại thì lượt hỏi sau gửi một mã mô hình mồ côi lên nhà cung cấp mặc định.
        if ($request->user()->refresh()->active_provider_id === null) {
            $request->user()->update(['active_model' => null]);
        }

        return redirect()->route('web.providers.index')->with('status', 'Đã xoá nhà cung cấp.');
    }

    private function activate(Request $request, ModelProvider $provider, string $model): void
    {
        $request->user()->update([
            'active_provider_id' => $provider->id,
            'active_model' => $model,
        ]);
    }
}
