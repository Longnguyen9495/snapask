'use strict';

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('snapask', {
  onCapture: (handler) => ipcRenderer.on('chat:capture', (event, payload) => handler(payload)),
  onEvent: (handler) => ipcRenderer.on('chat:event', (event, payload) => handler(payload)),
  ask: (payload) => ipcRenderer.invoke('chat:ask', payload),
  stop: () => ipcRenderer.invoke('chat:stop'),
  hide: () => ipcRenderer.invoke('chat:hide'),
  recapture: () => ipcRenderer.invoke('chat:recapture'),
  login: (credentials) => ipcRenderer.invoke('auth:login', credentials),
  register: (details) => ipcRenderer.invoke('auth:register', details),
  logout: () => ipcRenderer.invoke('auth:logout'),
  authState: () => ipcRenderer.invoke('auth:state'),
  openManagement: () => ipcRenderer.invoke('app:open-management'),
  settings: () => ipcRenderer.invoke('settings:read'),
  saveSettings: (patch) => ipcRenderer.invoke('settings:write', patch),
});
