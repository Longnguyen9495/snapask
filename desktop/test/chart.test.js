'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const chart = require('../src/renderer/lib/chart');

const THEME = { text: '#f3efe8', dim: '#a49c90', grid: '#2a251f' };
const spec = (json) => chart.normalize(JSON.stringify(json));

test('mô tả biểu đồ hợp lệ được giữ nguyên dữ liệu', () => {
  const result = spec({ type: 'bar', title: 'Doanh thu', labels: ['T1', 'T2'], datasets: [{ label: 'VND', data: [12, 19] }] });

  assert.equal(result.type, 'bar');
  assert.equal(result.title, 'Doanh thu');
  assert.deepEqual(result.labels, ['T1', 'T2']);
  assert.deepEqual(result.datasets[0].data, [12, 19]);
});

test('JSON hỏng hoặc không có số nào dùng được thì bỏ qua', () => {
  assert.equal(chart.normalize('{ chưa đóng'), null);
  assert.equal(chart.normalize('[]'), null);
  assert.equal(spec({ datasets: [] }), null);
  assert.equal(spec({ datasets: [{ data: ['x', 'y'] }] }), null);
});

test('loại biểu đồ ngoài danh sách cho phép lùi về cột', () => {
  assert.equal(spec({ type: 'alert(1)', datasets: [{ data: [1] }] }).type, 'bar');
  assert.equal(spec({ type: 'line', datasets: [{ data: [1] }] }).type, 'line');
});

test('số viết kiểu Việt đọc được, giá trị rác thành khoảng trống', () => {
  assert.deepEqual(spec({ datasets: [{ data: ['1.250', '3,5', 7] }] }).datasets[0].data, [1250, 3.5, 7]);
  assert.deepEqual(spec({ datasets: [{ data: [5, 'abc', null] }] }).datasets[0].data, [5, null, null]);
});

test('số chuỗi và số điểm bị chặn trần', () => {
  const many = spec({ datasets: Array.from({ length: 20 }, () => ({ data: [1] })) });
  assert.equal(many.datasets.length, 8);

  const long = spec({ datasets: [{ data: Array.from({ length: 500 }, (unused, index) => index) }] });
  assert.equal(long.datasets[0].data.length, 200);
});

/*
 * Điểm quan trọng nhất: mô hình chỉ được nói dữ liệu. Màu, sự kiện và mọi tuỳ
 * chọn hiển thị do chính ứng dụng quyết định, nên không trường nào của mô hình
 * được đi thẳng vào cấu hình Chart.js.
 */
test('màu và trường lạ của mô hình không lọt vào cấu hình', () => {
  const result = spec({
    datasets: [{ data: [1], backgroundColor: 'red', borderColor: '#fff' }],
    onclick: 'alert(1)',
    options: { onClick: 'alert(2)' },
  });

  assert.equal(result.datasets[0].color, chart.PALETTE[0]);

  const serialized = JSON.stringify(chart.config(result, THEME));
  assert.doesNotMatch(serialized, /red|alert/);
});

test('nhãn quá dài bị cắt để trục không vỡ', () => {
  const result = spec({ labels: ['x'.repeat(300)], datasets: [{ data: [1] }] });

  assert.equal(result.labels[0].length, 80);
});

test('biểu đồ tròn tô nhiều màu và luôn hiện chú giải', () => {
  const round = chart.config(spec({ type: 'pie', datasets: [{ data: [1, 2] }] }), THEME);
  assert.ok(Array.isArray(round.data.datasets[0].backgroundColor));
  assert.equal(round.options.plugins.legend.display, true);
  assert.deepEqual(round.options.scales, {});

  // Một chuỗi cột thì nhãn đã nằm ở trục, chú giải chỉ thêm rối.
  const bar = chart.config(spec({ type: 'bar', datasets: [{ data: [1] }] }), THEME);
  assert.equal(bar.options.plugins.legend.display, false);
  assert.ok(bar.options.scales.y.beginAtZero);
});
