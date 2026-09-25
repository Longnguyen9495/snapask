@props(['content'])

{{--
  Câu trả lời của mô hình, dựng từ markdown.

  Nội dung đến từ mô hình là dữ liệu không tin được: HTML thô trong đó bị thoát
  thành chữ (`html_input => escape`) và link dạng javascript:/data: bị bỏ
  (`allow_unsafe_links => false`), nên không có đường nào để chèn mã vào trang.
--}}
<div {{ $attributes->class('prose') }}>{!! \Illuminate\Support\Str::markdown((string) $content, [
    'html_input' => 'escape',
    'allow_unsafe_links' => false,
    'max_nesting_level' => 20,
]) !!}</div>
