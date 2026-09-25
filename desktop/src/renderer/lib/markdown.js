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
