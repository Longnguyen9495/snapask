'use strict';

/**
 * Lớp phủ chụp màn hình: chọn vùng, chỉnh lại vùng, ghi chú lên ảnh rồi gửi đi.
 *
 * Mọi toạ độ trong tệp này là pixel thật của ảnh chụp, không phải pixel CSS.
 * Giữ một hệ toạ độ duy nhất như vậy thì ảnh cắt ra vẫn nguyên độ phân giải gốc
 * trên màn hình HiDPI, và không phải quy đổi qua lại ở từng chỗ vẽ.
 */

const board = document.getElementById('board');
const ctx = board.getContext('2d');
const hint = document.getElementById('hint');
const sizeTag = document.getElementById('size');
const tools = document.getElementById('tools');
const colors = document.getElementById('colors');
const textInput = document.getElementById('text-input');
const toast = document.getElementById('toast');

const PALETTE = ['#e5484d', '#f2c14e', '#57ab6a', '#3b82f6', '#ffffff', '#100f0e'];
const HANDLE = 8;
const MIN_SIZE = 8;

const shot = new Image();

/** Số pixel ảnh trên một pixel CSS — suy từ ảnh chụp, không tin devicePixelRatio. */
let scale = 1;

let selection = null;
let shapes = [];
let tool = 'move';
let color = PALETTE[0];
let drag = null;
let cursor = { x: 0, y: 0 };
let pendingText = null;
let sent = false;

const toNative = (event) => ({ x: event.clientX * scale, y: event.clientY * scale });
const toCss = (value) => value / scale;
const clamp = (value, low, high) => Math.min(Math.max(value, low), high);

const normalise = (a, b) => ({
  x: Math.min(a.x, b.x),
  y: Math.min(a.y, b.y),
  w: Math.abs(a.x - b.x),
  h: Math.abs(a.y - b.y),
});

/* ---------- vẽ ---------- */

/**
 * Vẽ một ghi chú lên ngữ cảnh được truyền vào.
 *
 * Nhận `target` chứ không dùng thẳng canvas hiển thị, để lúc xuất ảnh cuối cùng
 * dùng lại đúng mã này thay vì viết một bản vẽ thứ hai dễ lệch.
 */
function drawShape(target, shape) {
  target.save();
  target.strokeStyle = shape.color;
  target.fillStyle = shape.color;
  target.lineWidth = 3 * scale;
  target.lineCap = 'round';
  target.lineJoin = 'round';

  if (shape.type === 'rect') {
    target.strokeRect(shape.x, shape.y, shape.w, shape.h);
  }

  if (shape.type === 'pen' && shape.points.length > 1) {
    target.beginPath();
    target.moveTo(shape.points[0].x, shape.points[0].y);

    for (const point of shape.points.slice(1)) {
      target.lineTo(point.x, point.y);
    }

    target.stroke();
  }

  if (shape.type === 'arrow') {
    const angle = Math.atan2(shape.y2 - shape.y1, shape.x2 - shape.x1);
    const head = 14 * scale;

    target.beginPath();
    target.moveTo(shape.x1, shape.y1);
    target.lineTo(shape.x2, shape.y2);
    target.stroke();

    target.beginPath();
    target.moveTo(shape.x2, shape.y2);
    target.lineTo(shape.x2 - head * Math.cos(angle - 0.4), shape.y2 - head * Math.sin(angle - 0.4));
    target.lineTo(shape.x2 - head * Math.cos(angle + 0.4), shape.y2 - head * Math.sin(angle + 0.4));
    target.closePath();
    target.fill();
  }

  if (shape.type === 'text' && shape.text) {
    target.font = `600 ${16 * scale}px 'Be Vietnam Pro', system-ui, sans-serif`;
    target.textBaseline = 'top';
    target.fillText(shape.text, shape.x, shape.y);
  }

  if (shape.type === 'blur' && shape.w > 1 && shape.h > 1) {
    // Vẽ lại chính vùng ảnh đó qua bộ lọc mờ. Phủ một mảng màu đặc thì kín hơn
    // nhưng người xem mất luôn ngữ cảnh chỗ vừa bị che.
    target.beginPath();
    target.rect(shape.x, shape.y, shape.w, shape.h);
    target.clip();
    target.filter = `blur(${Math.max(4, 8 * scale)}px)`;
    target.drawImage(shot, 0, 0);
  }

  target.restore();
}

function handlePoints(box) {
  const { x, y, w, h } = box;

  return [
    { id: 'nw', x, y },
    { id: 'n', x: x + w / 2, y },
    { id: 'ne', x: x + w, y },
    { id: 'e', x: x + w, y: y + h / 2 },
    { id: 'se', x: x + w, y: y + h },
    { id: 's', x: x + w / 2, y: y + h },
    { id: 'sw', x, y: y + h },
    { id: 'w', x, y: y + h / 2 },
  ];
}

/** Kính lúp lúc đang ngắm, để bắt đúng mép một dòng chữ nhỏ. */
function drawLoupe() {
  const size = 96 * scale;
  const zoom = 6;
  const gap = 22 * scale;

  let x = cursor.x + gap;
  let y = cursor.y + gap;

  if (x + size > board.width) x = cursor.x - gap - size;
  if (y + size + 20 * scale > board.height) y = cursor.y - gap - size;

  ctx.save();
  ctx.beginPath();
  ctx.rect(x, y, size, size);
  ctx.clip();
  ctx.imageSmoothingEnabled = false;
  ctx.drawImage(
    shot,
    cursor.x - size / (2 * zoom), cursor.y - size / (2 * zoom),
    size / zoom, size / zoom,
    x, y, size, size,
  );
  ctx.restore();

  ctx.save();
  ctx.lineWidth = 1 * scale;
  ctx.strokeStyle = 'rgba(255,255,255,.85)';
  ctx.strokeRect(x, y, size, size);

  // Chữ thập chỉ đúng pixel đang trỏ.
  ctx.beginPath();
  ctx.moveTo(x + size / 2, y);
  ctx.lineTo(x + size / 2, y + size);
  ctx.moveTo(x, y + size / 2);
  ctx.lineTo(x + size, y + size / 2);
  ctx.strokeStyle = 'rgba(227,160,75,.95)';
  ctx.stroke();

  ctx.fillStyle = 'rgba(26,24,22,.94)';
  ctx.fillRect(x, y + size, size, 20 * scale);
  ctx.fillStyle = '#f3efe8';
  ctx.font = `${11 * scale}px ui-monospace, monospace`;
  ctx.textBaseline = 'middle';
  ctx.fillText(
    `${Math.round(toCss(cursor.x))}, ${Math.round(toCss(cursor.y))}`,
    x + 6 * scale,
    y + size + 10 * scale,
  );
  ctx.restore();
}

function draw() {
  if (!shot.complete || !shot.naturalWidth) return;

  ctx.clearRect(0, 0, board.width, board.height);
  ctx.drawImage(shot, 0, 0);

  ctx.fillStyle = 'rgba(12, 10, 8, .58)';
  ctx.fillRect(0, 0, board.width, board.height);

  if (!selection) {
    drawLoupe();

    return;
  }

  // Trả lại màu thật cho vùng đã chọn, rồi mới vẽ ghi chú lên trên.
  ctx.save();
  ctx.beginPath();
  ctx.rect(selection.x, selection.y, selection.w, selection.h);
  ctx.clip();
  ctx.drawImage(shot, 0, 0);

  for (const shape of shapes) {
    drawShape(ctx, shape);
  }

  if (drag?.shape) drawShape(ctx, drag.shape);

  ctx.restore();

  ctx.save();
  ctx.strokeStyle = 'rgba(227,160,75,.95)';
  ctx.lineWidth = 1.5 * scale;
  ctx.strokeRect(selection.x, selection.y, selection.w, selection.h);

  if (tool === 'move') {
    ctx.fillStyle = '#e3a04b';
    ctx.strokeStyle = '#fff';

    for (const point of handlePoints(selection)) {
      const side = HANDLE * scale;
      ctx.beginPath();
      ctx.rect(point.x - side / 2, point.y - side / 2, side, side);
      ctx.fill();
      ctx.stroke();
    }
  }

  ctx.restore();
}

const render = () => requestAnimationFrame(draw);

/* ---------- giao diện quanh vùng chọn ---------- */

function layoutChrome() {
  if (!selection) {
    sizeTag.hidden = true;
    tools.hidden = true;
    hint.hidden = false;

    return;
  }

  hint.hidden = true;

  const left = toCss(selection.x);
  const top = toCss(selection.y);
  const width = toCss(selection.w);
  const height = toCss(selection.h);

  sizeTag.hidden = false;
  sizeTag.textContent = `${Math.round(width)} × ${Math.round(height)}`;
  sizeTag.style.left = `${left}px`;
  sizeTag.style.top = top > 28 ? `${top - 26}px` : `${top + 6}px`;

  // Giữ nguyên chỗ thanh công cụ trong lúc kéo: nó nhảy theo từng khung hình thì
  // con trỏ dễ trượt vào đúng lúc người dùng đang nhắm.
  if (drag) return;

  tools.hidden = false;
  const bar = tools.getBoundingClientRect();

  // Nằm dưới vùng chọn; hết chỗ thì lật lên trên, hết nữa thì nằm đè vào trong,
  // chứ không bao giờ tràn khỏi màn hình.
  let barTop = top + height + 10;

  if (barTop + bar.height > window.innerHeight - 8) barTop = top - bar.height - 10;
  if (barTop < 8) barTop = Math.min(top + height - bar.height - 10, window.innerHeight - bar.height - 8);

  tools.style.top = `${Math.max(8, barTop)}px`;
  tools.style.left = `${clamp(left + width - bar.width, 8, window.innerWidth - bar.width - 8)}px`;
}

function setTool(next) {
  tool = next;

  for (const button of document.querySelectorAll('.tool[data-tool]')) {
    button.classList.toggle('tool--on', button.dataset.tool === tool);
  }

  document.body.classList.toggle('is-moving', tool === 'move');
  document.body.classList.toggle('is-typing', tool === 'text');
  render();
}

function flash(message) {
  toast.textContent = message;
  toast.hidden = false;
  clearTimeout(flash.timer);
  flash.timer = setTimeout(() => {
    toast.hidden = true;
  }, 1600);
}

/* ---------- kết quả ---------- */

/** Ảnh cuối cùng: đúng vùng đã chọn, kèm mọi ghi chú, ở độ phân giải gốc. */
function compose() {
  const out = document.createElement('canvas');
  out.width = Math.max(1, Math.round(selection.w));
  out.height = Math.max(1, Math.round(selection.h));

  const paint = out.getContext('2d');
  paint.drawImage(
    shot,
    selection.x, selection.y, selection.w, selection.h,
    0, 0, out.width, out.height,
  );

  // Ghi chú lưu theo toạ độ của cả màn hình, nên dời gốc về góc vùng cắt là vẽ
  // lại được bằng đúng hàm đã dùng để hiển thị.
  paint.translate(-selection.x, -selection.y);

  for (const shape of shapes) {
    drawShape(paint, shape);
  }

  return out.toDataURL('image/png');
}

async function finish(action) {
  if (!selection || selection.w < MIN_SIZE || selection.h < MIN_SIZE) return;

  commitText();
  const dataUrl = compose();

  if (action === 'copy') {
    await window.overlay.copy(dataUrl);
    flash('Đã sao chép ảnh vào clipboard.');

    return;
  }

  if (action === 'save') {
    const saved = await window.overlay.save(dataUrl);
    flash(saved ? 'Đã lưu ảnh.' : 'Chưa lưu ảnh.');

    return;
  }

  sent = true;
  window.overlay.done({
    dataUrl,
    rect: {
      x: Math.round(toCss(selection.x)),
      y: Math.round(toCss(selection.y)),
      width: Math.round(toCss(selection.w)),
      height: Math.round(toCss(selection.h)),
    },
  });
}

/* ---------- chữ ---------- */

function openText(point) {
  pendingText = point;
  textInput.value = '';
  textInput.hidden = false;
  textInput.style.left = `${toCss(point.x)}px`;
  textInput.style.top = `${toCss(point.y)}px`;
  textInput.style.color = color;
  textInput.focus();
}

function commitText() {
  if (!pendingText) return;

  const text = textInput.value.trim();

  if (text) shapes.push({ type: 'text', x: pendingText.x, y: pendingText.y, text, color });

  pendingText = null;
  textInput.hidden = true;
  render();
}

/* ---------- chuột ---------- */

function hitHandle(point) {
  if (tool !== 'move' || !selection) return null;

  const reach = (HANDLE + 4) * scale;

  return handlePoints(selection).find(
    (handle) => Math.abs(handle.x - point.x) <= reach && Math.abs(handle.y - point.y) <= reach,
  ) ?? null;
}

const inside = (point, box) => point.x >= box.x && point.x <= box.x + box.w
  && point.y >= box.y && point.y <= box.y + box.h;

window.addEventListener('mousedown', (event) => {
  if (event.button !== 0 || event.target.closest('.tools') || event.target === textInput) return;

  const point = toNative(event);
  commitText();

  if (!selection) {
    drag = { mode: 'new', origin: point };

    return;
  }

  const handle = hitHandle(point);

  if (handle) {
    drag = { mode: 'resize', handle: handle.id, box: { ...selection } };

    return;
  }

  if (tool === 'move') {
    if (inside(point, selection)) {
      drag = { mode: 'move', origin: point, box: { ...selection } };
    } else {
      // Bấm ra ngoài vùng đã chọn thì chọn lại từ đầu.
      selection = null;
      shapes = [];
      drag = { mode: 'new', origin: point };
      layoutChrome();
    }

    return;
  }

  if (tool === 'text') {
    if (inside(point, selection)) openText(point);

    return;
  }

  if (!inside(point, selection)) return;

  drag = {
    mode: 'draw',
    origin: point,
    shape: tool === 'pen'
      ? { type: 'pen', color, points: [point] }
      : {
        type: tool,
        color,
        x: point.x,
        y: point.y,
        w: 0,
        h: 0,
        x1: point.x,
        y1: point.y,
        x2: point.x,
        y2: point.y,
      },
  };
});

window.addEventListener('mousemove', (event) => {
  cursor = toNative(event);

  if (!drag) {
    render();

    return;
  }

  if (drag.mode === 'new') {
    selection = normalise(drag.origin, cursor);
  }

  if (drag.mode === 'move') {
    selection = {
      ...drag.box,
      x: clamp(drag.box.x + (cursor.x - drag.origin.x), 0, board.width - drag.box.w),
      y: clamp(drag.box.y + (cursor.y - drag.origin.y), 0, board.height - drag.box.h),
    };
  }

  if (drag.mode === 'resize') {
    const box = drag.box;
    const edges = { left: box.x, top: box.y, right: box.x + box.w, bottom: box.y + box.h };

    if (drag.handle.includes('w')) edges.left = clamp(cursor.x, 0, edges.right - MIN_SIZE);
    if (drag.handle.includes('e')) edges.right = clamp(cursor.x, edges.left + MIN_SIZE, board.width);
    if (drag.handle.includes('n')) edges.top = clamp(cursor.y, 0, edges.bottom - MIN_SIZE);
    if (drag.handle.includes('s')) edges.bottom = clamp(cursor.y, edges.top + MIN_SIZE, board.height);

    selection = {
      x: edges.left,
      y: edges.top,
      w: edges.right - edges.left,
      h: edges.bottom - edges.top,
    };
  }

  if (drag.mode === 'draw') {
    const shape = drag.shape;

    if (shape.type === 'pen') {
      shape.points.push(cursor);
    } else {
      Object.assign(shape, normalise(drag.origin, cursor), {
        x1: drag.origin.x,
        y1: drag.origin.y,
        x2: cursor.x,
        y2: cursor.y,
      });
    }
  }

  layoutChrome();
  render();
});

window.addEventListener('mouseup', () => {
  if (!drag) return;

  if (drag.mode === 'draw') {
    const shape = drag.shape;
    const drawn = shape.type === 'pen' ? shape.points.length > 1 : shape.w > 2 || shape.h > 2;

    if (drawn) shapes.push(shape);
  }

  // Một cú bấm lỡ tay không được biến thành vùng chọn bé xíu.
  if (drag.mode === 'new' && selection && (selection.w < MIN_SIZE || selection.h < MIN_SIZE)) {
    selection = null;
  }

  drag = null;
  layoutChrome();
  render();
});

/* ---------- thanh công cụ ---------- */

for (const value of PALETTE) {
  const swatch = document.createElement('button');
  swatch.type = 'button';
  swatch.className = value === color ? 'swatch swatch--on' : 'swatch';
  swatch.style.background = value;
  swatch.dataset.color = value;
  colors.append(swatch);
}

tools.addEventListener('click', (event) => {
  const button = event.target.closest('button');

  if (!button) return;

  if (button.dataset.tool) {
    setTool(button.dataset.tool);

    return;
  }

  if (button.dataset.color) {
    color = button.dataset.color;

    for (const swatch of colors.children) {
      swatch.classList.toggle('swatch--on', swatch.dataset.color === color);
    }

    return;
  }

  if (button.dataset.action === 'undo') {
    shapes.pop();
    render();

    return;
  }

  finish(button.dataset.action);
});

/* ---------- bàn phím ---------- */

textInput.addEventListener('keydown', (event) => {
  // Phím gõ vào ô chữ không được rơi xuống phím tắt toàn cục bên dưới.
  event.stopPropagation();

  if (event.key === 'Enter') commitText();

  if (event.key === 'Escape') {
    pendingText = null;
    textInput.hidden = true;
  }
});

window.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    window.overlay.cancel();

    return;
  }

  if (event.key === 'Enter') {
    finish('ask');

    return;
  }

  if (!event.ctrlKey) return;

  if (event.key === 'z') {
    shapes.pop();
    render();
  }

  if (event.key === 'c') finish('copy');
  if (event.key === 's') finish('save');
});

// Chuột phải: bỏ ghi chú cuối, hết ghi chú thì bỏ vùng chọn, rồi mới thoát hẳn.
window.addEventListener('contextmenu', (event) => {
  event.preventDefault();

  if (shapes.length > 0) {
    shapes.pop();
  } else if (selection) {
    selection = null;
  } else {
    window.overlay.cancel();

    return;
  }

  layoutChrome();
  render();
});

window.addEventListener('blur', () => {
  // Mất tiêu điểm nghĩa là có thứ khác chen lên trên; đóng lại thay vì để một
  // tấm ảnh tĩnh phủ kín màn hình mà người dùng không gỡ được.
  if (!sent) window.overlay.cancel();
});

window.overlay.onImage(({ dataUrl, cssWidth }) => {
  shot.onload = () => {
    board.width = shot.naturalWidth;
    board.height = shot.naturalHeight;
    scale = shot.naturalWidth / cssWidth;
    render();
  };

  shot.src = dataUrl;
});
