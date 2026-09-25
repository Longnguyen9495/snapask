'use strict';

/**
 * Dựng biểu đồ từ khối ```chart trong câu trả lời của mô hình.
 *
 * Nội dung khối là JSON do mô hình sinh ra, tức dữ liệu không tin được. Không
 * có trường nào của nó được đưa thẳng vào Chart.js: mọi giá trị đều đi qua
 * `normalize()`, thứ gì không nằm trong danh sách cho phép thì bị bỏ. Màu sắc,
 * phông chữ và mọi tuỳ chọn hiển thị do chính mã này quyết định — mô hình chỉ
 * được nói dữ liệu và loại biểu đồ.
 *
 * Định dạng mô hình phải tuân theo:
 *
 *     ```chart
 *     {"type":"bar","labels":["T1","T2"],"datasets":[{"label":"Doanh thu","data":[12,19]}]}
 *     ```
 *
 * Nạp được cả bằng <script> (gắn vào window.SnapAskLib) lẫn require() khi test.
 */
(function (root, factory) {
  const lib = factory();

  if (typeof module === 'object' && module.exports) module.exports = lib;
  else (root.SnapAskLib = root.SnapAskLib || {}).chart = lib;
})(typeof self !== 'undefined' ? self : this, () => {
  /** Loại biểu đồ được phép. Ngoài danh sách này thì coi như khối mã thường. */
  const TYPES = ['bar', 'line', 'pie', 'doughnut', 'radar', 'polarArea', 'scatter'];

  /** Trần kích thước, để một câu trả lời hỏng không làm treo cửa sổ. */
  const MAX_DATASETS = 8;
  const MAX_POINTS = 200;
  const MAX_LABEL = 80;

  /*
   * Bảng màu hổ phách của sản phẩm, chuyển dần sang các sắc ấm lân cận.
   *
   * Cố định ở đây thay vì để mô hình chọn: màu do mô hình bịa ra thường chói và
   * lạc khỏi giao diện, và một chuỗi màu cố định giúp hai biểu đồ cạnh nhau đọc
   * được như cùng một hệ.
   */
  const PALETTE = ['#e3a04b', '#d9784a', '#c9a227', '#b5763f', '#8f9a4e', '#a8643c', '#6f8f6a', '#c08a5e'];

  const isFiniteNumber = (value) => typeof value === 'number' && Number.isFinite(value);

  /** Nhãn về chuỗi, cắt ngắn để trục không bị một nhãn dài đẩy vỡ. */
  const label = (value) => {
    if (value === null || value === undefined) return '';

    return String(value).slice(0, MAX_LABEL);
  };

  /**
   * Một điểm dữ liệu.
   *
   * Nhận số, chuỗi số ("1.250" hay "1,25"), hoặc cặp {x, y} cho scatter. Thứ gì
   * không đọc được thành số trả về `null` — Chart.js hiểu đó là khoảng trống,
   * tốt hơn là vẽ nhầm số 0.
   */
  function point(value) {
    if (isFiniteNumber(value)) return value;

    if (typeof value === 'string') {
      // Bỏ dấu phân cách hàng nghìn rồi đổi dấu thập phân kiểu Việt sang dấu chấm.
      const cleaned = value.trim().replace(/[\s.](?=\d{3}\b)/g, '').replace(',', '.').replace(/[^\d.eE+-]/g, '');
      const parsed = Number.parseFloat(cleaned);

      return Number.isFinite(parsed) ? parsed : null;
    }

    if (value && typeof value === 'object' && isFiniteNumber(value.x) && isFiniteNumber(value.y)) {
      return { x: value.x, y: value.y };
    }

    return null;
  }

  /**
   * Đọc JSON trong khối ```chart và trả về mô tả đã làm sạch.
   *
   * @param {string} source nội dung khối, chưa parse
   * @returns {{ type: string, labels: string[], datasets: object[], title: string }|null}
   */
  function normalize(source) {
    let spec;

    try {
      spec = JSON.parse(String(source));
    } catch {
      return null;
    }

    if (!spec || typeof spec !== 'object' || Array.isArray(spec)) return null;

    const type = TYPES.includes(spec.type) ? spec.type : 'bar';
    const labels = Array.isArray(spec.labels) ? spec.labels.slice(0, MAX_POINTS).map(label) : [];
    const rawSets = Array.isArray(spec.datasets) ? spec.datasets : [];

    const datasets = rawSets
      .slice(0, MAX_DATASETS)
      .filter((set) => set && typeof set === 'object' && Array.isArray(set.data))
      .map((set, index) => ({
        label: label(set.label ?? ''),
        data: set.data.slice(0, MAX_POINTS).map(point),
        color: PALETTE[index % PALETTE.length],
      }))
      .filter((set) => set.data.some((value) => value !== null));

    if (!datasets.length) return null;

    return { type, labels, datasets, title: label(spec.title ?? '') };
  }

  /**
   * Tuỳ chọn Chart.js cho một mô tả đã làm sạch.
   *
   * Tách riêng khỏi `mount` để test được mà không cần DOM hay Chart.js thật.
   *
   * @param {object} spec kết quả của normalize()
   * @param {{ text: string, dim: string, grid: string }} theme màu lấy từ CSS đang dùng
   */
  function config(spec, theme) {
    const round = ['pie', 'doughnut', 'polarArea'].includes(spec.type);
    const many = spec.datasets.length > 1;

    return {
      type: spec.type,
      data: {
        labels: spec.labels,
        datasets: spec.datasets.map((set) => ({
          label: set.label,
          data: set.data,
          // Biểu đồ tròn tô mỗi miếng một màu; các loại khác một màu cho cả chuỗi.
          backgroundColor: round ? PALETTE : (spec.type === 'line' ? 'transparent' : set.color),
          borderColor: set.color,
          borderWidth: spec.type === 'line' ? 2 : 1,
          pointBackgroundColor: set.color,
          pointRadius: spec.type === 'line' ? 2.5 : 3,
          tension: spec.type === 'line' ? 0.28 : 0,
          fill: false,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        // Tắt hiệu ứng: biểu đồ hiện ra sau khi mô hình trả lời xong, thêm màn
        // chạy số chỉ làm người đọc phải chờ thêm.
        animation: false,
        plugins: {
          // Một chuỗi thì nhãn đã nằm ở tiêu đề hoặc trục, chú giải là thừa.
          legend: {
            display: round || many,
            labels: { color: theme.dim, boxWidth: 12, boxHeight: 12, font: { size: 11 } },
          },
          title: {
            display: Boolean(spec.title),
            text: spec.title,
            color: theme.text,
            font: { size: 13, weight: '600' },
            padding: { bottom: 12 },
          },
          tooltip: {
            backgroundColor: '#17150f',
            borderColor: theme.grid,
            borderWidth: 1,
            titleColor: theme.text,
            bodyColor: theme.dim,
            padding: 9,
          },
        },
        scales: round ? {} : {
          x: { ticks: { color: theme.dim, font: { size: 11 } }, grid: { color: theme.grid } },
          y: { ticks: { color: theme.dim, font: { size: 11 } }, grid: { color: theme.grid }, beginAtZero: true },
        },
      },
    };
  }

  /**
   * Biến một khối ```chart đã render thành biểu đồ thật.
   *
   * Không tự tìm phần tử: chỗ gọi truyền vào thẻ <pre> đã dựng, vì chỉ nó biết
   * lúc nào câu trả lời đã trọn vẹn. Trả về instance Chart để hủy khi cần.
   *
   * @param {HTMLElement} pre thẻ <pre data-lang="chart">
   * @param {object} deps { Chart, theme, labels } — tách ra để test không cần DOM
   * @returns {object|null} instance Chart, hoặc null nếu spec không hợp lệ
   */
  function mount(pre, { Chart, theme, labels = {} }) {
    const spec = normalize(pre.textContent || '');

    if (!spec) return null;

    const figure = document.createElement('figure');
    figure.className = 'md-chart';
    figure.setAttribute('data-md-chart', '');

    const canvas = document.createElement('canvas');
    // Chart.js đặt kích thước thật qua CSSOM; hai thuộc tính này chỉ là tỉ lệ
    // ban đầu để khung không nhảy trước lúc vẽ.
    canvas.width = 640;
    canvas.height = 320;
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', spec.title || labels.chart || 'Biểu đồ');

    figure.append(canvas);
    pre.replaceWith(figure);

    const instance = new Chart(canvas, config(spec, theme));

    // Giữ lại mô tả để nút xuất file dùng lại mà không phải đọc ngược từ canvas.
    figure.chartSpec = spec;

    return instance;
  }

  return { TYPES, PALETTE, normalize, config, mount };
});
