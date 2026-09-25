@extends('layouts.app')

@section('title', __('Model providers · SnapAsk'))
@section('description', __('Plug in your own API key and pick the model that answers.'))

@section('content')
  <div class="row">
    <div>
      <h1>{{ __('Model providers') }}</h1>
      <p class="note" style="margin-top: 6px">
        {{ __('Plug in your own API key to pay for your own tokens and lift the ask limit.') }}
      </p>
    </div>
  </div>

  @if (session('status'))
    <p class="flash">{{ session('status') }}</p>
  @endif

  {{-- Nhà cung cấp mặc định luôn có mặt, không xoá được, và là nơi lùi về. --}}
  <article class="card {{ $user->active_provider_id === null ? 'card--on' : '' }}">
    <div class="row" style="margin-bottom: 4px; align-items: baseline">
      <h2>{{ __('SnapAsk default') }}</h2>
      @if ($user->active_provider_id === null)
        <span class="pill">{{ __('in use') }}</span>
      @endif
    </div>

    <p class="note tabular">
      {{ __('Uses the SnapAsk key, metered against your :plan plan — :remaining of :limit asks left this month.', [
          'plan' => $user->plan,
          'remaining' => $user->quotaSummary()['remaining'],
          'limit' => $user->monthly_ask_limit,
      ]) }}
    </p>

    @if ($user->active_provider_id !== null)
      <div class="actions">
        <form method="POST" action="{{ route('web.providers.default') }}">
          @csrf
          <button type="submit" class="btn--quiet">{{ __('Back to default') }}</button>
        </form>
      </div>
    @endif
  </article>

  <h2 style="margin: 30px 0 12px">{{ __('Your providers') }}</h2>

  @forelse ($providers as $provider)
    <article class="card {{ $user->active_provider_id === $provider->id ? 'card--on' : '' }}">
      <div class="row" style="margin-bottom: 4px; align-items: baseline">
        <h2>{{ $provider->name }}</h2>
        @if ($user->active_provider_id === $provider->id)
          <span class="pill">{{ __('using :model', ['model' => $user->active_model]) }}</span>
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
              onsubmit="return confirm('{{ __('Delete :name?', ['name' => $provider->name]) }}')">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn--quiet btn--danger">{{ __('Delete') }}</button>
        </form>
      </div>
    </article>
  @empty
    <div class="card">
      <h2>{{ __('No provider of your own yet') }}</h2>
      <p class="note" style="margin-top: 8px; max-width: 56ch">
        {{ __('You are on the SnapAsk key and capped by your plan. Plug in your own key to lift the cap — the token bill goes straight to the provider you pick.') }}
      </p>
    </div>
  @endforelse

  <h2 style="margin: 30px 0 12px">{{ __('Add a provider') }}</h2>

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
      {{ __('Display name') }}
      <input type="text" name="name" value="{{ old('name') }}" placeholder="OpenAI" required>
    </label>

    <label>
      {{ __('API format') }}
      <select name="api_format" id="api-format" required>
        @foreach ($formats as $format)
          <option value="{{ $format->value }}"
                  data-hint="{{ $format->baseUrlHint() }}"
                  @selected(old('api_format') === $format->value)>{{ $format->label() }}</option>
        @endforeach
      </select>
    </label>

    <label>
      {{ __('Base URL') }}
      <input type="url" name="base_url" id="base-url" value="{{ old('base_url') }}"
             placeholder="https://api.example.com/v1" required>
    </label>

    <label>
      {{ __('API key') }}
      {{-- Không đổ lại giá trị cũ: khoá là bí mật, không nên nằm trong HTML. --}}
      <input type="password" name="api_key" autocomplete="off" required>
    </label>

    <label>
      {{ __('Model list') }}
      <textarea name="models" rows="4" placeholder="gpt-4o-mini&#10;gpt-4o" required>{{ old('models') }}</textarea>
      <span class="hint">{{ __('One model id per line. The model must be able to read images.') }}</span>
    </label>

    <button type="submit">{{ __('Add a provider') }}</button>
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
