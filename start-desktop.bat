@echo off
REM Khoi dong ung dung Electron. Xoa ELECTRON_RUN_AS_NODE de tranh loi trong VS Code terminal.
set ELECTRON_RUN_AS_NODE=
cd /d "%~dp0desktop"
npm start
