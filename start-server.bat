@echo off
REM Khoi dong Laravel server cho moi truong test local
cd /d "%~dp0server"
php artisan serve --host=127.0.0.1 --port=8000
