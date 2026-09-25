'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const validate = require('../src/main/validate');

const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

test('lượt hỏi hợp lệ được chuẩn hoá', () => {
  assert.deepEqual(validate.askPayload({ requestId: 'w1abc', conversationId: '12', question: '  Lỗi gì?  ', image: PNG }), {
    requestId: 'w1abc',
    conversationId: 12,
    question: 'Lỗi gì?',
    imageDataUrl: PNG,
  });

  assert.equal(validate.askPayload({ requestId: 'a', question: 'x' }).conversationId, null);
});

test('lượt hỏi sai hình dạng bị từ chối', () => {
  const bad = [
    null,
    { requestId: 'a b', question: 'x' },
    { requestId: 'a', question: '   ' },
    { requestId: 'a', question: 'x'.repeat(2001) },
    { requestId: 'a', question: 'x', conversationId: -1 },
    { requestId: 'a', question: 'x', conversationId: 1.5 },
    { requestId: 'a', question: 'x', image: 'data:text/html;base64,PHNjcmlwdD4=' },
    { requestId: 'a', question: 'x', image: 'file:///etc/passwd' },
    { requestId: 'a', question: 'x', image: `${PNG}"><script>` },
  ];

  for (const payload of bad) {
    assert.throws(() => validate.askPayload(payload), validate.ValidationError, JSON.stringify(payload)?.slice(0, 60));
  }
});

test('truy vấn danh sách giới hạn trang và độ dài từ khoá', () => {
  assert.deepEqual(validate.listQuery({}), { page: 1, search: '' });
  assert.deepEqual(validate.listQuery({ page: 3, search: '  hoá đơn ' }), { page: 3, search: 'hoá đơn' });
  assert.equal(validate.listQuery({ search: 'a'.repeat(300) }).search.length, 100);
  assert.throws(() => validate.listQuery({ page: 0 }), validate.ValidationError);
  assert.throws(() => validate.listQuery({ search: { $ne: 1 } }), validate.ValidationError);
});

test('bản vá cài đặt chỉ nhận khoá được phép', () => {
  assert.deepEqual(validate.settingsPatch({ locale: 'en', closeBehavior: 'quit', token: 'hack', tokenEnc: 'x' }), {
    locale: 'en',
    closeBehavior: 'quit',
  });

  assert.throws(() => validate.settingsPatch({ maxImageWidth: 99999 }), validate.ValidationError);
  assert.throws(() => validate.settingsPatch({ launchAtLogin: 'true' }), validate.ValidationError);
  assert.throws(() => validate.settingsPatch([]), validate.ValidationError);
});

test('địa chỉ máy chủ chỉ nhận http/https, không kèm thông tin đăng nhập', () => {
  assert.equal(validate.serverUrl('https://snapask.congty.vn/'), 'https://snapask.congty.vn');
  assert.equal(validate.serverUrl('http://snapask.local/sub/'), 'http://snapask.local/sub');

  for (const bad of ['javascript:alert(1)', 'file:///c:/', 'https://user:pw@x.vn', 'https://x.vn/?token=1', 'không phải url']) {
    assert.throws(() => validate.serverUrl(bad), validate.ValidationError, bad);
  }
});

test('link trong câu trả lời chỉ mở được http/https', () => {
  assert.equal(validate.externalLink('https://example.com/a b'), 'https://example.com/a%20b');

  for (const bad of ['javascript:alert(1)', 'file:///c:/Windows', 'snapask://x', 'data:text/html,1']) {
    assert.throws(() => validate.externalLink(bad), validate.ValidationError, bad);
  }
});
