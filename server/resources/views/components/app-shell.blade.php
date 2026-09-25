@props(['user', 'quota', 'section' => null, 'narrow' => false])

{{--
  Khung của trang quản lý: thanh bên, thanh trên và vùng nội dung.

  Tách khỏi `layouts/app.blade.php` để cái layout chỉ còn lo phần <head> và
  nạp tài nguyên, còn hình dạng của khung nằm gọn một chỗ.

  `$slot` là nội dung trang. Thông báo sau thao tác (`x-flash`) đặt sẵn ở đây
  nên mọi trang đều hiện nó ở cùng một vị trí, không trang nào quên.
--}}
@php
  $locale = app()->getLocale();
  $quotaLow = ! $quota['own_key'] && $quota['remaining'] <= max(1, (int) floor($quota['limit'] * 0.1));
@endphp

<div class="shell">
  <x-sidebar-nav :user="$user" :quota="$quota" />
  <div class="scrim" aria-hidden="true"></div>

  <div class="main">
    <header class="topbar">
      <button type="button" class="btn btn--ghost btn--icon nav-toggle" data-nav-toggle
              aria-expanded="false" aria-controls="portal-nav" aria-label="{{ __('Open navigation') }}">
        <x-icon name="menu" />
      </button>

      <span class="topbar__title">{{ $section ?? __('Management') }}</span>

      <div class="topbar__end">
        <a href="{{ route('web.account') }}#plan" @class(['quota-chip', 'quota-chip--low' => $quotaLow])
           aria-label="{{ $quota['own_key']
              ? __('Own key, no ask limit')
              : __(':remaining of :limit asks left this month', ['remaining' => $quota['remaining'], 'limit' => $quota['limit']]) }}">
          @if ($quota['own_key'])
            <x-icon name="key" :size="15" />
            <span>{{ __('Own key') }}</span>
          @else
            <x-icon name="message" :size="15" />
            <b class="tabular">{{ $quota['remaining'] }}/{{ $quota['limit'] }}</b>
            <span>{{ __('asks left') }}</span>
          @endif
        </a>

        <form method="POST" action="{{ route('locale.switch', $locale === 'vi' ? 'en' : 'vi') }}">
          @csrf
          <button type="submit" class="lang-switch" title="{{ __('Switch language') }}"
                  aria-label="{{ __('Switch language') }}">{{ $locale === 'vi' ? 'EN' : 'VI' }}</button>
        </form>

        <x-account-menu :user="$user" />
      </div>
    </header>

    <main id="main" class="content" tabindex="-1">
      <div @class(['page', 'page--narrow' => $narrow])>
        <x-flash />
        {{ $slot }}
      </div>
    </main>
  </div>
</div>
