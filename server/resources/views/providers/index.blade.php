@extends('layouts.app')

@section('title', 'Nhà cung cấp mô hình · SnapAsk')
@section('description', 'Cắm khoá API của riêng bạn và chọn mô hình dùng để trả lời.')

@section('content')
  <div class="row">
    <div>
      <h1>Nhà cung cấp mô hình</h1>
      <p class="note" style="margin-top: 6px">
        Cắm khoá API của riêng bạn để tự trả tiền token và không bị giới hạn lượt hỏi.
      </p>
    </div>
  </div>

  @if (session('status'))
    <p class="flash">{{ session('status') }}</p>
  @endif

  {{-- Nhà cung cấp mặc định luôn có mặt, không xoá được, và là nơi lùi về. --}}
  <article class="card {{ $user->active_provider_id === null ? 'card--on' : '' }}">
    <div class="row" style="margin-bottom: 4px; align-items: baseline">
      <h2>Mặc định của SnapAsk</h2>
      @if ($user->active_provider_id === null)
        <span class="pill">đang dùng</span>
      @endif
    </div>

    <p class="note">
      Dùng khoá của SnapAsk, tính theo hạn mức gói
      <strong class="tabular">{{ $user->plan }}</strong> — còn
      <strong class="tabular">{{ $user->quotaSummary()['remaining'] }}/{{ $user->monthly_ask_limit }}</strong>
      lượt trong tháng.
    </p>

    @if ($user->active_provider_id !== null)
      <div class="actions">
        <form method="POST" action="{{ route('web.providers.default') }}">
          @csrf
          <button type="submit" class="btn--quiet">Quay về mặc định</button>
        </form>
      </div>
    @endif
  </article>

  <h2 style="margin: 30px 0 12px">Nhà cung cấp của bạn</h2>

  @forelse ($providers as $provider)
    <article class="card {{ $user->active_provider_id === $provider->id ? 'card--on' : '' }}">
      <div class="row" style="margin-bottom: 4px; align-items: baseline">
        <h2>{{ $provider->name }}</h2>
        @if ($user->active_provider_id === $provider->id)
          <span class="pill">đang dùng {{ $user->active_model }}</span>
        @endif
      </div>

      <p class="note" style="word-break: break-all">
        {{ $provider->base_url }} · {{ $provider->api_format->label() }}
      </p>

      <div class="models">
        @foreach ($provider->models as $model)
          <form method="POST" action="{{ route('web.providers.select', $provider) }}">
            @csrf
            <input type="hidden" name="model" value="{{ $model }}">
            <button type="submit"
                    class="chip {{ $user->active_provider_id === $provider->id && $user->active_model === $model ? 'chip--on' : '' }}">
              {{ $model }}
            </button>
          </form>
        @endforeach
      </div>

      <div class="actions">
        <form method="POST"
              action="{{ route('web.providers.destroy', $provider) }}"
              onsubmit="return confirm('Xoá {{ $provider->name }}?')">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn--quiet btn--danger">Xoá</button>
        </form>
      </div>
    </article>
  @empty
    <div class="card">
      <h2>Chưa có nhà cung cấp riêng</h2>
      <p class="note" style="margin-top: 8px; max-width: 56ch">
        Bạn đang dùng khoá của SnapAsk và bị giới hạn theo gói. Cắm khoá của
        riêng bạn thì hết giới hạn, và hoá đơn token về thẳng nhà cung cấp bạn chọn.
      </p>
    </div>
  @endforelse

  <h2 style="margin: 30px 0 12px">Thêm nhà cung cấp</h2>

  <form method="POST" action="{{ route('web.providers.store') }}" class="stack card">
    @csrf

    @if ($errors->any())
      <div class="errors">
        <ul>
          @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <label>
      Tên hiển thị
      <input type="text" name="name" value="{{ old('name') }}" placeholder="OpenAI của tôi" required>
    </label>

    <label>
      Định dạng API
      <select name="api_format" id="api-format" required>
        @foreach ($formats as $format)
          <option value="{{ $format->value }}"
                  data-hint="{{ $format->baseUrlHint() }}"
                  @selected(old('api_format') === $format->value)>{{ $format->label() }}</option>
        @endforeach
      </select>
    </label>

    <label>
      Base URL
      <input type="url" name="base_url" id="base-url" value="{{ old('base_url') }}"
             placeholder="https://api.example.com/v1" required>
    </label>

    <label>
      Khoá API
      {{-- Không đổ lại giá trị cũ: khoá là bí mật, không nên nằm trong HTML. --}}
      <input type="password" name="api_key" autocomplete="off" required>
    </label>

    <label>
      Danh sách mô hình
      <textarea name="models" rows="4" placeholder="gpt-4o-mini&#10;gpt-4o" required>{{ old('models') }}</textarea>
      <span class="hint">Mỗi dòng một mã mô hình. Mô hình phải đọc được ảnh.</span>
    </label>

    <button type="submit">Thêm nhà cung cấp</button>
  </form>

  <script>
    // Đổi định dạng thì gợi ý luôn địa chỉ tương ứng, đỡ phải đi tra tài liệu.
    document.getElementById('api-format').addEventListener('change', (event) => {
      const baseUrl = document.getElementById('base-url');
      const option = event.target.selectedOptions[0];

      if (!baseUrl.value || baseUrl.dataset.filled !== 'user') {
        baseUrl.value = option.dataset.hint;
        baseUrl.dataset.filled = 'hint';
      }
    });

    document.getElementById('base-url').addEventListener('input', (event) => {
      event.target.dataset.filled = 'user';
    });
  </script>
@endsection
