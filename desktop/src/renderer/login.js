'use strict';

const guest = document.getElementById('guest');
const signed = document.getElementById('signed');
const form = document.getElementById('form');
const error = document.getElementById('error');
const notice = document.getElementById('notice');
const submit = document.getElementById('submit');
const lead = document.getElementById('lead');
const socialNote = document.getElementById('social-note');

const field = (id) => document.getElementById(id);

/** 'login' hoặc 'register' — quyết định hiện ô nào và gọi API nào. */
let mode = 'login';

// Khoá dịch chứ không phải câu đã dịch: đổi ngôn ngữ thì setMode() chạy lại và
// lấy đúng bản mới, không phải dựng lại bảng này.
const COPY = {
  login: {
    lead: 'Snap a region of your screen and ask AI right there.',
    submit: 'Sign in',
    passwordAutocomplete: 'current-password',
  },
  register: {
    lead: 'Create a trial account, no card required.',
    submit: 'Create account',
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

  lead.textContent = window.i18n.t(copy.lead);
  submit.textContent = window.i18n.t(copy.submit);
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
  show(notice, '');
  show(socialNote, '');
}

async function refresh() {
  const state = await window.snapask.authState();

  guest.hidden = state.authenticated;
  signed.hidden = !state.authenticated;

  if (!state.authenticated) return;

  document.getElementById('who').textContent = window.i18n.t('Signed in as :email', { email: state.user?.email ?? '' });
  document.getElementById('quota').textContent = state.quota?.own_key
    ? window.i18n.t(':plan plan · your own key, no ask limit', { plan: state.quota.plan })
    : window.i18n.t(':plan plan · :remaining of :limit asks left this month', {
      plan: state.quota.plan,
      remaining: state.quota.remaining,
      limit: state.quota.limit,
    });
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  show(error, '');

  const email = field('email').value.trim();
  const password = field('password').value;

  if (mode === 'register' && password !== field('password2').value) {
    // Bắt ngay tại chỗ: một vòng lên máy chủ chỉ để báo gõ lệch mật khẩu là
    // khoảng chờ vô ích.
    show(error, window.i18n.t('The two passwords do not match.'));
    return;
  }

  submit.disabled = true;

  const result = mode === 'login'
    ? await window.snapask.login({ email, password })
    : await window.snapask.register({ name: field('name').value.trim(), email, password });

  submit.disabled = false;

  if (result.ok && result.pending) {
    // Tài khoản đã có nhưng chưa xác thực email: chuyển sang tab đăng nhập, giữ
    // sẵn email để lát bấm link xong chỉ còn gõ mật khẩu.
    form.reset();
    setMode('login');
    field('email').value = email;
    show(notice, result.message);
  } else if (result.ok) {
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
  show(socialNote, window.i18n.t('Sign in with :provider is coming in the next release.', {
    provider: PROVIDER_NAMES[button.dataset.provider],
  }));
});

document.getElementById('manage').addEventListener('click', () => {
  window.snapask.openManagement();
});

document.getElementById('logout').addEventListener('click', async () => {
  await window.snapask.logout();
  refresh();
});

/*
 * Đổi ngôn ngữ tại chỗ.
 *
 * Dịch lại cả những chữ do JS dựng ra — nhãn nút, dòng hạn mức — vì `data-i18n`
 * chỉ với tới được phần nằm sẵn trong HTML.
 */
for (const locale of ['vi', 'en']) {
  document.getElementById(`lang-${locale}`).addEventListener('click', () => {
    window.i18n.setLocale(locale);
  });
}

function markActiveLocale(locale) {
  for (const one of ['vi', 'en']) {
    document.getElementById(`lang-${one}`).classList.toggle('auth__ghost--on', one === locale);
  }
}

/*
 * Nút Hiện/Ẩn trong mỗi ô mật khẩu. Nhớ ô nào đang hiện để đổi ngôn ngữ thì chỉ
 * dịch lại chữ trên nút, không lật trạng thái của ô.
 */
const passwordToggles = [];

for (const input of document.querySelectorAll('input[type=password]')) {
  const wrap = document.createElement('span');
  const toggle = document.createElement('button');

  wrap.className = 'pw';
  toggle.type = 'button';
  toggle.className = 'pw__toggle';

  const render = () => {
    const visible = input.type === 'text';

    toggle.textContent = window.i18n.t(visible ? 'Hide' : 'Show');
    toggle.setAttribute('aria-label', window.i18n.t(visible ? 'Hide password' : 'Show password'));
    toggle.setAttribute('aria-pressed', String(visible));
  };

  toggle.addEventListener('click', () => {
    input.type = input.type === 'password' ? 'text' : 'password';
    render();
    input.focus();
  });

  input.replaceWith(wrap);
  wrap.append(input, toggle);
  passwordToggles.push(render);
}

// Gửi form xong thì ô về lại dạng ẩn, không để mật khẩu lộ ra lần sau.
form.addEventListener('reset', () => {
  for (const input of form.querySelectorAll('.pw input')) input.type = 'password';
  setTimeout(() => passwordToggles.forEach((render) => render()));
});

passwordToggles.forEach((render) => render());

/*
 * Tiến trình chính mở cửa sổ này vì phiên đã hết hạn: nói rõ lý do, để người
 * dùng không tưởng mình vừa bị đăng xuất vô cớ.
 */
let sessionNotice = null;

window.snapask.onAuthNotice(({ message }) => {
  sessionNotice = message;
  setMode('login');
  show(notice, message);
  field('email').focus();
});

// Gõ vào ô là người dùng đã đọc thông báo; thôi hiện lại nó khi đổi ngôn ngữ.
form.addEventListener('input', () => { sessionNotice = null; }, { once: true });

window.i18n.start((locale) => {
  markActiveLocale(locale);
  passwordToggles.forEach((render) => render());
  setMode(mode);
  if (sessionNotice) show(notice, sessionNotice);
  refresh();
});

setMode('login');
refresh();
