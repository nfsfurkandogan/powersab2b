@echo off
setlocal

cd /d "%~dp0"

if not exist node_modules (
  call npm ci --omit=dev
)

node logo-sync-doctor.mjs
if errorlevel 1 exit /b %errorlevel%

endlocal
