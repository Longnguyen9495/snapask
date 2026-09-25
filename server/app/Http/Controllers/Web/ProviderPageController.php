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
            ->with('status', __('Added :name.', ['name' => $provider->name]));
    }

    public function update(ProviderRequest $request, ModelProvider $provider): RedirectResponse
    {
        abort_unless($provider->user_id === $request->user()->id, 404);

        $provider->update([
            ...$request->safe()->only(['name', 'base_url', 'api_format']),
            ...($request->filled('api_key') ? ['api_key' => $request->validated('api_key')] : []),
            'models' => $request->modelList(),
        ]);

        return redirect()->route('web.providers.index')
            ->with('status', __('Saved :name.', ['name' => $provider->name]));
    }

    /** Chọn nhà cung cấp và mô hình dùng cho các lượt hỏi tiếp theo. */
    public function select(Request $request, ModelProvider $provider): RedirectResponse
    {
        abort_unless($provider->user_id === $request->user()->id, 404);

        $model = (string) $request->string('model');

        if (! in_array($model, $provider->models, true)) {
            return back()->with('status', __('That model is not in the list.'));
        }

        $this->activate($request, $provider, $model);

        return redirect()->route('web.providers.index')
            ->with('status', __('Now using :model via :provider.', ['model' => $model, 'provider' => $provider->name]));
    }

    /** Quay về nhà cung cấp mặc định của hệ thống, kèm hạn mức theo gói. */
    public function useDefault(Request $request): RedirectResponse
    {
        $request->user()->update(['active_provider_id' => null, 'active_model' => null]);

        return redirect()->route('web.providers.index')
            ->with('status', __('Back on the default provider.'));
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

        return redirect()->route('web.providers.index')->with('status', __('Provider deleted.'));
    }

    private function activate(Request $request, ModelProvider $provider, string $model): void
    {
        $request->user()->update([
            'active_provider_id' => $provider->id,
            'active_model' => $model,
        ]);
    }
}
