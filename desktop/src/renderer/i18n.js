'use strict';

/**
 * Dịch chữ trong cửa sổ.
 *
 * Từ điển nằm ở tiến trình chính; ở đây chỉ giữ một bản sao để dịch không phải
 * đi qua IPC cho từng chữ một.
 *
 * Nạp bằng <script src> thường chứ không phải module, giống các file renderer
 * còn lại, nên nó treo `window.i18n` thay vì export.
 *
 * Đánh dấu chỗ cần dịch bằng thuộc tính trên thẻ:
 *
 *   data-i18n="Ask"            nội dung của thẻ
 *   data-i18n-title="Text"     thuộc tính title
 *   data-i18n-placeholder=…    thuộc tính placeholder
 *   data-i18n-alt=…            thuộc tính alt
 *   data-i18n-aria-label=…     nhãn cho trình đọc màn hình
 */

(() => {
  let dictionary = {};
  let locale = 'vi';

  /** Dịch một khoá, thay các chỗ giữ `:ten` bằng giá trị trong `vars`. */
  const t = (key, vars = {}) => Object.entries(vars).reduce(
    (text, [name, value]) => text.split(`:${name}`).join(String(value)),
    dictionary[key] ?? key,
  );

  const ATTRIBUTES = {
    'data-i18n-title': 'title',
    'data-i18n-placeholder': 'placeholder',
    'data-i18n-alt': 'alt',
    'data-i18n-aria-label': 'aria-label',
  };

  /** Dịch lại toàn bộ cây DOM đang hiện. */
  const apply = (root = document) => {
    for (const el of root.querySelectorAll('[data-i18n]')) {
      el.textContent = t(el.dataset.i18n);
    }

    for (const [dataAttr, target] of Object.entries(ATTRIBUTES)) {
      for (const el of root.querySelectorAll(`[${dataAttr}]`)) {
        el.setAttribute(target, t(el.getAttribute(dataAttr)));
      }
    }

    document.documentElement.lang = locale;
  };

  /**
   * Nạp từ điển rồi dịch trang, và dịch lại mỗi khi người dùng đổi ngôn ngữ.
   *
   * `onChange` để từng cửa sổ vẽ lại phần chữ nó tự dựng bằng JS — những chỗ
   * `data-i18n` không với tới được.
   */
  const start = async (onChange) => {
    const payload = await window.snapask.locale();

    dictionary = payload.dictionary;
    locale = payload.locale;
    apply();
    onChange?.(locale);

    window.snapask.onLocale((next) => {
      dictionary = next.dictionary;
      locale = next.locale;
      apply();
      onChange?.(locale);
    });
  };

  window.i18n = { t, apply, start, getLocale: () => locale, setLocale: (l) => window.snapask.setLocale(l) };
})();
