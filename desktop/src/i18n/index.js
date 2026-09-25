'use strict';

/**
 * Song ngữ cho ứng dụng desktop.
 *
 * Từ điển nạp một lần lúc khởi động rồi nằm trong bộ nhớ: hai file này tổng
 * cộng vài KB, đọc lại từ đĩa mỗi lần dịch một chữ thì chẳng được gì.
 *
 * Khoá là câu tiếng Anh, giống hệt bên máy chủ. Quên dịch một chữ thì người
 * dùng đọc được tiếng Anh, chứ không thấy một mã khoá trần trụi.
 */

const vi = require('./vi.json');
const en = require('./en.json');

const DICTIONARIES = { vi, en };
const FALLBACK = 'en';

let current = 'vi';

/** Ngôn ngữ này có từ điển không. */
function supported(locale) {
  return Object.prototype.hasOwnProperty.call(DICTIONARIES, locale);
}

/**
 * Rút gọn một thẻ ngôn ngữ về mã hai chữ cái mà ứng dụng hiểu.
 *
 * "vi-VN" và "en-GB" đều phải ra đúng từ điển, nên chỉ lấy phần đầu.
 */
function normalise(tag) {
  if (typeof tag !== 'string' || tag === '') return null;

  const primary = tag.toLowerCase().split(/[-_]/)[0];

  return supported(primary) ? primary : null;
}

function getLocale() {
  return current;
}

/**
 * Đổi ngôn ngữ đang dùng.
 *
 * Trả về ngôn ngữ thực sự được đặt, để nơi gọi biết yêu cầu của mình có được
 * chấp nhận hay không mà không phải tự kiểm tra lại.
 */
function setLocale(locale) {
  const normalised = normalise(locale);

  if (normalised) current = normalised;

  return current;
}

/**
 * Dịch một chuỗi.
 *
 * `vars` thay các chỗ giữ `:ten` trong câu, cùng cú pháp với bên máy chủ để hai
 * bộ từ điển đọc giống nhau.
 */
function t(key, vars = {}) {
  const line = DICTIONARIES[current]?.[key] ?? DICTIONARIES[FALLBACK]?.[key] ?? key;

  return Object.entries(vars).reduce(
    (text, [name, value]) => text.split(`:${name}`).join(String(value)),
    line,
  );
}

/** Toàn bộ từ điển của ngôn ngữ đang dùng, để gửi một lần xuống renderer. */
function dictionary() {
  return { ...DICTIONARIES[FALLBACK], ...DICTIONARIES[current] };
}

module.exports = { t, getLocale, setLocale, supported, normalise, dictionary };
