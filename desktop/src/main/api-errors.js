'use strict';

/**
 * Chuẩn hoá lỗi từ máy chủ SnapAsk về một bộ mã cố định.
 *
 * Renderer chỉ cần biết loại lỗi để chọn hành vi — đăng nhập lại, báo hết
 * lượt, cho thử lại — chứ không nên tự đọc mã HTTP. Câu hiển thị ưu tiên câu
 * máy chủ đã dịch sẵn theo Accept-Language.
 *
 * Không phụ thuộc Electron để chạy được bằng `node --test`.
 */

/**
 * @typedef {'unauthorized'|'forbidden'|'not_found'|'validation'|'quota'|'rate_limited'|'server'|'network'|'unknown'} ErrorCode
 */

class ApiError extends Error {
  /**
   * @param {string} message
   * @param {number} status 0 khi chưa tới được máy chủ.
   * @param {ErrorCode} code
   * @param {object} [extra]
   */
  constructor(message, status, code, extra = {}) {
    super(message);
    this.status = status;
    this.code = code;
    Object.assign(this, extra);
  }
}

/** Loại lỗi theo mã HTTP và thân phản hồi. */
function classify(status, body = {}) {
  if (status === 0) return 'network';
  if (status === 401) return 'unauthorized';
  // 403 từ middleware `verified`: tài khoản chưa xác thực email.
  if (status === 403) return 'forbidden';
  if (status === 404) return 'not_found';
  if (status === 422) return 'validation';
  // /api/ask trả 429 kèm `quota` khi hết lượt tháng; 429 trơn là do throttle.
  if (status === 429) return body && typeof body === 'object' && body.quota ? 'quota' : 'rate_limited';
  if (status >= 500) return 'server';

  return 'unknown';
}

/**
 * Dựng ApiError từ phản hồi lỗi.
 *
 * @param {number} status
 * @param {object} body thân JSON đã đọc (có thể rỗng)
 * @param {(key: string, vars?: object) => string} t hàm dịch
 */
function fromResponse(status, body, t) {
  const safeBody = body && typeof body === 'object' ? body : {};
  const code = classify(status, safeBody);

  // Lỗi 422 kèm chi tiết từng ô; lấy dòng đầu vì nó nói rõ sai ở đâu, còn
  // `message` của Laravel chỉ là câu tóm tắt chung.
  const detail = Object.values(safeBody.errors ?? {})[0]?.[0];

  const fallback = {
    unauthorized: t('Your session has ended. Sign in again.'),
    forbidden: t('Verify your email before using SnapAsk.'),
    not_found: t('This conversation no longer exists.'),
    rate_limited: t('Too many requests. Wait a moment and try again.'),
    server: t('The SnapAsk server had a problem. Try again in a few minutes.'),
  }[code];

  // Thông điệp 5xx có thể lộ chi tiết hạ tầng; chỉ dùng câu chung.
  const message = code === 'server'
    ? fallback
    : (typeof detail === 'string' && detail) || (typeof safeBody.message === 'string' && safeBody.message) || fallback
      || t('The server returned error :status.', { status });

  return new ApiError(message, status, code, safeBody.quota ? { quota: safeBody.quota } : {});
}

/** Lỗi không tới được máy chủ: mất mạng, DNS, TLS, máy chủ tắt. */
function network(t) {
  return new ApiError(t('Could not reach the SnapAsk server.'), 0, 'network');
}

/** Dạng gửi qua IPC: chỉ những trường an toàn, không kèm stack hay header. */
function toIpc(error, t = (key) => key) {
  if (error instanceof ApiError) {
    return { ok: false, code: error.code, status: error.status, message: error.message, ...(error.quota ? { quota: error.quota } : {}) };
  }

  if (error && error.code === 'invalid') {
    return { ok: false, code: 'invalid', message: t('The request was not valid.') };
  }

  return { ok: false, code: 'unknown', message: t('An error occurred.') };
}

module.exports = { ApiError, classify, fromResponse, network, toIpc };
