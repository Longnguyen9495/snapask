'use strict';

/**
 * Cửa sổ chính của SnapAsk: lịch sử bên trái, hội thoại và ô soạn bên phải.
 *
 * Không dùng framework: một object `state` làm nguồn sự thật phía renderer, và
 * vài hàm vẽ lại đúng phần vừa đổi. Máy chủ vẫn là nguồn sự thật cho hội thoại
 * và tin nhắn; ở đây chỉ giữ bản sao để vẽ nhanh và cập nhật lạc quan.
 *
 * Mọi lời gọi mạng đi qua `window.snapask` (preload/workspace.js) tới tiến
 * trình chính. Renderer không bao giờ thấy token.
 */
(() => {
  const api = window.snapask;
  const { markdown, history: historyLib, stream: streamLib, chart: chartLib } = window.SnapAskLib;

  /*
   * Biểu đồ đang hiển thị.
   *
   * Chart.js gắn listener lên canvas và giữ tham chiếu nội bộ, nên nút DOM bị
   * thay không đủ để nó được thu dọn. Mỗi lần dựng lại luồng hội thoại phải gọi
   * `destroy()` cho từng cái, nếu không đọc lâu sẽ tích lại hàng chục biểu đồ
   * chết trong bộ nhớ.
   */
  const charts = [];

  function destroyCharts() {
    while (charts.length) {
      try {
        charts.pop().destroy();
      } catch {
        // Đã bị huỷ từ trước thì thôi.
      }
    }
  }
  const icons = window.SnapAskIcons;
  const t = (key, vars) => window.i18n.t(key, vars);

  const $ = (id) => document.getElementById(id);

  const els = {
    body: document.body,
    side: $('side'),
    collapse: $('collapse'),
    newChat: $('new-chat'),
    capture: $('capture'),
    hotkeyKbd: $('hotkey-kbd'),
    search: $('search'),
    historyNav: $('history'),
    historyList: $('history-list'),
    historyFoot: $('history-foot'),
    accountBtn: $('account-btn'),
    accountMenu: $('account-menu'),
    main: $('main'),
    banner: $('banner'),
    bannerText: $('banner-text'),
    bannerRetry: $('banner-retry'),
    convHead: $('conv-head'),
    convTitle: $('conv-title'),
    convMeta: $('conv-meta'),
    convMenuBtn: $('conv-menu-btn'),
    convMenu: $('conv-menu'),
    stage: $('stage'),
    welcomeTitle: $('welcome-title'),
    welcomeRecent: $('welcome-recent'),
    welcomeHotkey: $('welcome-hotkey'),
    thread: $('thread'),
    composer: $('composer'),
    input: $('composer-input'),
    count: $('composer-count'),
    send: $('send'),
    stop: $('stop'),
    note: $('composer-note'),
    attachment: $('attachment'),
    attachmentImg: $('attachment-img'),
    attachmentSize: $('attachment-size'),
    attachmentError: $('attachment-error'),
    settings: $('settings'),
    confirm: $('confirm'),
    rename: $('rename'),
    renameInput: $('rename-input'),
    renameError: $('rename-error'),
    toasts: $('toasts'),
    live: $('live'),
    alert: $('alert'),
  };

  const isMac = navigator.userAgentData?.platform === 'macOS' || /Mac/.test(navigator.platform);
  const MAX_IMAGE_BYTES = 4 * 1024 * 1024;
  const QUESTION_MAX = 2000;
  const SEARCH_DEBOUNCE_MS = 300;

  const state = {
    session: { authenticated: false, online: true, user: null, quota: null, setup: null },
    settings: {},
    updates: { status: 'idle' },
    history: { items: [], page: 0, lastPage: 1, loading: false, error: null, query: '', loaded: false, seq: 0 },
    // `view` là thứ đang hiện ở vùng chính: 'new' (câu hỏi mới) hoặc id hội thoại.
    view: 'new',
    conversation: { entity: null, messages: [], loading: false, error: null, seq: 0 },
    // Lượt hỏi đang chạy của workspace; có thể vẫn chạy khi người dùng đã chuyển sang hội thoại khác.
    active: null,
    attachment: null,
    // Lượt cuối bị lỗi, để nút Thử lại gửi lại đúng câu hỏi và ảnh đó.
    lastFailed: null,
  };

  /** Chi tiết hội thoại đã tải, để chuyển qua lại thấy ngay; ảnh giữ riêng, chỉ trong bộ nhớ. */
  const detailCache = new Map();
  const imageCache = new Map();
  const CACHE_LIMIT = 12;

  const remember = (map, key, value) => {
    map.delete(key);
    map.set(key, value);

    while (map.size > CACHE_LIMIT) map.delete(map.keys().next().value);
  };

  /* ============================================================
   * Tiện ích
   * ============================================================ */

  const newRequestId = () => `w${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`;

  const formatBytes = (bytes) => (bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

  const formatHotkey = (hotkey) => String(hotkey || '')
    .split('+')
    .map((part) => ({ Cmd: '⌘', Command: '⌘', CommandOrControl: isMac ? '⌘' : 'Ctrl', Shift: isMac ? '⇧' : 'Shift', Alt: isMac ? '⌥' : 'Alt', Option: '⌥', Ctrl: isMac ? '⌃' : 'Ctrl' }[part] || part))
    .join(isMac ? '' : ' + ');

  const shortcutLabel = (key) => (isMac ? `⌘${key}` : `Ctrl ${key}`);

  const timeOf = (iso) => {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) return '';

    return date.toLocaleString(window.i18n.getLocale() === 'vi' ? 'vi-VN' : 'en-GB', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' });
  };

  const initials = (name, email) => {
    const words = String(name || '').trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) return String(email || '?').slice(0, 1).toUpperCase();

    return (words[0][0] + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase();
  };

  const el = (tag, className, text) => {
    const node = document.createElement(tag);

    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;

    return node;
  };

  const iconEl = (name, size = 16) => {
    const span = el('span');
    span.innerHTML = icons.svg(name, size);

    return span.firstChild;
  };

  function announce(message) {
    els.live.textContent = '';
    requestAnimationFrame(() => { els.live.textContent = message; });
  }

  function alertMessage(message) {
    els.alert.textContent = '';
    requestAnimationFrame(() => { els.alert.textContent = message; });
  }

  function toast(message, { tone = 'ok', action = null } = {}) {
    const node = el('div', `toast${tone === 'error' ? ' toast--error' : ''}`);
    node.append(iconEl(tone === 'error' ? 'alert' : 'check'), el('span', null, message));

    if (action) {
      const button = el('button', 'btn btn--ghost btn--sm', action.label);
      button.type = 'button';
      button.addEventListener('click', () => { node.remove(); action.run(); });
      node.append(button);
    }

    els.toasts.append(node);
    setTimeout(() => node.remove(), action ? 8000 : 4000);
  }

  const quotaExhausted = () => {
    const quota = state.session.quota;

    return Boolean(quota && !quota.own_key && quota.remaining <= 0);
  };

  const quotaLow = () => {
    const quota = state.session.quota;

    return Boolean(quota && !quota.own_key && quota.remaining > 0 && quota.remaining <= Math.max(1, Math.floor(quota.limit * 0.1)));
  };

  const isStreamingHere = () => Boolean(state.active && !streamLib.isFinal(state.active.message) && state.active.view === state.view);

  /* ============================================================
   * Kết nối và phiên
   * ============================================================ */

  function setOnline(online, message) {
    state.session.online = online;
    els.banner.hidden = online;

    if (!online) els.bannerText.textContent = message || t('Could not reach the SnapAsk server. Your draft is kept.');

    renderComposer();
  }

  /** Lỗi từ IPC: mất mạng thì bật banner ngoại tuyến; hết phiên do tiến trình chính lo. */
  function noteFailure(result) {
    if (result?.code === 'network') setOnline(false, result.message);
  }

  async function boot() {
    const result = await api.workspace.ready();

    if (!result?.ok) {
      setOnline(false);

      return;
    }

    const { session, settings, updates } = result.data;

    state.settings = settings;
    state.updates = updates;
    applySettings();

    if (!session.authenticated) {
      resetForSignedOut();

      return;
    }

    state.session = { ...state.session, ...session, authenticated: true };
    setOnline(session.online !== false, session.message);
    renderAccount();
    renderWelcome();

    loadHistory({ reset: true });
  }

  function resetForSignedOut() {
    if (state.active) api.ask.cancel(state.active.requestId);

    state.session = { authenticated: false, online: true, user: null, quota: null, setup: null };
    state.history = { items: [], page: 0, lastPage: 1, loading: false, error: null, query: '', loaded: false, seq: state.history.seq + 1 };
    state.active = null;
    state.attachment = null;
    state.lastFailed = null;
    detailCache.clear();
    imageCache.clear();
    els.search.value = '';
    els.input.value = '';
    showNewChat({ focus: false });
    renderHistory();
    renderAccount();
  }

  async function refreshAccount() {
    const result = await api.account.refresh();

    if (result?.ok) {
      state.session = { ...state.session, ...result.data };
      renderAccount();
      renderComposer();
    }
  }

  /* ============================================================
   * Tài khoản
   * ============================================================ */

  function quotaShort() {
    const quota = state.session.quota;

    if (!quota) return '';
    if (quota.own_key) return t('Own key, no limit');

    return t(':remaining/:limit asks left', { remaining: quota.remaining, limit: quota.limit });
  }

  function renderAccount() {
    const { user, quota, setup } = state.session;

    $('account-initials').textContent = user ? initials(user.name, user.email) : '·';
    $('account-name').textContent = user?.name || (state.session.online ? '' : t('Offline'));
    const quotaEl = $('account-quota');
    quotaEl.textContent = quotaShort();
    quotaEl.classList.toggle('is-low', quotaLow() || quotaExhausted());
    els.accountBtn.setAttribute('aria-label', user ? t('Account and settings for :name', { name: user.name }) : t('Account and settings'));

    $('menu-name').textContent = user?.name || '';
    $('menu-email').textContent = user?.email || '';
    $('menu-verified').hidden = !user?.email_verified;
    $('menu-plan').textContent = quota ? quota.plan.charAt(0).toUpperCase() + quota.plan.slice(1) : '—';
    $('menu-quota').textContent = quota ? (quota.own_key ? '∞' : `${quota.remaining}/${quota.limit}`) : '—';
    $('menu-model').textContent = setup?.model || '—';
    $('menu-model').title = setup?.model || '';
  }

  function renderWelcome() {
    const name = state.session.user?.name?.trim().split(/\s+/).pop();

    els.welcomeTitle.textContent = name ? t('Hello, :name', { name }) : t('Hello');
    els.welcomeRecent.hidden = state.history.items.length === 0 || Boolean(state.history.query);

    const hotkey = formatHotkey(state.settings.hotkey);

    els.welcomeHotkey.replaceChildren();

    if (hotkey) {
      els.welcomeHotkey.append(el('span', null, t('Quick snap from anywhere:')), el('kbd', null, hotkey));
    }
  }

  /* ============================================================
   * Lịch sử
   * ============================================================ */

  async function loadHistory({ reset = false } = {}) {
    const h = state.history;

    if (h.loading && !reset) return;
    if (!reset && h.page >= h.lastPage) return;

    const seq = ++h.seq;
    const page = reset ? 1 : h.page + 1;

    h.loading = true;
    h.error = null;
    renderHistoryFoot();

    // Lần tải đầu hoặc đổi từ khoá: hiện khung chờ. Làm mới nền thì giữ danh sách cũ, không nháy.
    if (reset && (!h.loaded || h.pendingQueryChange)) {
      h.items = [];
      renderHistory({ skeleton: true });
    }

    const result = await api.conversations.list({ page, search: h.query });

    if (seq !== h.seq) return;

    h.loading = false;
    h.pendingQueryChange = false;

    if (!result?.ok) {
      h.error = result;
      noteFailure(result);
      renderHistory();

      return;
    }

    if (!state.session.online) setOnline(true);

    const data = result.data;
    h.items = historyLib.mergePage(h.items, data.data || [], { replace: reset });
    h.page = data.current_page || page;
    h.lastPage = data.last_page || h.page;
    h.loaded = true;
    renderHistory();
    renderWelcome();
  }

  function historyLabel(key) {
    return { today: t('Today'), yesterday: t('Yesterday'), week: t('Previous 7 days'), older: t('Older') }[key];
  }

  function historyItem(item) {
    const button = el('button', 'item');
    button.type = 'button';
    button.dataset.id = String(item.id);
    button.setAttribute('role', 'listitem');

    if (state.view === item.id) button.setAttribute('aria-current', 'true');

    const title = el('span', 'item__title');

    if (item.has_image) title.append(iconEl('image', 13));

    title.append(el('span', null, item.title || t('Untitled')));

    const meta = el('span', 'item__meta');
    meta.append(
      el('span', 'item__preview', item.last_message_preview ? markdown.plain(item.last_message_preview) : ''),
      el('span', 'item__time', historyLib.relative(item.last_activity_at || item.updated_at, t, new Date(), window.i18n.getLocale())),
    );

    button.append(title, meta);
    button.title = item.title || '';

    return button;
  }

  function renderHistory({ skeleton = false } = {}) {
    const h = state.history;
    const list = els.historyList;

    list.replaceChildren();
    list.setAttribute('role', 'list');
    list.setAttribute('aria-busy', String(skeleton || h.loading));

    if (skeleton) {
      for (let i = 0; i < 7; i++) {
        const row = el('div', 'skeleton-item');
        row.setAttribute('aria-hidden', 'true');
        row.append(el('span', 'skeleton-line skeleton-line--long'), el('span', 'skeleton-line skeleton-line--short'));
        list.append(row);
      }

      renderHistoryFoot();

      return;
    }

    if (h.items.length === 0 && h.loaded) {
      const empty = el('div', 'history__empty');

      if (h.query) {
        empty.append(el('p', null, t('No conversations match “:query”.', { query: h.query })), el('p', null, t('Search looks in titles and message text.')));
      } else {
        empty.append(el('p', null, t('No conversations yet.')), el('p', null, t('Ask your first question on the right.')));
      }

      list.append(empty);
    }

    for (const group of historyLib.groupByTime(h.items)) {
      const section = el('div', 'history__group');
      section.setAttribute('role', 'presentation');
      const label = el('div', 'history__label', historyLabel(group.key));
      label.setAttribute('role', 'presentation');
      section.append(label);

      for (const item of group.items) section.append(historyItem(item));

      list.append(section);
    }

    renderHistoryFoot();
  }

  function renderHistoryFoot() {
    const h = state.history;
    const foot = els.historyFoot;

    foot.replaceChildren();

    if (h.loading && h.items.length > 0) {
      const row = el('div', 'skeleton-item');
      row.append(el('span', 'skeleton-line skeleton-line--long'), el('span', 'skeleton-line skeleton-line--short'));
      foot.append(row, el('span', 'sr-only', t('Loading more conversations…')));

      return;
    }

    if (h.error) {
      const box = el('div', 'history__error');
      box.append(el('span', null, h.error.code === 'network' ? t('Could not load your history. Check your connection.') : (h.error.message || t('Could not load your history.'))));

      const retry = el('button', 'btn btn--secondary btn--sm', t('Try again'));
      retry.type = 'button';
      retry.addEventListener('click', () => loadHistory({ reset: h.items.length === 0 }));
      box.append(retry);
      foot.append(box);
    }
  }

  function markCurrentInHistory() {
    for (const button of els.historyList.querySelectorAll('.item')) {
      if (Number(button.dataset.id) === state.view) button.setAttribute('aria-current', 'true');
      else button.removeAttribute('aria-current');
    }
  }

  /** Đưa một hội thoại lên đầu lịch sử (vừa hỏi xong, hoặc vừa được tạo). */
  function touchHistoryItem(item) {
    if (state.history.query) return;

    state.history.items = historyLib.upsert(state.history.items, item);
    renderHistory();
  }

  let searchTimer = null;

  function onSearchInput() {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
      const query = els.search.value.trim();

      if (query === state.history.query) return;

      state.history.query = query;
      state.history.pendingQueryChange = true;
      loadHistory({ reset: true });
    }, SEARCH_DEBOUNCE_MS);
  }

  /* ============================================================
   * Vùng chính: câu hỏi mới / hội thoại
   * ============================================================ */

  function setMainMode(empty) {
    els.main.classList.toggle('is-empty', empty);
    els.thread.hidden = empty;
    els.convHead.hidden = empty;
  }

  function showNewChat({ focus = true } = {}) {
    state.view = 'new';
    state.conversation = { entity: null, messages: [], loading: false, error: null, seq: state.conversation.seq + 1 };
    state.lastFailed = null;
    setMainMode(true);
    els.thread.replaceChildren();
    markCurrentInHistory();
    renderWelcome();
    renderComposer();
    closePopovers();

    if (focus) els.input.focus();
  }

  function renderHeader() {
    const entity = state.conversation.entity;

    els.convTitle.textContent = entity?.title || t('Untitled');

    const parts = [];

    if (entity?.model) parts.push(entity.model);
    if (entity?.messages_count) parts.push(t(':count messages', { count: entity.messages_count }));
    if (entity?.last_activity_at) parts.push(historyLib.relative(entity.last_activity_at, t, new Date(), window.i18n.getLocale()));

    els.convMeta.textContent = parts.join(' · ');
    els.convMenuBtn.disabled = !entity?.id;
    $('conv-rename').disabled = !entity?.id;
    $('conv-web').disabled = !entity?.id;
    $('conv-delete').disabled = !entity?.id;
  }

  async function openConversation(id, { focus = true } = {}) {
    if (!Number.isInteger(id)) return;

    closePopovers();

    const seq = state.conversation.seq + 1;
    const cached = detailCache.get(id);
    const fromList = state.history.items.find((item) => item.id === id);

    state.view = id;
    state.lastFailed = null;
    state.conversation = {
      entity: cached?.conversation || fromList || { id, title: '' },
      messages: cached?.messages || [],
      loading: !cached,
      error: null,
      seq,
    };

    setMainMode(false);
    markCurrentInHistory();
    renderHeader();
    renderThread();
    renderComposer();

    if (focus) {
      els.convTitle.focus({ preventScroll: true });
      announce(t('Opened conversation: :title', { title: state.conversation.entity.title || t('Untitled') }));
    }

    const result = await api.conversations.show(id);

    if (seq !== state.conversation.seq) return;

    state.conversation.loading = false;

    if (!result?.ok) {
      noteFailure(result);

      if (result?.code === 'not_found') {
        state.history.items = historyLib.remove(state.history.items, id);
        detailCache.delete(id);
        renderHistory();
      }

      if (!cached) state.conversation.error = result;

      renderThread();

      return;
    }

    remember(detailCache, id, result.data);
    state.conversation.entity = result.data.conversation;
    state.conversation.messages = result.data.messages;
    renderHeader();
    renderThread();
  }

  /* ---------- vẽ tin nhắn ---------- */

  /** Màu hiện hành của giao diện, để biểu đồ vẽ cùng một hệ với phần còn lại. */
  function chartTheme() {
    const style = getComputedStyle(document.body);

    return {
      text: style.getPropertyValue('--text').trim() || '#f3efe8',
      dim: style.getPropertyValue('--text-dim').trim() || '#a49c90',
      grid: style.getPropertyValue('--line-soft').trim() || '#2a251f',
    };
  }

  /*
   * Đổi khối ```chart thành biểu đồ thật.
   *
   * Chỉ chạy khi câu trả lời đã trọn vẹn: lúc đang stream, JSON còn dở nên
   * không parse được, và `fillLiveNode` dựng lại toàn bộ nút ở mỗi mẩu chữ nên
   * biểu đồ vừa vẽ sẽ bị xoá ngay.
   */
  function enhanceCharts(container) {
    if (!window.Chart) return;

    for (const pre of container.querySelectorAll('pre[data-lang="chart"]')) {
      // Khối đã nằm trong khung sao chép thì bỏ qua: nó là mã, không phải biểu đồ.
      if (pre.parentElement?.classList.contains('code')) continue;

      try {
        const instance = chartLib.mount(pre, {
          Chart: window.Chart,
          theme: chartTheme(),
          labels: { chart: t('Chart') },
        });

        // JSON không dùng được thì để nguyên khối mã — người dùng vẫn đọc được
        // số liệu thô, hơn là thấy một khoảng trống không giải thích.
        if (instance) charts.push(instance);
      } catch (error) {
        console.warn('chart:', error.message);
      }
    }
  }

  /** Bảng rộng hơn khung thì cuộn ngang; người dùng bàn phím cũng phải cuộn được. */
  function enhanceTables(container) {
    for (const table of container.querySelectorAll('[data-md-table]')) {
      if (table.hasAttribute('tabindex')) continue;

      table.tabIndex = 0;
      table.setAttribute('role', 'region');
      table.setAttribute('aria-label', t('Table'));
    }
  }

  function enhanceCode(container) {
    for (const pre of container.querySelectorAll('pre')) {
      if (pre.parentElement.classList.contains('code')) continue;

      const wrap = el('div', 'code');
      const button = el('button', 'code__copy');
      button.type = 'button';
      button.append(iconEl('copy', 13), el('span', null, t('Copy')));

      button.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(pre.innerText.replace(/\n$/, ''));
          button.lastChild.textContent = t('Copied');
          button.classList.add('is-done');
          announce(t('Copied'));
        } catch {
          button.lastChild.textContent = t('Could not copy');
        }

        setTimeout(() => {
          button.lastChild.textContent = t('Copy');
          button.classList.remove('is-done');
        }, 1600);
      });

      pre.replaceWith(wrap);
      wrap.append(pre, button);
    }
  }

  function toolsDisclosure(tools, { live = false } = {}) {
    const details = el('details', 'tools');
    const summary = el('summary');

    summary.append(
      iconEl('wrench', 14),
      el('span', null, live && tools.some((tool) => !tool.done)
        ? t('Looking up…')
        : t('Looked up :count tools', { count: tools.length })),
      iconEl('chevron-right', 14),
    );
    summary.lastChild.classList.add('tools__chevron');
    details.append(summary);

    const list = el('ul');

    for (const tool of tools) {
      const item = el('li', live && !tool.done ? 'is-running' : '');
      item.append(el('span', 'dot'), el('span', null, tool.label || tool.tool || ''));
      list.append(item);

      if (tool.arguments && Object.keys(tool.arguments).length > 0) {
        list.append(el('pre', null, JSON.stringify(tool.arguments, null, 2)));
      }
    }

    details.append(list);

    // Đang tra cứu thì mở sẵn để người dùng thấy vì sao phải chờ.
    if (live && tools.some((tool) => !tool.done)) details.open = true;

    return details;
  }

  function shotElement(conversationId, image) {
    if (!image) return null;

    if (!image.available) {
      const gone = el('div', 'shot shot--gone');
      gone.append(iconEl('image', 16), el('span', null, t('The screenshot has been deleted after the retention period.')));

      return gone;
    }

    const cached = imageCache.get(conversationId);
    const figure = el('figure', `shot${cached ? '' : ' shot--loading'}`);

    const fill = (dataUrl) => {
      figure.classList.remove('shot--loading');
      figure.replaceChildren();

      const img = el('img');
      img.src = dataUrl;
      img.alt = t('Screenshot attached to this conversation');
      if (image.width) { img.width = image.width; img.height = image.height; }

      const meta = el('figcaption', 'shot__meta');
      meta.append(iconEl('image', 13), el('span', null, image.width ? `${image.width} × ${image.height} px` : t('Screenshot')));

      if (image.expires_at) {
        const days = Math.max(0, Math.ceil((Date.parse(image.expires_at) - Date.now()) / 86400000));
        meta.append(el('span', null, `· ${t('Deleted in :count days', { count: days })}`));
      }

      figure.append(img, meta);
    };

    if (cached) {
      fill(cached);
    } else {
      figure.setAttribute('aria-label', t('Loading screenshot…'));

      api.conversations.image(conversationId).then((result) => {
        if (result?.ok) {
          remember(imageCache, conversationId, result.data.dataUrl);
          fill(result.data.dataUrl);
        } else {
          figure.className = 'shot shot--gone';
          figure.replaceChildren(iconEl('image', 16), el('span', null, result?.code === 'not_found'
            ? t('The screenshot has been deleted after the retention period.')
            : t('The screenshot could not be loaded.')));
        }
      });
    }

    return figure;
  }

  function userMessageNode(message, { image = null, conversationId = null } = {}) {
    const node = el('article', 'msg msg--user');
    const who = el('p', 'msg__who', t('You'));

    if (message.created_at) who.append(el('span', 'msg__time', timeOf(message.created_at)));

    node.append(who);

    if (message.localImage) {
      const figure = el('figure', 'shot msg__attachment');
      const img = el('img');
      img.src = message.localImage;
      img.alt = t('The region you just snapped');
      figure.append(img);
      node.append(figure);
    } else if (image && conversationId) {
      const shot = shotElement(conversationId, image);
      if (shot) { shot.classList.add('msg__attachment'); node.append(shot); }
    }

    node.append(el('div', 'msg__body', message.content));

    return node;
  }

  function assistantBody(content, { streaming = false } = {}) {
    const body = el('div', 'msg__body prose');
    body.innerHTML = markdown.render(content, { streaming });

    // Bảng hiện được ngay lúc đang gõ; biểu đồ thì chờ câu trả lời trọn vẹn,
    // vì JSON còn dở không parse được và nút sẽ bị dựng lại ở mẩu chữ kế tiếp.
    enhanceTables(body);

    if (!streaming) {
      enhanceCharts(body);
      enhanceCode(body);
    }

    return body;
  }

  function assistantNode(message) {
    const node = el('article', 'msg msg--assistant');
    const who = el('p', 'msg__who', 'SnapAsk');

    if (message.created_at) who.append(el('span', 'msg__time', timeOf(message.created_at)));

    node.append(who);

    const tools = message.tools_used || [];

    if (tools.length > 0) node.append(toolsDisclosure(tools));

    node.append(assistantBody(message.content));

    return node;
  }

  /** Nút của câu trả lời đang stream: vẽ lại riêng nút này ở mỗi khung hình, không đụng phần còn lại. */
  function liveNode(message) {
    const node = el('article', 'msg msg--assistant');
    node.dataset.requestId = message.requestId;
    fillLiveNode(node, message);

    return node;
  }

  function fillLiveNode(node, message) {
    // Nút này được dựng lại ở mỗi mẩu chữ. Biểu đồ chỉ mọc ra ở lần cuối, khi
    // câu trả lời đã trọn, nhưng lần dựng sau đó — chẳng hạn khi sửa dòng trạng
    // thái — vẫn phải huỷ cái cũ trước, nếu không nó nằm lại trong bộ nhớ.
    if (node.querySelector('[data-md-chart]')) destroyCharts();

    node.replaceChildren();
    node.className = `msg msg--assistant${message.status === 'pending' ? ' msg--pending' : ''}${message.status === 'streaming' ? ' msg--streaming' : ''}`;
    node.setAttribute('aria-busy', String(!streamLib.isFinal(message)));

    node.append(el('p', 'msg__who', 'SnapAsk'));

    if (message.tools.length > 0) node.append(toolsDisclosure(message.tools, { live: !streamLib.isFinal(message) }));

    if (message.content || message.status === 'pending') {
      node.append(assistantBody(message.content, { streaming: !streamLib.isFinal(message) }));
    }

    const stateLine = messageStateLine(message);

    if (stateLine) node.append(stateLine);
  }

  function messageStateLine(message) {
    if (message.status === 'stopped') {
      const line = el('p', 'msg__state', t('Stopped. The part above is what arrived before you stopped.'));

      return line;
    }

    if (message.status !== 'failed' && message.status !== 'incomplete') return null;

    const code = message.error?.code;
    const line = el('p', 'msg__state msg__state--error');
    line.append(iconEl('alert', 14));

    const text = {
      stopped: t('Stopped before the answer started.'),
      quota: t('You have used every ask this month.'),
      network: message.status === 'incomplete' ? t('The connection dropped before the answer finished.') : t('Could not reach the SnapAsk server.'),
    }[code] || message.error?.message || t('Could not get an answer.');

    line.append(el('span', null, text));

    if (code === 'quota') {
      const plan = el('button', 'btn btn--secondary btn--sm', t('See your plan'));
      plan.type = 'button';
      plan.addEventListener('click', () => api.external.account());
      line.append(plan);
    } else if (code !== 'unauthorized' && state.lastFailed?.requestId === message.requestId) {
      const retry = el('button', 'btn btn--secondary btn--sm');
      retry.type = 'button';
      retry.append(iconEl('rotate-ccw', 14), el('span', null, t('Try again')));
      retry.addEventListener('click', () => retryLast());
      line.append(retry);
    }

    return line;
  }

  function renderThread() {
    const c = state.conversation;
    const thread = els.thread;

    // Luồng sắp bị thay toàn bộ: huỷ biểu đồ cũ trước khi mất tham chiếu tới chúng.
    destroyCharts();
    thread.replaceChildren();
    thread.setAttribute('aria-busy', String(c.loading));

    if (c.loading && c.messages.length === 0) {
      for (const kind of ['user', 'assistant', 'user']) {
        const row = el('div', `skeleton-msg skeleton-msg--${kind}`);
        row.setAttribute('aria-hidden', 'true');
        row.append(el('span', 'skeleton-line skeleton-line--short'));
        if (kind === 'assistant') row.append(el('span', 'skeleton-line skeleton-line--long'), el('span', 'skeleton-line skeleton-line--long'));
        else row.append(el('span', 'skeleton-line'));
        thread.append(row);
      }

      thread.append(el('span', 'sr-only', t('Loading conversation…')));

      return;
    }

    if (c.error) {
      const notice = el('div', 'thread__notice');

      if (c.error.code === 'not_found') {
        notice.append(el('h2', null, t('This conversation no longer exists')), el('p', null, t('It may have been deleted on another device or on the web.')));
      } else {
        notice.append(el('h2', null, t('Could not open this conversation')), el('p', null, c.error.message || ''));
        const retry = el('button', 'btn btn--secondary btn--sm', t('Try again'));
        retry.type = 'button';
        retry.addEventListener('click', () => openConversation(state.view));
        notice.append(retry);
      }

      const fresh = el('button', 'btn btn--ghost btn--sm', t('New question'));
      fresh.type = 'button';
      fresh.addEventListener('click', () => showNewChat());
      notice.append(fresh);
      thread.append(notice);

      return;
    }

    const image = c.entity?.image || null;
    let firstUser = true;

    for (const message of c.messages) {
      if (message.role === 'user') {
        thread.append(userMessageNode(message, { image: firstUser ? image : null, conversationId: c.entity?.id }));
        firstUser = false;
      } else if (message.requestId) {
        thread.append(liveNode(message));
      } else {
        thread.append(assistantNode(message));
      }
    }

    // Câu trả lời đang chạy nền của chính hội thoại này (vừa quay lại xem giữa chừng).
    if (state.active && state.active.view === state.view && !c.messages.includes(state.active.message)) {
      const alreadyHasQuestion = c.messages.some((message) => message.role === 'user' && message.content === state.active.question);

      if (!alreadyHasQuestion) thread.append(userMessageNode({ content: state.active.question }));

      thread.append(liveNode(state.active.message));
    }

    scrollToBottom(true);
  }

  const nearBottom = () => els.stage.scrollHeight - els.stage.scrollTop - els.stage.clientHeight < 120;

  function scrollToBottom(force = false) {
    if (force || nearBottom()) els.stage.scrollTop = els.stage.scrollHeight;
  }

  /* ============================================================
   * Ô soạn và streaming
   * ============================================================ */

  function renderComposer() {
    const streamingHere = isStreamingHere();
    const busyElsewhere = Boolean(state.active && !streamLib.isFinal(state.active.message) && !streamingHere);
    const text = els.input.value.trim();
    const attachmentBad = Boolean(state.attachment?.error);
    const blocked = quotaExhausted() || !state.session.online || busyElsewhere || !state.session.authenticated;

    els.send.hidden = streamingHere;
    els.stop.hidden = !streamingHere;
    els.send.disabled = blocked || attachmentBad || text === '';
    els.composer.classList.toggle('is-disabled', quotaExhausted() || !state.session.authenticated);

    const length = els.input.value.length;
    els.count.textContent = length > QUESTION_MAX * 0.8 ? `${length}/${QUESTION_MAX}` : '';
    els.count.classList.toggle('is-near', length > QUESTION_MAX * 0.95);

    let note = null;
    let warn = false;
    let action = null;

    if (quotaExhausted()) {
      note = t('You have used every ask this month. Plug in your own model key on the web to keep going.');
      warn = true;
      action = { label: t('See your plan'), run: () => api.external.providerManagement() };
    } else if (!state.session.online) {
      note = t('Offline. Your draft is kept and you can send it once the connection is back.');
      warn = true;
    } else if (busyElsewhere) {
      note = t('Another answer is still coming in. Wait for it to finish, or open that conversation to stop it.');
    } else if (state.attachment && text === '') {
      note = t('Add a short question about the screenshot.');
    } else if (quotaLow()) {
      note = t('Only :count asks left this month.', { count: state.session.quota.remaining });
      warn = true;
    }

    els.note.hidden = !note;
    els.note.classList.toggle('is-warn', warn);
    els.note.replaceChildren();

    if (note) {
      els.note.append(el('span', null, note));

      if (action) {
        const button = el('button', 'btn btn--ghost btn--sm', action.label);
        button.type = 'button';
        button.addEventListener('click', action.run);
        els.note.append(button);
      }
    }
  }

  function autosize() {
    els.input.style.height = 'auto';
    els.input.style.height = `${Math.min(els.input.scrollHeight, 220)}px`;
  }

  function setAttachment(capture) {
    if (!capture) {
      state.attachment = null;
      els.attachment.hidden = true;
      els.attachmentImg.removeAttribute('src');
      renderComposer();

      return;
    }

    const tooBig = capture.bytes > MAX_IMAGE_BYTES;

    state.attachment = {
      image: capture.image,
      width: capture.width,
      height: capture.height,
      bytes: capture.bytes,
      error: tooBig ? t('This screenshot is larger than 4 MB. Snap a smaller region.') : null,
    };

    els.attachmentImg.src = capture.image;
    els.attachmentSize.textContent = `${capture.width} × ${capture.height} px · ${formatBytes(capture.bytes)}`;
    els.attachmentError.hidden = !tooBig;
    els.attachmentError.textContent = state.attachment.error || '';
    els.attachment.hidden = false;
    renderComposer();
  }

  /**
   * Gửi câu hỏi.
   *
   * Tin nhắn của người dùng và chỗ cho câu trả lời hiện ngay (lạc quan); chữ về
   * tới đâu vẽ tới đó. Hội thoại mới chỉ được tạo trên máy chủ ở lượt gửi đầu,
   * không phải lúc bấm "Câu hỏi mới".
   */
  async function send({ question, image, conversationId } = {}) {
    const text = (question ?? els.input.value).trim();

    if (!text || text.length > QUESTION_MAX) return;
    if (quotaExhausted() || !state.session.online || state.attachment?.error) return;
    if (state.active && !streamLib.isFinal(state.active.message)) return;

    const imageToSend = image !== undefined ? image : state.attachment?.image || null;
    const targetId = conversationId !== undefined ? conversationId : (state.view === 'new' ? null : state.view);
    const requestId = newRequestId();
    const view = targetId ?? `draft:${requestId}`;

    if (state.view === 'new') {
      state.view = view;
      state.conversation = {
        entity: { id: null, title: markdown.plain(text).slice(0, 80) },
        messages: [],
        loading: false,
        error: null,
        seq: state.conversation.seq + 1,
      };
      setMainMode(false);
      renderHeader();
      renderThread();
      markCurrentInHistory();
    } else if (typeof state.view === 'string' && state.view.startsWith('draft:')) {
      // Thử lại một câu hỏi mới chưa kịp có id: vẫn là màn hình nháp đó, chỉ đổi mã lượt.
      state.view = view;
    }

    const userMessage = { role: 'user', content: text, localImage: imageToSend, created_at: new Date().toISOString() };
    const assistant = streamLib.initial(requestId);

    state.conversation.messages = [...state.conversation.messages, userMessage, assistant];
    state.active = { requestId, view, question: text, image: imageToSend, conversationId: targetId, message: assistant, node: null };
    state.lastFailed = null;

    els.thread.append(userMessageNode(userMessage));
    state.active.node = liveNode(assistant);
    els.thread.append(state.active.node);
    scrollToBottom(true);

    if (question === undefined) {
      els.input.value = '';
      autosize();
      setAttachment(null);
    }

    renderComposer();
    announce(t('Question sent. Waiting for the answer…'));

    const result = await api.ask.start({ requestId, conversationId: targetId, question: text, image: imageToSend });

    if (!result?.ok) {
      noteFailure(result);
      onAskEvent({ requestId, type: 'error', code: result?.code || 'unknown', message: result?.code === 'invalid' ? t('The question is empty or too long.') : result?.message });
    }
  }

  function retryLast() {
    const failed = state.lastFailed;

    if (!failed) return;

    // Bỏ cặp tin nhắn lỗi khỏi luồng rồi gửi lại đúng câu hỏi và ảnh đó.
    const messages = state.conversation.messages;
    const index = messages.findIndex((message) => message.requestId === failed.requestId);

    if (index > 0) state.conversation.messages = messages.slice(0, index - 1).concat(messages.slice(index + 1));

    // Máy chủ đã tạo hội thoại ở lượt lỗi (sự kiện `conversation`): hỏi lại vào đó, không tạo cái mới.
    const conversationId = failed.conversationId ?? null;

    state.lastFailed = null;
    renderThread();
    send({ question: failed.question, image: conversationId ? null : failed.image, conversationId });
  }

  let frame = null;

  function scheduleLiveRender() {
    if (frame) return;

    frame = requestAnimationFrame(() => {
      frame = null;

      const active = state.active;

      if (!active || active.view !== state.view) return;

      const node = els.thread.querySelector(`[data-request-id="${active.requestId}"]`);

      if (!node) return;

      const stick = nearBottom();
      fillLiveNode(node, active.message);

      if (streamLib.isFinal(active.message)) {
        node.querySelectorAll('.prose').forEach((body) => enhanceCode(body));
      }

      if (stick) scrollToBottom(true);
    });
  }

  function onAskEvent(event) {
    const active = state.active;

    // Sự kiện của lượt cũ (đã huỷ, đã thay) thì bỏ qua.
    if (!active || event.requestId !== active.requestId) return;

    const before = active.message;
    const message = streamLib.apply(before, event);

    active.message = message;
    replaceActiveMessage(before, message);

    if (event.type === 'conversation' && event.conversation_id) {
      active.conversationId = event.conversation_id;
      adoptConversationId(active, event.conversation_id);
    }

    if (event.type === 'error') {
      if (event.quota) {
        state.session.quota = event.quota;
        renderAccount();
      }

      state.lastFailed = { requestId: active.requestId, question: active.question, image: active.image, conversationId: active.conversationId };
      alertMessage(message.error?.message || t('Could not get an answer.'));
    }

    if (event.type === 'done') {
      finishActive(event.conversation_id || active.conversationId);
      announce(t('Answer ready.'));
    }

    if (streamLib.isFinal(message)) renderComposer();

    scheduleLiveRender();
  }

  function replaceActiveMessage(before, after) {
    if (state.conversation.messages.includes(before)) {
      state.conversation.messages = state.conversation.messages.map((message) => (message === before ? after : message));
    }
  }

  /** Hội thoại mới vừa có id: đổi view nháp sang id thật và chèn vào lịch sử. */
  function adoptConversationId(active, id) {
    const wasViewing = state.view === active.view;

    if (typeof active.view === 'string') {
      active.view = id;

      if (wasViewing) {
        state.view = id;
        state.conversation.entity = { ...state.conversation.entity, id };
        renderHeader();
      }
    }

    touchHistoryItem({
      id,
      title: state.conversation.entity?.id === id ? state.conversation.entity.title : markdown.plain(active.question).slice(0, 80),
      last_activity_at: new Date().toISOString(),
      last_message_preview: active.question,
      has_image: Boolean(active.image),
      messages_count: 1,
    });
    markCurrentInHistory();
  }

  /** Lượt xong: khớp lại với máy chủ — tiêu đề, số tin nhắn, hạn mức. */
  async function finishActive(id) {
    const active = state.active;

    if (!id) return;

    if (active && active.view !== id) adoptConversationId(active, id);

    detailCache.delete(id);

    const [detail] = await Promise.all([api.conversations.show(id), refreshAccount()]);

    if (detail?.ok) {
      remember(detailCache, id, detail.data);

      const conversation = detail.data.conversation;
      const last = detail.data.messages[detail.data.messages.length - 1];

      touchHistoryItem({ ...conversation, last_message_preview: last?.content || '' });

      // Đang xem và không có lượt mới nào chen vào: thay bản lạc quan bằng bản của máy chủ.
      if (state.view === id && (!state.active || streamLib.isFinal(state.active.message))) {
        state.conversation.entity = conversation;
        state.conversation.messages = detail.data.messages;
        renderHeader();

        const stick = nearBottom();
        renderThread();
        if (!stick) els.stage.scrollTop = els.stage.scrollTop;
      }
    }
  }

  async function stopActive() {
    const active = state.active;

    if (!active || streamLib.isFinal(active.message)) return;

    await api.ask.cancel(active.requestId);

    const before = active.message;
    active.message = streamLib.stop(before);
    replaceActiveMessage(before, active.message);

    if (active.message.status === 'failed') {
      state.lastFailed = { requestId: active.requestId, question: active.question, image: active.image, conversationId: active.conversationId };
    }

    scheduleLiveRender();
    renderComposer();
    announce(t('Stopped.'));
    els.input.focus();

    // Câu hỏi đã được lưu dù câu trả lời bị dừng; làm mới lịch sử để thấy đúng thứ tự.
    if (active.conversationId) detailCache.delete(active.conversationId);
    loadHistory({ reset: true });
  }

  /* ============================================================
   * Xoá, đổi tên
   * ============================================================ */

  let confirmResolve = null;

  function confirmDialog({ title, text, action }) {
    $('confirm-title').textContent = title;
    $('confirm-text').textContent = text;
    $('confirm-accept').textContent = action;

    const trigger = document.activeElement;

    return new Promise((resolve) => {
      confirmResolve = resolve;
      els.confirm.returnValue = '';
      els.confirm.showModal();
      // Nút an toàn nhận focus trước: bấm Enter theo quán tính không xoá gì cả.
      $('confirm-cancel').focus();

      els.confirm.addEventListener('close', () => {
        confirmResolve = null;
        resolve(els.confirm.returnValue === 'accept');
        if (trigger && document.contains(trigger)) trigger.focus();
      }, { once: true });
    });
  }

  async function deleteConversation(id) {
    const item = state.history.items.find((entry) => entry.id === id) || state.conversation.entity;
    const title = item?.title || t('Untitled');

    const ok = await confirmDialog({
      title: t('Delete this conversation?'),
      text: t('“:title” and all of its messages will be deleted for good, together with its screenshot. This cannot be undone.', { title }),
      action: t('Delete conversation'),
    });

    if (!ok) return;

    // Xoá lạc quan: gỡ khỏi danh sách ngay, trả lại chỗ cũ nếu máy chủ từ chối.
    const snapshot = state.history.items;
    const neighbour = historyLib.neighbourOf(snapshot, id);
    const wasViewing = state.view === id;

    state.history.items = historyLib.remove(snapshot, id);
    renderHistory();

    if (wasViewing) {
      if (neighbour) openConversation(neighbour.id, { focus: false });
      else showNewChat({ focus: false });
    }

    const result = await api.conversations.remove(id);

    if (!result?.ok && result?.code !== 'not_found') {
      state.history.items = snapshot;
      renderHistory();
      noteFailure(result);
      toast(result?.message || t('Could not delete the conversation.'), { tone: 'error' });

      if (wasViewing) openConversation(id);

      return;
    }

    detailCache.delete(id);
    imageCache.delete(id);
    toast(t('Conversation deleted.'));

    // Focus về mục kế tiếp, hoặc nút Câu hỏi mới khi không còn gì.
    const next = neighbour && els.historyList.querySelector(`.item[data-id="${neighbour.id}"]`);
    (next || els.newChat).focus();
  }

  function openRename() {
    const entity = state.conversation.entity;

    if (!entity?.id) return;

    closePopovers();
    els.renameInput.value = entity.title || '';
    els.renameError.hidden = true;
    els.renameInput.removeAttribute('aria-invalid');
    els.rename.showModal();
    els.renameInput.select();
  }

  async function submitRename(event) {
    event.preventDefault();

    const id = state.conversation.entity?.id;
    const title = els.renameInput.value.trim();

    const showError = (message) => {
      els.renameError.textContent = message;
      els.renameError.hidden = false;
      els.renameInput.setAttribute('aria-invalid', 'true');
      els.renameInput.focus();
    };

    if (!title) {
      showError(t('Enter a title.'));

      return;
    }

    const save = $('rename-save');
    save.disabled = true;
    const result = await api.conversations.rename(id, title);
    save.disabled = false;

    if (!result?.ok) {
      noteFailure(result);
      showError(result?.message || t('Could not rename the conversation.'));

      return;
    }

    els.rename.close();

    const updated = result.data;
    state.conversation.entity = { ...state.conversation.entity, ...updated };
    state.history.items = state.history.items.map((item) => (item.id === id ? { ...item, title: updated.title } : item));

    const cached = detailCache.get(id);
    if (cached) cached.conversation = { ...cached.conversation, title: updated.title };

    renderHeader();
    renderHistory();
    toast(t('Conversation renamed.'));
    els.convTitle.focus();
  }

  /* ============================================================
   * Menu nổi
   * ============================================================ */

  function togglePopover(button, panel, open = panel.hidden) {
    closePopovers(panel);
    panel.hidden = !open;
    button.setAttribute('aria-expanded', String(open));

    if (open) panel.querySelector('button:not(:disabled)')?.focus();
  }

  function closePopovers(except = null) {
    for (const [button, panel] of [[els.accountBtn, els.accountMenu], [els.convMenuBtn, els.convMenu]]) {
      if (panel === except) continue;
      panel.hidden = true;
      button.setAttribute('aria-expanded', 'false');
    }
  }

  function popoverKeys(event, button, panel) {
    const items = [...panel.querySelectorAll('button:not(:disabled)')];
    const index = items.indexOf(document.activeElement);

    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      togglePopover(button, panel, false);
      button.focus();
    } else if (event.key === 'ArrowDown') {
      event.preventDefault();
      items[(index + 1) % items.length]?.focus();
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      items[(index - 1 + items.length) % items.length]?.focus();
    } else if (event.key === 'Tab') {
      togglePopover(button, panel, false);
    }
  }

  /* ============================================================
   * Cài đặt
   * ============================================================ */

  let settingsTrigger = null;

  function applySettings() {
    const s = state.settings;

    els.body.classList.toggle('is-collapsed', Boolean(s.sidebarCollapsed));
    els.collapse.setAttribute('aria-expanded', String(!s.sidebarCollapsed));
    const collapseLabel = s.sidebarCollapsed ? t('Expand sidebar') : t('Collapse sidebar');
    els.collapse.setAttribute('aria-label', collapseLabel);
    els.collapse.title = collapseLabel;

    // Sidebar hẹp: viết liền "Ctrl+Alt+W" để nhãn nút không bị cắt.
    els.hotkeyKbd.textContent = formatHotkey(s.hotkey).replace(/ \+ /g, '+');

    for (const kbd of document.querySelectorAll('[data-shortcut]')) kbd.textContent = shortcutLabel(kbd.dataset.shortcut);

    renderWelcome();
    renderSettings();
  }

  function renderSettings() {
    const s = state.settings;

    for (const button of document.querySelectorAll('[data-locale]')) {
      button.setAttribute('aria-checked', String(button.dataset.locale === (s.locale || window.i18n.getLocale())));
      button.tabIndex = button.dataset.locale === (s.locale || window.i18n.getLocale()) ? 0 : -1;
    }

    const login = $('set-login');
    login.checked = Boolean(s.launchAtLogin);
    login.disabled = !s.loginItemSupported;
    $('set-login-hint').textContent = s.loginItemSupported
      ? t('Starts quietly in the tray, ready for the hotkey.')
      : t('Available in the installed app.');

    $('set-close').value = s.closeBehavior || 'tray';
    $('set-destination').value = s.captureDestination || 'compact';
    $('set-width').value = String(s.maxImageWidth || 1280);
    $('set-hotkey').textContent = formatHotkey(s.hotkey);
    $('set-version').textContent = s.version || '';

    const server = $('set-server');
    if (document.activeElement !== server) server.value = s.serverUrl || '';

    renderUpdates();
  }

  function renderUpdates() {
    const u = state.updates || {};
    const status = $('set-update-status');

    status.textContent = {
      unsupported: t('Automatic updates work in the installed app.'),
      idle: t('Checks for updates automatically.'),
      checking: t('Checking for updates…'),
      available: t('Version :version found. Downloading…', { version: u.version || '' }),
      downloading: t('Downloading version :version… :progress%', { version: u.version || '', progress: u.progress ?? 0 }),
      downloaded: t('Version :version is ready. Restart to install.', { version: u.version || '' }),
      current: t('You are on the latest version.'),
      error: t('Could not check for updates. Try again later.'),
    }[u.status] || '';

    $('set-update-check').disabled = ['unsupported', 'checking', 'downloading', 'downloaded'].includes(u.status);
    $('set-update-install').hidden = u.status !== 'downloaded';
  }

  async function updateSettings(patch) {
    const result = await api.settings.update(patch);

    if (!result?.ok) {
      toast(result?.message || t('Could not save the setting.'), { tone: 'error' });
      renderSettings();

      return false;
    }

    state.settings = result.data;
    applySettings();

    return true;
  }

  function openSettings() {
    if (!els.settings.hidden) return;

    closePopovers();
    settingsTrigger = document.activeElement;
    renderSettings();
    els.settings.hidden = false;
    $('settings-close').focus();
  }

  function closeSettings() {
    if (els.settings.hidden) return;

    els.settings.hidden = true;
    $('set-server-error').hidden = true;

    if (settingsTrigger && document.contains(settingsTrigger)) settingsTrigger.focus();
    else els.input.focus();
  }

  function trapFocus(container, event) {
    if (event.key !== 'Tab') return;

    const items = [...container.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), [tabindex="0"]')]
      .filter((node) => node.offsetParent !== null);

    if (items.length === 0) return;

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  async function saveServer(event) {
    event.preventDefault();

    const input = $('set-server');
    const error = $('set-server-error');
    const value = input.value.trim();

    error.hidden = true;
    input.removeAttribute('aria-invalid');

    if (value === state.settings.serverUrl) return;

    const ok = await confirmDialog({
      title: t('Change the server address?'),
      text: t('You will be signed out, and your next sign-in will go to :url. Only do this if your organisation told you to.', { url: value }),
      action: t('Change and sign out'),
    });

    if (!ok) return;

    const result = await api.settings.update({ serverUrl: value });

    if (!result?.ok) {
      error.textContent = t('Enter a full address starting with https:// or http://.');
      error.hidden = false;
      input.setAttribute('aria-invalid', 'true');
      input.focus();

      return;
    }

    state.settings = result.data;
    applySettings();
  }

  async function resetSettings() {
    const ok = await confirmDialog({
      title: t('Reset settings?'),
      text: t('Language, window and capture settings go back to their defaults. You stay signed in.'),
      action: t('Reset'),
    });

    if (!ok) return;

    const result = await api.settings.reset();

    if (result?.ok) {
      state.settings = result.data;
      applySettings();
      toast(t('Settings reset.'));
    }
  }

  /* ============================================================
   * Sự kiện giao diện
   * ============================================================ */

  function bindUi() {
    els.newChat.addEventListener('click', () => showNewChat());
    els.capture.addEventListener('click', () => startCapture());
    $('composer-capture').addEventListener('click', () => startCapture());
    $('welcome-capture').addEventListener('click', () => startCapture());
    els.welcomeRecent.addEventListener('click', () => {
      const latest = state.history.items[0];
      if (latest) openConversation(latest.id);
    });

    els.collapse.addEventListener('click', () => updateSettings({ sidebarCollapsed: !state.settings.sidebarCollapsed }));

    /* lịch sử */
    els.historyList.addEventListener('click', (event) => {
      const item = event.target.closest('.item');
      if (item) openConversation(Number(item.dataset.id));
    });

    els.historyList.addEventListener('keydown', (event) => {
      if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;

      const items = [...els.historyList.querySelectorAll('.item')];
      const index = items.indexOf(document.activeElement);

      if (index === -1) return;

      event.preventDefault();

      const next = {
        ArrowDown: items[Math.min(index + 1, items.length - 1)],
        ArrowUp: items[Math.max(index - 1, 0)],
        Home: items[0],
        End: items[items.length - 1],
      }[event.key];

      next?.focus();
      next?.scrollIntoView({ block: 'nearest' });
    });

    // Tải trang tiếp khi cuộn gần cuối danh sách.
    els.historyNav.addEventListener('scroll', () => {
      const nav = els.historyNav;

      if (nav.scrollHeight - nav.scrollTop - nav.clientHeight < 160) loadHistory();
    });

    els.search.addEventListener('input', onSearchInput);

    // Escape lần đầu xoá từ khoá, lần sau trả focus về ô soạn.
    els.search.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();

        if (els.search.value) {
          els.search.value = '';
          onSearchInput();
        } else {
          els.input.focus();
        }
      } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        els.historyList.querySelector('.item')?.focus();
      }
    });

    /* ô soạn */
    els.input.addEventListener('input', () => {
      autosize();
      renderComposer();
    });

    els.input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        if (!els.send.disabled && !els.send.hidden) send();
      }
    });

    els.composer.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!els.send.disabled) send();
    });

    els.stop.addEventListener('click', () => stopActive());
    $('attachment-remove').addEventListener('click', () => {
      setAttachment(null);
      els.input.focus();
    });

    /* link trong câu trả lời: nhờ tiến trình chính mở, chỉ http/https */
    els.thread.addEventListener('click', (event) => {
      const link = event.target.closest('.md-link');

      if (link) {
        event.preventDefault();
        api.external.link(link.dataset.href);
      }
    });

    els.bannerRetry.addEventListener('click', () => {
      els.bannerRetry.disabled = true;
      boot().finally(() => { els.bannerRetry.disabled = false; });
    });

    /* menu tài khoản */
    els.accountBtn.addEventListener('click', () => togglePopover(els.accountBtn, els.accountMenu));
    els.accountMenu.addEventListener('keydown', (event) => popoverKeys(event, els.accountBtn, els.accountMenu));
    $('menu-settings').addEventListener('click', () => openSettings());
    $('menu-providers').addEventListener('click', () => { closePopovers(); api.external.providerManagement(); });
    $('menu-dashboard').addEventListener('click', () => { closePopovers(); api.external.dashboard(); });
    $('menu-logout').addEventListener('click', async () => {
      closePopovers();
      await api.account.logout();
    });

    /* menu hội thoại */
    els.convMenuBtn.addEventListener('click', () => togglePopover(els.convMenuBtn, els.convMenu));
    els.convMenu.addEventListener('keydown', (event) => popoverKeys(event, els.convMenuBtn, els.convMenu));
    $('conv-rename').addEventListener('click', () => openRename());
    $('conv-web').addEventListener('click', () => {
      closePopovers();
      if (state.conversation.entity?.id) api.external.conversation(state.conversation.entity.id);
    });
    $('conv-delete').addEventListener('click', () => {
      closePopovers();
      if (state.conversation.entity?.id) deleteConversation(state.conversation.entity.id);
    });

    document.addEventListener('click', (event) => {
      if (!event.target.closest('.popover, #account-btn, #conv-menu-btn')) closePopovers();
    });

    /* đổi tên */
    $('rename-form').addEventListener('submit', submitRename);
    $('rename-cancel').addEventListener('click', () => els.rename.close());
    els.rename.addEventListener('close', () => {
      if (state.conversation.entity?.id) els.convTitle.focus();
    });

    /* cài đặt */
    $('settings-close').addEventListener('click', () => closeSettings());
    els.settings.addEventListener('click', (event) => { if (event.target === els.settings) closeSettings(); });
    els.settings.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeSettings();
      }

      trapFocus(els.settings, event);
    });

    for (const button of document.querySelectorAll('[data-locale]')) {
      button.addEventListener('click', () => updateSettings({ locale: button.dataset.locale }));
      button.addEventListener('keydown', (event) => {
        if (['ArrowLeft', 'ArrowRight'].includes(event.key)) {
          event.preventDefault();
          const next = button.dataset.locale === 'vi' ? 'en' : 'vi';
          updateSettings({ locale: next }).then(() => document.querySelector(`[data-locale="${next}"]`)?.focus());
        }
      });
    }

    $('set-login').addEventListener('change', (event) => updateSettings({ launchAtLogin: event.target.checked }));
    $('set-close').addEventListener('change', (event) => updateSettings({ closeBehavior: event.target.value }));
    $('set-destination').addEventListener('change', (event) => updateSettings({ captureDestination: event.target.value }));
    $('set-width').addEventListener('change', (event) => updateSettings({ maxImageWidth: Number(event.target.value) }));
    $('set-server-form').addEventListener('submit', saveServer);
    $('set-reset').addEventListener('click', () => resetSettings());

    $('set-update-check').addEventListener('click', async () => {
      $('set-update-check').disabled = true;
      const result = await api.updates.check();
      if (result?.ok) state.updates = result.data;
      renderUpdates();
    });
    $('set-update-install').addEventListener('click', () => api.updates.install());

    /* phím tắt trong cửa sổ */
    document.addEventListener('keydown', (event) => {
      const mod = isMac ? event.metaKey : event.ctrlKey;
      const modalOpen = els.confirm.open || els.rename.open;

      if (mod && !event.shiftKey && !event.altKey && !modalOpen) {
        const key = event.key.toLowerCase();

        if (key === 'n') {
          event.preventDefault();
          closeSettings();
          showNewChat();

          return;
        }

        if (key === 'k') {
          event.preventDefault();
          closeSettings();
          if (state.settings.sidebarCollapsed) updateSettings({ sidebarCollapsed: false }).then(() => els.search.focus());
          else els.search.focus();

          return;
        }

        if (key === ',') {
          event.preventDefault();
          openSettings();

          return;
        }
      }

      if (event.key === 'Escape' && !modalOpen && els.settings.hidden) {
        if (!els.accountMenu.hidden || !els.convMenu.hidden) {
          closePopovers();
        }
      }
    });

    window.addEventListener('online', () => { if (!state.session.online) boot(); });
  }

  function startCapture() {
    closePopovers();
    api.capture.start();
  }

  /* ============================================================
   * Sự kiện từ tiến trình chính
   * ============================================================ */

  function bindMain() {
    api.ask.onEvent(onAskEvent);

    api.capture.onCompleted((payload) => {
      setAttachment(payload);

      // Chụp trong lúc đang xem một hội thoại cũ thì ảnh đi vào câu hỏi mới:
      // máy chủ chỉ gắn ảnh cho lượt đầu của một hội thoại.
      if (state.view !== 'new' && !isStreamingHere()) showNewChat({ focus: false });

      els.input.focus();
      announce(t('Screenshot attached. Type your question.'));
    });

    api.capture.onCancelled(() => els.input.focus());

    api.capture.onError((payload) => {
      toast(payload?.message || t('Could not process the screenshot.'), { tone: 'error' });
      els.input.focus();
    });

    api.conversations.onChanged(({ id }) => {
      detailCache.delete(id);
      loadHistory({ reset: true });

      if (state.view === id && !isStreamingHere()) openConversation(id, { focus: false });
    });

    api.conversations.onDeleted(({ id }) => {
      if (!state.history.items.some((item) => item.id === id) && state.view !== id) return;

      state.history.items = historyLib.remove(state.history.items, id);
      renderHistory();

      if (state.view === id) showNewChat({ focus: false });
    });

    api.workspace.onCommand(({ command, id }) => {
      if (command === 'new-chat') {
        closeSettings();
        showNewChat();
      } else if (command === 'open-settings') {
        openSettings();
      } else if (command === 'open-conversation' && Number.isInteger(id)) {
        closeSettings();
        openConversation(id);
      }
    });

    api.account.onSession(({ authenticated }) => {
      if (authenticated) boot();
      else resetForSignedOut();
    });

    api.settings.onChanged((settings) => {
      state.settings = settings;
      applySettings();
    });

    api.updates.onState((updates) => {
      state.updates = updates;
      renderUpdates();
    });
  }

  /* ============================================================
   * Khởi động
   * ============================================================ */

  /** Đổi ngôn ngữ: dịch lại mọi chữ do JS dựng mà `data-i18n` không với tới. */
  function relabel() {
    applySettings();
    renderAccount();
    renderHistory();
    renderComposer();

    if (state.view !== 'new') {
      renderHeader();
      renderThread();
    }
  }

  icons.hydrate();
  bindUi();
  bindMain();

  window.i18n.start(() => relabel()).then(() => {
    els.body.classList.remove('is-booting');
    renderHistory({ skeleton: true });
    els.input.focus();

    return boot();
  });
})();
