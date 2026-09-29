'use strict';

const { app, dialog, Notification } = require('electron');
const { autoUpdater } = require('electron-updater');
const path = require('node:path');

const config = require('./config');
const i18n = require('../i18n');

const t = i18n.t;
const iconFile = () => path.join(__dirname, '..', 'assets', 'icon.png');
const feedUrl = () => `${config.serverUrl().replace(/\/+$/, '')}/updates`;

/**
 * Trạng thái cập nhật, để cửa sổ Cài đặt hiện đúng đang ở bước nào.
 *
 * status: 'unsupported' (bản chạy từ mã nguồn) | 'idle' | 'checking' |
 *         'available' | 'downloading' | 'downloaded' | 'current' | 'error'
 */
let state = { status: app.isPackaged ? 'idle' : 'unsupported', version: null, progress: null, checkedAt: null };
const listeners = new Set();

function setState(patch) {
  state = { ...state, ...patch };

  for (const listener of listeners) listener(state);
}

/** Đăng ký nghe thay đổi; trả về hàm huỷ đăng ký. */
function subscribe(listener) {
  listeners.add(listener);

  return () => listeners.delete(listener);
}

const getState = () => ({ ...state, current: app.getVersion() });

/**
 * Cập nhật chỉ chạy trong bản đã đóng gói. electron-updater tự xác minh chữ ký
 * của gói khi nhà phát hành đã cấu hình chứng thư ký mã cho Windows/macOS.
 */
/**
 * App thường nằm dưới khay nhiều ngày liền, nên chỉ kiểm tra lúc khởi động là
 * không đủ: cứ chừng này lại hỏi máy chủ một lần.
 */
const CHECK_INTERVAL_MS = 4 * 60 * 60 * 1000;

/** Lượt kiểm tra đang chạy do người dùng bấm, không phải lượt định kỳ. */
let manual = false;

/** Thông báo hệ thống; bấm vào thì chạy `onClick`. Máy không hỗ trợ thì bỏ qua. */
function toast(title, body, onClick) {
  if (!Notification.isSupported()) return;

  const notification = new Notification({ title, body, icon: iconFile() });
  if (onClick) notification.on('click', onClick);
  notification.show();
}

function toastDownloading() {
  toast(
    t('Downloading SnapAsk :version…', { version: state.version }),
    t('The update downloads in the background. We will let you know when it is ready.'),
  );
}

function configure() {
  if (!app.isPackaged) return;

  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;
  autoUpdater.allowDowngrade = false;
  autoUpdater.setFeedURL({ provider: 'generic', url: feedUrl() });

  autoUpdater.on('checking-for-update', () => setState({ status: 'checking' }));

  autoUpdater.on('update-not-available', () => setState({ status: 'current', checkedAt: Date.now() }));

  autoUpdater.on('update-available', (info) => {
    setState({ status: 'available', version: info.version, checkedAt: Date.now() });

    // Người vừa bấm kiểm tra cần biết là có bản mới và đang tải, bằng không
    // bấm xong chẳng thấy gì cho tới khi hơn trăm MB tải xong.
    if (manual) toastDownloading();
  });

  autoUpdater.on('download-progress', (progress) => setState({ status: 'downloading', progress: Math.round(progress.percent) }));

  autoUpdater.on('update-downloaded', async (info) => {
    setState({ status: 'downloaded', version: info.version, progress: 100 });

    // Lượt định kỳ chỉ báo nhẹ bằng thông báo, không bật hộp thoại chặn ngang
    // giữa lúc người ta đang làm việc. Không bấm thì lần thoát app sẽ tự cài.
    if (!manual) {
      toast(
        t('SnapAsk :version is ready to install.', { version: info.version }),
        t('Click to restart and install now. Otherwise it installs the next time SnapAsk quits.'),
        install,
      );

      return;
    }

    manual = false;
    await promptInstall();
  });

  autoUpdater.on('error', (error) => {
    console.error('SnapAsk auto-update failed:', error?.message);
    setState({ status: 'error', checkedAt: Date.now() });
    manual = false;
  });

  setInterval(() => check(), CHECK_INTERVAL_MS);
}

/** Hỏi có khởi động lại để cài bản đã tải xong không. */
async function promptInstall() {
  const { response } = await dialog.showMessageBox({
    type: 'info',
    title: 'SnapAsk',
    message: t('SnapAsk :version is ready to install.', { version: state.version }),
    detail: t('Restart SnapAsk now to finish installing the update. Your account and settings will be kept.'),
    buttons: [t('Restart and install'), t('Later')],
    defaultId: 0,
    cancelId: 1,
  });

  if (response === 0) install();
}

async function check({ notify = false } = {}) {
  if (!app.isPackaged) return getState();

  // Đã tải xong từ lượt định kỳ: người bấm kiểm tra được hỏi cài luôn.
  if (state.status === 'downloaded') {
    if (notify) await promptInstall();

    return getState();
  }

  // Đang tải dở: bấm kiểm tra thì nhận lượt đó, tải xong sẽ hỏi cài.
  if (state.status === 'checking' || state.status === 'available' || state.status === 'downloading') {
    if (notify && !manual) {
      manual = true;
      // Lúc còn `checking` thì chưa biết phiên bản; `update-available` sẽ báo.
      if (state.status !== 'checking') toastDownloading();
    }

    return getState();
  }

  manual = notify;

  try {
    await autoUpdater.checkForUpdates();

    if (state.status === 'current') manual = false;

    if (notify && state.status === 'current') {
      await dialog.showMessageBox({ type: 'info', title: 'SnapAsk', message: t('SnapAsk is up to date.') });
    }
  } catch (error) {
    console.error('SnapAsk update check failed:', error?.message);
    setState({ status: 'error', checkedAt: Date.now() });
    manual = false;

    if (notify) {
      await dialog.showMessageBox({
        type: 'warning',
        title: 'SnapAsk',
        message: t('Could not check for updates.'),
        detail: t('Check your internet connection and try again later.'),
      });
    }
  }

  return getState();
}

/** Khởi động lại để cài bản đã tải. Chỉ có tác dụng khi đã tải xong. */
function install() {
  if (state.status !== 'downloaded') return false;

  // Đặt cờ để trình xử lý đóng cửa sổ không giữ cửa sổ lại dưới khay.
  app.isQuitting = true;
  // Cài im lặng: không hiện trình hướng dẫn cài đặt, cài xong tự mở lại app
  // ở đúng thư mục cũ.
  autoUpdater.quitAndInstall(true, true);

  return true;
}

module.exports = { configure, check, install, subscribe, getState };
