<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'SnapAsk')</title>
<meta name="description" content="@yield('description', __('Snap a region of your screen and ask AI right there.'))">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
{{--
    CSS viết thẳng vào trang thay vì qua Vite: máy chủ này chỉ có vài trang quản
    trị, không đáng để thêm một bước build mà lúc triển khai lại dễ quên chạy.
--}}
<style>
  /*
   * Cùng hệ thiết kế với ứng dụng desktop: nền đen ngả ấm, một màu nhấn hổ
   * phách duy nhất.
   */
  :root {
    --ink-0: #100f0e;
    --ink-1: #1a1816;
    --ink-2: #232019;
    --line: #322d27;
    --line-soft: #26221d;

    --text: #f3efe8;
    --text-dim: #a49c90;
    --text-faint: #6f675d;

    --accent: #e3a04b;
    --accent-press: #cc8c3a;
    --accent-ink: #1a1206;

    --danger: #e0705c;
    --ok: #86b36a;

    --r-sm: 8px;
    --r-md: 12px;
    --ease: cubic-bezier(.32, .72, 0, 1);
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  html { scroll-behavior: smooth; }

  body {
    position: relative;
    min-height: 100dvh;
    padding: 56px 24px 96px;
    font: 400 15px/1.65 'Be Vietnam Pro', system-ui, sans-serif;
    color: var(--text);
    background: var(--ink-0);
    -webkit-font-smoothing: antialiased;
  }

  /* Quầng sáng lệch tâm, để trang không phẳng lì như một bản mẫu chưa xong. */
  body::before {
    content: '';
    position: fixed; inset: -30% 40% 50% -20%;
    background: radial-gradient(circle at 35% 35%, rgba(227, 160, 75, .1), transparent 60%);
    pointer-events: none;
  }

  .wrap { position: relative; max-width: 760px; margin: 0 auto; }
  .wrap--narrow { max-width: 400px; }

  /* Lối tắt cho người dùng bàn phím, chỉ hiện khi được focus. */
  .skip {
    position: absolute; left: 0; top: -60px;
    padding: 10px 16px; border-radius: var(--r-sm);
    color: var(--accent-ink); background: var(--accent); font-weight: 600;
    transition: top .2s var(--ease);
  }
  .skip:focus { top: 0; }

  :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

  .brand { display: flex; align-items: center; gap: 11px; margin-bottom: 30px; }
  .brand svg { width: 30px; height: 30px; flex: none; }
  .brand__name { font-size: 17px; font-weight: 600; letter-spacing: -.01em; }

  h1 { font-size: 26px; font-weight: 600; letter-spacing: -.025em; line-height: 1.25; }
  h2 { font-size: 16px; font-weight: 600; letter-spacing: -.01em; }
  .lead { margin: 8px 0 28px; color: var(--text-dim); max-width: 58ch; text-wrap: pretty; }

  .card {
    padding: 22px; margin-bottom: 12px;
    border: 1px solid var(--line-soft); border-radius: var(--r-md); background: var(--ink-1);
    transition: border-color .2s var(--ease);
  }
  .card:hover { border-color: var(--line); }

  form.stack { display: flex; flex-direction: column; gap: 16px; }

  label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 500; color: var(--text-dim); }
  label.inline { flex-direction: row; align-items: center; gap: 9px; font-weight: 400; }

  input[type=text], input[type=email], input[type=password], input[type=url] {
    padding: 11px 13px; font: 400 15px/1.4 inherit; color: var(--text);
    background: var(--ink-0); border: 1px solid var(--line-soft); border-radius: var(--r-sm);
    transition: border-color .18s var(--ease), background .18s var(--ease);
  }
  input::placeholder { color: var(--text-faint); }
  input:hover { border-color: var(--line); }
  input:focus { outline: 0; border-color: var(--accent); background: var(--ink-2); }

  button, .btn {
    display: inline-block; padding: 12px 18px; border: 0; border-radius: var(--r-sm);
    font: 600 15px/1.4 inherit; text-decoration: none;
    color: var(--accent-ink); background: var(--accent); cursor: pointer;
    transition: background .18s var(--ease), transform .12s var(--ease);
  }
  button:hover, .btn:hover { background: var(--accent-press); }
  button:active, .btn:active { transform: translateY(1px); }

  .btn--quiet {
    padding: 8px 13px; font-size: 13px; font-weight: 500;
    color: var(--text-dim); background: transparent; border: 1px solid var(--line-soft);
  }
  .btn--quiet:hover { color: var(--text); background: var(--ink-2); border-color: var(--line); }
  .btn--danger:hover { color: var(--danger); background: transparent; border-color: var(--danger); }

  a { color: var(--accent); text-decoration-thickness: 1px; text-underline-offset: 3px; }

  .note { font-size: 13.5px; line-height: 1.7; color: var(--text-dim); text-wrap: pretty; }
  .note code {
    padding: 2px 6px; border-radius: 5px;
    font: 12.5px/1.5 ui-monospace, monospace; background: var(--ink-2);
  }

  .flash, .errors {
    padding: 12px 15px; margin-bottom: 20px; border-radius: var(--r-sm); font-size: 14px;
    animation: rise .3s var(--ease) both;
  }
  .flash {
    color: var(--ok); border: 1px solid color-mix(in srgb, var(--ok) 35%, transparent);
    background: color-mix(in srgb, var(--ok) 10%, transparent);
  }
  .errors {
    color: var(--danger); border: 1px solid color-mix(in srgb, var(--danger) 35%, transparent);
    background: color-mix(in srgb, var(--danger) 10%, transparent);
  }
  .errors ul { margin-left: 18px; }

  @keyframes rise { from { opacity: 0; transform: translateY(6px); } }

  .row { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 24px; }
  .actions { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
  .actions form { display: inline; }

  .tag {
    padding: 3px 8px; border-radius: 6px;
    font: 500 12px/1.5 ui-monospace, monospace; color: var(--text-dim); background: var(--ink-2);
  }
  .status--ok { color: var(--ok); }
  .status--bad { color: var(--danger); }
  .tools { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px; }

  .center { text-align: center; }
  .tabular { font-variant-numeric: tabular-nums; }

  .nav { display: flex; gap: 2px; margin-left: auto; }

  /* Chưa đăng nhập thì không có nav, nút ngôn ngữ tự lùi về mép phải. */
  .lang { margin-left: auto; }
  .nav + .lang { margin-left: 8px; }
  .nav__link {
    padding: 7px 13px; border-radius: var(--r-sm);
    font-size: 13.5px; font-weight: 500; color: var(--text-faint); text-decoration: none;
    transition: color .18s var(--ease), background .18s var(--ease);
  }
  .nav__link:hover { color: var(--text); background: var(--ink-1); }
  .nav__link--on { color: var(--text); background: var(--ink-2); }

  select, textarea {
    padding: 11px 13px; font: 400 15px/1.4 inherit; color: var(--text);
    background: var(--ink-0); border: 1px solid var(--line-soft); border-radius: var(--r-sm);
    transition: border-color .18s var(--ease);
  }
  textarea { resize: vertical; font-family: ui-monospace, monospace; font-size: 14px; }
  select:hover, textarea:hover { border-color: var(--line); }
  select:focus, textarea:focus { outline: 0; border-color: var(--accent); }

  .hint { font-size: 12.5px; font-weight: 400; color: var(--text-faint); }

  /* Thẻ của mục đang được dùng: viền sáng lên thay vì đổi hẳn nền. */
  .card--on { border-color: color-mix(in srgb, var(--accent) 45%, var(--line)); }

  .pill {
    padding: 3px 10px; border-radius: 999px; white-space: nowrap;
    font-size: 12px; font-weight: 600; color: var(--accent-ink); background: var(--accent);
  }

  .models { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
  .models form { display: inline; }
  .chip {
    padding: 6px 12px; border: 1px solid var(--line-soft); border-radius: 999px;
    font: 500 12.5px/1.4 ui-monospace, monospace;
    color: var(--text-dim); background: var(--ink-0);
  }
  .chip:hover { color: var(--text); background: var(--ink-2); border-color: var(--line); }
  .chip--on { color: var(--accent-ink); background: var(--accent); border-color: var(--accent); }
  .chip--on:hover { color: var(--accent-ink); background: var(--accent-press); }
</style>
</head>
<body>
  <a href="#main" class="skip">{{ __('Skip to main content') }}</a>

  @php($locale = app()->getLocale())

  <div class="wrap @yield('wrap-modifier')">
    <header class="brand">
      <svg viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="8" fill="#1a1816" stroke="#322d27"/>
        <g fill="#e3a04b">
          <path d="M9 9h5v2.4H11.4V14H9zM23 9h-5v2.4h2.6V14H23zM9 23h5v-2.4H11.4V18H9zM23 23h-5v-2.4h2.6V18H23z"/>
        </g>
        <rect x="14.6" y="14.6" width="2.8" height="2.8" rx=".6" fill="#f3efe8"/>
      </svg>
      <span class="brand__name">SnapAsk</span>

      @auth
        <nav class="nav">
          <a href="{{ route('web.providers.index') }}" @class(['nav__link', 'nav__link--on' => request()->routeIs('web.providers.*')])>{{ __('Models') }}</a>
          <a href="{{ route('web.connectors.index') }}" @class(['nav__link', 'nav__link--on' => request()->routeIs('web.connectors.*')])>{{ __('Services') }}</a>
          <a href="{{ route('web.conversations.index') }}" @class(['nav__link', 'nav__link--on' => request()->routeIs('web.conversations.*')])>{{ __('History') }}</a>
        </nav>
      @endauth

      {{--
        Nút chuyển ngôn ngữ: form POST chứ không phải link, vì bấm vào đây là ghi
        cookie và ghi vào tài khoản — một hành động, không phải một trang để xem.
      --}}
      <form method="POST" action="{{ route('locale.switch', $locale === 'vi' ? 'en' : 'vi') }}" class="lang">
        @csrf
        <button type="submit" class="btn--quiet" title="{{ __('Switch language') }}"
                aria-label="{{ __('Switch language') }}">{{ $locale === 'vi' ? 'EN' : 'VI' }}</button>
      </form>
    </header>

    <main id="main">
      @yield('content')
    </main>
  </div>
</body>
</html>
