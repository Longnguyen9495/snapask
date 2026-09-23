'use strict';

const store = require('./store');

class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

const base = () => store.read().serverUrl.replace(/\/+$/, '');

function headers(extra = {}) {
  const token = store.getToken();

  return {
    Accept: 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...extra,
  };
}

async function decode(response) {
  const body = await response.json().catch(() => ({}));

  if (!response.ok) {
    // Lỗi 422 kèm chi tiết từng ô; lấy dòng đầu vì nó nói rõ sai ở đâu, còn
    // `message` của Laravel chỉ là câu tóm tắt chung.
    const detail = Object.values(body.errors ?? {})[0]?.[0];

    throw new ApiError(detail || body.message || `Máy chủ trả về lỗi ${response.status}.`, response.status);
  }

  return body;
}

async function login(email, password, deviceName) {
  const response = await fetch(`${base()}/api/auth/token`, {
    method: 'POST',
    headers: headers({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ email, password, device_name: deviceName }),
  });

  const body = await decode(response);
  store.setToken(body.token);

  return body.user;
}

async function register(name, email, password, deviceName) {
  const response = await fetch(`${base()}/api/auth/register`, {
    method: 'POST',
    headers: headers({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({
      name,
      email,
      password,
      // Máy chủ đòi xác nhận mật khẩu; giao diện đã đối chiếu hai ô trước khi
      // gọi tới đây nên chỉ việc gửi lại cùng một giá trị.
      password_confirmation: password,
      device_name: deviceName,
    }),
  });

  const body = await decode(response);
  store.setToken(body.token);

  return body.user;
}

async function logout() {
  await fetch(`${base()}/api/auth/token`, { method: 'DELETE', headers: headers() }).catch(() => {});
  store.setToken(null);
}

const me = async () => decode(await fetch(`${base()}/api/me`, { headers: headers() }));

/**
 * Gửi một lượt hỏi và đọc câu trả lời theo dòng SSE.
 *
 * `onEvent` được gọi cho từng sự kiện: {type:'delta'|'done'|'error', ...}.
 * Trả về hàm huỷ để người dùng đóng ô chat giữa chừng mà không bỏ rơi kết nối.
 */
async function ask({ conversationId, question, imageDataUrl }, onEvent) {
  const controller = new AbortController();

  const run = async () => {
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
        signal: controller.signal,
      });
    } catch (error) {
      if (error.name !== 'AbortError') {
        onEvent({ type: 'error', message: 'Không kết nối được máy chủ SnapAsk.' });
      }

      return;
    }

    if (!response.ok) {
      const body = await response.json().catch(() => ({}));
      onEvent({
        type: 'error',
        status: response.status,
        message: body.message || `Máy chủ trả về lỗi ${response.status}.`,
      });

      return;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

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
          onEvent(JSON.parse(payload));
        } catch {
          // Bỏ qua dòng hỏng thay vì cắt ngang cả câu trả lời.
        }
      }
    }
  };

  run().catch((error) => {
    if (error.name !== 'AbortError') {
      onEvent({ type: 'error', message: error.message });
    }
  });

  return () => controller.abort();
}

module.exports = { login, register, logout, me, ask, ApiError };
