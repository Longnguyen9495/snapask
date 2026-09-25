{{--
  Hộp thoại xác nhận dùng chung cho mọi thao tác xoá.

  Form nào cần hỏi lại thì khai `data-confirm="câu hỏi"` (và tuỳ chọn
  `data-confirm-title`, `data-confirm-action`); portal.js chặn lần gửi đầu,
  mở hộp thoại này rồi mới gửi thật. <dialog> dạng modal tự giữ focus bên trong
  và tự đóng bằng Escape.
--}}
<dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-message">
  <div class="dialog__body">
    <span class="dialog__icon" aria-hidden="true"><x-icon name="trash" /></span>
    <div>
      <h2 id="confirm-title" data-confirm-title>{{ __('Are you sure?') }}</h2>
      <p id="confirm-message" data-confirm-message></p>
    </div>
  </div>
  <div class="dialog__actions">
    <button type="button" class="btn btn--secondary" data-confirm-cancel>{{ __('Cancel') }}</button>
    <button type="button" class="btn btn--danger-solid" data-confirm-accept>{{ __('Delete') }}</button>
  </div>
</dialog>
