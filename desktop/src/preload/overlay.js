'use strict';

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('overlay', {
  onImage: (handler) => ipcRenderer.on('overlay:image', (event, payload) => handler(payload)),
  done: (result) => ipcRenderer.invoke('overlay:done', result),
  cancel: () => ipcRenderer.invoke('overlay:cancel'),
  copy: (dataUrl) => ipcRenderer.invoke('overlay:copy', dataUrl),
  save: (dataUrl) => ipcRenderer.invoke('overlay:save', dataUrl),
});
