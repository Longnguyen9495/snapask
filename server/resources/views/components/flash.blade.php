{{--
  Thông báo sau một thao tác.

  role="status" để trình đọc màn hình đọc lên mà không cắt ngang người dùng.
  Câu báo lỗi kết nối vẫn đi qua đây nhưng mang giọng cảnh báo, vì thao tác lưu
  đã thành công — chỉ việc kết nối là chưa.
--}}
@if (session('status'))
  @php($tone = session('status_tone', 'ok'))
  <div @class(['flash', 'flash--danger' => $tone === 'error', 'flash--accent' => $tone === 'info']) role="status">
    <x-icon :name="$tone === 'error' ? 'alert' : 'check-circle'" />
    <div class="flash__body">
      <p>{{ session('status') }}</p>
      @if (session('status_hint'))
        <p>{{ session('status_hint') }}</p>
      @endif
    </div>
  </div>
@endif
