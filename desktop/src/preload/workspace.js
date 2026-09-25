'use strict';

const { contextBridge, ipcRenderer } = require('electron');

/**
 * Cầu nối cho cửa sổ chính.
 *
 * Chỉ bày từng việc cụ thể, không bày ipcRenderer: renderer hiển thị nội dung
 * do mô hình sinh ra, nên nó không được tự chọn kênh để gọi. Không có token,
 * đường dẫn tệp hay đối tượng Electron nào đi qua đây.
 *
 * Mọi hàm nghe sự kiện trả về hàm huỷ đăng ký.
 */
const listen = (channel) => (handler) => {
  const wrapped = (event, payload) => handler(payload);

  ipcRenderer.on(channel, wrapped);

  return () => ipcRenderer.removeListener(channel, wrapped);
};

const invoke = (channel) => (...args) => ipcRenderer.invoke(channel, ...args);

contextBridge.exposeInMainWorld('snapask', {
  // Ngôn ngữ, cùng tên với các cửa sổ khác để renderer/i18n.js dùng chung được.
  locale: invoke('app:locale'),
  setLocale: invoke('app:set-locale'),
  onLocale: listen('app:locale'),

  workspace: {
    ready: invoke('workspace:ready'),
    hide: invoke('workspace:hide'),
    quit: invoke('workspace:quit'),
    onCommand: listen('workspace:command'),
  },

  account: {
    refresh: invoke('account:refresh'),
    logout: invoke('account:logout'),
    onSession: listen('session:changed'),
  },

  conversations: {
    list: (query) => ipcRenderer.invoke('conversations:list', query),
    show: (id) => ipcRenderer.invoke('conversations:show', id),
    rename: (id, title) => ipcRenderer.invoke('conversations:rename', id, title),
    remove: (id) => ipcRenderer.invoke('conversations:delete', id),
    image: (id) => ipcRenderer.invoke('conversations:image', id),
    onChanged: listen('conversation:changed'),
    onDeleted: listen('conversation:deleted'),
  },

  ask: {
    start: (payload) => ipcRenderer.invoke('ask:start', payload),
    cancel: (requestId) => ipcRenderer.invoke('ask:cancel', requestId),
    onEvent: listen('ask:event'),
  },

  capture: {
    start: invoke('capture:start'),
    onCompleted: listen('capture:completed'),
    onCancelled: listen('capture:cancelled'),
    onError: listen('capture:error'),
  },

  settings: {
    get: invoke('settings:get'),
    update: (patch) => ipcRenderer.invoke('settings:update', patch),
    reset: invoke('settings:reset'),
    onChanged: listen('settings:changed'),
  },

  updates: {
    check: invoke('updates:check'),
    install: invoke('updates:install'),
    onState: listen('updates:state'),
  },

  external: {
    providerManagement: invoke('external:open-provider-management'),
    dashboard: invoke('external:open-dashboard'),
    account: invoke('external:open-account'),
    conversation: (id) => ipcRenderer.invoke('external:open-conversation', id),
    link: (url) => ipcRenderer.invoke('external:open-link', url),
  },
});
