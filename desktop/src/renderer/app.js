'use strict';

const thread = document.getElementById('thread');
const preview = document.getElementById('preview');
const previewImage = document.getElementById('preview-image');
const previewMeta = document.getElementById('preview-meta');
const emptyState = document.getElementById('empty');
const question = document.getElementById('question');
const send = document.getElementById('send');

let capture = null;
let conversationId = null;
let streaming = null;
let activeStep = null;

const escapeHtml = (text) => text.replace(/[&<>"]/g, (char) => (
  { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]
));

/**
 * Định dạng tối thiểu cho câu trả lời: khối mã, mã trong dòng, in đậm.
 *
 * Tự viết thay vì kéo một thư viện markdown về, vì nội dung đến từ mô hình là
 * dữ liệu không tin được — ở đây mọi thứ đều đã escape trước khi chèn thẻ.
 */
function render(text) {
  return escapeHtml(text)
    .replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => `<pre><code>${code.replace(/\n$/, '')}</code></pre>`)
    .replace(/`([^`\n]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
}

function addMessage(role, text = '') {
  emptyState.hidden = true;

  const node = document.createElement('div');
  node.className = `msg msg--${role}`;
  node.innerHTML = render(text);
  thread.append(node);
  thread.scrollTop = thread.scrollHeight;

  return node;
}

function setBusy(busy) {
  send.disabled = busy;
  send.textContent = busy ? '…' : window.i18n.t('Ask');
}

window.snapask.onCapture((payload) => {
  capture = payload;
  conversationId = null;
  streaming = null;
  activeStep = null;
  thread.replaceChildren(emptyState);
  emptyState.hidden = false;

  previewImage.src = payload.image;
  previewMeta.textContent = `${payload.width} × ${payload.height} px · ${Math.round(payload.bytes / 1024)} KB`;
  preview.hidden = false;

  question.value = '';
  question.focus();
  setBusy(false);
});

/**
 * Đánh dấu bước đang chạy là đã xong, khi có chữ mới hoặc lượt kết thúc.
 *
 * Giữ tham chiếu tới đúng một nút thay vì dò lại cả cây: hàm này chạy ở mỗi
 * token của câu trả lời, mà một câu trả lời dài có hàng trăm token.
 */
function settleStep() {
  if (!activeStep) return;

  activeStep.classList.add('step--done');
  activeStep = null;
}

window.snapask.onEvent((event) => {
  // Mô hình đang gọi dịch vụ của khách: nói rõ bước nào, để người dùng hiểu vì
  // sao phải chờ thay vì nhìn một màn hình đứng im.
  if (event.type === 'tool') {
    settleStep();

    emptyState.hidden = true;

    activeStep = document.createElement('div');
    activeStep.className = 'step';
    activeStep.textContent = event.label;
    thread.append(activeStep);
    thread.scrollTop = thread.scrollHeight;

    // Chữ tiếp theo thuộc về một câu trả lời mới, không nối vào đoạn đang dở.
    streaming = null;

    return;
  }

  if (event.type === 'delta') {
    settleStep();

    if (!streaming) {
      streaming = { node: addMessage('ai'), text: '' };
      streaming.node.classList.add('msg--typing');
    }

    streaming.text += event.text;
    streaming.node.innerHTML = render(streaming.text);
    thread.scrollTop = thread.scrollHeight;

    return;
  }

  if (event.type === 'done') {
    settleStep();

    if (streaming) streaming.node.classList.remove('msg--typing');

    // Lượt sau gửi kèm id hội thoại để máy chủ nối tiếp ngữ cảnh mà không phải
    // đính lại ảnh — ảnh gửi lại mỗi lượt là khoản tốn token lớn nhất.
    conversationId = event.conversation_id || conversationId;
    streaming = null;
    setBusy(false);

    return;
  }

  if (event.type === 'error') {
    settleStep();

    if (streaming) streaming.node.classList.remove('msg--typing');

    streaming = null;
    addMessage('error', event.message || window.i18n.t('An error occurred.'));
    setBusy(false);
  }
});

async function ask() {
  const text = question.value.trim();

  if (!text || send.disabled) return;

  addMessage('user', text);
  question.value = '';
  question.style.height = 'auto';
  setBusy(true);

  await window.snapask.ask({
    conversationId,
    question: text,
    // Ảnh chỉ đi kèm lượt đầu của mỗi hội thoại.
    imageDataUrl: conversationId ? null : capture?.image,
  });
}

send.addEventListener('click', ask);

question.addEventListener('input', () => {
  question.style.height = 'auto';
  question.style.height = `${Math.min(question.scrollHeight, 120)}px`;
});

question.addEventListener('keydown', (event) => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    ask();
  }
});

window.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    window.snapask.stop();
    window.snapask.hide();
  }
});

document.getElementById('close').addEventListener('click', () => {
  window.snapask.stop();
  window.snapask.hide();
});

document.getElementById('recapture').addEventListener('click', () => {
  window.snapask.stop();
  window.snapask.recapture();
});

/*
 * Nạp từ điển rồi dịch cửa sổ.
 *
 * Nhãn nút Hỏi do JS dựng nên phải vẽ lại tay mỗi lần đổi ngôn ngữ; phần còn
 * lại đã có data-i18n lo. Trạng thái bận đọc thẳng từ nút, vì đó là nơi duy
 * nhất nó được lưu.
 */
window.i18n.start(() => setBusy(send.disabled));
