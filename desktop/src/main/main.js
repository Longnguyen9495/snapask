'use strict';

const { app, BrowserWindow, Tray, Menu, globalShortcut, ipcMain, shell, screen, nativeImage, clipboard, ClipboardItem, dialog, systemPreferences } = require('electron');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const config = require('./config');
const store = require('./store');
const capture = require('./capture');
const api = require('./api');
const i18n = require('../i18n');

const t = i18n.t;

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

/**
 * Quyền ghi màn hình trên macOS.
 *
 * Chưa được cấp thì `desktopCapturer` vẫn chạy nhưng trả về ảnh đen, nên phải
 * chặn trước và chỉ đường, bằng không người dùng ngồi nhìn một khung đen mà
 * không hiểu mình làm sai ở đâu.
 *
 * macOS chỉ đọc lại danh sách quyền lúc tiến trình khởi động, nên cấp xong phải
 * mở lại ứng dụng — câu hướng dẫn nói thẳng điều đó.
 */
async function ensureScreenAccess() {
  if (process.platform !== 'darwin') return true;

  if (systemPreferences.getMediaAccessStatus('screen') === 'granted') return true;

  const { response } = await dialog.showMessageBox({
    type: 'info',
    title: 'SnapAsk',
    message: t('Screen recording permission is needed'),
    detail: t('SnapAsk needs screen recording permission to capture the region you select. Open System Settings › Privacy & Security › Screen Recording, tick SnapAsk, then start the app again.'),
    buttons: [t('Open System Settings'), t('Hide')],
    defaultId: 0,
    cancelId: 1,
  });

  if (response === 0) {
    shell.openExternal('x-apple.systempreferences:com.apple.preference.security?Privacy_ScreenCapture');
  }

  return false;
}

async function startCapture() {
  if (capture.isOpen()) return;

  if (!store.getToken()) {
    openAuthWindow();
    return;
  }

  if (!await ensureScreenAccess()) return;

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
      message: t('Could not register the hotkey :hotkey.', { hotkey }),
      detail: t('Another program is holding this key combination. Close it, then start SnapAsk again.'),
    });
  }

  return ok;
}

/**
 * Icon cho khay hệ thống.
 *
 * macOS cần ảnh template đơn sắc để tự tô lại theo thanh menu sáng hay tối;
 * Windows và Linux thì dùng icon màu như thường.
 */
function trayIcon() {
  if (process.platform !== 'darwin') {
    return nativeImage.createFromPath(assetFile('tray.png'));
  }

  const image = nativeImage.createFromPath(assetFile('trayTemplate.png'));
  image.setTemplateImage(true);

  return image;
}

function buildTray() {
  // Windows từ chối tạo khay hệ thống với ảnh rỗng, nên icon phải là file thật.
  tray = new Tray(trayIcon());
  tray.setToolTip('SnapAsk');
  rebuildTrayMenu();

  tray.on('double-click', () => startCapture());
}

/**
 * Dựng lại menu khay.
 *
 * Phải dựng lại được chứ không dựng một lần như trước: đổi ngôn ngữ thì menu
 * này cũng phải đổi theo, mà Electron không cho sửa nhãn của một menu đã tạo.
 */
function rebuildTrayMenu() {
  if (!tray || tray.isDestroyed()) return;

  tray.setContextMenu(Menu.buildFromTemplate([
    { label: t('Snap and ask (:hotkey)', { hotkey: config.hotkey() }), click: () => startCapture() },
    { type: 'separator' },
    { label: t('Account…'), click: () => openAuthWindow() },
    { label: t('Open the management page'), click: () => shell.openExternal(managementUrl()) },
    { type: 'separator' },
    { label: t('Quit'), click: () => app.quit() },
  ]));
}

/**
 * Ngôn ngữ lúc khởi động, theo thứ tự người dùng nói rõ ý nhất:
 *
 *   1. lựa chọn đã lưu trong snapask.json — họ tự bấm chọn trong app;
 *   2. ngôn ngữ của hệ điều hành — phỏng đoán hợp lý cho lần chạy đầu.
 *
 * `users.locale` từ máy chủ được áp sau, lúc `auth:state` trả về, vì tới lúc đó
 * mới biết người dùng là ai.
 */
function resolveStartupLocale() {
  const saved = store.read().locale;

  if (i18n.supported(saved)) return i18n.setLocale(saved);

  return i18n.setLocale(i18n.normalise(app.getLocale()) ?? 'vi');
}

/**
 * Đổi ngôn ngữ và dựng lại những gì đã vẽ bằng ngôn ngữ cũ.
 *
 * Menu khay phải dựng lại, còn các cửa sổ tự nghe `app:locale` rồi thay chữ
 * tại chỗ, nên không cửa sổ nào phải tải lại.
 */
function applyLocale(locale, { persist = true } = {}) {
  const applied = i18n.setLocale(locale);

  if (persist) store.write({ locale: applied });

  rebuildTrayMenu();

  for (const win of BrowserWindow.getAllWindows()) {
    if (!win.isDestroyed()) win.webContents.send('app:locale', { locale: applied, dictionary: i18n.dictionary() });
  }

  return applied;
}

app.whenReady().then(() => {
  resolveStartupLocale();

  // Ứng dụng sống ở khay hệ thống, không phải ở Dock: một icon Dock cho thứ
  // không có cửa sổ chính chỉ tổ chiếm chỗ.
  if (process.platform === 'darwin') app.dock?.hide();

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
      title: t('Save the screenshot'),
      defaultPath: path.join(app.getPath('pictures'), `SnapAsk ${stamp}.png`),
      filters: [{ name: t('PNG image'), extensions: ['png'] }],
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
      const state = await api.me();

      /*
       * Ngôn ngữ đã chọn trên trang web theo người dùng về tới đây — nhưng chỉ
       * khi họ chưa tự chọn trong app. Lựa chọn tại chỗ bao giờ cũng thắng, vì
       * đó là cái họ vừa bấm trên chính máy này.
       */
      if (!i18n.supported(store.read().locale) && i18n.supported(state.user?.locale)) {
        applyLocale(state.user.locale, { persist: false });
      }

      return { authenticated: true, ...state };
    } catch (error) {
      if (error.status === 401) store.setToken(null);

      return { authenticated: false, message: error.message };
    }
  });

  ipcMain.handle('app:locale', () => ({ locale: i18n.getLocale(), dictionary: i18n.dictionary() }));

  ipcMain.handle('app:set-locale', async (event, locale) => {
    const applied = applyLocale(locale);

    // Gửi lên máy chủ để cùng một tài khoản mở trên máy khác cũng đúng thứ
    // tiếng. Hỏng thì cũng không sao: lựa chọn đã nằm trong snapask.json rồi.
    if (store.getToken()) await api.setLocale(applied).catch(() => {});

    return applied;
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
