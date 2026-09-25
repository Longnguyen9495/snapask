'use strict';

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('overlay', {
  onImage: (handler) => ipcRenderer.on('overlay:image', (event, payload) => handler(payload)),
  done: (result) => ipcRenderer.invoke('overlay:done', result),
  cancel: () => ipcRenderer.invoke('overlay:cancel'),
  copy: (dataUrl) => ipcRenderer.invoke('overlay:copy', dataUrl),
  save: (dataUrl) => ipcRenderer.invoke('overlay:save', dataUrl),
});

/*
 * Lớp phủ cũng cần dịch, nhưng nó không có `window.snapask`.
 *
 * Bày đúng ba thứ mà renderer/i18n.js dùng, dưới cùng một tên, để file đó chạy
 * được ở cả hai nơi mà không phải phân nhánh.
 */
contextBridge.exposeInMainWorld('snapask', {
  locale: () => ipcRenderer.invoke('app:locale'),
  setLocale: (locale) => ipcRenderer.invoke('app:set-locale', locale),
  onLocale: (handler) => ipcRenderer.on('app:locale', (event, payload) => handler(payload)),
});
