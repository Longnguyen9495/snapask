'use strict';

const { contextBridge, ipcRenderer } = require('electron');

/**
 * Cầu nối cho ô chat nhỏ và cửa sổ đăng nhập.
 *
 * Giữ nguyên các hàm của 0.2.x để app.js và login.js không phải đổi, chỉ thêm:
 * mở hội thoại trong workspace, nghe hội thoại bị xoá, nghe thông báo hết phiên.
 */
const listen = (channel) => (handler) => {
  const wrapped = (event, payload) => handler(payload);

  ipcRenderer.on(channel, wrapped);

  return () => ipcRenderer.removeListener(channel, wrapped);
};

contextBridge.exposeInMainWorld('snapask', {
  onCapture: listen('chat:capture'),
  onEvent: listen('chat:event'),
  ask: (payload) => ipcRenderer.invoke('chat:ask', payload),
  stop: () => ipcRenderer.invoke('chat:stop'),
  hide: () => ipcRenderer.invoke('chat:hide'),
  recapture: () => ipcRenderer.invoke('chat:recapture'),
  openInWorkspace: (conversationId) => ipcRenderer.invoke('chat:open-in-workspace', conversationId ?? null),
  onConversationDeleted: listen('conversation:deleted'),

  login: (credentials) => ipcRenderer.invoke('auth:login', credentials),
  register: (details) => ipcRenderer.invoke('auth:register', details),
  logout: () => ipcRenderer.invoke('auth:logout'),
  authState: () => ipcRenderer.invoke('auth:state'),
  onAuthNotice: listen('auth:notice'),
  openManagement: () => ipcRenderer.invoke('app:open-management'),
  openHistory: () => ipcRenderer.invoke('app:open-history'),
  settings: () => ipcRenderer.invoke('settings:read'),
  saveSettings: (patch) => ipcRenderer.invoke('settings:write', patch),

  // Ngôn ngữ: renderer hỏi một lần lúc mở, rồi nghe tiếp mỗi lần người dùng đổi.
  locale: () => ipcRenderer.invoke('app:locale'),
  setLocale: (locale) => ipcRenderer.invoke('app:set-locale', locale),
  onLocale: listen('app:locale'),
});
