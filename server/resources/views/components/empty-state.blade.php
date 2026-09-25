@props(['title', 'icon' => 'message', 'level' => 'h2'])

{{-- Trạng thái rỗng nói rõ bước tiếp theo, không chỉ một dòng "chưa có gì". --}}
<div {{ $attributes->class('empty') }}>
  <span class="empty__icon" aria-hidden="true"><x-icon :name="$icon" :size="20" /></span>
  <{{ $level }}>{{ $title }}</{{ $level }}>
  <p>{{ $slot }}</p>

  @if (isset($actions) && $actions->isNotEmpty())
    <div class="actions">{{ $actions }}</div>
  @endif
</div>
