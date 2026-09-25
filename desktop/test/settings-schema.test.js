'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const schema = require('../src/main/settings-schema');

const DEFAULT_URL = 'https://snapask.vn';

test('file snapask.json của 0.2.2 giữ nguyên token, ngôn ngữ và địa chỉ máy chủ', () => {
  // Đúng hình dạng file mà bản 0.2.2 ghi ra.
  const legacy = {
    serverUrl: 'https://snapask.congty.vn',
    maxImageWidth: 1280,
    locale: 'en',
    token: null,
    tokenEnc: 'djEwAAAA-ma-hoa-boi-safeStorage==',
  };

  const migrated = schema.migrate(legacy, DEFAULT_URL);

  assert.equal(migrated.tokenEnc, legacy.tokenEnc);
  assert.equal(migrated.locale, 'en');
  assert.equal(migrated.serverUrl, 'https://snapask.congty.vn');
  assert.equal(migrated.maxImageWidth, 1280);
  // Khoá mới lấy mặc định.
  assert.equal(migrated.closeBehavior, 'tray');
  assert.equal(migrated.captureDestination, 'compact');
  assert.equal(migrated.launchAtLogin, false);
  assert.equal(migrated.sidebarCollapsed, false);
  assert.equal(migrated.settingsVersion, schema.SETTINGS_VERSION);
});

test('token không mã hoá của máy không có safeStorage vẫn còn sau nâng cấp', () => {
  const migrated = schema.migrate({ token: 'plain-token', tokenEnc: null }, DEFAULT_URL);

  assert.equal(migrated.token, 'plain-token');
});

test('địa chỉ máy chủ cũ được đổi sang địa chỉ mới', () => {
  const migrated = schema.migrate({ serverUrl: 'http://localhost:8000' }, DEFAULT_URL, (url) => (url === 'http://localhost:8000' ? DEFAULT_URL : url));

  assert.equal(migrated.serverUrl, DEFAULT_URL);
});

test('giá trị hỏng được sửa về mặc định hoặc mốc gần nhất', () => {
  const migrated = schema.migrate({
    maxImageWidth: 1500,
    closeBehavior: 'explode',
    captureDestination: 42,
    launchAtLogin: 'yes',
    locale: 'fr',
    serverUrl: '',
  }, DEFAULT_URL);

  assert.equal(migrated.maxImageWidth, 1600);
  assert.equal(migrated.closeBehavior, 'tray');
  assert.equal(migrated.captureDestination, 'compact');
  assert.equal(migrated.launchAtLogin, false);
  assert.equal('locale' in migrated, false);
  assert.equal(migrated.serverUrl, DEFAULT_URL);
});

test('file hỏng hoặc không phải object thì dùng mặc định', () => {
  for (const raw of [null, 'text', [1, 2], 42]) {
    assert.deepEqual(schema.migrate(raw, DEFAULT_URL), schema.defaults(DEFAULT_URL));
  }

  assert.equal(schema.migrate({ maxImageWidth: 'abc' }, DEFAULT_URL).maxImageWidth, 1280);
});

test('bản gửi xuống renderer không bao giờ có token', () => {
  const view = schema.publicView({ ...schema.defaults(DEFAULT_URL), token: 'a', tokenEnc: 'b' });

  assert.equal('token' in view, false);
  assert.equal('tokenEnc' in view, false);
  assert.equal(view.serverUrl, DEFAULT_URL);
});
