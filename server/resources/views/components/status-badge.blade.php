@props(['status' => 'neutral'])

{{--
  Nhãn trạng thái. Luôn có chữ và icon đi kèm màu, để người mù màu đỏ–xanh vẫn
  đọc được đúng trạng thái.
--}}
@php
  $variant = match ($status) {
      'ok' => ['class' => 'badge--ok', 'icon' => 'check-circle'],
      'error' => ['class' => 'badge--danger', 'icon' => 'alert'],
      'active' => ['class' => 'badge--accent', 'icon' => 'check'],
      'disabled' => ['class' => '', 'icon' => 'ban'],
      default => ['class' => '', 'icon' => 'circle'],
  };
@endphp
<span {{ $attributes->class(['badge', $variant['class']]) }}>
  <x-icon :name="$variant['icon']" :size="13" />
  {{ $slot }}
</span>
