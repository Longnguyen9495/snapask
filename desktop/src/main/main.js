'use strict';

const { app, BrowserWindow, Tray, Menu, globalShortcut, ipcMain, shell, screen, nativeImage, clipboard, ClipboardItem, dialog } = require('electron');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const config = require('./config');
const store = require('./store');
const capture = require('./capture');
const api = require('./api');

let tray = null;
let chatWindow = null;
let authWindow = null;
let cancelStream = null;

const rendererFile = (name) => path.join(__dirname, '..', 'renderer', name);
const assetFile = (name) => path.join(__dirname, '..', 'assets', name);
const deviceName = () => `SnapAsk · ${os.hostname()}`;
const managementUrl = () => `${store.read().serverUrl.replace(/\/+$/, '')}/providers`;
const preloadFile = (name) => path.join(__dirname, '..', 'preload', name);

// Chỉ cho chạy một bản. Bản thứ hai sẽ giành mất phím tắt toàn cục của bản đầu
// và người dùng thấy như phần mềm hỏng.
if (!app.requestSingleInstanceLock()) {
  app.quit();
}

app.on('second-instance', () => startCapture());

function createChatWindow() {
  if (chatWindow && !chatWindow.isDestroyed()) return chatWindow;

  chatWindow = new BrowserWindow({
    width: 460,
    height: 620,
    icon: assetFile('icon.png'),
    minWidth: 360,
    minHeight: 420,
    show: false,
    frame: false,
    resizable: true,
    skipTaskbar: true,
    alwaysOnTop: true,
    backgroundColor: '#100f0e',
    webPreferences: {
      preload: preloadFile('chat.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  chatWindow.loadFile(rendererFile('chat.html'));
  chatWindow.on('closed', () => {
    chatWindow = null;
  });

  return chatWindow;
}

/**
 * Đặt ô chat cạnh vùng vừa chụp, nhưng luôn nằm trọn trong màn hình chứa nó.
 */
function placeChatNear(rect) {
  const win = createChatWindow();
  const [width, height] = win.getSize();
  const area = screen.getDisplayNearestPoint({ x: rect.x, y: rect.y }).workArea;

  const right = rect.x + rect.width + 12;
  const x = right + width <= area.x + area.width ? right : Math.max(area.x, rect.x - width - 12);
  const y = Math.min(Math.max(area.y, rect.y), area.y + area.height - height);

  win.setPosition(Math.round(x), Math.round(y));
}

async function startCapture() {
  if (capture.isOpen()) return;

  if (!store.getToken()) {
    openAuthWindow();
    return;
  }

  if (chatWindow && !chatWindow.isDestroyed()) chatWindow.hide();

  const selection = await capture.selectRegion();

  if (!selection) return;

  const win = createChatWindow();
  placeChatNear(selection.screenRect);
  win.show();
  win.focus();
  win.webContents.send('chat:capture', {
    image: selection.dataUrl,
    width: selection.width,
    height: selection.height,
    bytes: selection.bytes,
  });
}

function openAuthWindow() {
  if (authWindow && !authWindow.isDestroyed()) {
    authWindow.show();
    authWindow.focus();
    return;
  }

  authWindow = new BrowserWindow({
    width: 420,
    height: 780,
    icon: assetFile('icon.png'),
    // Danh sách dịch vụ dài ra theo số connector khách nối, nên cửa sổ phải co
    // giãn được thay vì khoá cứng một kích thước.
    resizable: true,
    minWidth: 380,
    minHeight: 560,
    title: 'SnapAsk',
    backgroundColor: '#100f0e',
    webPreferences: {
      preload: preloadFile('chat.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  authWindow.setMenuBarVisibility(false);
  authWindow.loadFile(rendererFile('login.html'));
  authWindow.on('closed', () => {
    authWindow = null;
  });
}

function registerHotkey() {
  globalShortcut.unregisterAll();

  const hotkey = config.hotkey();
  const ok = globalShortcut.register(hotkey, () => startCapture());

  if (!ok) {
    dialog.showMessageBox({
      type: 'warning',
      title: 'SnapAsk',
      message: `Không đăng ký được phím tắt ${hotkey}.`,
      detail: 'Một phần mềm khác đang giữ tổ hợp phím này. Hãy tắt phần mềm đó rồi mở lại SnapAsk.',
    });
  }

  return ok;
}

function buildTray() {
  // Windows từ chối tạo khay hệ thống với ảnh rỗng, nên icon phải là file thật.
  tray = new Tray(nativeImage.createFromPath(assetFile('tray.png')));
  tray.setToolTip('SnapAsk');

  // Phím tắt đã cố định nên menu này không bao giờ phải dựng lại.
  tray.setContextMenu(Menu.buildFromTemplate([
    { label: `Chụp và hỏi (${config.hotkey()})`, click: () => startCapture() },
    { type: 'separator' },
    { label: 'Tài khoản…', click: () => openAuthWindow() },
    { label: 'Mở trang quản lý', click: () => shell.openExternal(managementUrl()) },
    { type: 'separator' },
    { label: 'Thoát', click: () => app.quit() },
  ]));

  tray.on('double-click', () => startCapture());
}

app.whenReady().then(() => {
  buildTray();
  registerHotkey();

  if (!store.getToken()) openAuthWindow();

  ipcMain.handle('overlay:done', (event, result) => {
    const overlay = BrowserWindow.fromWebContents(event.sender);

    if (!overlay || !result?.dataUrl) {
      capture.cancel();

      return;
    }

    const { display } = overlay.shot;
    const image = capture.shrink(result.dataUrl, store.read().maxImageWidth);

    capture.finish({
      ...image,
      // Toạ độ màn hình thật của vùng vừa chọn, để đặt ô chat ngay cạnh nó.
      screenRect: {
        x: display.bounds.x + result.rect.x,
        y: display.bounds.y + result.rect.y,
        width: result.rect.width,
        height: result.rect.height,
      },
    });
  });

  ipcMain.handle('overlay:cancel', () => capture.cancel());

  /*
   * Electron 44 thay clipboard cũ bằng API kiểu web: bất đồng bộ và nhận một
   * mảng ClipboardItem. clipboard.writeImage() không còn tồn tại.
   */
  ipcMain.handle('overlay:copy', async (event, dataUrl) => {
    const png = nativeImage.createFromDataURL(dataUrl).toPNG();

    await clipboard.write([
      new ClipboardItem({ 'image/png': new Blob([png], { type: 'image/png' }) }),
    ]);

    return true;
  });

  ipcMain.handle('overlay:save', async (event, dataUrl) => {
    const overlay = BrowserWindow.fromWebContents(event.sender);
    const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');

    // Hộp thoại phải gắn vào chính lớp phủ, nếu không nó hiện phía sau lớp phủ
    // luôn-trên-cùng và người dùng tưởng phần mềm treo.
    const { canceled, filePath } = await dialog.showSaveDialog(overlay, {
      title: 'Lưu ảnh chụp',
      defaultPath: path.join(app.getPath('pictures'), `SnapAsk ${stamp}.png`),
      filters: [{ name: 'Ảnh PNG', extensions: ['png'] }],
    });

    if (canceled || !filePath) return false;

    fs.writeFileSync(filePath, nativeImage.createFromDataURL(dataUrl).toPNG());

    return true;
  });

  // Lỗi ném qua ipcMain tới renderer bị bọc thành "Error invoking remote method…",
  // nên trả kết quả tường minh để cửa sổ đăng nhập hiện đúng câu của máy chủ.
  ipcMain.handle('auth:login', async (event, { email, password }) => {
    try {
      const user = await api.login(email, password, deviceName());

      if (authWindow && !authWindow.isDestroyed()) authWindow.close();

      return { ok: true, user };
    } catch (error) {
      return { ok: false, message: error.message };
    }
  });

  ipcMain.handle('auth:register', async (event, { name, email, password }) => {
    try {
      const user = await api.register(name, email, password, deviceName());

      if (authWindow && !authWindow.isDestroyed()) authWindow.close();

      return { ok: true, user };
    } catch (error) {
      return { ok: false, message: error.message };
    }
  });

  ipcMain.handle('auth:logout', async () => {
    await api.logout();
    return true;
  });

  ipcMain.handle('auth:state', async () => {
    if (!store.getToken()) return { authenticated: false };

    try {
      return { authenticated: true, ...(await api.me()) };
    } catch (error) {
      if (error.status === 401) store.setToken(null);

      return { authenticated: false, message: error.message };
    }
  });

  // Việc khai báo dịch vụ cho AI tra cứu nằm trên trang web của máy chủ, nên
  // ở đây chỉ mở trình duyệt tới đó thay vì dựng lại cả màn hình quản lý.
  ipcMain.handle('app:open-management', () => shell.openExternal(managementUrl()));

  ipcMain.handle('settings:read', () => store.settings());
  ipcMain.handle('settings:write', (event, patch) => {
    store.write(patch);

    return store.settings();
  });

  ipcMain.handle('chat:ask', async (event, payload) => {
    if (cancelStream) cancelStream();

    const sender = event.sender;
    cancelStream = await api.ask(payload, (chunk) => {
      if (!sender.isDestroyed()) sender.send('chat:event', chunk);
    });

    return true;
  });

  ipcMain.handle('chat:stop', () => {
    if (cancelStream) cancelStream();
    cancelStream = null;

    return true;
  });

  ipcMain.handle('chat:hide', () => {
    if (chatWindow && !chatWindow.isDestroyed()) chatWindow.hide();
  });

  ipcMain.handle('chat:recapture', () => startCapture());
});

// Đây là phần mềm sống ở khay hệ thống: đóng hết cửa sổ không phải là thoát.
// Chỉ cần có mặt một listener là Electron thôi tự thoát; sự kiện này không
// truyền tham số nào nên không được gọi preventDefault() ở đây.
app.on('window-all-closed', () => {});
app.on('will-quit', () => globalShortcut.unregisterAll());
