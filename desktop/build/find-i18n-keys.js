'use strict';

/**
 * Liệt kê khoá dịch đang dùng trong mã desktop mà từ điển chưa có.
 *
 * Quét lời gọi t('…') / i18n.t('…') trong JS và thuộc tính data-i18n* trong
 * HTML. check-i18n.js chỉ so hai file từ điển với nhau; script này bắt trường
 * hợp cả hai file cùng quên một câu — khi đó chữ tiếng Anh lọt vào giữa cửa sổ
 * tiếng Việt mà không kiểm tra nào kêu.
 *
 * Chạy: node build/find-i18n-keys.js   (thoát mã 1 nếu thiếu khoá)
 */

const fs = require('node:fs');
const path = require('node:path');

const SRC = path.join(__dirname, '..', 'src');
const vi = JSON.parse(fs.readFileSync(path.join(SRC, 'i18n', 'vi.json'), 'utf8'));

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);

    if (entry.isDirectory()) return entry.name === 'assets' ? [] : walk(full);

    return /\.(js|html)$/.test(entry.name) ? [full] : [];
  });
}

const used = new Map();
const add = (key, file) => { if (!used.has(key)) used.set(key, path.relative(SRC, file)); };

for (const file of walk(SRC)) {
  const text = fs.readFileSync(file, 'utf8');

  if (file.endsWith('.js')) {
    for (const match of text.matchAll(/\bt\(\s*'((?:[^'\\]|\\.)+)'/g)) add(match[1].replace(/\\'/g, "'"), file);
  } else {
    for (const match of text.matchAll(/data-i18n(?:-[a-z-]+)?="([^"]+)"/g)) add(match[1].replace(/&amp;/g, '&'), file);
  }
}

// Khoá dựng động (COPY trong login.js…) đã được đưa vào từ điển từ trước.
const missing = [...used].filter(([key]) => !(key in vi));

for (const [key, file] of missing) console.log(`${JSON.stringify(key)}  // ${file}`);

console.log(`${used.size} khoá đang dùng, ${missing.length} khoá thiếu.`);
process.exit(missing.length > 0 ? 1 : 0);
