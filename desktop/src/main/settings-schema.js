'use strict';

/**
 * Hình dạng của snapask.json và cách nâng cấp bản cũ lên.
 *
 * File từ 0.2.2 chỉ có `serverUrl`, `maxImageWidth`, `locale` và token. Bản mới
 * thêm khoá, nên khi đọc phải trộn với mặc định và sửa những giá trị không hợp
 * lệ — nhưng không bao giờ bỏ khoá cũ hay token: người dùng nâng cấp xong vẫn
 * phải còn đăng nhập và còn nguyên lựa chọn của họ.
 *
 * Không phụ thuộc Electron để chạy được bằng `node --test`.
 */

const SETTINGS_VERSION = 2;

const MAX_IMAGE_WIDTHS = [960, 1280, 1600, 1920];

/** @param {string} serverUrl địa chỉ mặc định, do config.js quyết định */
function defaults(serverUrl) {
  return {
    settingsVersion: SETTINGS_VERSION,
    serverUrl,
    maxImageWidth: 1280,
    // Đóng cửa sổ chính thì ẩn xuống khay: phím tắt chụp vẫn phải chạy.
    closeBehavior: 'tray',
    // Phím tắt toàn cục mở ô chat nhỏ cạnh vùng chụp, như trước đây.
    captureDestination: 'compact',
    launchAtLogin: false,
    sidebarCollapsed: false,
  };
}

const oneOf = (value, allowed, fallback) => (allowed.includes(value) ? value : fallback);

/**
 * Trộn dữ liệu đọc từ đĩa với mặc định và sửa giá trị hỏng.
 *
 * @param {unknown} raw nội dung JSON đã parse (có thể là bất cứ thứ gì)
 * @param {string} serverUrl địa chỉ mặc định
 * @param {(url: string) => string} normalizeServerUrl đổi địa chỉ cũ sang mới
 */
function migrate(raw, serverUrl, normalizeServerUrl = (url) => url) {
  const base = defaults(serverUrl);
  const data = raw && typeof raw === 'object' && !Array.isArray(raw) ? raw : {};
  const merged = { ...base, ...data };

  merged.serverUrl = typeof merged.serverUrl === 'string' && merged.serverUrl !== ''
    ? normalizeServerUrl(merged.serverUrl)
    : base.serverUrl;

  // 0.2.2 cho phép bất kỳ số nào; làm tròn về mốc gần nhất trong danh sách mới.
  const width = Number(merged.maxImageWidth);
  merged.maxImageWidth = MAX_IMAGE_WIDTHS.includes(width)
    ? width
    : MAX_IMAGE_WIDTHS.reduce((best, option) => (Math.abs(option - width) < Math.abs(best - width) ? option : best), base.maxImageWidth);

  if (!Number.isFinite(width)) merged.maxImageWidth = base.maxImageWidth;

  merged.closeBehavior = oneOf(merged.closeBehavior, ['tray', 'quit'], base.closeBehavior);
  merged.captureDestination = oneOf(merged.captureDestination, ['compact', 'workspace'], base.captureDestination);
  merged.launchAtLogin = merged.launchAtLogin === true;
  merged.sidebarCollapsed = merged.sidebarCollapsed === true;

  if (merged.locale !== undefined && !['vi', 'en'].includes(merged.locale)) delete merged.locale;

  merged.settingsVersion = SETTINGS_VERSION;

  return merged;
}

/** Các khoá được gửi xuống renderer: không bao giờ kèm token. */
function publicView(settings) {
  const { token, tokenEnc, ...rest } = settings;

  return rest;
}

module.exports = { SETTINGS_VERSION, MAX_IMAGE_WIDTHS, defaults, migrate, publicView };
