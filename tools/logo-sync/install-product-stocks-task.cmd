@echo off
setlocal

cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-product-stocks-task.ps1" %*
exit /b %ERRORLEVEL%
