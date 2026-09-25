@props(['bag' => 'default', 'prefix' => 'field-'])

{{--
  Tóm tắt lỗi ở đầu biểu mẫu. Mỗi dòng là một link tới đúng ô sai, và khối này
  nhận focus khi trang tải lại để người dùng bàn phím không phải đi tìm.
--}}
@php($messages = $errors->getBag($bag))
@if ($messages->any())
  <div class="error-summary" role="alert" tabindex="-1" aria-labelledby="{{ $prefix }}errors-title">
    <h3 id="{{ $prefix }}errors-title"><x-icon name="alert" :size="16" />{{ __('Please fix the following') }}</h3>
    <ul>
      @foreach ($messages->keys() as $field)
        <li><a href="#{{ $prefix }}{{ $field }}">{{ $messages->first($field) }}</a></li>
      @endforeach
    </ul>
  </div>
@endif
