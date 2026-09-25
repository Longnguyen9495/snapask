@extends('layouts.app')

@section('title', __('Settings · SnapAsk'))
@section('section', __('Settings'))
@section('narrow', true)

@section('content')
  @php
    $locale = app()->getLocale();
    $downloadUrl = $locale === 'vi' ? route('download') : route('download.en');
  @endphp

  <x-page-header :title="__('Settings')" :lead="__('Preferences that follow your account to every device.')" />

  <section class="section" aria-labelledby="language-title">
    <div class="section__head"><h2 id="language-title">{{ __('Language') }}</h2></div>
    <div class="panel panel--pad">
      <p class="muted">{{ __('Used on this website, in emails and in the desktop app when you have not picked one there.') }}</p>
      <div class="actions settings__choices" role="group" aria-labelledby="language-title">
        @foreach ($locales as $code => $meta)
          <form method="POST" action="{{ route('locale.switch', $code) }}">
            @csrf
            <button type="submit" lang="{{ $meta['html'] }}" class="chip" aria-pressed="{{ $locale === $code ? 'true' : 'false' }}">
              @if ($locale === $code) <x-icon name="check" :size="14" /> @endif
              {{ $meta['name'] }}
            </button>
          </form>
        @endforeach
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="privacy-title">
    <div class="section__head"><h2 id="privacy-title">{{ __('Data and privacy') }}</h2></div>
    <div class="panel panel--pad">
      <dl class="kv">
        <dt>{{ __('Screenshots') }}</dt>
        <dd>{{ __('Deleted automatically :days days after the question. You can delete one sooner by deleting its conversation.', ['days' => $retentionDays]) }}</dd>
        <dt>{{ __('Conversation text') }}</dt>
        <dd>{{ __('Kept until you delete the conversation, so you can search and continue it later.') }}</dd>
        <dt>{{ __('Deleting') }}</dt>
        <dd>{{ __('Deleting a conversation removes its messages and screenshot right away. There is no trash to restore from.') }}</dd>
        <dt>{{ __('Keys and tokens') }}</dt>
        <dd>{{ __('Provider keys and service tokens are stored encrypted and are never shown again, on the web or in the app.') }}</dd>
        <dt>{{ __('AI requests') }}</dt>
        <dd>{{ __('Questions are sent to the AI from our server, so no key ever reaches your machine.') }}</dd>
      </dl>
    </div>
  </section>

  <section class="section" aria-labelledby="updates-title">
    <div class="section__head"><h2 id="updates-title">{{ __('Desktop app and updates') }}</h2></div>
    <div class="panel panel--pad">
      <dl class="kv">
        <dt>{{ __('Latest release') }}</dt>
        <dd class="tabular">{{ __('Version :version', ['version' => $release['version']]) }}</dd>
        <dt>{{ __('Update channel') }}</dt>
        <dd>{{ __('Stable. The app checks for updates on launch and installs them when you restart it.') }}</dd>
        <dt>{{ __('App settings') }}</dt>
        <dd>{{ __('Hotkey, capture and window behaviour live in the desktop app’s own Settings, because they belong to each machine.') }}</dd>
      </dl>
      <p class="field__hint"><a href="{{ $downloadUrl }}" class="link">{{ __('Download page') }} <x-icon name="arrow-right" :size="14" /></a></p>
    </div>
  </section>
@endsection
