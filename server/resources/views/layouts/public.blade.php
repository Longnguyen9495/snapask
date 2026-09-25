<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', __('SnapAsk — snap a region, ask AI on the spot'))</title>
<meta name="description" content="@yield('description', __('Snap a region of your screen and ask AI right there.'))">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">

{{--
  Mỗi trang công khai có hai bản. Khai rõ để Google gom chúng thành một trang
  hai thứ tiếng thay vì hai trang trùng nội dung.
--}}
@foreach (['vi', 'en'] as $alt)
  <link rel="alternate" hreflang="{{ $alt }}" href="{{ $alternates[$alt] }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $alternates['vi'] }}">

<meta property="og:type" content="website">
<meta property="og:title" content="@yield('title', __('SnapAsk — snap a region, ask AI on the spot'))">
<meta property="og:description" content="@yield('description', __('Snap a region of your screen and ask AI right there.'))">
<meta property="og:locale" content="{{ app()->getLocale() === 'vi' ? 'vi_VN' : 'en_US' }}">
<meta property="og:url" content="{{ url()->current() }}">

<style>
  body { padding: 0; }

  .shell { max-width: 1060px; margin: 0 auto; padding: 0 24px; }

  /* Quầng sáng lệch tâm, để trang không phẳng lì như một bản mẫu chưa xong. */
  body::before {
    content: '';
    position: fixed; inset: -35% 35% 55% -25%;
    background: radial-gradient(circle at 35% 35%, rgba(227, 160, 75, .11), transparent 60%);
    pointer-events: none; z-index: 0;
  }

  /* ---- Thanh đầu trang ---- */
  .top {
    position: sticky; top: 0; z-index: 5;
    background: color-mix(in srgb, var(--ink-0) 82%, transparent);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid transparent;
    transition: border-color .2s var(--ease);
  }
  .top--stuck { border-bottom-color: var(--line-soft); }
  .top__inner { display: flex; align-items: center; gap: 22px; height: 64px; }

  .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); }
  .brand svg { width: 28px; height: 28px; flex: none; }
  .brand__name { font-size: 16px; font-weight: 600; letter-spacing: -.01em; }

  .top__nav { display: none; gap: 4px; margin-left: 8px; }
  .top__link {
    padding: 7px 12px; border-radius: var(--r-sm);
    font-size: 14px; font-weight: 500; color: var(--text-dim); text-decoration: none;
    transition: color .18s var(--ease), background .18s var(--ease);
  }
  .top__link:hover { color: var(--text); background: var(--ink-1); }

  .top__end { display: flex; align-items: center; gap: 8px; margin-left: auto; }

  @media (min-width: 720px) { .top__nav { display: flex; } }

  /* ---- Nút ---- */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    padding: 13px 22px; border: 0; border-radius: var(--r-sm);
    font: 600 15px/1.4 inherit; text-decoration: none; white-space: nowrap;
    color: var(--accent-ink); background: var(--accent); cursor: pointer;
    transition: background .18s var(--ease), transform .12s var(--ease);
  }
  .btn:hover { background: var(--accent-press); }
  .btn:active { transform: translateY(1px); }

  .btn--quiet {
    padding: 8px 13px; font-size: 13.5px; font-weight: 500;
    color: var(--text-dim); background: transparent; border: 1px solid var(--line-soft);
  }
  .btn--quiet:hover { color: var(--text); background: var(--ink-2); border-color: var(--line); }

  .lang { display: inline-flex; }

  /* ---- Chân trang ---- */
  .foot {
    position: relative; z-index: 1;
    margin-top: 110px; padding: 34px 0 44px;
    border-top: 1px solid var(--line-soft);
  }
  .foot__inner { display: flex; flex-wrap: wrap; align-items: center; gap: 14px 24px; }
  .foot__note { font-size: 13.5px; color: var(--text-faint); }
  .foot__links { display: flex; flex-wrap: wrap; gap: 18px; margin-left: auto; }
  .foot__links a { font-size: 13.5px; color: var(--text-dim); text-decoration: none; }
  .foot__links a:hover { color: var(--text); }

  main { position: relative; z-index: 1; }

  @media (max-width: 480px) {
    .shell { padding: 0 16px; }
  }
</style>
@stack('styles')
</head>
<body>
  <a href="#main" class="skip">{{ __('Skip to main content') }}</a>

  @php($locale = app()->getLocale())

  <header class="top" id="top">
    <div class="shell top__inner">
      <a href="{{ $homeUrl }}" class="brand">
        <svg viewBox="0 0 32 32" aria-hidden="true">
          <rect width="32" height="32" rx="8" fill="#1a1816" stroke="#322d27"/>
          <g fill="#e3a04b">
            <path d="M9 9h5v2.4H11.4V14H9zM23 9h-5v2.4h2.6V14H23zM9 23h5v-2.4H11.4V18H9zM23 23h-5v-2.4h2.6V18H23z"/>
          </g>
          <rect x="14.6" y="14.6" width="2.8" height="2.8" rx=".6" fill="#f3efe8"/>
        </svg>
        <span class="brand__name">SnapAsk</span>
      </a>

      <nav class="top__nav">
        <a href="{{ $homeUrl }}#features" class="top__link">{{ __('Features') }}</a>
        <a href="{{ $downloadUrl }}" class="top__link">{{ __('Download') }}</a>
        <a href="{{ $homeUrl }}#faq" class="top__link">{{ __('FAQ') }}</a>
      </nav>

      <div class="top__end">
        <form method="POST" action="{{ route('locale.switch', $locale === 'vi' ? 'en' : 'vi') }}" class="lang">
          @csrf
          <button type="submit" class="btn btn--quiet" title="{{ __('Switch language') }}"
                  aria-label="{{ __('Switch language') }}">{{ $locale === 'vi' ? 'EN' : 'VI' }}</button>
        </form>

        {{-- Người đã đăng nhập không cần lời mời đăng nhập nữa, họ cần lối vào. --}}
        @auth
          <a href="{{ route('web.connectors.index') }}" class="btn btn--quiet">{{ __('Dashboard') }}</a>
        @else
          <a href="{{ route('login') }}" class="btn btn--quiet">{{ __('Sign in') }}</a>
        @endauth
      </div>
    </div>
  </header>

  <main id="main">
    @yield('content')
  </main>

  <footer class="foot">
    <div class="shell foot__inner">
      <p class="foot__note">© {{ date('Y') }} SnapAsk</p>
      <nav class="foot__links">
        <a href="{{ $downloadUrl }}">{{ __('Download') }}</a>
        @auth
          <a href="{{ route('web.connectors.index') }}">{{ __('Dashboard') }}</a>
        @else
          <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
        @endauth
      </nav>
    </div>
  </footer>

  <script>
    // Viền dưới thanh đầu trang chỉ hiện khi đã cuộn — lúc ở đỉnh trang thì
    // một đường kẻ ngang chẳng để làm gì.
    const top = document.getElementById('top');
    const onScroll = () => top.classList.toggle('top--stuck', window.scrollY > 8);
    addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  </script>
  @stack('scripts')
</body>
</html>
