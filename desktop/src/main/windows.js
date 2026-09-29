'use strict';

const { BrowserWindow, screen, session, shell } = require('electron');
const path = require('node:path');

const store = require('./store');

const rendererFile = (name) => path.join(__dirname, '..', 'renderer', name);
const preloadFile = (name) => path.join(__dirname, '..', 'preload', name);
const iconFile = () => path.join(__dirname, '..', 'assets', 'icon.png');

/** Nền cửa sổ trùng nền trang, để lúc tải không loé một khung trắng. */
const BACKGROUND = '#121110';

let workspaceWindow = null;
let chatWindow = null;
let authWindow = null;

const alive = (win) => Boolean(win && !win.isDestroyed());

/**
 * Khoá mọi cửa sổ vào đúng trang của nó.
 *
 * Renderer hiển thị nội dung do mô hình sinh ra; lỡ có một link lọt qua thì
 * cũng không được mở cửa sổ mới hay điều hướng khỏi file cục bộ — mọi thứ
 * muốn ra ngoài phải đi qua IPC `external:*` có danh sách cho phép.
 */
function harden(win) {
  win.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));

  win.webContents.on('will-navigate', (event, url) => {
    if (!url.startsWith('file://')) event.preventDefault();
  });

  win.webContents.on('will-attach-webview', (event) => event.preventDefault());
}

const webPreferences = (preload) => ({
  preload: preloadFile(preload),
  contextIsolation: true,
  nodeIntegration: false,
  sandbox: true,
  spellcheck: false,
});

/**
 * Cửa sổ chính.
 *
 * Tạo một lần rồi ẩn/hiện, không tạo lại mỗi lần mở: giữ nguyên lịch sử đang
 * xem, bản nháp đang gõ và vị trí cuộn.
 *
 * @param {{ onClose: (event: Electron.Event) => void, onDestroyed: (id: number) => void }} hooks
 */
function createWorkspace(hooks) {
  if (alive(workspaceWindow)) return workspaceWindow;

  const area = screen.getPrimaryDisplay().workAreaSize;

  workspaceWindow = new BrowserWindow({
    width: Math.min(1180, area.width),
    height: Math.min(760, area.height),
    minWidth: 860,
    minHeight: 600,
    show: false,
    title: 'SnapAsk',
    icon: iconFile(),
    backgroundColor: BACKGROUND,
    autoHideMenuBar: true,
    webPreferences: webPreferences('workspace.js'),
  });

  harden(workspaceWindow);
  workspaceWindow.setMenuBarVisibility(false);
  workspaceWindow.loadFile(rendererFile('workspace.html'));

  const id = workspaceWindow.webContents.id;

  workspaceWindow.on('close', (event) => hooks.onClose(event));
  workspaceWindow.on('closed', () => {
    workspaceWindow = null;
    hooks.onDestroyed(id);
  });

  return workspaceWindow;
}

/** Hiện và đưa cửa sổ chính lên trước, tạo mới nếu chưa có. */
function showWorkspace(hooks) {
  const win = createWorkspace(hooks);

  const reveal = () => {
    if (win.isMinimized()) win.restore();
    win.show();
    win.focus();
  };

  if (win.webContents.isLoading()) win.once('ready-to-show', reveal);
  else reveal();

  return win;
}

function hideWorkspace() {
  if (alive(workspaceWindow)) workspaceWindow.hide();
}

/**
 * Ô chat nhỏ cạnh vùng vừa chụp, cho luồng chụp nhanh bằng phím tắt.
 *
 * @param {{ onDestroyed: (id: number) => void }} hooks
 */
function createChat(hooks) {
  if (alive(chatWindow)) return chatWindow;

  chatWindow = new BrowserWindow({
    width: 460,
    height: 620,
    icon: iconFile(),
    minWidth: 360,
    minHeight: 420,
    show: false,
    frame: false,
    resizable: true,
    skipTaskbar: true,
    alwaysOnTop: true,
    backgroundColor: '#100f0e',
    webPreferences: webPreferences('chat.js'),
  });

  harden(chatWindow);
  chatWindow.loadFile(rendererFile('chat.html'));

  const id = chatWindow.webContents.id;

  chatWindow.on('closed', () => {
    chatWindow = null;
    hooks.onDestroyed(id);
  });

  return chatWindow;
}

/**
 * Đặt ô chat cạnh vùng vừa chụp, nhưng luôn nằm trọn trong màn hình chứa nó.
 *
 * Toạ độ vùng chụp là điểm ảnh logic (DIP) của hệ màn hình ảo, nên dùng được
 * thẳng cho setPosition dù các màn hình khác tỉ lệ phóng.
 */
function placeChatNear(win, rect) {
  const [width, height] = win.getSize();
  const area = screen.getDisplayNearestPoint({ x: rect.x, y: rect.y }).workArea;

  const right = rect.x + rect.width + 12;
  const x = right + width <= area.x + area.width ? right : Math.max(area.x, rect.x - width - 12);
  const y = Math.min(Math.max(area.y, rect.y), area.y + area.height - height);

  win.setPosition(Math.round(x), Math.round(y));
}

function hideChat() {
  if (alive(chatWindow)) chatWindow.hide();
}

/**
 * Cửa sổ đăng nhập / đăng ký.
 *
 * @param {{ onDestroyed: (id: number) => void }} hooks
 */
function showAuth(hooks) {
  if (alive(authWindow)) {
    authWindow.show();
    authWindow.focus();

    return authWindow;
  }

  authWindow = new BrowserWindow({
    width: 420,
    height: 780,
    icon: iconFile(),
    // Danh sách dịch vụ dài ra theo số connector khách nối, nên cửa sổ phải co
    // giãn được thay vì khoá cứng một kích thước.
    resizable: true,
    minWidth: 380,
    minHeight: 560,
    title: 'SnapAsk',
    backgroundColor: '#100f0e',
    webPreferences: webPreferences('chat.js'),
  });

  harden(authWindow);
  authWindow.setMenuBarVisibility(false);
  authWindow.loadFile(rendererFile('login.html'));

  const id = authWindow.webContents.id;

  authWindow.on('closed', () => {
    authWindow = null;
    hooks.onDestroyed(id);
  });

  return authWindow;
}

function closeAuth() {
  if (alive(authWindow)) authWindow.close();
}

/** Gửi một sự kiện tới mọi cửa sổ còn sống. */
function broadcast(channel, payload) {
  for (const win of BrowserWindow.getAllWindows()) {
    if (!win.isDestroyed()) win.webContents.send(channel, payload);
  }
}

/** Gửi tới cửa sổ chính, nếu đang có. */
function sendToWorkspace(channel, payload) {
  if (alive(workspaceWindow) && !workspaceWindow.webContents.isLoading()) {
    workspaceWindow.webContents.send(channel, payload);

    return true;
  }

  if (alive(workspaceWindow)) {
    workspaceWindow.webContents.once('did-finish-load', () => workspaceWindow?.webContents.send(channel, payload));

    return true;
  }

  return false;
}

/** Cửa sổ của một sender IPC có phải là cửa sổ chính không. */
const isWorkspaceSender = (sender) => alive(workspaceWindow) && sender.id === workspaceWindow.webContents.id;
const isChatSender = (sender) => alive(chatWindow) && sender.id === chatWindow.webContents.id;
const isAuthSender = (sender) => alive(authWindow) && sender.id === authWindow.webContents.id;

const serverOrigin = () => new URL(store.read().serverUrl).origin;

/** Mở một trang của máy chủ SnapAsk trong trình duyệt. */
function openServerPage(page) {
  const url = new URL(page.replace(/^\/+/, ''), `${store.read().serverUrl.replace(/\/+$/, '')}/`);

  return shell.openExternal(url.toString());
}

/**
 * Phiên riêng cho cửa sổ quản lý: cookie web không lẫn với phần còn lại của
 * app, và đăng xuất thì xoá sạch được mà không đụng tới gì khác.
 */
const PORTAL_PARTITION = 'persist:snapask-portal';
const portalSession = () => session.fromPartition(PORTAL_PARTITION);

let portalWindow = null;

/**
 * Trang quản lý của máy chủ, mở ngay trong app.
 *
 * Đây là nội dung web thật chứ không phải file cục bộ, nên khoá chặt hơn các
 * cửa sổ khác: không preload, không quyền hệ thống, chỉ được đi lại trong đúng
 * máy chủ SnapAsk. Link ra ngoài thì đẩy sang trình duyệt.
 *
 * @param {string} url  Link đăng nhập dùng một lần từ `api.webSessionUrl`.
 */
function openPortal(url) {
  if (alive(portalWindow)) {
    portalWindow.loadURL(url);
    if (portalWindow.isMinimized()) portalWindow.restore();
    portalWindow.show();
    portalWindow.focus();

    return portalWindow;
  }

  const area = screen.getPrimaryDisplay().workAreaSize;

  portalWindow = new BrowserWindow({
    width: Math.min(1240, area.width),
    height: Math.min(820, area.height),
    minWidth: 760,
    minHeight: 560,
    show: false,
    title: 'SnapAsk',
    icon: iconFile(),
    backgroundColor: BACKGROUND,
    autoHideMenuBar: true,
    webPreferences: {
      partition: PORTAL_PARTITION,
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      spellcheck: false,
    },
  });

  portalWindow.setMenuBarVisibility(false);
  portalSession().setPermissionRequestHandler((webContents, permission, callback) => callback(false));

  const contents = portalWindow.webContents;
  const inside = (target) => {
    try {
      return new URL(target).origin === serverOrigin();
    } catch {
      return false;
    }
  };
  const openOutside = (target) => {
    if (/^https?:\/\//i.test(target)) shell.openExternal(target);
  };

  contents.setWindowOpenHandler(({ url: target }) => {
    // Link `target="_blank"` trong chính trang quản lý thì mở ngay tại cửa sổ này.
    if (inside(target)) contents.loadURL(target);
    else openOutside(target);

    return { action: 'deny' };
  });

  const guard = (event, target) => {
    if (inside(target)) return;

    event.preventDefault();
    openOutside(target);
  };
  contents.on('will-navigate', guard);
  contents.on('will-redirect', guard);
  contents.on('will-attach-webview', (event) => event.preventDefault());

  // Tiêu đề cửa sổ theo trang đang xem, như một cửa sổ bình thường của app.
  contents.on('page-title-updated', (event, title) => {
    event.preventDefault();
    portalWindow?.setTitle(title ? `${title} · SnapAsk` : 'SnapAsk');
  });

  // Rơi về trang đăng nhập web (bấm Đăng xuất, hoặc phiên web hết hạn) thì cửa
  // sổ này hết việc: đóng lại, lần sau mở từ app sẽ tự đăng nhập lại.
  contents.on('did-navigate', (event, target) => {
    if (inside(target) && /^\/(login|register)\/?$/.test(new URL(target).pathname)) portalWindow?.close();
  });

  portalWindow.once('ready-to-show', () => portalWindow?.show());
  portalWindow.on('closed', () => { portalWindow = null; });

  portalWindow.loadURL(url);

  return portalWindow;
}

/** Đăng xuất khỏi app: đóng cửa sổ quản lý và bỏ luôn phiên web của nó. */
async function closePortal() {
  if (alive(portalWindow)) portalWindow.destroy();

  await portalSession().clearStorageData().catch(() => {});
}

module.exports = {
  createWorkspace,
  showWorkspace,
  hideWorkspace,
  createChat,
  placeChatNear,
  hideChat,
  showAuth,
  closeAuth,
  broadcast,
  sendToWorkspace,
  isWorkspaceSender,
  isChatSender,
  isAuthSender,
  openServerPage,
  openPortal,
  closePortal,
  workspace: () => (alive(workspaceWindow) ? workspaceWindow : null),
  chat: () => (alive(chatWindow) ? chatWindow : null),
  auth: () => (alive(authWindow) ? authWindow : null),
  alive,
};
