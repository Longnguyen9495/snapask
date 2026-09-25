'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const errors = require('../src/main/api-errors');

const t = (key, vars = {}) => Object.entries(vars).reduce((text, [name, value]) => text.replace(`:${name}`, value), key);

test('phân loại lỗi theo mã HTTP', () => {
  assert.equal(errors.classify(0), 'network');
  assert.equal(errors.classify(401), 'unauthorized');
  assert.equal(errors.classify(403), 'forbidden');
  assert.equal(errors.classify(404), 'not_found');
  assert.equal(errors.classify(422), 'validation');
  assert.equal(errors.classify(429, { quota: { remaining: 0 } }), 'quota');
  assert.equal(errors.classify(429, { message: 'Too Many Attempts.' }), 'rate_limited');
  assert.equal(errors.classify(500), 'server');
  assert.equal(errors.classify(503), 'server');
  assert.equal(errors.classify(418), 'unknown');
});

test('lỗi 422 lấy dòng chi tiết đầu tiên', () => {
  const error = errors.fromResponse(422, { message: 'The given data was invalid.', errors: { title: ['Hãy nhập tiêu đề.'] } }, t);

  assert.equal(error.code, 'validation');
  assert.equal(error.message, 'Hãy nhập tiêu đề.');
});

test('hết lượt kèm thông tin hạn mức', () => {
  const error = errors.fromResponse(429, { message: 'Đã dùng hết lượt hỏi.', quota: { remaining: 0, limit: 50 } }, t);

  assert.equal(error.code, 'quota');
  assert.equal(error.quota.limit, 50);
  assert.equal(errors.toIpc(error).quota.remaining, 0);
});

test('lỗi 5xx không lộ thông điệp của máy chủ', () => {
  const error = errors.fromResponse(500, { message: 'SQLSTATE[HY000] at /var/www/app.php' }, t);

  assert.equal(error.code, 'server');
  assert.doesNotMatch(error.message, /SQLSTATE|var\/www/);
});

test('dạng gửi qua IPC không kèm stack hay chi tiết lạ', () => {
  const ipc = errors.toIpc(new Error('secret stack'), t);

  assert.deepEqual(ipc, { ok: false, code: 'unknown', message: 'An error occurred.' });
  assert.equal(errors.toIpc({ code: 'invalid' }, t).code, 'invalid');
  assert.equal(errors.toIpc(errors.network(t)).code, 'network');
});
