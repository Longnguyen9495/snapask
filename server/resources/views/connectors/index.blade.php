@extends('layouts.app')

@section('title', __('Connected services · SnapAsk'))
@section('description', __('Declare a service so the AI reads real data instead of guessing from the screenshot.'))
@section('section', __('Connected services'))

@section('content')
  @php $createErrors = $errors->getBag('default')->any(); @endphp

  <x-page-header :title="__('Connected services')" :eyebrow="__('Services for the AI to look up')"
                 :lead="__('Declare a service so the AI reads real data instead of guessing from the screenshot.')">
    <x-slot:actions>
      <a href="#add-service" class="btn" data-open-details="add-service"><x-icon name="plus" :size="16" />{{ __('Add a service') }}</a>
    </x-slot:actions>
  </x-page-header>

  @if ($summary['total'] > 0)
    <section class="panel stats summary-strip" aria-label="{{ __('Service health') }}">
      <div class="stat">
        <p class="stat__label"><x-icon name="plug" :size="15" />{{ __('Connected') }}</p>
        <p class="stat__value tabular">{{ $summary['total'] }}</p>
      </div>
      <div class="stat">
        <p class="stat__label"><x-icon name="check-circle" :size="15" />{{ __('Healthy') }}</p>
        <p class="stat__value tabular">{{ $summary['ok'] }}</p>
      </div>
      <div class="stat">
        <p class="stat__label"><x-icon name="alert" :size="15" />{{ __('Failing') }}</p>
        <p class="stat__value tabular">{{ $summary['error'] }}</p>
      </div>
      <div class="stat">
        <p class="stat__label"><x-icon name="ban" :size="15" />{{ __('Disabled') }}</p>
        <p class="stat__value tabular">{{ $summary['disabled'] }}</p>
      </div>
    </section>

    <ul class="list" aria-label="{{ __('Your services') }}">
      @foreach ($connectors as $connector)
        @php
          $status = $connector->healthStatus();
          $bag = 'connector'.$connector->id;
          $editErrors = $errors->getBag($bag)->any();
          $prefix = 'connector-'.$connector->id.'-';
          $toolCount = count($connector->tools ?? []);
        @endphp
        <li class="option" id="connector-{{ $connector->id }}">
          <div class="option__body">
            <div class="option__head">
              <h3>{{ $connector->name }}</h3>
              <span class="tag">{{ $connector->slug }}</span>
              @switch($status)
                @case('ok') <x-status-badge status="ok">{{ __('Healthy') }}</x-status-badge> @break
                @case('error') <x-status-badge status="error">{{ __('Failing') }}</x-status-badge> @break
                @case('disabled') <x-status-badge status="disabled">{{ __('Disabled') }}</x-status-badge> @break
                @default <x-status-badge>{{ __('Not synced yet') }}</x-status-badge>
              @endswitch
            </div>

            <p class="option__meta break" title="{{ $connector->url }}">{{ \Illuminate\Support\Str::limit($connector->url, 80) }}</p>
            <p class="option__meta tabular">
              {{ trans_choice(':count tool|:count tools', $toolCount, ['count' => $toolCount]) }}
              ·
              @if ($connector->synced_at)
                {{ __('synced') }} <x-time :at="$connector->synced_at" />
              @else
                {{ __('never synced') }}
              @endif
              · {{ filled($connector->auth_token) ? __('token saved') : __('no token') }}
            </p>

            @if ($connector->last_error)
              <div class="status-note" role="note">
                <x-icon name="alert" :size="16" />
                <div>
                  <p>{{ __('Could not connect: :error', ['error' => $connector->last_error]) }}</p>
                  <p>{{ __('Check that the address is reachable over HTTPS and that the token is still valid, then sync again.') }}</p>
                </div>
              </div>
            @endif

            @if ($toolCount > 0)
              <details class="disclosure disclosure--quiet option__edit">
                <summary>{{ trans_choice('Show :count tool|Show all :count tools', $toolCount, ['count' => $toolCount]) }}<x-icon name="chevron-down" :size="14" class="icon--chevron" /></summary>
                <div class="disclosure__body chips">
                  @foreach ($connector->tools as $tool)
                    <span class="tag" title="{{ $tool['description'] ?? '' }}">{{ $tool['name'] }}</span>
                  @endforeach
                </div>
              </details>
            @endif

            <details class="disclosure disclosure--quiet option__edit" @if ($editErrors) open @endif>
              <summary><x-icon name="pencil" :size="14" />{{ __('Edit :name', ['name' => $connector->name]) }}<x-icon name="chevron-down" :size="14" class="icon--chevron" /></summary>
              <div class="disclosure__body">
                <form method="POST" action="{{ route('web.connectors.update', $connector) }}" class="form" novalidate
                      data-pending="{{ __('Saving and connecting…') }}">
                  @csrf
                  @method('PUT')
                  <x-error-summary :bag="$bag" :prefix="$prefix" />

                  <div class="form__grid">
                    <x-form-field name="name" :id="$prefix.'name'" :bag="$bag" :label="__('Display name')"
                                  :value="$editErrors ? old('name') : $connector->name" required />
                    <x-form-field name="slug" :id="$prefix.'slug'" :bag="$bag" :label="__('Short code')"
                                  :value="$editErrors ? old('slug') : $connector->slug"
                                  :hint="__('Lowercase letters, digits, - and _.')" pattern="[a-z0-9_-]+" required />
                    <x-form-field name="url" type="url" :id="$prefix.'url'" :bag="$bag" :label="__('MCP address')" :full="true"
                                  :value="$editErrors ? old('url') : $connector->url"
                                  :hint="__('Must be https — the token travels with every call.')" required />
                    {{-- Không đổ lại token cũ: token là bí mật, không nên nằm trong HTML. --}}
                    <x-form-field name="auth_token" type="password" :id="$prefix.'auth_token'" :bag="$bag" :full="true"
                                  :label="__('Access token')" :optional="true" autocomplete="off"
                                  :hint="__('Leave blank to keep the saved token.')" />
                    @if (filled($connector->auth_token))
                      <label class="check field--full">
                        <input type="checkbox" name="clear_token" value="1">
                        {{ __('Remove the saved token') }}
                      </label>
                    @endif
                  </div>

                  <div class="form__actions">
                    <button type="submit" class="btn">{{ __('Save and reconnect') }}</button>
                  </div>
                </form>
              </div>
            </details>
          </div>

          <div class="option__side">
            <form method="POST" action="{{ route('web.connectors.resync', $connector) }}" data-pending="{{ __('Syncing :name…', ['name' => $connector->name]) }}">
              @csrf
              <button type="submit" class="btn btn--secondary btn--sm" aria-label="{{ __('Sync :name again', ['name' => $connector->name]) }}">
                <x-icon name="refresh" :size="15" />{{ __('Re-sync') }}
              </button>
            </form>

            <form method="POST" action="{{ route('web.connectors.toggle', $connector) }}">
              @csrf
              <button type="submit" class="btn btn--ghost btn--sm" aria-label="{{ $connector->enabled ? __('Turn off :name', ['name' => $connector->name]) : __('Turn on :name', ['name' => $connector->name]) }}">
                <x-icon name="power" :size="15" />{{ $connector->enabled ? __('Turn off') : __('Turn on') }}
              </button>
            </form>

            <form method="POST" action="{{ route('web.connectors.destroy', $connector) }}"
                  data-confirm="{{ __('Delete :name? The AI will no longer be able to look up data in this service.', ['name' => $connector->name]) }}"
                  data-confirm-title="{{ __('Delete this service?') }}"
                  data-confirm-action="{{ __('Delete service') }}">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn--ghost btn--icon btn--sm" aria-label="{{ __('Delete :name', ['name' => $connector->name]) }}">
                <x-icon name="trash" :size="15" />
              </button>
            </form>
          </div>
        </li>
      @endforeach
    </ul>
  @else
    {{--
      Trạng thái rỗng giải thích luôn dịch vụ này để làm gì. Một dòng "chưa có gì"
      không nói cho người mới biết bước tiếp theo là gì.
    --}}
    <x-empty-state :title="__('No service connected yet')" icon="plug">
      {{ __('Right now the AI only reads what it can see in the screenshot. Connect a service that speaks MCP and it can look up real data — stock, orders, appointments — while it answers.') }}
    </x-empty-state>
  @endif

  <section class="section" aria-labelledby="add-service-title">
    <details class="disclosure disclosure--panel" id="add-service" @if ($createErrors || $summary['total'] === 0) open @endif>
      <summary>
        <x-icon name="plus" :size="16" />
        <span id="add-service-title">{{ __('Add a service') }}</span>
        <x-icon name="chevron-down" :size="16" class="icon--chevron" />
      </summary>
      <div class="disclosure__body">
        <form method="POST" action="{{ route('web.connectors.store') }}" class="form" novalidate data-pending="{{ __('Saving and connecting…') }}">
          @csrf
          <x-error-summary />

          <div class="form__grid">
            <x-form-field name="name" :label="__('Display name')" :value="old('name')" required />
            <x-form-field name="slug" :label="__('Short code')" :value="old('slug')"
                          :hint="__('Lowercase letters, digits, - and _. The AI sees it as the service name.')" pattern="[a-z0-9_-]+" required />
            <x-form-field name="url" type="url" :label="__('MCP address')" :full="true" :value="old('url')"
                          :hint="__('Must be https — the token travels with every call.')" required />
            <x-form-field name="auth_token" type="password" :label="__('Access token')" :optional="true" :full="true" autocomplete="off"
                          :hint="__('Only if the service requires one. Stored encrypted and never shown again.')" />
          </div>

          <div class="form__actions">
            <button type="submit" class="btn"><x-icon name="plug" :size="16" />{{ __('Add and connect') }}</button>
          </div>
        </form>
      </div>
    </details>
  </section>
@endsection
