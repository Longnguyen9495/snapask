'use strict';

const store = require('./store');
const i18n = require('../i18n');
const errors = require('./api-errors');

const { ApiError } = errors;

const base = () => store.read().serverUrl.replace(/\/+$/, '');

/** Thời gian chờ cho các lời gọi thường; lượt hỏi SSE không bị giới hạn ở đây. */
const REQUEST_TIMEOUT_MS = 20000;

/**
 * Header chung. Token chỉ đi trong header Authorization, không bao giờ nằm
 * trong URL: đường dẫn có token dễ lọt vào nhật ký máy chủ và proxy.
 */
function headers(extra = {}) {
  const token = store.getToken();

  return {
    Accept: 'application/json',
    // Máy chủ dịch thông báo lỗi theo header này, nên câu "hết lượt hỏi" về tới
    // đây đã đúng thứ tiếng người dùng đang xem.
    'Accept-Language': i18n.getLocale(),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...extra,
  };
}

/**
 * Gọi API và trả JSON, hoặc ném ApiError đã chuẩn hoá.
 *
 * Lỗi mạng (không tới được máy chủ) thành mã `network` để giao diện hiện trạng
 * thái ngoại tuyến thay vì coi như hết phiên.
 */
async function request(path, { method = 'GET', body, signal } = {}) {
  const timeout = AbortSignal.timeout(REQUEST_TIMEOUT_MS);
  let response;

  try {
    response = await fetch(`${base()}${path}`, {
      method,
      headers: headers(body ? { 'Content-Type': 'application/json' } : {}),
      body: body ? JSON.stringify(body) : undefined,
      signal: signal ? AbortSignal.any([signal, timeout]) : timeout,
    });
  } catch (error) {
    if (signal?.aborted) throw error;

    throw errors.network(i18n.t);
  }

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) throw errors.fromResponse(response.status, payload, i18n.t);

  return payload;
}

async function login(email, password, deviceName) {
  const body = await request('/api/auth/token', {
    method: 'POST',
    body: { email, password, device_name: deviceName },
  });

  store.setToken(body.token);

  return body.user;
}

async function register(name, email, password, deviceName) {
  const body = await request('/api/auth/register', {
    method: 'POST',
    body: {
      name,
      email,
      password,
      // Máy chủ đòi xác nhận mật khẩu; giao diện đã đối chiếu hai ô trước khi
      // gọi tới đây nên chỉ việc gửi lại cùng một giá trị.
      password_confirmation: password,
      device_name: deviceName,
    },
  });

  // Máy chủ bắt xác thực email thì chưa có token; báo lên để cửa sổ bảo người
  // dùng mở hộp thư thay vì coi như đã đăng nhập.
  if (body.verification_required) {
    return { user: body.user, verificationRequired: true, message: body.message };
  }

  store.setToken(body.token);

  return { user: body.user, verificationRequired: false };
}

/** Thu hồi token trên máy chủ rồi xoá khỏi máy. Mất mạng thì vẫn xoá ở máy. */
async function logout() {
  if (store.getToken()) {
    await request('/api/auth/token', { method: 'DELETE' }).catch(() => {});
  }

  store.setToken(null);
}

const me = () => request('/api/me');

/**
 * Ghi ngôn ngữ đã chọn lên tài khoản.
 *
 * Để cùng một người mở trang web hay mở app trên máy khác đều thấy đúng thứ
 * tiếng họ đã chọn.
 */
const setLocale = (locale) => request('/api/me', { method: 'PATCH', body: { locale } });

/* ---------- hội thoại ---------- */

/** Một trang lịch sử, hoạt động gần nhất trước; `search` tìm cả tiêu đề lẫn nội dung. */
function listConversations({ page = 1, search = '' } = {}) {
  const query = new URLSearchParams({ page: String(page) });

  if (search) query.set('search', search);

  return request(`/api/conversations?${query}`);
}

const showConversation = (id) => request(`/api/conversations/${encodeURIComponent(id)}`);

const renameConversation = (id, title) => request(`/api/conversations/${encodeURIComponent(id)}`, {
  method: 'PATCH',
  body: { title },
});

const deleteConversation = (id) => request(`/api/conversations/${encodeURIComponent(id)}`, { method: 'DELETE' });

/**
 * Ảnh chụp của một hội thoại, dưới dạng data URL để renderer hiển thị.
 *
 * Tải trong tiến trình chính kèm token ở header; renderer chỉ nhận dữ liệu
 * ảnh, không nhận địa chỉ hay token. Không ghi ra đĩa: ảnh màn hình nhạy cảm
 * chỉ sống trong bộ nhớ.
 */
async function conversationImage(id) {
  let response;

  try {
    response = await fetch(`${base()}/api/conversations/${encodeURIComponent(id)}/image`, {
      headers: headers({ Accept: 'image/*' }),
      signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });
  } catch {
    throw errors.network(i18n.t);
  }

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    throw errors.fromResponse(response.status, payload, i18n.t);
  }

  const mime = (response.headers.get('content-type') || '').split(';')[0].trim();

  if (!['image/png', 'image/jpeg', 'image/webp'].includes(mime)) {
    throw new ApiError(i18n.t('The screenshot could not be displayed.'), response.status, 'unknown');
  }

  const buffer = Buffer.from(await response.arrayBuffer());

  return { dataUrl: `data:${mime};base64,${buffer.toString('base64')}`, bytes: buffer.byteLength };
}

/* ---------- hỏi AI ---------- */

/**
 * Gửi một lượt hỏi và đọc câu trả lời theo dòng SSE.
 *
 * `onEvent` được gọi cho từng sự kiện: {type:'delta'|'tool'|'done'|'error', ...}.
 * Lỗi luôn kèm `code` đã chuẩn hoá (xem api-errors.js). Huỷ bằng `signal`: khi
 * đó không phát thêm sự kiện nào, để nơi gọi tự quyết định hiển thị "đã dừng".
 *
 * Trả về promise xong khi luồng kết thúc, dù thành công, lỗi hay bị huỷ.
 */
async function ask({ conversationId, question, imageDataUrl }, onEvent, signal) {
  let response;

  try {
    response = await fetch(`${base()}/api/ask`, {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json', Accept: 'text/event-stream' }),
      body: JSON.stringify({
        conversation_id: conversationId || null,
        question,
        image: imageDataUrl || null,
      }),
      signal,
    });
  } catch {
    if (!signal?.aborted) {
      onEvent({ type: 'error', code: 'network', message: i18n.t('Could not reach the SnapAsk server.') });
    }

    return;
  }

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    const error = errors.fromResponse(response.status, payload, i18n.t);

    onEvent({ type: 'error', code: error.code, status: error.status, message: error.message, ...(error.quota ? { quota: error.quota } : {}) });

    return;
  }

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let finished = false;

  try {
    while (true) {
      const { done, value } = await reader.read();

      if (done) break;

      buffer += decoder.decode(value, { stream: true });

      // SSE ngắt theo dòng; giữ lại phần đuôi dở dang cho vòng đọc sau, nếu
      // không một sự kiện bị cắt đôi sẽ hỏng JSON.
      let index;

      while ((index = buffer.indexOf('\n')) !== -1) {
        const line = buffer.slice(0, index).trim();
        buffer = buffer.slice(index + 1);

        if (!line.startsWith('data:')) continue;

        const payload = line.slice(5).trim();

        if (payload === '[DONE]') return;

        try {
          const event = JSON.parse(payload);

          if (event.type === 'done' || event.type === 'error') finished = true;
          if (event.type === 'error' && !event.code) event.code = 'server';

          onEvent(event);
        } catch {
          // Bỏ qua dòng hỏng thay vì cắt ngang cả câu trả lời.
        }
      }
    }
  } catch {
    if (signal?.aborted) return;

    onEvent({ type: 'error', code: 'network', message: i18n.t('The connection dropped before the answer finished.') });

    return;
  }

  // Máy chủ đóng luồng mà không báo xong: coi như đứt giữa chừng.
  if (!finished && !signal?.aborted) {
    onEvent({ type: 'error', code: 'network', message: i18n.t('The connection dropped before the answer finished.') });
  }
}

module.exports = {
  login,
  register,
  logout,
  me,
  setLocale,
  listConversations,
  showConversation,
  renameConversation,
  deleteConversation,
  conversationImage,
  ask,
  ApiError,
};
