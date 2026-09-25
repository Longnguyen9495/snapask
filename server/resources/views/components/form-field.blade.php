@props([
    'name',
    'label',
    'id' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'bag' => 'default',
    'optional' => false,
    'full' => false,
])

{{--
  Một ô nhập: nhãn ở trên, gợi ý và lỗi ở dưới, nối với ô bằng aria-describedby.

  Không dùng placeholder làm nhãn — chữ gợi ý biến mất ngay khi người dùng gõ.
  `type="select"` và `type="textarea"` đổ phần tử con qua slot.
--}}
@php
  $id ??= 'field-'.$name;
  $errorBag = $errors->getBag($bag);
  $error = $errorBag->first($name);
  $describedBy = collect([$hint ? $id.'-hint' : null, $error ? $id.'-error' : null])->filter()->implode(' ');
  $controlAttributes = $attributes->merge([
      'id' => $id,
      'name' => $name,
      'aria-invalid' => $error ? 'true' : null,
      'aria-describedby' => $describedBy ?: null,
  ]);
@endphp

<div @class(['field', 'field--full' => $full])>
  <label class="field__label" for="{{ $id }}">
    {{ $label }}
    @if ($optional)
      <span class="faint">({{ __('optional') }})</span>
    @endif
  </label>

  @if ($type === 'select')
    <select {{ $controlAttributes->class('select') }}>{{ $slot }}</select>
  @elseif ($type === 'textarea')
    <textarea {{ $controlAttributes->class('textarea') }}>{{ $slot }}</textarea>
  @else
    <input type="{{ $type }}" {{ $controlAttributes->class('input') }} @if ($value !== null) value="{{ $value }}" @endif>
  @endif

  @if ($hint)
    <p class="field__hint" id="{{ $id }}-hint">{{ $hint }}</p>
  @endif

  @if ($error)
    <p class="field__error" id="{{ $id }}-error"><x-icon name="alert" :size="14" />{{ $error }}</p>
  @endif
</div>
