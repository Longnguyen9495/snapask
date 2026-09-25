'use strict';

/**
 * Kiểm tra cú pháp mọi file JS trong src/ và test/ bằng `node --check`.
 *
 * Renderer nạp file bằng <script> thường, nên một dấu ngoặc thiếu chỉ lộ ra khi
 * mở đúng cửa sổ đó. Chạy trước mỗi lần build để bắt sớm.
 */

const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.join(__dirname, '..');

function walk(dir) {
  if (!fs.existsSync(dir)) return [];

  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);

    if (entry.isDirectory()) return walk(full);

    return entry.name.endsWith('.js') ? [full] : [];
  });
}

const files = [...walk(path.join(ROOT, 'src')), ...walk(path.join(ROOT, 'test'))];
let failed = 0;

for (const file of files) {
  try {
    execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
  } catch (error) {
    failed++;
    console.error(`✗ ${path.relative(ROOT, file)}\n${error.stderr?.toString() || error.message}`);
  }
}

console.log(`syntax: ${files.length - failed}/${files.length} file hợp lệ.`);
process.exit(failed > 0 ? 1 : 0);
