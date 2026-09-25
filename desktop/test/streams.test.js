'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const { StreamRegistry } = require('../src/main/streams');

test('huỷ một lượt không đụng lượt của cửa sổ khác', () => {
  const registry = new StreamRegistry();
  const workspace = registry.start(1, 'r1');
  const compact = registry.start(2, 'compact');

  assert.equal(registry.cancel(1, 'r1'), true);
  assert.equal(workspace.signal.aborted, true);
  assert.equal(compact.signal.aborted, false);
  assert.equal(registry.size, 1);
});

test('lượt trùng mã của cùng cửa sổ thay lượt cũ', () => {
  const registry = new StreamRegistry();
  const first = registry.start(2, 'compact');
  const second = registry.start(2, 'compact');

  assert.equal(first.signal.aborted, true);
  assert.equal(second.signal.aborted, false);
  assert.equal(registry.size, 1);
});

test('finish chỉ gỡ đúng controller đó, không gỡ lượt mới cùng mã', () => {
  const registry = new StreamRegistry();
  const old = registry.start(1, 'r');
  const fresh = registry.start(1, 'r');

  registry.finish(1, 'r', old);
  assert.equal(registry.size, 1);

  registry.finish(1, 'r', fresh);
  assert.equal(registry.size, 0);
});

test('đóng cửa sổ huỷ mọi lượt của nó; đăng xuất huỷ tất cả', () => {
  const registry = new StreamRegistry();
  const a = registry.start(1, 'a');
  const b = registry.start(1, 'b');
  const c = registry.start(2, 'c');

  assert.equal(registry.cancelOwner(1), 2);
  assert.ok(a.signal.aborted && b.signal.aborted);
  assert.equal(c.signal.aborted, false);

  assert.equal(registry.cancelAll(), 1);
  assert.equal(c.signal.aborted, true);
  assert.equal(registry.size, 0);
});
