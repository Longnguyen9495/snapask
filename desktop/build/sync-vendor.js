'use strict';

/**
 * Chép thư viện của bên thứ ba từ node_modules vào `src/renderer/vendor/`.
 *
 * Renderer nạp file bằng thẻ <script> chứ không qua bundler, và CSP chỉ cho
 * `script-src 'self'` nên không thể lấy từ CDN. Bản chép phải nằm trong repo để
 * `electron-builder` đóng vào gói.
 *
 * Chạy `node build/sync-vendor.js` sau mỗi lần nâng phiên bản thư viện;
 * `--check` chỉ so sánh và trả mã lỗi, dùng trong `npm run check` và CI.
 */

const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const target = path.join(root, 'src', 'renderer', 'vendor');

/** Mỗi mục: gói npm, file trong dist của nó, và tên khi nằm trong vendor. */
const FILES = [
  { pkg: 'chart.js', from: 'dist/chart.umd.min.js', to: 'chart.umd.min.js' },
];

const check = process.argv.includes('--check');
let failed = 0;

fs.mkdirSync(target, { recursive: true });

for (const file of FILES) {
  const source = path.join(root, 'node_modules', file.pkg, file.from);
  const dest = path.join(target, file.to);

  if (!fs.existsSync(source)) {
    // Thiếu node_modules lúc kiểm tra là chuyện bình thường ở máy chỉ chạy
    // lint; chỉ báo lỗi khi được yêu cầu chép thật.
    if (check && fs.existsSync(dest)) continue;

    console.error(`vendor: không tìm thấy ${file.pkg}/${file.from} — chạy npm install trước.`);
    failed++;

    continue;
  }

  const wanted = fs.readFileSync(source);
  const current = fs.existsSync(dest) ? fs.readFileSync(dest) : null;

  if (current && current.equals(wanted)) continue;

  if (check) {
    console.error(`vendor: ${file.to} đã lệch với ${file.pkg} trong node_modules — chạy node build/sync-vendor.js.`);
    failed++;

    continue;
  }

  fs.writeFileSync(dest, wanted);
  console.log(`vendor: cập nhật ${file.to} (${(wanted.length / 1024).toFixed(0)} KB)`);
}

if (failed) process.exit(1);

console.log(`vendor: ${FILES.length}/${FILES.length} file khớp.`);
