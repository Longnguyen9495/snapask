<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'SnapAsk')</title>
<meta name="description" content="@yield('description', __('Snap a region of your screen and ask AI right there.'))">
<meta name="theme-color" content="#121110">
{{-- Trang sau lớp đăng nhập: không cần có mặt trên công cụ tìm kiếm. --}}
<meta name="robots" content="noindex">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
{{--
  CSS nằm ở file tĩnh thay vì qua Vite: máy chủ này chỉ có vài trang quản trị,
  không đáng để thêm một bước build mà lúc triển khai lại dễ quên chạy.
--}}
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body class="portal">
  <a href="#main" class="skip">{{ __('Skip to main content') }}</a>
  @php($locale = app()->getLocale())
  @php($quotaLow = ! $shellQuota['own_key'] && $shellQuota['remaining'] <= max(1, (int) floor($shellQuota['limit'] * 0.1)))

  <div class="shell">
    <x-sidebar-nav :user="$shellUser" :quota="$shellQuota" />
    <div class="scrim" aria-hidden="true"></div>

    <div class="main">
      <header class="topbar">
        <button type="button" class="btn btn--ghost btn--icon nav-toggle" data-nav-toggle
                aria-expanded="false" aria-controls="portal-nav" aria-label="{{ __('Open navigation') }}">
          <x-icon name="menu" />
        </button>

        <span class="topbar__title">@yield('section', __('Management'))</span>

        <div class="topbar__end">
          <a href="{{ route('web.account') }}#plan" @class(['quota-chip', 'quota-chip--low' => $quotaLow])
             aria-label="{{ $shellQuota['own_key']
                ? __('Own key, no ask limit')
                : __(':remaining of :limit asks left this month', ['remaining' => $shellQuota['remaining'], 'limit' => $shellQuota['limit']]) }}">
            @if ($shellQuota['own_key'])
              <x-icon name="key" :size="15" />
              <span>{{ __('Own key') }}</span>
            @else
              <x-icon name="message" :size="15" />
              <b class="tabular">{{ $shellQuota['remaining'] }}/{{ $shellQuota['limit'] }}</b>
              <span>{{ __('asks left') }}</span>
            @endif
          </a>

          <form method="POST" action="{{ route('locale.switch', $locale === 'vi' ? 'en' : 'vi') }}">
            @csrf
            <button type="submit" class="lang-switch" title="{{ __('Switch language') }}"
                    aria-label="{{ __('Switch language') }}">{{ $locale === 'vi' ? 'EN' : 'VI' }}</button>
          </form>

          <x-account-menu :user="$shellUser" />
        </div>
      </header>

      <main id="main" class="content" tabindex="-1">
        <div @class(['page', 'page--narrow' => View::hasSection('narrow')])>
          <x-flash />
          @yield('content')
        </div>
      </main>
    </div>
  </div>

  <x-confirm-dialog />
  <p class="sr-only" id="portal-live" role="status" aria-live="polite"></p>

  @include('partials.portal-strings')
  <script src="{{ asset('js/portal.js') }}" defer></script>
</body>
</html>
