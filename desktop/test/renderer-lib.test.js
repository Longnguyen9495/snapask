'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const history = require('../src/renderer/lib/history');
const stream = require('../src/renderer/lib/stream');
const markdown = require('../src/renderer/lib/markdown');

const at = (iso) => ({ last_activity_at: iso });

/* ---------- nhóm theo thời gian ---------- */

test('nhóm lịch sử theo Hôm nay / Hôm qua / 7 ngày / Cũ hơn', () => {
  const now = new Date(2026, 8, 25, 15, 0);
  const items = [
    { id: 1, ...at(new Date(2026, 8, 25, 9, 0).toISOString()) },
    { id: 2, ...at(new Date(2026, 8, 24, 23, 59).toISOString()) },
    { id: 3, ...at(new Date(2026, 8, 20, 10, 0).toISOString()) },
    { id: 4, ...at(new Date(2026, 7, 1).toISOString()) },
  ];

  const groups = history.groupByTime(items, now);

  assert.deepEqual(groups.map((group) => [group.key, group.items.map((item) => item.id)]), [
    ['today', [1]],
    ['yesterday', [2]],
    ['week', [3]],
    ['older', [4]],
  ]);
});

test('nhóm rỗng bị bỏ', () => {
  assert.deepEqual(history.groupByTime([], new Date()), []);
});

/* ---------- gộp trang ---------- */

test('gộp trang không nhân đôi khi hội thoại vừa nổi lên đầu', () => {
  const page1 = [{ id: 3, ...at('2026-09-25T10:00:00Z') }, { id: 2, ...at('2026-09-25T09:00:00Z') }];
  // Trong lúc tải trang 2, hội thoại 2 bị đẩy xuống và xuất hiện lại ở trang 2 với dữ liệu cũ hơn.
  const page2 = [{ id: 2, ...at('2026-09-25T09:00:00Z'), title: 'cũ' }, { id: 1, ...at('2026-09-24T09:00:00Z') }];

  const merged = history.mergePage(page1, page2);

  assert.deepEqual(merged.map((item) => item.id), [3, 2, 1]);
});

test('bản mới hơn thắng và được xếp lên đầu', () => {
  const items = [{ id: 1, ...at('2026-09-25T10:00:00Z') }, { id: 2, ...at('2026-09-25T09:00:00Z'), title: 'A' }];
  const updated = history.upsert(items, { id: 2, ...at('2026-09-25T11:00:00Z'), title: 'B' });

  assert.deepEqual(updated.map((item) => item.id), [2, 1]);
  assert.equal(updated[0].title, 'B');
});

test('replace thay toàn bộ danh sách (làm mới trang đầu, đổi từ khoá)', () => {
  const merged = history.mergePage([{ id: 9, ...at('2026-01-01T00:00:00Z') }], [{ id: 1, ...at('2026-09-01T00:00:00Z') }], { replace: true });

  assert.deepEqual(merged.map((item) => item.id), [1]);
});

test('mục kế tiếp sau khi xoá', () => {
  const items = [{ id: 1 }, { id: 2 }, { id: 3 }];

  assert.equal(history.neighbourOf(items, 2).id, 3);
  assert.equal(history.neighbourOf(items, 3).id, 2);
  assert.equal(history.neighbourOf([{ id: 1 }], 1), null);
});

/* ---------- streaming ---------- */

test('stream: chữ, công cụ rồi xong', () => {
  let message = stream.initial('r1');

  message = stream.apply(message, { type: 'conversation', conversation_id: 7 });
  message = stream.apply(message, { type: 'tool', label: 'Đang hỏi Kho hàng…' });
  message = stream.apply(message, { type: 'delta', text: 'Còn ' });
  message = stream.apply(message, { type: 'delta', text: '12 cái.' });
  message = stream.apply(message, { type: 'done', conversation_id: 7 });

  assert.equal(message.content, 'Còn 12 cái.');
  assert.equal(message.status, 'done');
  assert.equal(message.conversationId, 7);
  assert.deepEqual(message.tools, [{ label: 'Đang hỏi Kho hàng…', done: true }]);
});

test('stream: lỗi trước chữ đầu thì cho thử lại, sau chữ đầu thì giữ nội dung', () => {
  const failed = stream.apply(stream.initial('r'), { type: 'error', code: 'network', message: 'x' });
  assert.equal(failed.status, 'failed');
  assert.equal(failed.error.code, 'network');

  const partial = stream.apply(stream.apply(stream.initial('r'), { type: 'delta', text: 'Một phần' }), { type: 'error', code: 'server' });
  assert.equal(partial.status, 'incomplete');
  assert.equal(partial.content, 'Một phần');
});

test('stream: dừng giữ phần đã nhận và bỏ qua sự kiện đến muộn', () => {
  let message = stream.apply(stream.initial('r'), { type: 'delta', text: 'Đang' });
  message = stream.stop(message);
  message = stream.apply(message, { type: 'delta', text: ' trả lời' });

  assert.equal(message.status, 'stopped');
  assert.equal(message.content, 'Đang');
  assert.equal(stream.stop(stream.initial('r')).status, 'failed');
});

test('stream: hết lượt mang theo hạn mức', () => {
  const message = stream.apply(stream.initial('r'), { type: 'error', code: 'quota', quota: { remaining: 0 } });

  assert.equal(message.error.code, 'quota');
  assert.equal(message.error.quota.remaining, 0);
});

/* ---------- markdown an toàn ---------- */

test('markdown thoát mọi HTML từ mô hình', () => {
  const html = markdown.render('<img src=x onerror=alert(1)> **đậm** <script>alert(1)</script>');

  assert.doesNotMatch(html, /<img|<script/);
  assert.match(html, /&lt;script&gt;/);
  assert.match(html, /<strong>đậm<\/strong>/);
});

test('markdown: link chỉ http/https và không bao giờ là href thật', () => {
  const html = markdown.render('[bấm](javascript:alert(1)) [web](https://example.com/a?b=1) https://snapask.vn/x.');

  assert.doesNotMatch(html, /href="javascript|data-href="javascript/);
  assert.doesNotMatch(html, / href="https/);
  assert.match(html, /data-href="https:\/\/example.com\/a\?b=1"/);
  assert.match(html, /data-href="https:\/\/snapask.vn\/x"/);
});

test('markdown: thuộc tính không bị phá bằng dấu nháy', () => {
  const html = markdown.render('[x](https://a.vn/"onmouseover="alert(1))');

  assert.doesNotMatch(html, /" onmouseover="|"onmouseover="alert/);
});

test('markdown: khối mã giữ nguyên ký hiệu, danh sách và tiêu đề', () => {
  const html = markdown.render('## Cách sửa\n\n- Một\n- **Hai**\n\n```js\nconst a = x ?? [];\n```\n\n1. Bước một');

  assert.match(html, /<h4>Cách sửa<\/h4>/);
  assert.match(html, /<ul><li>Một<\/li><li><strong>Hai<\/strong><\/li><\/ul>/);
  assert.match(html, /<pre data-lang="js"><code>const a = x \?\? \[\];<\/code><\/pre>/);
  assert.match(html, /<ol><li>Bước một<\/li><\/ol>/);
});

test('markdown: khối mã chưa đóng khi đang stream vẫn hiện như khối mã', () => {
  assert.match(markdown.render('```\nđang gõ', { streaming: true }), /<pre><code>đang gõ<\/code><\/pre>/);
});

test('chữ thuần cho đoạn xem trước', () => {
  assert.equal(markdown.plain('**Tổng** là `1.250.000`\n\n- đồng'), 'Tổng là 1.250.000 đồng');
});
