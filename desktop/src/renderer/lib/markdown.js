'use strict';

/**
 * Dựng HTML từ markdown của câu trả lời, an toàn trước nội dung do mô hình sinh.
 *
 * Nguyên tắc duy nhất: thoát toàn bộ HTML trước, rồi mới chèn thẻ do chính mã
 * này sinh ra. Không có đường nào để chuỗi từ mô hình trở thành thẻ HTML thật.
 * Link chỉ nhận http/https và không bao giờ là href thật — renderer bắt sự kiện
 * bấm rồi nhờ tiến trình chính mở bằng trình duyệt qua danh sách cho phép.
 *
 * Hỗ trợ đúng những gì câu trả lời hay dùng: khối mã, mã trong dòng, đậm,
 * nghiêng, tiêu đề, danh sách, trích dẫn, link. Không cố làm đủ CommonMark.
 *
 * Nạp được cả bằng <script> (gắn vào window.SnapAskLib) lẫn require() khi test.
 */
(function (root, factory) {
  const lib = factory();

  if (typeof module === 'object' && module.exports) module.exports = lib;
  else (root.SnapAskLib = root.SnapAskLib || {}).markdown = lib;
})(typeof self !== 'undefined' ? self : this, () => {
  const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (char) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]
  ));

  /** Định dạng trong một dòng. Nhận chữ ĐÃ thoát HTML. */
  function inline(escaped) {
    const codes = [];

    // Cất mã trong dòng ra trước để ký hiệu bên trong nó không bị hiểu là đậm/nghiêng.
    let text = escaped.replace(/`([^`\n]+)`/g, (match, code) => {
      codes.push(code);

      return `\u0000${codes.length - 1}\u0000`;
    });

    text = text
      .replace(/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g, (match, label, url) => `<a href="#" data-href="${url}" class="md-link">${label}</a>`)
      .replace(/(^|[\s(])(https?:\/\/[^\s<]+[^\s<.,;:!?)"'])/g, (match, lead, url) => `${lead}<a href="#" data-href="${url}" class="md-link">${url}</a>`)
      .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
      .replace(/__([^_\n]+)__/g, '<strong>$1</strong>')
      .replace(/(^|[^*\w])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')
      .replace(/~~([^~\n]+)~~/g, '<del>$1</del>');

    return text.replace(/\u0000(\d+)\u0000/g, (match, index) => `<code>${codes[Number(index)]}</code>`);
  }

  /** Tách một dòng bảng thành các ô, bỏ dấu `|` ở hai đầu. */
  const cellsOf = (line) => line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((cell) => cell.trim());

  /**
   * Nhận diện bảng GitHub bắt đầu tại dòng `start`.
   *
   * Trả `null` khi không phải bảng, để chỗ gọi đi tiếp các luật khác. Số ô của
   * mỗi hàng được ép về đúng số cột của tiêu đề: mô hình hay trả thừa hoặc
   * thiếu một ô, mà thừa thì bảng vỡ còn thiếu thì lệch cột.
   *
   * @param {string[]} lines toàn bộ dòng
   * @param {number} start chỉ số dòng tiêu đề
   * @returns {{ html: string, end: number }|null} `end` là chỉ số dòng cuối của bảng
   */
  function matchTable(lines, start) {
    const head = lines[start];
    const divider = lines[start + 1];

    if (!head || !divider || !head.includes('|')) return null;
    if (!/^\s{0,3}\|?\s*:?-{1,}:?\s*(\|\s*:?-{1,}:?\s*)*\|?\s*$/.test(divider)) return null;

    const headers = cellsOf(head);
    const aligns = cellsOf(divider).map((spec) => {
      const left = spec.startsWith(':');
      const right = spec.endsWith(':');

      if (left && right) return 'center';

      return right ? 'right' : (left ? 'left' : '');
    });

    if (headers.length < 1 || aligns.length !== headers.length) return null;

    // Căn lề đi qua class chứ không qua thuộc tính style: CSP của workspace
    // không cho phép style nội tuyến.
    const cell = (tag, text, index) => {
      const align = aligns[index];

      return `<${tag}${align ? ` class="md-${align}"` : ''}>${inline(escapeHtml(text))}</${tag}>`;
    };

    const rows = [];
    let end = start + 1;

    for (let i = start + 2; i < lines.length; i++) {
      const line = lines[i];

      if (!line.includes('|') || /^\s*$/.test(line)) break;

      const cells = cellsOf(line);

      while (cells.length < headers.length) cells.push('');
      rows.push(cells.slice(0, headers.length));
      end = i;
    }

    const thead = `<thead><tr>${headers.map((text, index) => cell('th', text, index)).join('')}</tr></thead>`;
    const tbody = rows.length
      ? `<tbody>${rows.map((cells) => `<tr>${cells.map((text, index) => cell('td', text, index)).join('')}</tr>`).join('')}</tbody>`
      : '';

    // `data-md-table` để lớp bên ngoài tìm lại bảng mà gắn nút xuất file.
    return { html: `<div class="md-table" data-md-table><table>${thead}${tbody}</table></div>`, end };
  }

  /**
   * @param {string} source markdown thô từ mô hình
   * @param {{ streaming?: boolean }} options đang stream thì khối mã chưa đóng vẫn hiện như khối mã
   * @returns {string} HTML an toàn
   */
  function render(source, { streaming = false } = {}) {
    const lines = String(source ?? '').replace(/\r\n?/g, '\n').split('\n');
    const out = [];
    let paragraph = [];
    let list = null;

    const flushParagraph = () => {
      if (paragraph.length) {
        out.push(`<p>${paragraph.map((line) => inline(escapeHtml(line))).join('<br>')}</p>`);
        paragraph = [];
      }
    };

    const flushList = () => {
      if (list) {
        out.push(`<${list.type}>${list.items.map((item) => `<li>${inline(escapeHtml(item))}</li>`).join('')}</${list.type}>`);
        list = null;
      }
    };

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      const fence = line.match(/^\s*```\s*([\w+-]*)\s*$/);

      if (fence) {
        flushParagraph();
        flushList();

        const code = [];
        let closed = false;

        for (i += 1; i < lines.length; i++) {
          if (/^\s*```\s*$/.test(lines[i])) {
            closed = true;
            break;
          }

          code.push(lines[i]);
        }

        if (!closed && !streaming && code.length === 0) continue;

        const lang = fence[1] ? ` data-lang="${escapeHtml(fence[1])}"` : '';
        out.push(`<pre${lang}><code>${escapeHtml(code.join('\n'))}</code></pre>`);

        continue;
      }

      /*
       * Bảng kiểu GitHub.
       *
       * Phải đứng trước heading và danh sách: dòng phân cách `|---|:--:|` cũng
       * khớp luật đường kẻ ngang nếu để nó xét trước. Bảng chỉ thành hình khi
       * dòng thứ hai đúng là dòng phân cách — một dòng lẻ có dấu `|` vẫn là
       * đoạn văn bình thường.
       */
      const table = matchTable(lines, i);

      if (table) {
        flushParagraph();
        flushList();
        out.push(table.html);
        i = table.end;

        continue;
      }

      const heading = line.match(/^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$/);

      if (heading) {
        flushParagraph();
        flushList();
        const level = Math.min(heading[1].length + 2, 6);
        out.push(`<h${level}>${inline(escapeHtml(heading[2]))}</h${level}>`);

        continue;
      }

      const bullet = line.match(/^\s{0,3}[-*+]\s+(.*)$/);
      const ordered = line.match(/^\s{0,3}\d+[.)]\s+(.*)$/);

      if (bullet || ordered) {
        flushParagraph();
        const type = bullet ? 'ul' : 'ol';

        if (!list || list.type !== type) {
          flushList();
          list = { type, items: [] };
        }

        list.items.push((bullet || ordered)[1]);

        continue;
      }

      const quote = line.match(/^\s{0,3}>\s?(.*)$/);

      if (quote) {
        flushParagraph();
        flushList();
        out.push(`<blockquote>${inline(escapeHtml(quote[1]))}</blockquote>`);

        continue;
      }

      if (/^\s*$/.test(line)) {
        flushParagraph();
        flushList();

        continue;
      }

      if (/^\s{0,3}([-*_])(\s*\1){2,}\s*$/.test(line)) {
        flushParagraph();
        flushList();
        out.push('<hr>');

        continue;
      }

      // Dòng thường nối vào danh sách đang mở thì coi như phần tiếp của mục cuối.
      if (list && /^\s{2,}\S/.test(line)) {
        list.items[list.items.length - 1] += ` ${line.trim()}`;

        continue;
      }

      flushList();
      paragraph.push(line);
    }

    flushParagraph();
    flushList();

    return out.join('');
  }

  /** Chữ thuần từ markdown, cho đoạn xem trước và tiêu đề tạm. */
  function plain(source) {
    return String(source ?? '')
      .replace(/```[\w-]*\n?/g, '')
      .replace(/`([^`]*)`/g, '$1')
      .replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1')
      .replace(/(\*\*|__|~~)(.+?)\1/g, '$2')
      .replace(/^\s{0,3}(#{1,6}\s+|>\s?|[-*+]\s+|\d+[.)]\s+)/gm, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  return { render, plain, escapeHtml };
});
