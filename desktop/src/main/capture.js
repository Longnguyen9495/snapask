'use strict';

const { BrowserWindow, desktopCapturer, screen, nativeImage } = require('electron');
const path = require('node:path');

let overlays = [];
let pending = null;

const isOpen = () => overlays.length > 0;

/**
 * Chụp toàn bộ các màn hình đang có, kèm tỉ lệ thật của từng ảnh.
 *
 * Electron co ảnh thumbnail cho vừa `thumbnailSize` và giữ nguyên tỉ lệ khung,
 * nên với nhiều màn hình khác độ phân giải, ảnh trả về không trùng kích thước
 * màn hình. Phải đo lại từng ảnh rồi mới quy đổi được vùng người dùng quét.
 */
async function grabScreens() {
  const displays = screen.getAllDisplays();
  const widest = Math.max(...displays.map((d) => d.size.width * d.scaleFactor));
  const tallest = Math.max(...displays.map((d) => d.size.height * d.scaleFactor));

  const sources = await desktopCapturer.getSources({
    types: ['screen'],
    thumbnailSize: { width: widest, height: tallest },
  });

  return displays
    .map((display) => {
      const source = sources.find((item) => String(item.display_id) === String(display.id))
        ?? sources[displays.indexOf(display)];

      if (!source || source.thumbnail.isEmpty()) return null;

      const size = source.thumbnail.getSize();

      return {
        display,
        image: source.thumbnail,
        scale: size.width / display.size.width,
        dataUrl: source.thumbnail.toDataURL(),
      };
    })
    .filter(Boolean);
}

/**
 * Mở lớp phủ quét chọn trên mọi màn hình và chờ người dùng khoanh một vùng.
 *
 * Trả về `null` khi người dùng bấm Esc hoặc không chọn được vùng nào đủ lớn.
 */
async function selectRegion() {
  if (isOpen()) return null;

  const screens = await grabScreens();

  if (screens.length === 0) return null;

  return new Promise((resolve) => {
    pending = resolve;

    for (const shot of screens) {
      const { x, y, width, height } = shot.display.bounds;

      const overlay = new BrowserWindow({
        x,
        y,
        width,
        height,
        frame: false,
        transparent: false,
        resizable: false,
        movable: false,
        minimizable: false,
        maximizable: false,
        skipTaskbar: true,
        alwaysOnTop: true,
        fullscreenable: false,
        show: false,
        backgroundColor: '#000000',
        webPreferences: {
          preload: path.join(__dirname, '..', 'preload', 'overlay.js'),
          contextIsolation: true,
          nodeIntegration: false,
        },
      });

      // 'screen-saver' là mức duy nhất phủ được lên thanh taskbar và các cửa sổ
      // ghim-luôn-trên-cùng khác; mức mặc định bị taskbar Windows che mất.
      overlay.setAlwaysOnTop(true, 'screen-saver');
      overlay.setVisibleOnAllWorkspaces(true, { visibleOnFullScreen: true });
      overlay.shot = shot;

      overlay.loadFile(path.join(__dirname, '..', 'renderer', 'overlay.html'));
      overlay.webContents.once('did-finish-load', () => {
        overlay.webContents.send('overlay:image', {
          dataUrl: shot.dataUrl,
          cssWidth: shot.display.size.width,
        });
        overlay.show();
        overlay.focus();
      });

      overlays.push(overlay);
    }
  });
}

/** Đóng mọi lớp phủ và trả kết quả về đúng một lần. */
function finish(result) {
  const resolve = pending;
  pending = null;

  for (const overlay of overlays) {
    if (!overlay.isDestroyed()) overlay.destroy();
  }

  overlays = [];

  if (resolve) resolve(result);
}

function cancel() {
  finish(null);
}

/**
 * Thu nhỏ ảnh đã cắt rồi mã hoá lại cho vừa hầu bao token.
 *
 * Một ảnh full-HD tốn cỡ 1.100 token mỗi lượt hỏi, còn bản 1280px chỉ tốn
 * khoảng một nửa mà mô hình vẫn đọc rõ chữ.
 *
 * @param {string} dataUrl Ảnh PNG đã cắt và đã vẽ ghi chú, do lớp phủ dựng.
 */
function shrink(dataUrl, maxWidth) {
  const image = nativeImage.createFromDataURL(dataUrl);
  const size = image.getSize();
  const final = size.width > maxWidth ? image.resize({ width: maxWidth, quality: good }) : image;

  // Ảnh chụp giao diện gần như toàn mảng màu phẳng nên PNG vừa nhỏ vừa giữ chữ
  // sắc nét. Chỉ khi PNG phình quá mới đổi sang JPEG, vì JPEG làm nhoè chữ nhỏ
  // và mô hình đọc sai số liệu.
  const png = final.toPNG();
  const buffer = png.byteLength <= 500 * 1024 ? png : final.toJPEG(88);
  const mime = buffer === png ? image/png : image/jpeg;

  return {
    dataUrl: `data:${mime};base64,${buffer.toString(base64)}`,
    bytes: buffer.byteLength,
    width: final.getSize().width,
    height: final.getSize().height,
  };
}
module.exports = { selectRegion, finish, cancel, shrink, isOpen };
