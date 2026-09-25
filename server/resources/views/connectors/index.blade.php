@extends('layouts.app')

@section('title', __('Connected services · SnapAsk'))
@section('description', __('Declare a service so the AI reads real data instead of guessing from the screenshot.'))

@section('content')
  <div class="row">
    <div>
      <h1>{{ __('Services for the AI to look up') }}</h1>
      <p class="note tabular" style="margin-top: 6px">
        {{ $quota['own_key']
            ? __(':plan plan · your own key, no ask limit', ['plan' => $quota['plan']])
            : __(':plan plan · :remaining of :limit asks left this month', [
                'plan' => $quota['plan'],
                'remaining' => $quota['remaining'],
                'limit' => $quota['limit'],
            ]) }}
      </p>
    </div>

    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="btn--quiet">{{ __('Sign out') }}</button>
    </form>
  </div>

  @if (session('status'))
    <p class="flash">{{ session('status') }}</p>
  @endif

  @forelse ($connectors as $connector)
    <article class="card">
      <div class="row" style="margin-bottom: 4px; align-items: baseline">
        <h2>{{ $connector->name }} <span class="tag">{{ $connector->slug }}</span></h2>
        @unless ($connector->enabled)
          <span class="tag">{{ __('disabled') }}</span>
        @endunless
      </div>

      <p class="note" style="word-break: break-all">{{ $connector->url }}</p>

      @if ($connector->last_error)
        <p class="note status--bad">{{ __('Could not connect: :error', ['error' => $connector->last_error]) }}</p>
      @elseif (filled($connector->tools))
        <p class="note status--ok">
          {{ trans_choice(':count tool · synced :time|:count tools · synced :time', count($connector->tools), [
              'count' => count($connector->tools),
              'time' => $connector->synced_at?->diffForHumans(),
          ]) }}
        </p>
        <div class="tools">
          @foreach ($connector->tools as $tool)
            <span class="tag">{{ $tool['name'] }}</span>
          @endforeach
        </div>
      @else
        <p class="note">{{ __('No tools read yet.') }}</p>
      @endif

      <div class="actions">
        <form method="POST" action="{{ route('web.connectors.resync', $connector) }}">
          @csrf
          <button type="submit" class="btn--quiet">{{ __('Re-sync') }}</button>
        </form>

        <form method="POST"
              action="{{ route('web.connectors.destroy', $connector) }}"
              onsubmit="return confirm('{{ __('Delete :name? The AI will no longer be able to look up data in this service.', ['name' => $connector->name]) }}')">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn--quiet btn--danger">{{ __('Delete') }}</button>
        </form>
      </div>
    </article>
  @empty
    {{--
      Trạng thái rỗng giải thích luôn dịch vụ này để làm gì. Một dòng "chưa có gì"
      không nói cho người mới biết bước tiếp theo là gì.
    --}}
    <div class="card" style="padding: 30px 26px">
      <h2>{{ __('No service connected yet') }}</h2>
      <p class="note" style="margin-top: 8px; max-width: 54ch">
        {{ __('Right now the AI only reads what it can see in the screenshot. Connect a service that speaks MCP and it can look up real data — stock, orders, appointments — while it answers.') }}
      </p>
      <p class="note" style="margin-top: 10px">
        {!! __('The address must be :scheme, because the token travels with every call.', ['scheme' => '<code>https</code>']) !!}
      </p>
    </div>
  @endforelse

  <h2 style="margin: 32px 0 12px">{{ __('Add a service') }}</h2>

  <form method="POST" action="{{ route('web.connectors.store') }}" class="stack card">
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
      <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('Warehouse') }}" required>
    </label>

    <label>
      {{ __('Short code') }}
      <input type="text" name="slug" value="{{ old('slug') }}" placeholder="{{ __('stock') }}"
             pattern="[a-z0-9_-]+" required>
    </label>

    <label>
      {{ __('MCP address') }}
      <input type="url" name="url" value="{{ old('url') }}" placeholder="https://…/mcp" required>
    </label>

    <label>
      {{ __('Access token (if the service requires one)') }}
      {{-- Không đổ lại giá trị cũ: token là bí mật, không nên nằm trong HTML. --}}
      <input type="password" name="auth_token" autocomplete="off">
    </label>

    <button type="submit">{{ __('Add and connect') }}</button>
  </form>
@endsection
