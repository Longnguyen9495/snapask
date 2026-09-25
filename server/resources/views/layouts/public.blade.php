<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', __('SnapAsk - capture anywhere, ask right there'))</title>
<meta name="description" content="@yield('description', __('Capture any part of your screen, ask a question, and get an AI answer right where you work.'))">
<meta name="theme-color" content="#100f0e">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">

@foreach (['vi', 'en'] as $alt)
  <link rel="alternate" hreflang="{{ $alt }}" href="{{ $alternates[$alt] }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $alternates['vi'] }}">

<meta property="og:type" content="website">
<meta property="og:title" content="@yield('title', __('SnapAsk - capture anywhere, ask right there'))">
<meta property="og:description" content="@yield('description', __('Capture any part of your screen, ask a question, and get an AI answer right where you work.'))">
<meta property="og:locale" content="{{ app()->getLocale() === 'vi' ? 'vi_VN' : 'en_US' }}">
<meta property="og:url" content="{{ url()->current() }}">

<style>
  body { padding: 0; overflow-x: hidden; }
  body::before { content: ''; position: fixed; inset: -32% 38% 58% -28%; z-index: 0; pointer-events: none; background: radial-gradient(circle at 42% 44%, rgba(227, 160, 75, .1), transparent 62%); }
  .shell { width: min(100%, 1280px); margin-inline: auto; padding-inline: 28px; }
  main { position: relative; z-index: 1; }

  .top { position: sticky; top: 0; z-index: 20; min-height: 68px; border-bottom: 1px solid var(--line-soft); background: rgba(16, 15, 14, .92); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
  .top__inner { display: flex; align-items: center; gap: 24px; min-height: 68px; }
  .brand { display: inline-flex; align-items: center; gap: 10px; flex: none; color: var(--text); text-decoration: none; }
  .brand svg { width: 29px; height: 29px; flex: none; }
  .brand__name { font-size: 16px; font-weight: 650; letter-spacing: -.018em; }
  .top__nav { display: flex; align-items: center; gap: 2px; }
  .top__link { display: inline-flex; align-items: center; min-height: 44px; padding: 8px 12px; border-radius: 10px; color: var(--text-dim); text-decoration: none; font-size: 13.5px; font-weight: 500; transition: color .18s var(--ease), background .18s var(--ease); }
  .top__link:hover { color: var(--text); background: var(--ink-1); }
  .top__end { display: flex; align-items: center; gap: 8px; margin-left: auto; }
  .lang { display: inline-flex; }

  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 12px 20px; border: 1px solid transparent; border-radius: 12px; color: var(--accent-ink); background: var(--accent); cursor: pointer; text-decoration: none; white-space: nowrap; font: 650 14px/1.35 'Be Vietnam Pro', system-ui, sans-serif; transition: background .18s var(--ease), border-color .18s var(--ease), color .18s var(--ease), transform .12s var(--ease), opacity .18s var(--ease); }
  .btn:hover { color: var(--accent-ink); background: var(--accent-press); }
  .btn:active { transform: scale(.98); }
  :where(a, button, summary):focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
  .btn:disabled, .btn[aria-disabled="true"] { cursor: not-allowed; opacity: .48; }
  .btn.is-loading { cursor: wait; opacity: .7; }
  .btn--secondary, .btn--quiet { color: var(--text); background: transparent; border-color: var(--line); }
  .btn--secondary:hover, .btn--quiet:hover { color: var(--text); background: var(--ink-2); border-color: #494138; }
  .btn--quiet { min-height: 42px; padding: 9px 12px; font-size: 13px; font-weight: 550; }
  .top__download { min-height: 42px; padding: 9px 15px; font-size: 13px; }
  .menu-toggle { display: none; width: 44px; height: 44px; padding: 0; border: 1px solid var(--line-soft); border-radius: 12px; color: var(--text); background: var(--ink-1); cursor: pointer; }
  .menu-toggle span, .menu-toggle::before, .menu-toggle::after { content: ''; display: block; width: 18px; height: 1.5px; margin: 4px auto; background: currentColor; transition: transform .2s var(--ease), opacity .2s var(--ease); }
  .menu-toggle[aria-expanded="true"] span { opacity: 0; }
  .menu-toggle[aria-expanded="true"]::before { transform: translateY(5.5px) rotate(45deg); }
  .menu-toggle[aria-expanded="true"]::after { transform: translateY(-5.5px) rotate(-45deg); }

  .foot { position: relative; z-index: 1; margin-top: 52px; padding: 32px 0 40px; border-top: 1px solid var(--line-soft); }
  .foot__inner, .foot__brand, .foot__links { display: flex; align-items: center; }
  .foot__inner { flex-wrap: wrap; gap: 18px 30px; }
  .foot__brand { gap: 14px; }
  .foot__name { color: var(--text); font-weight: 600; }
  .foot__note { color: var(--text-faint); font-size: 13px; }
  .foot__links { flex-wrap: wrap; gap: 18px; margin-left: auto; }
  .foot__links a { color: var(--text-dim); text-decoration: none; font-size: 13px; }
  .foot__links a:hover { color: var(--text); }

  @media (max-width: 900px) {
    .top__nav { position: absolute; top: calc(100% + 1px); left: 20px; right: 20px; display: none; padding: 10px; border: 1px solid var(--line); border-radius: 16px; background: #1b1815; box-shadow: 0 24px 60px rgba(40, 28, 15, .42); }
    .top__nav.is-open { display: grid; }
    .top__link { padding-inline: 14px; }
    .menu-toggle { display: block; }
    .top__account { display: none; }
  }
  @media (max-width: 600px) {
    .shell { padding-inline: 20px; }
    .top__inner { gap: 10px; }
    .top__download { display: none; }
    .foot__inner, .foot__brand { align-items: flex-start; }
    .foot__inner { flex-direction: column; }
    .foot__brand { flex-direction: column; gap: 4px; }
    .foot__links { margin-left: 0; }
  }
  @media (min-width: 901px) { .top__link--menu { display: none; } }
  @supports not (backdrop-filter: blur(1px)) { .top { background: #141210; } }
</style>
@stack('styles')
</head>
<body>
  <a href="#main" class="skip">{{ __('Skip to main content') }}</a>
  @php($locale = app()->getLocale())

  <header class="top" id="top">
    <div class="shell top__inner">
      <a href="{{ $homeUrl }}" class="brand" aria-label="{{ __('SnapAsk home') }}">
        <svg viewBox="0 0 32 32" aria-hidden="true">
          <rect width="32" height="32" rx="8" fill="#1a1816" stroke="#322d27"/>
          <g fill="#e3a04b"><path d="M9 9h5v2.4H11.4V14H9zM23 9h-5v2.4h2.6V14H23zM9 23h5v-2.4H11.4V18H9zM23 23h-5v-2.4h2.6V18H23z"/></g>
          <rect x="14.6" y="14.6" width="2.8" height="2.8" rx=".6" fill="#f3efe8"/>
        </svg>
        <span class="brand__name">SnapAsk</span>
      </a>

      <nav class="top__nav" id="primary-navigation" aria-label="{{ __('Primary navigation') }}">
        <a href="{{ $homeUrl }}#features" class="top__link">{{ __('Features') }}</a>
        <a href="{{ $homeUrl }}#privacy" class="top__link">{{ __('Privacy') }}</a>
        <a href="{{ $homeUrl }}#faq" class="top__link">{{ __('FAQ') }}</a>
        @auth
          <a href="{{ route('dashboard') }}" class="top__link top__link--menu">{{ __('Dashboard') }}</a>
        @else
          <a href="{{ route('login') }}" class="top__link top__link--menu">{{ __('Sign in') }}</a>
        @endauth
      </nav>

      <div class="top__end">
        <form method="POST" action="{{ route('locale.switch', $locale === 'vi' ? 'en' : 'vi') }}" class="lang">
          @csrf
          <button type="submit" class="btn btn--quiet" title="{{ __('Switch language') }}" aria-label="{{ __('Switch language') }}">{{ $locale === 'vi' ? 'EN' : 'VI' }}</button>
        </form>
        @auth
          <a href="{{ route('dashboard') }}" class="btn btn--quiet top__account">{{ __('Open management') }}</a>
        @else
          <a href="{{ route('login') }}" class="btn btn--quiet top__account">{{ __('Sign in') }}</a>
        @endauth
        <a href="{{ $downloadUrl }}" class="btn top__download">{{ __('Download') }}</a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" aria-label="{{ __('Open navigation') }}"><span></span></button>
      </div>
    </div>
  </header>

  <main id="main">@yield('content')</main>

  <footer class="foot">
    <div class="shell foot__inner">
      <div class="foot__brand"><span class="foot__name">SnapAsk</span><p class="foot__note">© {{ date('Y') }} SnapAsk</p></div>
      <nav class="foot__links" aria-label="{{ __('Footer navigation') }}">
        <a href="{{ $downloadUrl }}">{{ __('Download') }}</a>
        <a href="{{ $homeUrl }}#privacy">{{ __('Privacy') }}</a>
        <a href="{{ $homeUrl }}#faq">{{ __('FAQ') }}</a>
        @auth
          <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
        @else
          <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
        @endauth
      </nav>
    </div>
  </footer>

  <script>
    (() => {
      const toggle = document.querySelector('.menu-toggle');
      const navigation = document.getElementById('primary-navigation');
      if (!toggle || !navigation) return;

      const closeMenu = (restoreFocus = false) => {
        toggle.setAttribute('aria-expanded', 'false');
        navigation.classList.remove('is-open');
        if (restoreFocus) toggle.focus();
      };

      toggle.addEventListener('click', () => {
        const opening = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(opening));
        navigation.classList.toggle('is-open', opening);
        if (opening) navigation.querySelector('a')?.focus();
      });
      navigation.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeMenu();
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') closeMenu(true);
      });
    })();
  </script>
  @stack('scripts')
</body>
</html>
