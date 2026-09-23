'use strict';

/**
 * Sinh icon PNG cho khay hệ thống và bộ cài, không cần thư viện ngoài.
 *
 * Icon vẽ khung ngắm bốn góc — đúng động tác người dùng làm với phần mềm này —
 * cùng một chấm nhỏ gợi ô chat.
 */

const zlib = require('node:zlib');
const fs = require('node:fs');
const path = require('node:path');

const BG = [17, 19, 26, 255];
const ACCENT = [108, 140, 255, 255];
const DOT = [232, 235, 242, 255];

function draw(size) {
  const pixels = Buffer.alloc(size * size * 4);
  const put = (x, y, rgba) => {
    if (x < 0 || y < 0 || x >= size || y >= size) return;

    pixels.set(rgba, (y * size + x) * 4);
  };

  const radius = Math.round(size * 0.22);
  const inside = (x, y) => {
    // Bo bốn góc: chỉ điểm nằm ngoài bán kính ở đúng góc mới bị bỏ trống.
    const cx = Math.min(Math.max(x, radius), size - 1 - radius);
    const cy = Math.min(Math.max(y, radius), size - 1 - radius);

    return (x - cx) ** 2 + (y - cy) ** 2 <= radius ** 2;
  };

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      put(x, y, inside(x, y) ? BG : [0, 0, 0, 0]);
    }
  }

  const margin = Math.round(size * 0.26);
  const arm = Math.round(size * 0.16);
  const thick = Math.max(2, Math.round(size * 0.075));
  const far = size - 1 - margin;

  const hLine = (x0, x1, y) => {
    for (let x = x0; x <= x1; x++) {
      for (let t = 0; t < thick; t++) put(x, y + t, ACCENT);
    }
  };

  const vLine = (x, y0, y1) => {
    for (let y = y0; y <= y1; y++) {
      for (let t = 0; t < thick; t++) put(x + t, y, ACCENT);
    }
  };

  hLine(margin, margin + arm, margin);
  vLine(margin, margin, margin + arm);
  hLine(far - arm, far, margin);
  vLine(far - thick + 1, margin, margin + arm);
  hLine(margin, margin + arm, far - thick + 1);
  vLine(margin, far - arm, far);
  hLine(far - arm, far, far - thick + 1);
  vLine(far - thick + 1, far - arm, far);

  const dot = Math.max(1, Math.round(size * 0.09));
  const mid = Math.round(size / 2 - dot / 2);

  for (let y = 0; y < dot; y++) {
    for (let x = 0; x < dot; x++) put(mid + x, mid + y, DOT);
  }

  return pixels;
}

function encode(size, pixels) {
  const raw = Buffer.alloc((size * 4 + 1) * size);

  for (let y = 0; y < size; y++) {
    // Mỗi hàng PNG bắt đầu bằng một byte kiểu lọc; 0 là "không lọc".
    raw[y * (size * 4 + 1)] = 0;
    pixels.copy(raw, y * (size * 4 + 1) + 1, y * size * 4, (y + 1) * size * 4);
  }

  const chunk = (type, data) => {
    const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(body));

    return Buffer.concat([length, body, crc]);
  };

  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0);
  ihdr.writeUInt32BE(size, 4);
  ihdr[8] = 8;  // 8 bit mỗi kênh
  ihdr[9] = 6;  // RGBA

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

let table = null;

function crc32(buffer) {
  if (!table) {
    table = new Int32Array(256);

    for (let n = 0; n < 256; n++) {
      let c = n;

      for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;

      table[n] = c;
    }
  }

  let crc = -1;

  for (const byte of buffer) crc = table[(crc ^ byte) & 0xff] ^ (crc >>> 8);

  return (crc ^ -1) >>> 0;
}

for (const [name, size] of [['icon.png', 256], ['tray.png', 32]]) {
  // Vào src/ chứ không phải build/: buildResources không được gói vào asar,
  // nên icon để ở đó thì lúc chạy thật không tìm thấy.
  const file = path.join(__dirname, "..", "src", "assets", name);
  fs.writeFileSync(file, encode(size, draw(size)));
  console.log(`${name} (${size}px)`);
}
