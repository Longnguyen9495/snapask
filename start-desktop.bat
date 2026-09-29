@echo off
REM Khoi dong ung dung Electron. Xoa ELECTRON_RUN_AS_NODE de tranh loi trong VS Code terminal.
set ELECTRON_RUN_AS_NODE=
REM Ban cai dat tro ve production; luc phat trien thi dung may chu local.
if not defined SNAPASK_SERVER_URL set SNAPASK_SERVER_URL=http://snapask.local
cd /d "%~dp0desktop"
npm start
