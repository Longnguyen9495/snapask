'use strict';

const { app, dialog } = require('electron');
const { autoUpdater } = require('electron-updater');

const config = require('./config');
const i18n = require('../i18n');

const t = i18n.t;
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
function configure() {
  if (!app.isPackaged) return;

  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;
  autoUpdater.allowDowngrade = false;
  autoUpdater.setFeedURL({ provider: 'generic', url: feedUrl() });

  autoUpdater.on('checking-for-update', () => setState({ status: 'checking' }));

  autoUpdater.on('update-not-available', () => setState({ status: 'current', checkedAt: Date.now() }));

  autoUpdater.on('update-available', (info) => setState({ status: 'available', version: info.version, checkedAt: Date.now() }));

  autoUpdater.on('download-progress', (progress) => setState({ status: 'downloading', progress: Math.round(progress.percent) }));

  autoUpdater.on('update-downloaded', async (info) => {
    setState({ status: 'downloaded', version: info.version, progress: 100 });

    const { response } = await dialog.showMessageBox({
      type: 'info',
      title: 'SnapAsk',
      message: t('SnapAsk :version is ready to install.', { version: info.version }),
      detail: t('Restart SnapAsk now to finish installing the update. Your account and settings will be kept.'),
      buttons: [t('Restart and install'), t('Later')],
      defaultId: 0,
      cancelId: 1,
    });

    if (response === 0) install();
  });

  autoUpdater.on('error', (error) => {
    console.error('SnapAsk auto-update failed:', error?.message);
    setState({ status: 'error', checkedAt: Date.now() });
  });
}

async function check({ notify = false } = {}) {
  if (!app.isPackaged || state.status === 'checking' || state.status === 'downloading' || state.status === 'downloaded') {
    return getState();
  }

  try {
    await autoUpdater.checkForUpdates();

    if (notify && state.status === 'current') {
      await dialog.showMessageBox({ type: 'info', title: 'SnapAsk', message: t('SnapAsk is up to date.') });
    }
  } catch (error) {
    console.error('SnapAsk update check failed:', error?.message);
    setState({ status: 'error', checkedAt: Date.now() });

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
  autoUpdater.quitAndInstall(false, true);

  return true;
}

module.exports = { configure, check, install, subscribe, getState };
