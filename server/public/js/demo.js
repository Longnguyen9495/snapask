(() => {
  'use strict';

  /*
   * Bản dùng thử trên trang chủ.
   *
   * Khách chọn một màn hình mẫu, kéo chọn một vùng, rồi hỏi. Câu trả lời do mô
   * hình thật sinh ra — đó là điểm khác biệt so với bản trước, vốn chỉ đọc lại
   * bốn câu viết sẵn. Người đang cân nhắc mua cần thấy sản phẩm chạy, không cần
   * nghe kể nó chạy thế nào.
   *
   * Tách khỏi landing.js: file kia lo hiệu ứng cuộn của cả trang, còn đoạn này
   * là một tính năng có trạng thái và có gọi mạng.
   */

  const root = document.querySelector('[data-try-demo]');

  if (!root) return;

  const $ = (sel) => root.querySelector(sel);

  const screen = $('[data-demo-screen]');
  const selection = $('[data-demo-selection]');
  const hint = $('[data-demo-hint]');
  const empty = $('[data-demo-empty]');
  const form = $('[data-demo-form]');
  const question = $('[data-demo-question]');
  const preview = $('[data-demo-preview]');
  const chips = $('[data-demo-chips]');
  const submit = $('[data-demo-submit]');
  const thinking = $('[data-demo-thinking]');
  const result = $('[data-demo-result]');
  const resultLabel = $('[data-demo-result-label]');
  const answer = $('[data-demo-answer]');
  const cta = $('[data-demo-cta]');
  const errorBox = $('[data-demo-error]');
  const status = $('[data-demo-status]');

  if (!screen || !form || !question || !answer) return;

  const tabs = [...document.querySelectorAll('[data-scene-tab]')];
  const panels = [...document.querySelectorAll('[data-scene-panel]')];

  const live = root.dataset.live === '1';
  const endpoint = root.dataset.endpoint;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  /** Dữ liệu do Blade đặt sẵn: gợi ý câu hỏi, nhãn vùng chọn, chữ trạng thái. */
  const data = (() => {
    try {
      return JSON.parse(document.getElementById('demo-data')?.textContent || '{}');
    } catch {
      return {};
    }
  })();

  const scenes = data.scenes || {};
  const t = (key) => (data.strings || {})[key] || key;

  let current = tabs[0]?.dataset.sceneTab || Object.keys(scenes)[0];
  let dragStart = null;
  let busy = false;

  /* ---------- chuyển màn hình mẫu ---------- */

  function showScene(key, { focusTab = false } = {}) {
    current = key;

    for (const tab of tabs) {
      const on = tab.dataset.sceneTab === key;
      tab.setAttribute('aria-selected', String(on));
      tab.tabIndex = on ? 0 : -1;
      if (on && focusTab) tab.focus();
    }

    for (const panel of panels) panel.hidden = panel.dataset.scenePanel !== key;

    if (hint) hint.textContent = scenes[key]?.hint || hint.textContent;

    reset();
  }

  for (const [index, tab] of tabs.entries()) {
    tab.addEventListener('click', () => showScene(tab.dataset.sceneTab));

    // Mũi tên trái phải đi giữa các tab, đúng như trình đọc màn hình chờ đợi ở
    // một nhóm role="tablist".
    tab.addEventListener('keydown', (event) => {
      const step = event.key === 'ArrowRight' ? 1 : (event.key === 'ArrowLeft' ? -1 : 0);

      if (!step) return;

      event.preventDefault();
      const next = tabs[(index + step + tabs.length) % tabs.length];
      showScene(next.dataset.sceneTab, { focusTab: true });
    });
  }

  /* ---------- các bước ---------- */

  function setStep(step) {
    root.dataset.step = step;

    if (empty) empty.hidden = step !== 'select';
    form.hidden = step !== 'ask';
    if (thinking) thinking.hidden = step !== 'thinking';
    if (result) result.hidden = step !== 'answer';

    if (!status) return;

    status.textContent = {
      select: t('Drag around what you want to ask about.'),
      ask: t('Type a question, or pick one below.'),
      thinking: t('Reading the selected context…'),
      answer: t('Answer'),
    }[step] || '';
  }

  function reset() {
    selection?.classList.remove('is-active');
    selection?.removeAttribute('style');
    question.value = '';
    if (errorBox) errorBox.hidden = true;
    if (cta) cta.hidden = true;
    setStep('select');
  }

  /* ---------- chọn vùng ---------- */

  /** Khoanh sẵn đúng phần đáng hỏi, dùng cho bàn phím và cho cú kéo quá ngắn. */
  function selectTarget() {
    const panel = panels.find((p) => p.dataset.scenePanel === current);
    const targets = panel ? [...panel.querySelectorAll('.try-code__target')] : [];

    if (!targets.length) return finishSelection();

    const box = screen.getBoundingClientRect();
    const first = targets[0].getBoundingClientRect();
    const last = targets[targets.length - 1].getBoundingClientRect();

    place(
      first.left - box.left - 8,
      first.top - box.top - 6,
      Math.max(...targets.map((el) => el.getBoundingClientRect().width)) + 16,
      last.bottom - first.top + 12,
    );

    finishSelection();
  }

  function place(left, top, width, height) {
    if (!selection) return;

    selection.style.left = `${Math.max(0, left)}px`;
    selection.style.top = `${Math.max(0, top)}px`;
    selection.style.width = `${width}px`;
    selection.style.height = `${height}px`;
    selection.classList.add('is-active');
  }

  function finishSelection() {
    if (preview) preview.textContent = scenes[current]?.selection || '';

    renderChips();
    setStep('ask');
    window.setTimeout(() => question.focus(), 160);
  }

  screen.addEventListener('pointerdown', (event) => {
    if (root.dataset.step !== 'select') return;

    const box = screen.getBoundingClientRect();
    dragStart = { x: event.clientX - box.left, y: event.clientY - box.top };
    place(dragStart.x, dragStart.y, 0, 0);
    screen.setPointerCapture(event.pointerId);
  });

  screen.addEventListener('pointermove', (event) => {
    if (!dragStart) return;

    const box = screen.getBoundingClientRect();
    const x = Math.max(0, Math.min(event.clientX - box.left, box.width));
    const y = Math.max(0, Math.min(event.clientY - box.top, box.height));

    place(Math.min(dragStart.x, x), Math.min(dragStart.y, y), Math.abs(x - dragStart.x), Math.abs(y - dragStart.y));
  });

  screen.addEventListener('pointerup', (event) => {
    if (!dragStart) return;

    const box = selection.getBoundingClientRect();

    dragStart = null;
    screen.releasePointerCapture(event.pointerId);

    // Chạm nhẹ hay kéo quá ngắn: hiểu là "chọn giúp tôi" thay vì để lại một ô
    // bé xíu rồi bắt người ta kéo lại.
    if (box.width < 30 || box.height < 24) return selectTarget();

    finishSelection();
  });

  screen.addEventListener('keydown', (event) => {
    if ((event.key === 'Enter' || event.key === ' ') && root.dataset.step === 'select') {
      event.preventDefault();
      selectTarget();
    }
  });

  /* ---------- gợi ý câu hỏi ---------- */

  function renderChips() {
    if (!chips) return;

    chips.replaceChildren();

    for (const text of scenes[current]?.questions || []) {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'try-chip';
      chip.textContent = text;
      chip.addEventListener('click', () => {
        question.value = text;
        question.focus();
      });
      chips.append(chip);
    }
  }

  /* ---------- hỏi ---------- */

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (busy) return;

    const text = question.value.trim();

    if (!text) {
      showError(t('Type a question first.'));
      question.focus();

      return;
    }

    if (!live || !endpoint) return showSampleAnswer();

    busy = true;
    if (errorBox) errorBox.hidden = true;
    if (submit) submit.textContent = t('Asking…');
    setStep('thinking');

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        body: JSON.stringify({ scene: current, question: text }),
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        // Máy chủ đã soạn sẵn câu tiếng người cho từng trường hợp chạm trần;
        // dùng đúng câu đó thay vì dịch lại mã lỗi ở đây.
        setStep('ask');
        showError(payload.message || t('Something went wrong. Please try again.'));

        // Hết lượt là lúc hợp lý nhất để mời tạo tài khoản.
        if (response.status === 429 && cta) cta.hidden = false;

        return;
      }

      showAnswer(payload.answer || '', { sample: false });
    } catch {
      setStep('ask');
      showError(t('Something went wrong. Please try again.'));
    } finally {
      busy = false;
      if (submit) submit.textContent = t('Ask SnapAsk');
    }
  });

  function showError(message) {
    if (!errorBox) return;

    errorBox.textContent = message;
    errorBox.hidden = false;
  }

  /** Bản dùng thử đang tắt: vẫn cho khách đi hết luồng, bằng một câu mẫu. */
  function showSampleAnswer() {
    showAnswer(scenes[current]?.sample || '', { sample: true });
  }

  function showAnswer(markdown, { sample }) {
    if (resultLabel) resultLabel.textContent = sample ? t('Sample answer') : t('Answer');

    answer.replaceChildren(...render(markdown));

    setStep('answer');
    if (cta) cta.hidden = false;
    result?.focus();
  }

  /* ---------- dựng câu trả lời ---------- */

  /*
   * Markdown tối giản, dựng bằng DOM chứ không bằng chuỗi HTML.
   *
   * Nội dung này do mô hình sinh ra, tức dữ liệu không tin được. Tạo node rồi
   * gán `textContent` thì không có đường nào để một chuỗi trở thành thẻ thật —
   * an toàn hơn hẳn việc ghép chuỗi rồi nhét vào `innerHTML`.
   */
  function render(source) {
    const lines = String(source || '').replace(/\r\n?/g, '\n').split('\n');
    const out = [];

    let list = null;
    let para = [];
    let table = null;
    let fence = null;

    const flushPara = () => {
      if (!para.length) return;

      const p = document.createElement('p');
      // Qua `inline` để `**đậm**` và `mã` thành thẻ thật. Gán thẳng textContent
      // sẽ để nguyên dấu sao trước mắt người đọc.
      p.append(...inline(para.join(' ')));
      out.push(p);
      para = [];
    };

    const flushList = () => {
      if (list) out.push(list);
      list = null;
    };

    const flushTable = () => {
      if (table) out.push(wrapTable(table));
      table = null;
    };

    const flushAll = () => { flushPara(); flushList(); flushTable(); };

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      const fenceMark = line.match(/^\s*```\s*([\w+-]*)\s*$/);

      if (fenceMark) {
        if (fence) {
          out.push(fence.node);
          fence = null;
        } else {
          flushAll();
          const pre = document.createElement('pre');
          const code = document.createElement('code');
          pre.append(code);
          fence = { node: pre, code, lang: fenceMark[1] };
        }

        continue;
      }

      if (fence) {
        fence.code.textContent += (fence.code.textContent ? '\n' : '') + line;

        continue;
      }

      // Bảng: dòng tiêu đề cộng dòng phân cách. Đây là thứ đáng khoe nhất khi
      // khách chụp một bảng số liệu.
      if (line.includes('|') && /^\s{0,3}\|?\s*:?-{1,}:?\s*(\|\s*:?-{1,}:?\s*)*\|?\s*$/.test(lines[i + 1] || '')) {
        flushPara();
        flushList();

        const heads = cells(line);
        const aligns = cells(lines[i + 1]).map((spec) => {
          const left = spec.startsWith(':');
          const right = spec.endsWith(':');

          return left && right ? 'center' : (right ? 'right' : '');
        });

        table = { heads, aligns, rows: [] };
        i++;

        while (i + 1 < lines.length && lines[i + 1].includes('|') && lines[i + 1].trim() !== '') {
          i++;
          const row = cells(lines[i]);
          while (row.length < heads.length) row.push('');
          table.rows.push(row.slice(0, heads.length));
        }

        flushTable();

        continue;
      }

      const bullet = line.match(/^\s{0,3}[-*+]\s+(.*)$/);
      const ordered = line.match(/^\s{0,3}\d+[.)]\s+(.*)$/);

      if (bullet || ordered) {
        flushPara();
        flushTable();

        const type = bullet ? 'UL' : 'OL';

        if (!list || list.tagName !== type) {
          flushList();
          list = document.createElement(type.toLowerCase());
        }

        const li = document.createElement('li');
        li.append(...inline((bullet || ordered)[1]));
        list.append(li);

        continue;
      }

      const heading = line.match(/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/);

      if (heading) {
        flushAll();
        const h = document.createElement('h4');
        h.append(...inline(heading[1]));
        out.push(h);

        continue;
      }

      if (line.trim() === '') {
        flushAll();

        continue;
      }

      para.push(line.trim());
    }

    if (fence) out.push(fence.node);
    flushAll();

    return out;
  }

  const cells = (line) => line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((c) => c.trim());

  function wrapTable({ heads, aligns, rows }) {
    const wrap = document.createElement('div');
    wrap.className = 'md-table';

    const el = document.createElement('table');
    const thead = document.createElement('thead');
    const hr = document.createElement('tr');

    heads.forEach((text, index) => {
      const th = document.createElement('th');
      th.append(...inline(text));
      if (aligns[index]) th.className = `md-${aligns[index]}`;
      hr.append(th);
    });

    thead.append(hr);
    el.append(thead);

    if (rows.length) {
      const tbody = document.createElement('tbody');

      for (const row of rows) {
        const tr = document.createElement('tr');

        row.forEach((text, index) => {
          const td = document.createElement('td');
          td.append(...inline(text));
          if (aligns[index]) td.className = `md-${aligns[index]}`;
          tr.append(td);
        });

        tbody.append(tr);
      }

      el.append(tbody);
    }

    wrap.append(el);

    return wrap;
  }

  /** Đậm và mã trong dòng. Trả về mảng node, không bao giờ là chuỗi HTML. */
  function inline(text) {
    const nodes = [];
    const pattern = /(\*\*([^*]+)\*\*|`([^`]+)`)/g;
    let last = 0;
    let match;

    while ((match = pattern.exec(text)) !== null) {
      if (match.index > last) nodes.push(document.createTextNode(text.slice(last, match.index)));

      if (match[2] !== undefined) {
        const strong = document.createElement('strong');
        strong.textContent = match[2];
        nodes.push(strong);
      } else {
        const code = document.createElement('code');
        code.textContent = match[3];
        nodes.push(code);
      }

      last = pattern.lastIndex;
    }

    if (last < text.length) nodes.push(document.createTextNode(text.slice(last)));

    return nodes.length ? nodes : [document.createTextNode(text)];
  }

  /* ---------- khởi động ---------- */

  for (const button of root.querySelectorAll('[data-demo-reset]')) {
    button.addEventListener('click', () => {
      reset();
      screen.focus();
    });
  }

  if (hint) hint.textContent = scenes[current]?.hint || hint.textContent;
  setStep('select');
})();
