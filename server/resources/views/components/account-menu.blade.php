@props(['user'])

{{--
  Menu tài khoản ở thanh trên cùng.

  Là một nút mở/đóng một danh sách liên kết, không phải role="menu": các mục ở
  đây là điều hướng thường, và mẫu nút mở/đóng quen thuộc hơn với trình đọc màn
  hình. Mũi tên lên/xuống, Home, End và Escape do portal.js lo.
--}}
<div class="menu" data-menu>
  <button type="button" class="menu__trigger" data-menu-trigger aria-expanded="false" aria-controls="account-menu"
          aria-label="{{ __('Account menu for :name', ['name' => $user->name]) }}">
    <span class="avatar avatar--sm" aria-hidden="true">{{ $user->initials() }}</span>
    <x-icon name="chevron-down" :size="16" />
  </button>

  <div class="menu__panel" id="account-menu" data-menu-panel hidden>
    <div class="menu__head">
      <strong class="truncate">{{ $user->name }}</strong>
      <span class="truncate">{{ $user->email }}</span>
    </div>

    <a href="{{ route('web.account') }}" class="menu__item"><x-icon name="user" />{{ __('Account') }}</a>
    <a href="{{ route('web.settings') }}" class="menu__item"><x-icon name="sliders" />{{ __('Settings') }}</a>
    <a href="{{ app()->getLocale() === 'vi' ? route('download') : route('download.en') }}" class="menu__item"><x-icon name="download" />{{ __('Get the desktop app') }}</a>
    <div class="menu__sep" role="separator"></div>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="menu__item"><x-icon name="log-out" />{{ __('Sign out') }}</button>
    </form>
  </div>
</div>
