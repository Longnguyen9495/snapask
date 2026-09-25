@extends('layouts.app')

@section('title', __('Account · SnapAsk'))
@section('section', __('Account'))
@section('narrow', true)

@section('content')
  @php($locale = app()->getLocale())

  <x-page-header :title="__('Account')" :lead="__('Who you are signed in as, your plan and how much of it is left.')" />

  <section class="section" aria-labelledby="profile-title">
    <div class="section__head"><h2 id="profile-title">{{ __('Profile') }}</h2></div>
    <div class="panel panel--pad">
      <dl class="kv">
        <dt>{{ __('Name') }}</dt>
        <dd>{{ $user->name }}</dd>
        <dt>{{ __('Email') }}</dt>
        <dd class="actions">
          <span class="break">{{ $user->email }}</span>
          @if ($user->hasVerifiedEmail())
            <x-status-badge status="ok">{{ __('Verified') }}</x-status-badge>
          @else
            <x-status-badge status="error">{{ __('Not verified') }}</x-status-badge>
          @endif
        </dd>
        <dt>{{ __('Member since') }}</dt>
        <dd><x-time :at="$user->created_at" :relative="false" /></dd>
        <dt>{{ __('Desktop sign-ins') }}</dt>
        <dd class="tabular">{{ trans_choice(':count device|:count devices', $desktopSessions, ['count' => $desktopSessions]) }}</dd>
      </dl>
    </div>
  </section>

  <section class="section" id="plan" aria-labelledby="plan-title">
    <div class="section__head"><h2 id="plan-title">{{ __('Plan and quota') }}</h2></div>
    <div class="panel panel--pad">
      <dl class="kv">
        <dt>{{ __('Plan') }}</dt>
        <dd><x-status-badge status="active">{{ ucfirst($quota['plan']) }}</x-status-badge></dd>
        @if ($quota['own_key'])
          <dt>{{ __('Asks this month') }}</dt>
          <dd>{{ __('Unlimited — you are on your own provider key.') }}</dd>
        @else
          <dt>{{ __('Asks this month') }}</dt>
          <dd class="tabular">{{ __(':remaining of :limit left (:used used)', ['remaining' => $quota['remaining'], 'limit' => $quota['limit'], 'used' => $quota['used']]) }}</dd>
          <dt>{{ __('Resets') }}</dt>
          <dd>{{ $quotaResetsAt->translatedFormat('j F Y') }}</dd>
        @endif
        <dt>{{ __('AI model') }}</dt>
        <dd>
          <span class="tag">{{ $setup['model'] ?? __('Not set') }}</span>
          <span class="muted">{{ $setup['source'] === 'custom' ? __('via :provider', ['provider' => $setup['provider']]) : __('SnapAsk default') }}</span>
        </dd>
      </dl>
      <p class="field__hint">
        <a href="{{ route('web.providers.index') }}" class="link">{{ __('Plug in your own key to lift the limit') }} <x-icon name="arrow-right" :size="14" /></a>
      </p>
    </div>
  </section>

  <section class="section" aria-labelledby="language-title">
    <div class="section__head"><h2 id="language-title">{{ __('Language') }}</h2></div>
    <div class="panel panel--pad">
      <p class="muted">{{ __('Used on this website, in emails and in the desktop app when you have not picked one there.') }}</p>
      <div class="actions settings__choices" role="group" aria-labelledby="language-title">
        @foreach (config('snapask.locales') as $code => $meta)
          <form method="POST" action="{{ route('locale.switch', $code) }}">
            @csrf
            <button type="submit" lang="{{ $meta['html'] }}" @class(['chip']) aria-pressed="{{ $locale === $code ? 'true' : 'false' }}">
              @if ($locale === $code) <x-icon name="check" :size="14" /> @endif
              {{ $meta['name'] }}
            </button>
          </form>
        @endforeach
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="session-title">
    <div class="section__head"><h2 id="session-title">{{ __('Session') }}</h2></div>
    <div class="panel panel--pad actions">
      <p class="muted">{{ __('Signing out here does not sign out the desktop app; use Sign out inside the app for that.') }}</p>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn--secondary"><x-icon name="log-out" :size="16" />{{ __('Sign out') }}</button>
      </form>
    </div>
  </section>
@endsection
