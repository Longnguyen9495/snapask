@extends('layouts.app')

@section('title', __('Model providers · SnapAsk'))
@section('description', __('Plug in your own API key and pick the model that answers.'))
@section('section', __('AI models'))

@section('content')
  @php
    $usingDefault = $user->active_provider_id === null;
    $quota = $user->quotaSummary();
    $setup = $user->activeSetup();
    $createErrors = $errors->getBag('default')->any();
  @endphp

  <x-page-header :title="__('AI models')" :eyebrow="__('Model providers')"
                 :lead="__('Plug in your own API key to pay for your own tokens and lift the ask limit.')" />

  {{-- Cấu hình đang chạy: câu trả lời cho "ai đang trả lời tôi" nằm ngay đầu trang. --}}
  <section class="section panel panel--pad panel--accent" aria-labelledby="active-title">
    <p class="eyebrow" id="active-title">{{ __('Active configuration') }}</p>
    <div class="option__head">
      <h2 class="mono">{{ $setup['model'] ?? __('Not set') }}</h2>
      <x-status-badge status="active">{{ __('in use') }}</x-status-badge>
    </div>
    <p class="option__meta">
      @if ($usingDefault)
        {{ __('Uses the SnapAsk key, metered against your :plan plan — :remaining of :limit asks left this month.', [
            'plan' => $user->plan,
            'remaining' => $quota['remaining'],
            'limit' => $quota['limit'],
        ]) }}
      @else
        {{ __('Via :provider on your own key. Asks are not counted against your plan.', ['provider' => $setup['provider']]) }}
      @endif
    </p>
  </section>

  <section class="section" aria-labelledby="choose-title">
    <div class="section__head">
      <h2 id="choose-title">{{ __('Choose who answers') }}</h2>
      <p>{{ __('Pick a model; the next question uses it straight away.') }}</p>
    </div>

    <ul class="list">
      {{-- Nhà cung cấp mặc định luôn có mặt, không xoá được, và là nơi lùi về. --}}
      <li @class(['option', 'is-active' => $usingDefault])>
        <span class="option__radio" aria-hidden="true"></span>
        <div class="option__body">
          <div class="option__head">
            <h3>{{ __('SnapAsk default') }}</h3>
            @if ($usingDefault)
              <x-status-badge status="active">{{ __('in use') }}</x-status-badge>
            @endif
          </div>
          <p class="option__meta">{{ __('The SnapAsk key, counted against your monthly plan. Nothing to configure.') }}</p>
          @if (filled(config('snapask.model')))
            <div class="option__models chips"><span class="tag">{{ config('snapask.model') }}</span></div>
          @endif
        </div>
        @unless ($usingDefault)
          <div class="option__side">
            <form method="POST" action="{{ route('web.providers.default') }}">
              @csrf
              <button type="submit" class="btn btn--secondary btn--sm">{{ __('Use SnapAsk default') }}</button>
            </form>
          </div>
        @endunless
      </li>

      @foreach ($providers as $provider)
        @php
          $isActive = $user->active_provider_id === $provider->id;
          $bag = 'provider'.$provider->id;
          $editErrors = $errors->getBag($bag)->any();
          $prefix = 'provider-'.$provider->id.'-';
        @endphp
        <li @class(['option', 'is-active' => $isActive]) id="provider-{{ $provider->id }}">
          <span class="option__radio" aria-hidden="true"></span>
          <div class="option__body">
            <div class="option__head">
              <h3>{{ $provider->name }}</h3>
              @if ($isActive)
                <x-status-badge status="active">{{ __('using :model', ['model' => $setup['model']]) }}</x-status-badge>
              @endif
            </div>
            <p class="option__meta break">
              {{ $provider->base_url }} · {{ $provider->api_format->label() }}
            </p>
            <p class="option__meta">{{ __('API key saved — hidden for your safety') }}</p>

            <div class="option__models chips" role="group" aria-label="{{ __('Models of :name', ['name' => $provider->name]) }}">
              @foreach ($provider->models as $model)
                @php $selected = $isActive && $setup['model'] === $model; @endphp
                <form method="POST" action="{{ route('web.providers.select', $provider) }}">
                  @csrf
                  <input type="hidden" name="model" value="{{ $model }}">
                  <button type="submit" class="chip" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                          aria-label="{{ __('Use :model via :provider', ['model' => $model, 'provider' => $provider->name]) }}">
                    @if ($selected) <x-icon name="check" :size="14" /> @endif
                    {{ $model }}
                  </button>
                </form>
              @endforeach
            </div>

            <details class="disclosure disclosure--quiet option__edit" @if ($editErrors) open @endif>
              <summary><x-icon name="pencil" :size="14" />{{ __('Edit :name', ['name' => $provider->name]) }}<x-icon name="chevron-down" :size="14" class="icon--chevron" /></summary>
              <div class="disclosure__body">
                <form method="POST" action="{{ route('web.providers.update', $provider) }}" class="form" novalidate>
                  @csrf
                  @method('PUT')
                  <x-error-summary :bag="$bag" :prefix="$prefix" />

                  <div class="form__grid">
                    <x-form-field name="name" :id="$prefix.'name'" :bag="$bag" :label="__('Display name')"
                                  :value="$editErrors ? old('name') : $provider->name" required />

                    <x-form-field name="api_format" type="select" :id="$prefix.'api_format'" :bag="$bag" :label="__('API format')" required>
                      @foreach ($formats as $format)
                        <option value="{{ $format->value }}" @selected(($editErrors ? old('api_format') : $provider->api_format->value) === $format->value)>{{ $format->label() }}</option>
                      @endforeach
                    </x-form-field>

                    <x-form-field name="base_url" type="url" :id="$prefix.'base_url'" :bag="$bag" :label="__('Base URL')" :full="true"
                                  :value="$editErrors ? old('base_url') : $provider->base_url" :hint="__('Must start with https://.')" required />

                    {{-- Không đổ lại khoá cũ: khoá là bí mật, không nên nằm trong HTML. --}}
                    <x-form-field name="api_key" type="password" :id="$prefix.'api_key'" :bag="$bag" :label="__('API key')" :full="true"
                                  :hint="__('Leave blank to keep the saved key.')" autocomplete="off" />

                    <x-form-field name="models" type="textarea" :id="$prefix.'models'" :bag="$bag" :label="__('Model list')" :full="true"
                                  :hint="__('One model id per line. The model must be able to read images.')" rows="4" required>{{ $editErrors ? old('models') : implode("\n", $provider->models) }}</x-form-field>
                  </div>

                  <div class="form__actions">
                    <button type="submit" class="btn">{{ __('Save changes') }}</button>
                  </div>
                </form>

                <form method="POST" action="{{ route('web.providers.destroy', $provider) }}" class="option__edit"
                      data-confirm="{{ __(':name and its saved API key will be removed. Conversations stay.', ['name' => $provider->name]) }}"
                      data-confirm-title="{{ __('Delete :name?', ['name' => $provider->name]) }}"
                      data-confirm-action="{{ __('Delete provider') }}">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn--danger btn--sm"><x-icon name="trash" :size="15" />{{ __('Delete provider') }}</button>
                </form>
              </div>
            </details>
          </div>
        </li>
      @endforeach
    </ul>

    @if ($providers->isEmpty())
      <p class="field__hint">{{ __('You are on the SnapAsk key and capped by your plan. Plug in your own key to lift the cap — the token bill goes straight to the provider you pick.') }}</p>
    @endif
  </section>

  <section class="section" aria-labelledby="add-title">
    <details class="disclosure disclosure--panel" id="add-provider" @if ($createErrors) open @endif>
      <summary>
        <x-icon name="plus" :size="16" />
        <span id="add-title">{{ __('Add a provider') }}</span>
        <x-icon name="chevron-down" :size="16" class="icon--chevron" />
      </summary>
      <div class="disclosure__body">
        <form method="POST" action="{{ route('web.providers.store') }}" class="form" novalidate>
          @csrf
          <x-error-summary />

          <div class="form__grid">
            <x-form-field name="name" :label="__('Display name')" :value="old('name')" required />

            <x-form-field name="api_format" type="select" :label="__('API format')" data-format-select="field-base_url" required>
              @foreach ($formats as $format)
                <option value="{{ $format->value }}" data-hint="{{ $format->baseUrlHint() }}" @selected(old('api_format') === $format->value)>{{ $format->label() }}</option>
              @endforeach
            </x-form-field>

            <x-form-field name="base_url" type="url" :label="__('Base URL')" :full="true" :value="old('base_url')"
                          :hint="__('Must start with https:// — your key travels with every call.')" required />

            <x-form-field name="api_key" type="password" :label="__('API key')" :full="true" autocomplete="off"
                          :hint="__('Stored encrypted and never shown again.')" required />

            <x-form-field name="models" type="textarea" :label="__('Model list')" :full="true" rows="4"
                          :hint="__('One model id per line. The model must be able to read images.')" required>{{ old('models') }}</x-form-field>
          </div>

          <div class="form__actions">
            <button type="submit" class="btn"><x-icon name="plus" :size="16" />{{ __('Add a provider') }}</button>
          </div>
        </form>
      </div>
    </details>
  </section>
@endsection
