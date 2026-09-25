'use strict';

/**
 * Kiểm tra dữ liệu renderer gửi qua IPC.
 *
 * Renderer hiển thị nội dung do mô hình sinh ra, nên phải coi nó như một bên
 * không tin được: mọi thứ đi qua IPC đều được kiểm kiểu, độ dài và hình dạng
 * trước khi chạm tới mạng hay đĩa. Sai thì ném `ValidationError` để nơi gọi trả
 * về `{ ok: false, code: 'invalid' }` thay vì làm theo.
 *
 * Không phụ thuộc Electron để chạy được bằng `node --test`.
 */

class ValidationError extends Error {
  constructor(message) {
    super(message);
    this.code = 'invalid';
  }
}

const fail = (message) => { throw new ValidationError(message); };

/** Giới hạn khớp với máy chủ (`snapask.max_question_length`, `image.max_bytes`). */
const LIMITS = {
  question: 2000,
  search: 100,
  title: 120,
  requestId: 64,
  // Data URL base64 của ảnh 4 MB dài cỡ 5,6 triệu ký tự; chừa thêm cho tiền tố.
  imageDataUrl: 6 * 1024 * 1024,
};

const IMAGE_PREFIX = /^data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+/]+=*$/;
const REQUEST_ID = /^[A-Za-z0-9_-]{1,64}$/;

function conversationId(value) {
  const id = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value;

  if (!Number.isSafeInteger(id) || id <= 0) fail('conversation id');

  return id;
}

function optionalConversationId(value) {
  return value === null || value === undefined || value === '' ? null : conversationId(value);
}

function requestId(value) {
  if (typeof value !== 'string' || !REQUEST_ID.test(value)) fail('request id');

  return value;
}

function question(value) {
  if (typeof value !== 'string') fail('question');

  const text = value.trim();

  if (text === '' || text.length > LIMITS.question) fail('question');

  return text;
}

function imageDataUrl(value) {
  if (value === null || value === undefined || value === '') return null;

  if (typeof value !== 'string' || value.length > LIMITS.imageDataUrl || !IMAGE_PREFIX.test(value)) {
    fail('image');
  }

  return value;
}

/** `{ requestId, conversationId?, question, image? }` cho `ask:start`. */
function askPayload(payload) {
  if (!payload || typeof payload !== 'object') fail('payload');

  return {
    requestId: requestId(payload.requestId),
    conversationId: optionalConversationId(payload.conversationId),
    question: question(payload.question),
    imageDataUrl: imageDataUrl(payload.image),
  };
}

/** `{ page?, search? }` cho `conversations:list`. */
function listQuery(query = {}) {
  if (query === null || typeof query !== 'object') fail('query');

  const page = query.page === undefined ? 1 : Number(query.page);

  if (!Number.isSafeInteger(page) || page < 1 || page > 10000) fail('page');

  let search = query.search ?? '';

  if (typeof search !== 'string') fail('search');

  search = search.trim().slice(0, LIMITS.search);

  return { page, search };
}

function title(value) {
  if (typeof value !== 'string') fail('title');

  const text = value.trim();

  if (text === '' || text.length > LIMITS.title) fail('title');

  return text;
}

/**
 * Bản vá cài đặt từ renderer.
 *
 * Chỉ nhận những khoá người dùng được đổi, đúng kiểu và đúng tập giá trị. Khoá
 * lạ bị bỏ qua — renderer không được ghi `token` hay bất cứ thứ gì khác vào
 * snapask.json qua đường này.
 */
function settingsPatch(patch) {
  if (!patch || typeof patch !== 'object' || Array.isArray(patch)) fail('settings');

  const out = {};

  if ('locale' in patch) {
    if (!['vi', 'en'].includes(patch.locale)) fail('locale');
    out.locale = patch.locale;
  }

  if ('closeBehavior' in patch) {
    if (!['tray', 'quit'].includes(patch.closeBehavior)) fail('closeBehavior');
    out.closeBehavior = patch.closeBehavior;
  }

  if ('captureDestination' in patch) {
    if (!['compact', 'workspace'].includes(patch.captureDestination)) fail('captureDestination');
    out.captureDestination = patch.captureDestination;
  }

  if ('maxImageWidth' in patch) {
    const width = Number(patch.maxImageWidth);
    if (![960, 1280, 1600, 1920].includes(width)) fail('maxImageWidth');
    out.maxImageWidth = width;
  }

  if ('launchAtLogin' in patch) {
    if (typeof patch.launchAtLogin !== 'boolean') fail('launchAtLogin');
    out.launchAtLogin = patch.launchAtLogin;
  }

  if ('sidebarCollapsed' in patch) {
    if (typeof patch.sidebarCollapsed !== 'boolean') fail('sidebarCollapsed');
    out.sidebarCollapsed = patch.sidebarCollapsed;
  }

  if ('serverUrl' in patch) {
    out.serverUrl = serverUrl(patch.serverUrl);
  }

  return out;
}

/**
 * Địa chỉ máy chủ tự nhập ở phần Nâng cao.
 *
 * Chỉ http/https, không kèm thông tin đăng nhập, không kèm đường dẫn lạ: token
 * sẽ đi kèm mọi lời gọi tới địa chỉ này.
 */
function serverUrl(value) {
  if (typeof value !== 'string' || value.length > 2048) fail('serverUrl');

  let url;

  try {
    url = new URL(value.trim());
  } catch {
    fail('serverUrl');
  }

  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || url.search || url.hash) {
    fail('serverUrl');
  }

  return `${url.origin}${url.pathname.replace(/\/+$/, '')}`;
}

/** Link bấm từ nội dung câu trả lời: chỉ http/https mới được mở ra trình duyệt. */
function externalLink(value) {
  if (typeof value !== 'string' || value.length > 4096) fail('link');

  let url;

  try {
    url = new URL(value);
  } catch {
    fail('link');
  }

  if (!['http:', 'https:'].includes(url.protocol)) fail('link');

  return url.toString();
}

module.exports = {
  ValidationError,
  LIMITS,
  conversationId,
  optionalConversationId,
  requestId,
  askPayload,
  listQuery,
  title,
  settingsPatch,
  serverUrl,
  externalLink,
};
