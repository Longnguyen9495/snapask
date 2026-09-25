'use strict';

const { BrowserWindow, screen, shell } = require('electron');
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

/** Mở một trang của máy chủ SnapAsk trong trình duyệt. */
function openServerPage(page) {
  const url = new URL(page.replace(/^\/+/, ''), `${store.read().serverUrl.replace(/\/+$/, '')}/`);

  return shell.openExternal(url.toString());
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
  workspace: () => (alive(workspaceWindow) ? workspaceWindow : null),
  chat: () => (alive(chatWindow) ? chatWindow : null),
  auth: () => (alive(authWindow) ? authWindow : null),
  alive,
};
