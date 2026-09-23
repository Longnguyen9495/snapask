'use strict';

const { app, safeStorage } = require('electron');
const fs = require('node:fs');
const path = require('node:path');

const config = require('./config');

const FILE = () => path.join(app.getPath('userData'), 'snapask.json');

const DEFAULTS = {
  // Không còn là thứ người dùng nhập; xem src/main/config.js. Vẫn để trong kho
  // cài đặt để một bản triển khai riêng ghi đè được bằng snapask.json.
  serverUrl: config.serverUrl(),
  maxImageWidth: 1280,
};

let cache = null;

function read() {
  if (cache) return cache;

  try {
    cache = { ...DEFAULTS, ...JSON.parse(fs.readFileSync(FILE(), 'utf8')) };
  } catch {
    cache = { ...DEFAULTS };
  }

  return cache;
}

function write(patch) {
  cache = { ...read(), ...patch };
  fs.writeFileSync(FILE(), JSON.stringify(cache, null, 2), 'utf8');
  return cache;
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

const settings = () => {
  const { token, tokenEnc, ...rest } = read();
  return rest;
};

module.exports = { read, write, settings, setToken, getToken };
