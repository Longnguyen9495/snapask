/*
 * Hành vi của trang quản lý.
 *
 * Mọi trang vẫn chạy khi không có JS: form vẫn gửi, link vẫn mở. File này chỉ
 * thêm những thứ làm trải nghiệm tốt hơn — ngăn kéo điều hướng, menu tài khoản,
 * hộp thoại xác nhận thay cho window.confirm, trạng thái đang gửi, nút sao chép
 * khối mã.
 *
 * Chữ hiển thị lấy từ <script id="portal-strings">, do Blade dịch sẵn; ở đây
 * không viết cứng câu nào.
 */
(() => {
  'use strict';

  const strings = (() => {
    try {
      return JSON.parse(document.getElementById('portal-strings')?.textContent || '{}');
    } catch {
      return {};
    }
  })();

  const t = (key) => strings[key] ?? key;

  const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  const focusables = (root) => [...root.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null || el === document.activeElement);

  /** Giữ Tab vòng trong một vùng, dùng cho ngăn kéo điều hướng. */
  function trapTab(root, event) {
    if (event.key !== 'Tab') return;

    const items = focusables(root);

    if (items.length === 0) return;

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  /* ---------- ngăn kéo điều hướng ---------- */

  const shell = document.querySelector('.shell');
  const sidebar = document.getElementById('portal-nav');
  const navToggle = document.querySelector('[data-nav-toggle]');
  // Khớp với breakpoint ngăn kéo trong portal.css. Từ 768px trở lên thanh bên
  // là dải icon cố định, không còn là ngăn kéo để mở ra đóng vào.
  const narrow = window.matchMedia('(max-width: 767px)');

  if (shell && sidebar && navToggle) {
    const setOpen = (open, { restore = true } = {}) => {
      shell.classList.toggle('is-nav-open', open);
      navToggle.setAttribute('aria-expanded', String(open));

      if (open) {
        (sidebar.querySelector('[aria-current="page"]') || focusables(sidebar)[0])?.focus();
      } else if (restore) {
        navToggle.focus();
      }
    };

    navToggle.addEventListener('click', () => setOpen(!shell.classList.contains('is-nav-open')));
    document.querySelector('[data-nav-close]')?.addEventListener('click', () => setOpen(false));
    document.querySelector('.scrim')?.addEventListener('click', () => setOpen(false));

    sidebar.addEventListener('keydown', (event) => {
      if (!shell.classList.contains('is-nav-open')) return;

      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false);
      }

      trapTab(sidebar, event);
    });

    // Nới cửa sổ ra đủ rộng thì sidebar hiện cố định; bỏ trạng thái ngăn kéo
    // để quay lại màn hình hẹp không thấy nó mở sẵn.
    narrow.addEventListener('change', () => setOpen(false, { restore: false }));
  }

  /* ---------- menu tài khoản ---------- */

  for (const menu of document.querySelectorAll('[data-menu]')) {
    const trigger = menu.querySelector('[data-menu-trigger]');
    const panel = menu.querySelector('[data-menu-panel]');

    if (!trigger || !panel) continue;

    const items = () => [...panel.querySelectorAll('.menu__item')];

    const setOpen = (open, { focus = 'first' } = {}) => {
      panel.hidden = !open;
      trigger.setAttribute('aria-expanded', String(open));

      if (open && focus === 'first') items()[0]?.focus();
      if (!open && focus === 'trigger') trigger.focus();
    };

    trigger.addEventListener('click', () => setOpen(panel.hidden));

    trigger.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setOpen(true);
      }
    });

    panel.addEventListener('keydown', (event) => {
      const list = items();
      const index = list.indexOf(document.activeElement);

      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false, { focus: 'trigger' });
      } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        list[(index + 1) % list.length]?.focus();
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        list[(index - 1 + list.length) % list.length]?.focus();
      } else if (event.key === 'Home') {
        event.preventDefault();
        list[0]?.focus();
      } else if (event.key === 'End') {
        event.preventDefault();
        list[list.length - 1]?.focus();
      }
    });

    // Tab ra khỏi menu thì đóng, để không còn một bảng nổi mồ côi trên trang.
    menu.addEventListener('focusout', (event) => {
      if (!panel.hidden && !menu.contains(event.relatedTarget)) setOpen(false, { focus: 'none' });
    });

    document.addEventListener('click', (event) => {
      if (!panel.hidden && !menu.contains(event.target)) setOpen(false, { focus: 'none' });
    });
  }

  /* ---------- hộp thoại xác nhận ---------- */

  const dialog = document.getElementById('confirm-dialog');

  if (dialog && typeof dialog.showModal === 'function') {
    const title = dialog.querySelector('[data-confirm-title]');
    const message = dialog.querySelector('[data-confirm-message]');
    const accept = dialog.querySelector('[data-confirm-accept]');
    let pendingForm = null;
    let returnFocus = null;

    document.addEventListener('submit', (event) => {
      const form = event.target;

      if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;

      event.preventDefault();
      pendingForm = form;
      returnFocus = event.submitter || document.activeElement;

      title.textContent = form.dataset.confirmTitle || t('Are you sure?');
      message.textContent = form.dataset.confirm;
      accept.textContent = form.dataset.confirmAction || t('Delete');

      dialog.showModal();
      // Nút an toàn nhận focus trước: bấm Enter theo quán tính không xoá gì cả.
      dialog.querySelector('[data-confirm-cancel]')?.focus();
    }, true);

    accept.addEventListener('click', () => {
      const form = pendingForm;

      dialog.close('accept');

      if (!form) return;

      form.dataset.confirmed = '1';
      markBusy(form);
      form.submit();
    });

    dialog.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => dialog.close('cancel'));

    dialog.addEventListener('close', () => {
      if (dialog.returnValue !== 'accept') {
        pendingForm = null;
        returnFocus?.focus?.();
      }
    });
  } else {
    /*
     * Trình duyệt không có <dialog>: quay về hộp thoại của trình duyệt.
     *
     * Xấu hơn nhiều, nhưng xoá vĩnh viễn thì thà hỏi bằng hộp thoại xấu còn hơn
     * không hỏi. Không có nhánh này thì form đi thẳng, mất dữ liệu không báo.
     */
    document.addEventListener('submit', (event) => {
      const form = event.target;

      if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();

        return;
      }

      markBusy(form);
    }, true);
  }

  /* ---------- trạng thái đang gửi ---------- */

  const live = document.getElementById('portal-live');

  function markBusy(form, submitter) {
    const button = submitter || form.querySelector('button[type="submit"], button:not([type])');

    if (button) {
      button.setAttribute('aria-busy', 'true');
      button.setAttribute('aria-disabled', 'true');
    }

    if (live && form.dataset.pending) live.textContent = form.dataset.pending;
  }

  /*
   * Mọi form gửi đi đều bị khoá tới khi trang mới tải: bấm hai lần "Đồng bộ
   * lại" là hai lần gọi dịch vụ ngoài, bấm hai lần "Thêm" là hai bản ghi.
   */
  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.method.toLowerCase() === 'get') return;

    if (form.dataset.submitting === '1') {
      event.preventDefault();
      return;
    }

    form.dataset.submitting = '1';
    markBusy(form, event.submitter);
  });

  // Quay lại bằng nút Back thì trang lấy từ bộ đệm, còn nguyên trạng thái khoá.
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;

    for (const form of document.querySelectorAll('form[data-submitting="1"]')) {
      delete form.dataset.submitting;
      delete form.dataset.confirmed;

      for (const button of form.querySelectorAll('[aria-busy="true"]')) {
        button.removeAttribute('aria-busy');
        button.removeAttribute('aria-disabled');
      }
    }
  });

  /* ---------- sao chép khối mã ---------- */

  for (const pre of document.querySelectorAll('.prose pre')) {
    const wrap = document.createElement('div');
    const button = document.createElement('button');

    wrap.className = 'code';
    button.type = 'button';
    button.className = 'btn btn--secondary btn--sm code__copy';
    button.textContent = t('Copy');

    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(pre.innerText.replace(/\n$/, ''));
        button.textContent = t('Copied');
      } catch {
        button.textContent = t('Could not copy');
      }

      if (live) live.textContent = button.textContent;
      setTimeout(() => { button.textContent = t('Copy'); }, 1800);
    });

    pre.replaceWith(wrap);
    wrap.append(pre, button);
  }

  /* ---------- ô mật khẩu ---------- */

  for (const input of document.querySelectorAll('input[type="password"]')) {
    const wrap = document.createElement('span');
    const toggle = document.createElement('button');

    wrap.className = 'pw';
    toggle.type = 'button';
    toggle.className = 'pw__toggle';

    const render = (visible) => {
      toggle.textContent = visible ? t('Hide') : t('Show');
      toggle.setAttribute('aria-label', visible ? t('Hide password') : t('Show password'));
      toggle.setAttribute('aria-pressed', String(visible));
    };

    toggle.addEventListener('click', () => {
      const visible = input.type === 'password';

      input.type = visible ? 'text' : 'password';
      render(visible);
      input.focus();
    });

    input.replaceWith(wrap);
    wrap.append(input, toggle);
    render(false);
  }

  /* ---------- gợi ý địa chỉ theo định dạng API ---------- */

  for (const select of document.querySelectorAll('[data-format-select]')) {
    const baseUrl = document.getElementById(select.dataset.formatSelect);

    if (!baseUrl) continue;

    baseUrl.addEventListener('input', () => { baseUrl.dataset.filled = 'user'; });

    select.addEventListener('change', () => {
      if (!baseUrl.value || baseUrl.dataset.filled !== 'user') {
        baseUrl.value = select.selectedOptions[0]?.dataset.hint || '';
        baseUrl.dataset.filled = 'hint';
      }
    });
  }

  /* ---------- mở khối thu gọn theo link ---------- */

  const openDetails = (id) => {
    const details = id && document.getElementById(id);

    if (!(details instanceof HTMLDetailsElement)) return false;

    details.open = true;
    details.querySelector('summary')?.focus();
    details.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });

    return true;
  };

  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-open-details]');

    if (link && openDetails(link.dataset.openDetails)) event.preventDefault();
  });

  if (location.hash.length > 1) openDetails(decodeURIComponent(location.hash.slice(1)));

  /* ---------- tóm tắt lỗi ---------- */

  // Trang tải lại sau lỗi xác thực: đưa focus vào bản tóm tắt để trình đọc màn
  // hình đọc ngay, và để người dùng bàn phím đi thẳng tới ô sai.
  document.querySelector('.error-summary[tabindex="-1"]')?.focus();
})();
