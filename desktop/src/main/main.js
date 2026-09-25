'use strict';

const { app, BrowserWindow, Tray, Menu, globalShortcut, ipcMain, shell, nativeImage, clipboard, ClipboardItem, dialog, systemPreferences } = require('electron');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const config = require('./config');
const store = require('./store');
const capture = require('./capture');
const api = require('./api');
const updater = require('./updater');
const windows = require('./windows');
const validate = require('./validate');
const apiErrors = require('./api-errors');
const { StreamRegistry } = require('./streams');
const i18n = require('../i18n');

const t = i18n.t;

let tray = null;

/** Các lượt hỏi đang chạy, theo cửa sổ và mã lượt — xem streams.js. */
const streams = new StreamRegistry();

const assetFile = (name) => path.join(__dirname, '..', 'assets', name);
const deviceName = () => `SnapAsk · ${os.hostname()}`;
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Mở từ mục "khởi động cùng hệ điều hành": chỉ nằm ở khay, không bật cửa sổ. */
const launchedHidden = process.argv.includes('--hidden') || app.getLoginItemSettings().wasOpenedAsHidden;

// Chỉ cho chạy một bản. Bản thứ hai sẽ giành mất phím tắt toàn cục của bản đầu
// và người dùng thấy như phần mềm hỏng.
if (!app.requestSingleInstanceLock()) {
  app.quit();
}

/* ============================================================
 * Cửa sổ và phiên đăng nhập
 * ============================================================ */

const workspaceHooks = {
  /**
   * Bấm nút đóng: mặc định chỉ ẩn xuống khay, vì phím tắt chụp vẫn phải chạy.
   * Người dùng chọn "Thoát" trong Cài đặt thì đóng là thoát hẳn.
   */
  onClose(event) {
    if (app.isQuitting) return;

    if (store.read().closeBehavior === 'tray') {
      event.preventDefault();
      windows.hideWorkspace();

      return;
    }

    app.isQuitting = true;
    app.quit();
  },
  onDestroyed: (id) => streams.cancelOwner(id),
};

const chatHooks = { onDestroyed: (id) => streams.cancelOwner(id) };
const authHooks = { onDestroyed: () => {} };

const showWorkspace = () => windows.showWorkspace(workspaceHooks);
const showAuth = () => windows.showAuth(authHooks);

/** Mở đúng cửa sổ theo trạng thái đăng nhập: có token thì workspace, chưa thì đăng nhập. */
function openForSession() {
  if (store.getToken()) showWorkspace();
  else showAuth();
}

/**
 * Kết thúc phiên: đăng xuất hoặc token hết hạn.
 *
 * Huỷ mọi lượt đang chạy, cất các cửa sổ cần đăng nhập đi rồi mở cửa sổ đăng
 * nhập. Chỉ gọi khi máy chủ xác nhận lỗi xác thực — mất mạng thì không, vì xoá
 * token lúc đó là bắt người dùng đăng nhập lại vô cớ.
 */
function endSession({ reason }) {
  streams.cancelAll();

  if (reason === 'expired') store.setToken(null);

  windows.hideWorkspace();
  windows.hideChat();
  windows.broadcast('session:changed', { authenticated: false, reason });

  const auth = showAuth();
  const notice = reason === 'expired' ? t('Your session has ended. Sign in again.') : null;

  if (notice) {
    const send = () => auth.webContents.send('auth:notice', { message: notice });

    if (auth.webContents.isLoading()) auth.webContents.once('did-finish-load', send);
    else send();
  }

  rebuildTrayMenu();
}

/** Đăng nhập xong: đóng cửa sổ đăng nhập, mở workspace và báo mọi cửa sổ. */
function beginSession() {
  windows.closeAuth();
  showWorkspace();
  windows.broadcast('session:changed', { authenticated: true });
  rebuildTrayMenu();
}

/** Lỗi xác thực từ bất kỳ lời gọi nào đều dẫn về đây. */
function handleApiError(error) {
  if (error?.code === 'unauthorized') endSession({ reason: 'expired' });

  return apiErrors.toIpc(error, t);
}

/* ============================================================
 * Chụp màn hình
 * ============================================================ */

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

/**
 * Chụp một vùng màn hình.
 *
 * `origin` cho biết ai gọi: nút trong workspace thì ảnh quay về workspace dưới
 * dạng tệp đính kèm; phím tắt hay khay thì theo cài đặt "Sau khi chụp" — mặc
 * định là ô chat nhỏ cạnh vùng chụp, như trước đây.
 *
 * Mọi cửa sổ của SnapAsk được ẩn trước khi chụp để không lọt vào ảnh.
 *
 * @param {'shortcut'|'tray'|'workspace'} origin
 */
async function startCapture(origin = 'shortcut') {
  if (capture.isOpen()) return;

  if (!store.getToken()) {
    showAuth();

    return;
  }

  const workspace = windows.workspace();
  const workspaceWasShown = Boolean(workspace?.isVisible() && !workspace.isMinimized());
  const destination = origin === 'workspace' ? 'workspace' : store.read().captureDestination;

  const restoreWorkspace = () => {
    if (workspaceWasShown || destination === 'workspace') showWorkspace();
  };

  try {
    if (!await ensureScreenAccess()) {
      if (origin === 'workspace') windows.sendToWorkspace('capture:cancelled', { origin });

      return;
    }

    windows.hideChat();

    if (workspaceWasShown) {
      workspace.hide();
      // Windows mờ dần cửa sổ khi ẩn; chụp ngay thì còn dính bóng mờ trong ảnh.
      await delay(220);
    }

    const selection = await capture.selectRegion();

    if (!selection) {
      restoreWorkspace();
      if (destination === 'workspace') windows.sendToWorkspace('capture:cancelled', { origin });

      return;
    }

    const payload = {
      image: selection.dataUrl,
      width: selection.width,
      height: selection.height,
      bytes: selection.bytes,
      origin,
    };

    if (destination === 'workspace') {
      showWorkspace();
      windows.sendToWorkspace('capture:completed', payload);

      return;
    }

    // Workspace đang mở thì hiện lại phía sau, không giành focus của ô chat.
    if (workspaceWasShown) workspace.showInactive();

    const chat = windows.createChat(chatHooks);
    windows.placeChatNear(chat, selection.screenRect);

    const reveal = () => {
      chat.show();
      chat.focus();
      chat.webContents.send('chat:capture', payload);
    };

    if (chat.webContents.isLoading()) chat.webContents.once('did-finish-load', reveal);
    else reveal();
  } catch (error) {
    capture.cancel();
    console.error('Capture failed:', error?.message);
    restoreWorkspace();

    if (destination === 'workspace') {
      windows.sendToWorkspace('capture:error', { origin, message: t('Could not process the screenshot.') });

      return;
    }

    await dialog.showMessageBox({
      type: 'error',
      title: 'SnapAsk',
      message: t('Could not process the screenshot.'),
      detail: t('An error occurred.'),
      buttons: [t('Close')],
    });
  }
}

function registerHotkey() {
  globalShortcut.unregisterAll();

  const hotkey = config.hotkey();
  const ok = globalShortcut.register(hotkey, () => startCapture('shortcut'));

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

/* ============================================================
 * Khay hệ thống
 * ============================================================ */

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

  tray.on('double-click', () => openForSession());

  // Windows: bấm một lần vào icon cũng mở cửa sổ, như phần lớn ứng dụng khay.
  if (process.platform === 'win32') tray.on('click', () => openForSession());
}

/** Gửi một lệnh tới workspace sau khi đã mở nó lên. */
function workspaceCommand(command, extra = {}) {
  if (!store.getToken()) {
    showAuth();

    return;
  }

  showWorkspace();
  windows.sendToWorkspace('workspace:command', { command, ...extra });
}

/**
 * Dựng lại menu khay.
 *
 * Phải dựng lại được chứ không dựng một lần: đổi ngôn ngữ hay đăng nhập/đăng
 * xuất thì menu cũng phải đổi theo, mà Electron không cho sửa nhãn của một menu
 * đã tạo.
 */
function rebuildTrayMenu() {
  if (!tray || tray.isDestroyed()) return;

  const signedIn = Boolean(store.getToken());

  tray.setContextMenu(Menu.buildFromTemplate([
    signedIn
      ? { label: t('Open SnapAsk'), click: () => showWorkspace() }
      : { label: t('Sign in…'), click: () => showAuth() },
    { label: t('Snap and ask (:hotkey)', { hotkey: config.hotkey() }), click: () => startCapture('tray'), enabled: signedIn },
    { label: t('New question'), click: () => workspaceCommand('new-chat'), enabled: signedIn },
    { type: 'separator' },
    { label: t('Settings…'), click: () => workspaceCommand('open-settings'), enabled: signedIn },
    { label: t('Open the management page'), click: () => windows.openServerPage('dashboard') },
    { label: t('Check for updates…'), click: () => updater.check({ notify: true }) },
    { type: 'separator' },
    { label: t('Quit'), click: () => { app.isQuitting = true; app.quit(); } },
  ]));
}

/* ============================================================
 * Ngôn ngữ và cài đặt
 * ============================================================ */

/**
 * Ngôn ngữ lúc khởi động, theo thứ tự người dùng nói rõ ý nhất:
 *
 *   1. lựa chọn đã lưu trong snapask.json — họ tự bấm chọn trong app;
 *   2. tiếng Việt — mặc định cho lần chạy đầu, không đoán theo hệ điều hành.
 *
 * `users.locale` từ máy chủ được áp sau, lúc biết người dùng là ai.
 */
function resolveStartupLocale() {
  const saved = store.read().locale;

  if (i18n.supported(saved)) return i18n.setLocale(saved);

  return i18n.setLocale('vi');
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
  windows.broadcast('app:locale', { locale: applied, dictionary: i18n.dictionary() });

  return applied;
}

/** Ngôn ngữ trên tài khoản được dùng khi người dùng chưa tự chọn trong app. */
function adoptAccountLocale(state) {
  if (!i18n.supported(store.read().locale) && i18n.supported(state?.user?.locale)) {
    applyLocale(state.user.locale, { persist: false });
  }
}

/** Khởi động cùng hệ điều hành chỉ bật được ở bản đã cài; bản chạy từ mã nguồn sẽ đăng ký nhầm electron.exe. */
const loginItemSupported = () => app.isPackaged && ['win32', 'darwin'].includes(process.platform);

function applyLaunchAtLogin(enabled) {
  if (!loginItemSupported()) return false;

  app.setLoginItemSettings({ openAtLogin: enabled, openAsHidden: true, args: ['--hidden'] });

  return true;
}

/** Cài đặt gửi cho renderer, kèm vài thông tin chỉ đọc của ứng dụng. */
function publicSettings() {
  return {
    ...store.settings(),
    locale: i18n.getLocale(),
    hotkey: config.hotkey(),
    platform: process.platform,
    version: app.getVersion(),
    defaultServerUrl: config.serverUrl(),
    loginItemSupported: loginItemSupported(),
  };
}

/* ============================================================
 * IPC
 * ============================================================ */

/**
 * Đăng ký một kênh IPC trả `{ ok, data }` hoặc `{ ok:false, code, message }`.
 *
 * `from` giới hạn cửa sổ nào được gọi: kênh đọc lịch sử hay đổi cài đặt không
 * được mở cho lớp phủ chụp màn hình hay một trang lạ nào đó.
 */
function handle(channel, from, fn) {
  ipcMain.handle(channel, async (event, ...args) => {
    if (!from.some((check) => check(event.sender))) {
      return { ok: false, code: 'forbidden', message: t('This window is not allowed to do that.') };
    }

    try {
      return { ok: true, data: await fn(event, ...args) };
    } catch (error) {
      if (error instanceof validate.ValidationError) return apiErrors.toIpc(error, t);

      return handleApiError(error);
    }
  });
}

const fromWorkspace = [windows.isWorkspaceSender];
const fromAppWindows = [windows.isWorkspaceSender, windows.isChatSender, windows.isAuthSender];

/** Lớp phủ nào đang mở cho lần chụp này. */
const overlayOf = (sender) => {
  const win = BrowserWindow.fromWebContents(sender);

  return win && win.shot ? win : null;
};

const isImageDataUrl = (value) => typeof value === 'string' && value.length < 64 * 1024 * 1024 && /^data:image\/png;base64,/.test(value);

function registerOverlayIpc() {
  ipcMain.handle('overlay:done', (event, result) => {
    const overlay = overlayOf(event.sender);

    if (!overlay || !isImageDataUrl(result?.dataUrl) || !result?.rect) {
      capture.cancel();

      return { ok: false, message: t('The captured image could not be processed.') };
    }

    try {
      const { display } = overlay.shot;
      const image = capture.shrink(result.dataUrl, store.read().maxImageWidth);

      capture.finish({
        ...image,
        // Toạ độ màn hình thật của vùng vừa chọn, để đặt ô chat ngay cạnh nó.
        screenRect: {
          x: display.bounds.x + Number(result.rect.x || 0),
          y: display.bounds.y + Number(result.rect.y || 0),
          width: Number(result.rect.width || 0),
          height: Number(result.rect.height || 0),
        },
      });

      return { ok: true };
    } catch (error) {
      console.error('Could not process overlay result:', error?.message);

      return { ok: false, message: t('Could not process the screenshot.') };
    }
  });

  ipcMain.handle('overlay:cancel', (event) => {
    if (overlayOf(event.sender)) capture.cancel();
  });

  /*
   * Electron 44 thay clipboard cũ bằng API kiểu web: bất đồng bộ và nhận một
   * mảng ClipboardItem. clipboard.writeImage() không còn tồn tại.
   */
  ipcMain.handle('overlay:copy', async (event, dataUrl) => {
    if (!overlayOf(event.sender) || !isImageDataUrl(dataUrl)) return false;

    const png = nativeImage.createFromDataURL(dataUrl).toPNG();

    await clipboard.write([
      new ClipboardItem({ 'image/png': new Blob([png], { type: 'image/png' }) }),
    ]);

    return true;
  });

  ipcMain.handle('overlay:save', async (event, dataUrl) => {
    const overlay = overlayOf(event.sender);

    if (!overlay || !isImageDataUrl(dataUrl)) return false;

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
}

/**
 * Kênh của cửa sổ đăng nhập và ô chat nhỏ — giữ nguyên tên và hình dạng trả về
 * như 0.2.x để login.js và app.js không phải đổi theo.
 */
function registerLegacyIpc() {
  const allowed = (event) => fromAppWindows.some((check) => check(event.sender));

  // Lỗi ném qua ipcMain tới renderer bị bọc thành "Error invoking remote method…",
  // nên trả kết quả tường minh để cửa sổ đăng nhập hiện đúng câu của máy chủ.
  ipcMain.handle('auth:login', async (event, credentials) => {
    if (!allowed(event)) return { ok: false, message: t('This window is not allowed to do that.') };

    const email = typeof credentials?.email === 'string' ? credentials.email.trim().slice(0, 255) : '';
    const password = typeof credentials?.password === 'string' ? credentials.password.slice(0, 1024) : '';

    try {
      const user = await api.login(email, password, deviceName());

      beginSession();

      return { ok: true, user };
    } catch (error) {
      return { ok: false, message: error.message };
    }
  });

  ipcMain.handle('auth:register', async (event, details) => {
    if (!allowed(event)) return { ok: false, message: t('This window is not allowed to do that.') };

    const text = (value, max) => (typeof value === 'string' ? value.trim().slice(0, max) : '');

    try {
      const { user, verificationRequired, message } = await api.register(
        text(details?.name, 255),
        text(details?.email, 255),
        typeof details?.password === 'string' ? details.password.slice(0, 1024) : '',
        deviceName(),
      );

      // Giữ cửa sổ mở: người dùng còn phải quay lại đây đăng nhập sau khi bấm
      // link trong email.
      if (verificationRequired) return { ok: true, pending: true, message, user };

      beginSession();

      return { ok: true, user };
    } catch (error) {
      return { ok: false, message: error.message };
    }
  });

  ipcMain.handle('auth:logout', async (event) => {
    if (!allowed(event)) return false;

    streams.cancelAll();
    await api.logout();
    endSession({ reason: 'logout' });

    return true;
  });

  ipcMain.handle('auth:state', async (event) => {
    if (!allowed(event)) return { authenticated: false };
    if (!store.getToken()) return { authenticated: false };

    try {
      const state = await api.me();

      adoptAccountLocale(state);

      return { authenticated: true, ...state };
    } catch (error) {
      if (error.code === 'unauthorized') store.setToken(null);

      return { authenticated: false, offline: error.code === 'network', message: error.message };
    }
  });

  ipcMain.handle('app:locale', () => ({ locale: i18n.getLocale(), dictionary: i18n.dictionary() }));

  ipcMain.handle('app:set-locale', async (event, locale) => {
    if (!i18n.supported(locale)) return i18n.getLocale();

    const applied = applyLocale(locale);

    // Gửi lên máy chủ để cùng một tài khoản mở trên máy khác cũng đúng thứ
    // tiếng. Hỏng thì cũng không sao: lựa chọn đã nằm trong snapask.json rồi.
    if (store.getToken()) await api.setLocale(applied).catch(() => {});

    return applied;
  });

  // Trang quản lý của máy chủ: tổng quan, mô hình AI và dịch vụ kết nối.
  ipcMain.handle('app:open-management', (event) => {
    if (allowed(event)) windows.openServerPage('dashboard');
  });

  // Lịch sử giờ nằm ngay trong workspace; nút cũ của ô chat nhỏ mở nó thay vì trình duyệt.
  ipcMain.handle('app:open-history', (event) => {
    if (allowed(event)) workspaceCommand('show');
  });

  ipcMain.handle('settings:read', (event) => (allowed(event) ? store.settings() : {}));

  ipcMain.handle('settings:write', (event, patch) => {
    if (!allowed(event)) return store.settings();

    try {
      store.write(validate.settingsPatch(patch));
    } catch {
      // Bản vá sai kiểu bị bỏ qua, giống trình xử lý mới ở settings:update.
    }

    return store.settings();
  });

  /*
   * Ô chat nhỏ: mỗi cửa sổ chỉ có một lượt tại một thời điểm, hỏi câu mới thì
   * lượt cũ của chính nó bị huỷ — nhưng không đụng tới lượt đang chạy trong
   * workspace, vì hai bên nằm ở hai khoá khác nhau trong sổ.
   */
  ipcMain.handle('chat:ask', async (event, payload) => {
    if (!windows.isChatSender(event.sender)) return { ok: false, message: t('An error occurred.') };

    let input;

    try {
      input = validate.askPayload({ ...payload, requestId: 'compact', image: payload?.imageDataUrl });
    } catch {
      return { ok: false, message: t('The question is empty or too long.') };
    }

    const sender = event.sender;
    const controller = streams.start(sender.id, 'compact');

    api.ask(input, (chunk) => {
      if (!sender.isDestroyed()) sender.send('chat:event', chunk);

      if (chunk.type === 'done' && chunk.conversation_id) {
        windows.sendToWorkspace('conversation:changed', { id: chunk.conversation_id, source: 'compact' });
      }

      if (chunk.type === 'error' && chunk.code === 'unauthorized') endSession({ reason: 'expired' });
    }, controller.signal).finally(() => streams.finish(sender.id, 'compact', controller));

    return { ok: true };
  });

  ipcMain.handle('chat:stop', (event) => {
    streams.cancelOwner(event.sender.id);

    return true;
  });

  ipcMain.handle('chat:hide', (event) => {
    if (windows.isChatSender(event.sender)) windows.hideChat();
  });

  ipcMain.handle('chat:recapture', (event) => {
    if (windows.isChatSender(event.sender)) startCapture('shortcut');
  });

  // Mở hội thoại đang xem ở ô chat nhỏ trong workspace, để hỏi tiếp với đủ lịch sử.
  ipcMain.handle('chat:open-in-workspace', (event, id) => {
    if (!windows.isChatSender(event.sender)) return false;

    let conversationId = null;

    try {
      conversationId = validate.optionalConversationId(id);
    } catch {
      conversationId = null;
    }

    windows.hideChat();
    workspaceCommand(conversationId ? 'open-conversation' : 'show', conversationId ? { id: conversationId } : {});

    return true;
  });
}

/** Kênh của workspace, theo hợp đồng trong PLAN_DESKTOP_WORKSPACE.md mục 7. */
function registerWorkspaceIpc() {
  /* ---------- workspace ---------- */

  handle('workspace:ready', fromWorkspace, async () => {
    const base = {
      settings: publicSettings(),
      updates: updater.getState(),
    };

    if (!store.getToken()) return { ...base, session: { authenticated: false } };

    try {
      const state = await api.me();

      adoptAccountLocale(state);

      return { ...base, session: { authenticated: true, online: true, ...state } };
    } catch (error) {
      if (error.code === 'unauthorized') {
        endSession({ reason: 'expired' });

        return { ...base, session: { authenticated: false } };
      }

      // Không tới được máy chủ: vẫn mở khung workspace, báo ngoại tuyến, giữ token.
      return { ...base, session: { authenticated: true, online: false, code: error.code, message: error.message } };
    }
  });

  handle('workspace:hide', fromWorkspace, () => windows.hideWorkspace());

  handle('workspace:quit', fromWorkspace, () => {
    app.isQuitting = true;
    app.quit();
  });

  /* ---------- tài khoản ---------- */

  handle('account:refresh', fromWorkspace, async () => {
    const state = await api.me();

    adoptAccountLocale(state);

    return state;
  });

  handle('account:logout', fromWorkspace, async () => {
    streams.cancelAll();
    await api.logout();
    endSession({ reason: 'logout' });

    return true;
  });

  /* ---------- hội thoại ---------- */

  handle('conversations:list', fromWorkspace, (event, query) => api.listConversations(validate.listQuery(query)));

  handle('conversations:show', fromWorkspace, (event, id) => api.showConversation(validate.conversationId(id)));

  handle('conversations:rename', fromWorkspace, async (event, id, title) => {
    const result = await api.renameConversation(validate.conversationId(id), validate.title(title));

    return result.conversation;
  });

  handle('conversations:delete', fromWorkspace, async (event, id) => {
    const conversationId = validate.conversationId(id);

    await api.deleteConversation(conversationId);

    // Ô chat nhỏ có thể đang giữ đúng hội thoại này; báo để nó thôi nối tiếp vào đó.
    windows.broadcast('conversation:deleted', { id: conversationId });

    return { id: conversationId };
  });

  handle('conversations:image', fromWorkspace, (event, id) => api.conversationImage(validate.conversationId(id)));

  /* ---------- hỏi AI ---------- */

  /*
   * Bắt đầu một lượt. Trả về ngay; chữ đến sau qua `ask:event`, mỗi sự kiện kèm
   * requestId để renderer bỏ qua sự kiện của lượt cũ khi đã chuyển hội thoại.
   */
  handle('ask:start', fromWorkspace, (event, payload) => {
    const input = validate.askPayload(payload);
    const sender = event.sender;
    const controller = streams.start(sender.id, input.requestId);
    const started = Date.now();

    api.ask(input, (chunk) => {
      if (!sender.isDestroyed()) sender.send('ask:event', { requestId: input.requestId, ...chunk });

      if (chunk.type === 'error' && chunk.code === 'unauthorized') endSession({ reason: 'expired' });

      // Chỉ ghi mã lượt, trạng thái và thời gian — không ghi câu hỏi hay câu trả lời.
      if (chunk.type === 'done' || chunk.type === 'error') {
        console.info(`ask ${input.requestId} ${chunk.type}${chunk.code ? ` ${chunk.code}` : ''} ${Date.now() - started}ms`);
      }
    }, controller.signal).finally(() => streams.finish(sender.id, input.requestId, controller));

    return { requestId: input.requestId };
  });

  handle('ask:cancel', fromWorkspace, (event, requestId) => streams.cancel(event.sender.id, validate.requestId(requestId)));

  /* ---------- chụp ---------- */

  handle('capture:start', fromWorkspace, () => {
    // Không chờ: kết quả về qua `capture:completed` / `capture:cancelled`.
    startCapture('workspace');

    return true;
  });

  /* ---------- cài đặt ---------- */

  handle('settings:get', fromWorkspace, () => publicSettings());

  handle('settings:update', fromWorkspace, async (event, patch) => {
    const clean = validate.settingsPatch(patch);
    const previousServer = store.read().serverUrl;

    if (clean.locale) {
      applyLocale(clean.locale);
      if (store.getToken()) api.setLocale(clean.locale).catch(() => {});
      delete clean.locale;
    }

    if ('launchAtLogin' in clean) {
      if (!applyLaunchAtLogin(clean.launchAtLogin)) delete clean.launchAtLogin;
    }

    store.write(clean);

    // Token thuộc về máy chủ cũ; đổi máy chủ thì phải đăng nhập lại ở máy chủ mới.
    if (clean.serverUrl && clean.serverUrl !== previousServer) {
      streams.cancelAll();
      store.setToken(null);
      endSession({ reason: 'server-changed' });
    }

    const settings = publicSettings();
    windows.broadcast('settings:changed', settings);

    return settings;
  });

  handle('settings:reset', fromWorkspace, () => {
    const previousServer = store.read().serverUrl;

    store.reset();
    applyLaunchAtLogin(false);

    if (store.read().serverUrl !== previousServer) {
      store.setToken(null);
      endSession({ reason: 'server-changed' });
    }

    const settings = publicSettings();
    windows.broadcast('settings:changed', settings);

    return settings;
  });

  /* ---------- cập nhật ---------- */

  handle('updates:check', fromWorkspace, () => updater.check());
  handle('updates:install', fromWorkspace, () => updater.install());

  /* ---------- mở ra ngoài ---------- */

  // Chỉ mở trang của chính máy chủ SnapAsk, theo đường dẫn cố định ở đây.
  handle('external:open-provider-management', fromWorkspace, () => windows.openServerPage('providers'));
  handle('external:open-dashboard', fromWorkspace, () => windows.openServerPage('dashboard'));
  handle('external:open-account', fromWorkspace, () => windows.openServerPage('account'));
  handle('external:open-conversation', fromWorkspace, (event, id) => windows.openServerPage(`conversations/${validate.conversationId(id)}`));

  // Link trong câu trả lời: chỉ http/https, mở bằng trình duyệt mặc định.
  handle('external:open-link', fromWorkspace, (event, url) => shell.openExternal(validate.externalLink(url)));
}

/* ============================================================
 * Vòng đời ứng dụng
 * ============================================================ */

/**
 * Menu ứng dụng.
 *
 * macOS cần menu Edit thì Cmd+C/V mới chạy trong ô nhập. Windows không cần
 * menu cho việc đó, và bỏ menu mặc định để Ctrl+R không tải lại workspace giữa
 * lúc đang trả lời. Chạy với --dev thì giữ menu mặc định để mở DevTools.
 */
function buildAppMenu() {
  if (process.argv.includes('--dev')) return;

  if (process.platform !== 'darwin') {
    Menu.setApplicationMenu(null);

    return;
  }

  Menu.setApplicationMenu(Menu.buildFromTemplate([
    { role: 'appMenu' },
    { role: 'editMenu' },
    { role: 'windowMenu' },
  ]));
}

app.on('second-instance', () => openForSession());

app.on('activate', () => openForSession());

app.on('before-quit', () => {
  app.isQuitting = true;
  streams.cancelAll();
});

app.whenReady().then(() => {
  resolveStartupLocale();
  buildAppMenu();

  registerOverlayIpc();
  registerLegacyIpc();
  registerWorkspaceIpc();

  buildTray();
  registerHotkey();

  updater.configure();
  updater.subscribe((state) => windows.sendToWorkspace('updates:state', { ...state, current: app.getVersion() }));
  setTimeout(() => updater.check(), 5000);

  if (!launchedHidden) openForSession();
  else if (!store.getToken()) showAuth();
});

// Đây là phần mềm sống ở khay hệ thống: đóng hết cửa sổ không phải là thoát.
// Chỉ cần có mặt một listener là Electron thôi tự thoát; sự kiện này không
// truyền tham số nào nên không được gọi preventDefault() ở đây.
app.on('window-all-closed', () => {});
app.on('will-quit', () => globalShortcut.unregisterAll());
