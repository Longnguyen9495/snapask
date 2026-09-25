'use strict';

/**
 * Địa chỉ máy chủ SnapAsk.
 *
 * Cố định ở đây chứ không hỏi người dùng: khách cài bản phát hành chỉ cần email
 * và mật khẩu, không việc gì phải biết máy chủ nằm ở đâu. Khi phát hành, sửa
 * hằng số này rồi build lại.
 *
 * Vẫn đổi được mà không cần build lại, theo thứ tự ưu tiên:
 *   1. biến môi trường SNAPASK_SERVER_URL  (tiện nhất lúc phát triển)
 *   2. khoá "serverUrl" trong snapask.json ở thư mục userData
 *   3. hằng số dưới đây
 */
const DEFAULT_SERVER_URL = 'http://snapask.local';
const LEGACY_SERVER_URLS = new Set([
  'http://localhost:8000',
  'http://127.0.0.1:8000',
]);

/**
 * Phím tắt chụp màn hình.
 *
 * Cố định để mọi máy dùng chung một thao tác, đúng như các phần mềm chat quen
 * thuộc. Chỉ đổi được bằng biến môi trường, dành cho lúc phát triển khi tổ hợp
 * này đã bị phần mềm khác chiếm.
 *
 * Trên macOS phải là tổ hợp khác: `Ctrl+Alt+W` nằm sai tay trên bàn phím Mac,
 * còn `Cmd+Shift+3/4/5` thì hệ điều hành đã giữ cho công cụ chụp của nó. Còn
 * lại `Cmd+Shift+2`, cùng họ phím mà người dùng Mac vốn đã quen.
 */
const DEFAULT_HOTKEY = process.platform === 'darwin' ? 'Cmd+Shift+2' : 'Ctrl+Alt+W';

module.exports = {
  serverUrl: () => process.env.SNAPASK_SERVER_URL || DEFAULT_SERVER_URL,
  normalizeServerUrl: (url) => LEGACY_SERVER_URLS.has(url) ? DEFAULT_SERVER_URL : url,
  hotkey: () => process.env.SNAPASK_HOTKEY || DEFAULT_HOTKEY,
};
