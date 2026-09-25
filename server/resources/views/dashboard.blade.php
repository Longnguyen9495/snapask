@extends('layouts.app')

@section('title', __('Overview · SnapAsk'))
@section('section', __('Overview'))

@section('content')
  @php
    $downloadUrl = app()->getLocale() === 'vi' ? route('download') : route('download.en');
    $usedPercent = $quota['limit'] > 0 ? min(100, (int) round($quota['used'] / $quota['limit'] * 100)) : 100;
    $quotaLow = ! $quota['own_key'] && $quota['remaining'] <= max(1, (int) floor($quota['limit'] * 0.1));
  @endphp

  <header class="welcome">
    <div>
      <h1 id="page-title">{{ __('Hello, :name', ['name' => $user->name]) }}</h1>
      <p class="welcome__meta">
        <x-status-badge status="active">{{ __(':plan plan', ['plan' => ucfirst($quota['plan'])]) }}</x-status-badge>
        <span>{{ $user->email }}</span>
      </p>
    </div>
    <div class="actions">
      <a href="{{ route('web.conversations.index') }}" class="btn btn--secondary"><x-icon name="message" :size="16" />{{ __('Conversations') }}</a>
      <a href="{{ $downloadUrl }}" class="btn"><x-icon name="download" :size="16" />{{ $checklist['desktop'] ? __('Update the desktop app') : __('Get the desktop app') }}</a>
    </div>
  </header>

  @if ($showChecklist)
    <section class="section" aria-labelledby="setup-title">
      <div class="section__head">
        <h2 id="setup-title">{{ __('Finish setting up') }}</h2>
        <p class="tabular">{{ __(':done of :total done', ['done' => collect($checklist)->filter()->count(), 'total' => count($checklist)]) }}</p>
      </div>

      <ol class="checklist panel">
        @foreach ([
            'desktop' => [__('Install SnapAsk Desktop and sign in'), __('The app lives in the tray; snap a region any time with :windows on Windows or :mac on macOS.', ['windows' => 'Ctrl + Alt + W', 'mac' => 'Cmd + Shift + 2']), $downloadUrl, __('Download')],
            'model' => [__('Choose the AI model'), __('Use the SnapAsk default, or plug in your own provider key.'), route('web.providers.index'), __('Choose model')],
            'first_question' => [__('Ask your first question'), __('Type a question in the desktop workspace, or snap a region of your screen.'), $downloadUrl, __('Open the app')],
            'service' => [__('Connect a service (optional)'), __('Let the AI look up real data — stock, orders, appointments — while it answers.'), route('web.connectors.index'), __('Add a service')],
        ] as $key => [$title, $text, $url, $cta])
          <li @class(['is-done' => $checklist[$key]])>
            <span class="checklist__mark" aria-hidden="true">
              @if ($checklist[$key]) <x-icon name="check" :size="14" /> @else {{ $loop->iteration }} @endif
            </span>
            <div class="checklist__body">
              <p class="checklist__title">
                {{ $title }}
                <span class="sr-only">— {{ $checklist[$key] ? __('done') : __('not done yet') }}</span>
              </p>
              <p class="checklist__text">{{ $text }}</p>
            </div>
            @unless ($checklist[$key])
              <a href="{{ $url }}" class="btn btn--secondary btn--sm">{{ $cta }}</a>
            @endunless
          </li>
        @endforeach
      </ol>
    </section>
  @endif

  <section class="section" aria-labelledby="summary-title">
    <h2 id="summary-title" class="sr-only">{{ __('Summary') }}</h2>

    <div class="panel stats">
      <div class="stat">
        <p class="stat__label"><x-icon name="message" :size="15" />{{ __('Usage this month') }}</p>
        @if ($quota['own_key'])
          <p class="stat__value">{{ __('Unlimited') }}</p>
          <p class="stat__meta">{{ __('You are on your own provider key, so asks are not counted against the plan.') }}</p>
        @else
          <p class="stat__value tabular">{{ $quota['remaining'] }} <small>/ {{ $quota['limit'] }} {{ __('asks left') }}</small></p>
          <div @class(['meter', 'meter--low' => $quotaLow]) role="meter" aria-valuemin="0" aria-valuemax="{{ $quota['limit'] }}" aria-valuenow="{{ $quota['used'] }}"
               aria-label="{{ __(':used of :limit asks used', ['used' => $quota['used'], 'limit' => $quota['limit']]) }}">
            <div class="meter__bar" style="width: {{ $usedPercent }}%"></div>
          </div>
          <p class="stat__meta tabular">
            {{ __(':used used · resets :date', ['used' => $quota['used'], 'date' => $quotaResetsAt->translatedFormat('j F')]) }}
          </p>
          @if ($quotaLow)
            <p class="stat__meta"><a href="{{ route('web.providers.index') }}" class="link">{{ __('Running low — plug in your own key to lift the limit') }}</a></p>
          @endif
        @endif
      </div>

      <div class="stat">
        <p class="stat__label"><x-icon name="cpu" :size="15" />{{ __('AI model') }}</p>
        <p class="stat__value truncate mono">{{ $setup['model'] ?? __('Not set') }}</p>
        <p class="stat__meta">
          {{ $setup['source'] === 'custom' ? __('Via :provider, your own key', ['provider' => $setup['provider']]) : __('SnapAsk default') }}
        </p>
        <p class="stat__meta">
          @if ($modelReady)
            <x-status-badge status="ok">{{ __('Ready') }}</x-status-badge>
          @else
            <x-status-badge status="error">{{ __('Not configured') }}</x-status-badge>
          @endif
        </p>
        <p class="stat__foot"><a href="{{ route('web.providers.index') }}" class="link">{{ __('Change model') }} <x-icon name="arrow-right" :size="14" /></a></p>
      </div>

      <div class="stat">
        <p class="stat__label"><x-icon name="plug" :size="15" />{{ __('Connected services') }}</p>
        <p class="stat__value tabular">{{ $services['total'] }}</p>
        @if ($services['total'] > 0)
          <p class="actions">
            <x-status-badge status="ok">{{ trans_choice(':count healthy|:count healthy', $services['ok'], ['count' => $services['ok']]) }}</x-status-badge>
            @if ($services['error'] > 0)
              <x-status-badge status="error">{{ trans_choice(':count failing|:count failing', $services['error'], ['count' => $services['error']]) }}</x-status-badge>
            @endif
            @if ($services['disabled'] > 0)
              <x-status-badge status="disabled">{{ trans_choice(':count disabled|:count disabled', $services['disabled'], ['count' => $services['disabled']]) }}</x-status-badge>
            @endif
          </p>
          <p class="stat__meta">
            @if ($services['last_synced_at'])
              {{ __('Last sync') }} <x-time :at="$services['last_synced_at']" />
            @else
              {{ __('Not synced yet') }}
            @endif
          </p>
        @else
          <p class="stat__meta">{{ __('Optional. The AI answers from the screenshot and the question alone.') }}</p>
        @endif
        <p class="stat__foot"><a href="{{ route('web.connectors.index') }}" class="link">{{ __('Manage services') }} <x-icon name="arrow-right" :size="14" /></a></p>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="recent-title">
    <div class="section__head">
      <h2 id="recent-title">{{ __('Recent conversations') }}</h2>
      @if ($recent->isNotEmpty())
        <a href="{{ route('web.conversations.index') }}" class="link">{{ __('View all') }} <x-icon name="arrow-right" :size="14" /></a>
      @endif
    </div>

    @if ($recent->isEmpty())
      <x-empty-state :title="__('No conversations yet')" icon="message" level="h3">
        {{ __('Ask something in SnapAsk Desktop — by typing, or by snapping a region of your screen. Every conversation shows up here.') }}
        <x-slot:actions>
          <a href="{{ $downloadUrl }}" class="btn btn--secondary"><x-icon name="download" :size="16" />{{ __('Get the desktop app') }}</a>
        </x-slot:actions>
      </x-empty-state>
    @else
      <ul class="list">
        @foreach ($recent as $conversation)
          <x-conversation-row :conversation="$conversation" />
        @endforeach
      </ul>
    @endif
  </section>
@endsection
