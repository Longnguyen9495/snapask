@props(['at', 'relative' => true])

{{-- Mốc thời gian: chữ tương đối cho mắt đọc, mốc tuyệt đối trong title và datetime cho máy đọc. --}}
@if ($at)
  <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->translatedFormat('j F Y, H:i') }}" {{ $attributes }}>{{ $relative ? $at->diffForHumans() : $at->translatedFormat('j F Y, H:i') }}</time>
@endif
