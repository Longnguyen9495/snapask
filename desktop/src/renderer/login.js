'use strict';

const guest = document.getElementById('guest');
const signed = document.getElementById('signed');
const form = document.getElementById('form');
const error = document.getElementById('error');
const submit = document.getElementById('submit');
const lead = document.getElementById('lead');
const socialNote = document.getElementById('social-note');

const field = (id) => document.getElementById(id);

/** 'login' hoặc 'register' — quyết định hiện ô nào và gọi API nào. */
let mode = 'login';

const COPY = {
  login: {
    lead: 'Chụp một vùng màn hình, hỏi AI ngay tại chỗ.',
    submit: 'Đăng nhập',
    passwordAutocomplete: 'current-password',
  },
  register: {
    lead: 'Tạo tài khoản dùng thử, không cần thẻ.',
    submit: 'Tạo tài khoản',
    passwordAutocomplete: 'new-password',
  },
};

const PROVIDER_NAMES = { google: 'Google', facebook: 'Facebook', zalo: 'Zalo' };

function show(node, message) {
  node.textContent = message ?? '';
  node.hidden = !message;
}

function setMode(next) {
  mode = next;
  const copy = COPY[mode];

  lead.textContent = copy.lead;
  submit.textContent = copy.submit;
  field('password').autocomplete = copy.passwordAutocomplete;

  // Ô chỉ dùng khi đăng ký vừa phải ẩn vừa phải thôi bắt buộc, nếu không trình
  // duyệt sẽ chặn submit vì một ô required đang không nhìn thấy được.
  const registering = mode === 'register';

  for (const label of document.querySelectorAll('.only-register')) {
    label.hidden = !registering;
    label.querySelector('input').required = registering;
  }

  // Con trượt phía sau hai tab chạy theo thuộc tính này.
  document.querySelector('.tabs').dataset.mode = mode;

  for (const tab of document.querySelectorAll('.tabs__tab')) {
    tab.classList.toggle('tabs__tab--on', tab.dataset.mode === mode);
  }

  show(error, '');
  show(socialNote, '');
}

async function refresh() {
  const state = await window.snapask.authState();

  guest.hidden = state.authenticated;
  signed.hidden = !state.authenticated;

  if (!state.authenticated) return;

  document.getElementById('who').textContent = `Đang đăng nhập: ${state.user?.email ?? ''}`;
  document.getElementById('quota').textContent = state.quota?.own_key
    ? `Gói ${state.quota.plan} · dùng khoá riêng, không giới hạn lượt`
    : `Gói ${state.quota.plan} · còn ${state.quota.remaining}/${state.quota.limit} lượt hỏi trong tháng`;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  show(error, '');

  const email = field('email').value.trim();
  const password = field('password').value;

  if (mode === 'register' && password !== field('password2').value) {
    // Bắt ngay tại chỗ: một vòng lên máy chủ chỉ để báo gõ lệch mật khẩu là
    // khoảng chờ vô ích.
    show(error, 'Hai lần nhập mật khẩu không giống nhau.');
    return;
  }

  submit.disabled = true;

  const result = mode === 'login'
    ? await window.snapask.login({ email, password })
    : await window.snapask.register({ name: field('name').value.trim(), email, password });

  submit.disabled = false;

  if (result.ok) {
    form.reset();
    refresh();
  } else {
    show(error, result.message);
  }
});

document.querySelector('.tabs').addEventListener('click', (event) => {
  const tab = event.target.closest('.tabs__tab');

  if (tab) setMode(tab.dataset.mode);
});

document.querySelector('.socials').addEventListener('click', (event) => {
  const button = event.target.closest('.social');

  if (!button) return;

  // Giao diện dựng trước, phần nối thật với nhà cung cấp làm sau. Nói thẳng ra
  // còn hơn để nút bấm vào không có gì xảy ra.
  show(socialNote, `Đăng nhập bằng ${PROVIDER_NAMES[button.dataset.provider]} sẽ có ở bản tới.`);
});

document.getElementById('manage').addEventListener('click', () => {
  window.snapask.openManagement();
});

document.getElementById('logout').addEventListener('click', async () => {
  await window.snapask.logout();
  refresh();
});

setMode('login');
refresh();
