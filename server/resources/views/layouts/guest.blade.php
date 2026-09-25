<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'SnapAsk')</title>
<meta name="description" content="@yield('description', __('Snap a region of your screen and ask AI right there.'))">
<meta name="theme-color" content="#121110">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body class="guest">
  <a href="#main" class="skip">{{ __('Skip to main content') }}</a>
  @php($locale = app()->getLocale())

  <div class="guest__wrap">
    <header class="guest__top">
      <a href="{{ $locale === 'vi' ? route('home') : route('home.en') }}" class="brand" aria-label="{{ __('SnapAsk home') }}">
        <x-brand-mark />
        <span class="brand__name">SnapAsk</span>
      </a>

      {{--
        Nút chuyển ngôn ngữ: form POST chứ không phải link, vì bấm vào đây là ghi
        cookie và ghi vào tài khoản — một hành động, không phải một trang để xem.
      --}}
      <form method="POST" action="{{ route('locale.switch', $locale === 'vi' ? 'en' : 'vi') }}">
        @csrf
        <button type="submit" class="lang-switch" title="{{ __('Switch language') }}"
                aria-label="{{ __('Switch language') }}">{{ $locale === 'vi' ? 'EN' : 'VI' }}</button>
      </form>
    </header>

    <main id="main">
      @yield('content')
    </main>
  </div>

  @include('partials.portal-strings')
  <script src="{{ asset('js/portal.js') }}" defer></script>
</body>
</html>
