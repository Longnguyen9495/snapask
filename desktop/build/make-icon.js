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

// Cùng hệ màu với ứng dụng và trang web: nền đen ngả ấm, một màu nhấn hổ phách.
// Lý do chọn đã ghi trong README, mục Hệ thiết kế.
const BG = [26, 24, 22, 255];       // --ink-1  #1a1816
const ACCENT = [227, 160, 75, 255]; // --accent #e3a04b
const DOT = [243, 239, 232, 255];   // --text   #f3efe8

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

/**
 * Icon khay đơn sắc cho thanh menu macOS.
 *
 * macOS tự tô lại ảnh template theo nền sáng hay tối, nên nó chỉ đọc kênh alpha
 * — mọi màu trong ảnh đều bị bỏ. Vẽ đen đặc rồi để hệ điều hành lo phần còn lại.
 */
function drawTemplate(size) {
  const pixels = draw(size);

  for (let i = 0; i < pixels.length; i += 4) {
    const alpha = pixels[i + 3];

    // Nền của icon thường là chỗ đáng lẽ phải trong suốt trên thanh menu.
    const isBackground = alpha > 0
      && pixels[i] === BG[0] && pixels[i + 1] === BG[1] && pixels[i + 2] === BG[2];

    pixels[i] = 0;
    pixels[i + 1] = 0;
    pixels[i + 2] = 0;
    pixels[i + 3] = isBackground ? 0 : alpha;
  }

  return pixels;
}

/**
 * Gói nhiều ảnh PNG thành một file .ico.
 *
 * Bộ cài Windows cần .ico; electron-builder không tự dựng được từ PNG khi thiếu
 * thư viện ảnh, nên ghép tay ở đây — định dạng này chỉ là một bảng mục lục đơn
 * giản đặt trước các ảnh PNG nguyên vẹn.
 */
function ico(sizes) {
  const images = sizes.map((size) => encode(size, draw(size)));

  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0);            // để trống
  header.writeUInt16LE(1, 2);            // 1 = icon
  header.writeUInt16LE(sizes.length, 4);

  let offset = 6 + sizes.length * 16;

  const entries = sizes.map((size, i) => {
    const entry = Buffer.alloc(16);
    entry[0] = size >= 256 ? 0 : size;   // 0 nghĩa là 256
    entry[1] = size >= 256 ? 0 : size;
    entry[4] = 1;                        // số mặt phẳng màu
    entry.writeUInt16LE(32, 6);          // bit mỗi điểm ảnh
    entry.writeUInt32LE(images[i].length, 8);
    entry.writeUInt32LE(offset, 12);
    offset += images[i].length;

    return entry;
  });

  return Buffer.concat([header, ...entries, ...images]);
}

/**
 * Gói nhiều ảnh PNG thành một file .icns cho macOS.
 *
 * Mỗi ảnh là một khối có mã bốn ký tự nói rõ kích thước; bảng dưới đây là những
 * mã mà Finder và Dock thực sự đọc.
 */
function icns(entries) {
  const blocks = entries.map(([type, size]) => {
    const png = encode(size, draw(size));
    const header = Buffer.alloc(8);
    header.write(type, 0, 4, 'ascii');
    header.writeUInt32BE(png.length + 8, 4);

    return Buffer.concat([header, png]);
  });

  const body = Buffer.concat(blocks);
  const header = Buffer.alloc(8);
  header.write('icns', 0, 4, 'ascii');
  header.writeUInt32BE(body.length + 8, 4);

  return Buffer.concat([header, body]);
}

const assets = path.join(__dirname, '..', 'src', 'assets');

for (const [name, size] of [['icon.png', 256], ['tray.png', 32]]) {
  // Vào src/ chứ không phải build/: buildResources không được gói vào asar,
  // nên icon để ở đó thì lúc chạy thật không tìm thấy.
  const file = path.join(assets, name);
  fs.writeFileSync(file, encode(size, draw(size)));
  console.log(`${name} (${size}px)`);
}

// Thanh menu macOS cần ảnh template, kèm bản @2x cho màn Retina.
for (const [name, size] of [['trayTemplate.png', 22], ['trayTemplate@2x.png', 44]]) {
  fs.writeFileSync(path.join(assets, name), encode(size, drawTemplate(size)));
  console.log(`${name} (${size}px)`);
}

/*
 * Icon của bộ cài nằm trong build/: electron-builder đọc chúng lúc đóng gói,
 * còn lúc chạy thì không ai cần tới.
 */
fs.writeFileSync(path.join(__dirname, 'icon.ico'), ico([16, 24, 32, 48, 64, 128, 256]));
console.log('icon.ico (16–256px)');

fs.writeFileSync(path.join(__dirname, 'icon.icns'), icns([
  ['icp4', 16], ['icp5', 32], ['icp6', 64],
  ['ic07', 128], ['ic08', 256], ['ic09', 512],
]));
console.log('icon.icns (16–512px)');
