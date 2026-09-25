@props(['user', 'quota'])

{{--
  Điều hướng chính của trang quản lý.

  Mục đang mở mang aria-current="page" — trình đọc màn hình đọc được, và CSS
  dựa vào chính thuộc tính đó để tô, nên hai thứ không bao giờ lệch nhau.
--}}
@php
  $items = [
      ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'dashboard', 'label' => __('Overview')],
      ['route' => 'web.conversations.index', 'match' => 'web.conversations.*', 'icon' => 'message', 'label' => __('Conversations')],
      ['route' => 'web.providers.index', 'match' => 'web.providers.*', 'icon' => 'cpu', 'label' => __('AI models')],
      ['route' => 'web.connectors.index', 'match' => 'web.connectors.*', 'icon' => 'plug', 'label' => __('Connected services')],
  ];
  $personal = [
      ['route' => 'web.account', 'match' => 'web.account', 'icon' => 'user', 'label' => __('Account')],
      ['route' => 'web.settings', 'match' => 'web.settings', 'icon' => 'sliders', 'label' => __('Settings')],
  ];
@endphp

<aside class="sidebar" id="portal-nav" aria-label="{{ __('Management navigation') }}">
  <div class="sidebar__top">
    <a href="{{ route('dashboard') }}" class="brand" aria-label="{{ __('SnapAsk overview') }}">
      <x-brand-mark />
      <span class="brand__name">SnapAsk</span>
    </a>
    <button type="button" class="btn btn--ghost btn--icon nav-toggle" data-nav-close aria-label="{{ __('Close navigation') }}">
      <x-icon name="x" />
    </button>
  </div>

  <nav aria-label="{{ __('Primary navigation') }}">
    <ul class="nav">
      @foreach ($items as $item)
        <li>
          <a href="{{ route($item['route']) }}" class="nav__link" @if (request()->routeIs($item['match'])) aria-current="page" @endif>
            <x-icon :name="$item['icon']" />
            <span>{{ $item['label'] }}</span>
          </a>
        </li>
      @endforeach
    </ul>

    <p class="nav__label" id="nav-personal">{{ __('Personal') }}</p>
    <ul class="nav" aria-labelledby="nav-personal">
      @foreach ($personal as $item)
        <li>
          <a href="{{ route($item['route']) }}" class="nav__link" @if (request()->routeIs($item['match'])) aria-current="page" @endif>
            <x-icon :name="$item['icon']" />
            <span>{{ $item['label'] }}</span>
          </a>
        </li>
      @endforeach
    </ul>
  </nav>

  <div class="sidebar__foot">
    <a href="{{ app()->getLocale() === 'vi' ? route('download') : route('download.en') }}" class="get-app">
      <x-icon name="download" />
      <span>
        {{ __('Get the desktop app') }}
        <small>{{ __('Windows and macOS') }}</small>
      </span>
    </a>

    <div class="who">
      <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span>
      <span class="who__text">
        <span class="who__name truncate">{{ $user->name }}</span>
        <span class="who__meta truncate">{{ $user->email }}</span>
        <span class="who__meta">{{ __(':plan plan', ['plan' => ucfirst($quota['plan'])]) }}</span>
      </span>
    </div>
  </div>
</aside>
