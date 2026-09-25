'use strict';

/**
 * So hai file từ điển với nhau.
 *
 * Thiếu một khoá thì chữ rơi về tiếng Anh giữa một cửa sổ tiếng Việt, và không
 * ai thấy cho tới khi khách gặp đúng câu đó. Chạy trong CI trước mỗi lần build.
 *
 * Kiểm luôn phần giữ chỗ `:ten`: dịch xong mà đánh rơi một chỗ giữ chỗ thì câu
 * hiện ra thiếu hẳn con số, mà so khoá không bắt được lỗi ấy.
 */

const fs = require('node:fs');
const path = require('node:path');

const DIR = path.join(__dirname, '..', 'src', 'i18n');

const read = (name) => JSON.parse(fs.readFileSync(path.join(DIR, name), 'utf8'));

const vi = read('vi.json');
const en = read('en.json');

const problems = [];

for (const key of Object.keys(en)) {
  if (!(key in vi)) problems.push(`thiếu ở vi.json: ${key}`);
}

for (const key of Object.keys(vi)) {
  if (!(key in en)) problems.push(`thiếu ở en.json: ${key}`);
}

const placeholders = (text) => (String(text).match(/:[a-z_]+/gi) ?? []).sort().join(',');

for (const key of Object.keys(vi)) {
  if (!(key in en)) continue;

  // So với chính khoá, vì khoá là câu tiếng Anh gốc.
  if (placeholders(key) !== placeholders(vi[key])) {
    problems.push(`lệch chỗ giữ chỗ ở vi.json: ${key}`);
  }
}

if (problems.length > 0) {
  console.error(`i18n: ${problems.length} vấn đề\n`);
  for (const problem of problems) console.error(`  ${problem}`);
  process.exit(1);
}

console.log(`i18n: ${Object.keys(vi).length} khoá, hai file khớp nhau.`);
