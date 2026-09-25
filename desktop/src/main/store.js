'use strict';

const { app, safeStorage } = require('electron');
const fs = require('node:fs');
const path = require('node:path');

const config = require('./config');
const schema = require('./settings-schema');

const FILE = () => path.join(app.getPath('userData'), 'snapask.json');

let cache = null;

/**
 * Đọc snapask.json, trộn với mặc định của bản hiện tại.
 *
 * File của 0.2.2 vẫn đọc được: khoá mới lấy mặc định, khoá cũ và token giữ
 * nguyên (xem settings-schema.js). Chỉ ghi lại xuống đĩa ở lần `write()` tiếp
 * theo, để mở bản mới rồi quay về bản cũ không làm hỏng gì.
 */
function read() {
  if (cache) return cache;

  let raw = {};

  try {
    raw = JSON.parse(fs.readFileSync(FILE(), 'utf8'));
  } catch {
    raw = {};
  }

  cache = schema.migrate(raw, config.serverUrl(), config.normalizeServerUrl);

  return cache;
}

/**
 * Ghi đè một phần cài đặt.
 *
 * Ghi ra file tạm rồi đổi tên: mất điện giữa chừng thì còn nguyên file cũ,
 * không bị một snapask.json cụt làm mất token.
 */
function write(patch) {
  cache = { ...read(), ...patch };

  const file = FILE();
  const temp = `${file}.tmp`;

  fs.writeFileSync(temp, JSON.stringify(cache, null, 2), 'utf8');
  fs.renameSync(temp, file);

  return cache;
}

/**
 * Đưa cài đặt về mặc định nhưng giữ đăng nhập.
 *
 * "Đặt lại cài đặt" không phải "đăng xuất": token và lựa chọn ngôn ngữ ở lại.
 */
function reset() {
  const { token, tokenEnc, locale } = read();

  cache = null;

  return write({
    ...schema.defaults(config.serverUrl()),
    token: token ?? null,
    tokenEnc: tokenEnc ?? null,
    ...(locale ? { locale } : {}),
  });
}

/**
 * Token đăng nhập được mã hoá bằng kho bí mật của hệ điều hành khi máy hỗ trợ.
 *
 * Không mã hoá thì bất kỳ tiến trình nào chạy dưới cùng tài khoản Windows cũng
 * đọc được token trong file JSON và mạo danh được người dùng.
 */
function setToken(token) {
  if (!token) return write({ token: null, tokenEnc: null });

  if (safeStorage.isEncryptionAvailable()) {
    return write({ token: null, tokenEnc: safeStorage.encryptString(token).toString('base64') });
  }

  return write({ token, tokenEnc: null });
}

function getToken() {
  const data = read();

  if (data.tokenEnc && safeStorage.isEncryptionAvailable()) {
    try {
      return safeStorage.decryptString(Buffer.from(data.tokenEnc, 'base64'));
    } catch {
      return null;
    }
  }

  return data.token || null;
}

/** Cài đặt gửi xuống renderer: không bao giờ kèm token. */
const settings = () => schema.publicView(read());

module.exports = { read, write, reset, settings, setToken, getToken };
