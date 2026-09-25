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

  <x-app-shell :user="$shellUser" :quota="$shellQuota"
               :section="View::yieldContent('section', __('Management'))"
               :narrow="View::hasSection('narrow')">
    @yield('content')
  </x-app-shell>

  <x-confirm-dialog />
  <p class="sr-only" id="portal-live" role="status" aria-live="polite"></p>

  @include('partials.portal-strings')
  <script src="{{ asset('js/portal.js') }}" defer></script>
</body>
</html>
