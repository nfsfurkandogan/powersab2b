@echo off
setlocal

cd /d "%~dp0"

set "LOCKDIR=%~dp0.sync-customers.lock"
mkdir "%LOCKDIR%" 2>nul
if errorlevel 1 (
  echo [%date% %time%] previous customers sync is still running; skipped. >> "%~dp0sync-customers.log"
  exit /b 0
)

if not exist node_modules (
  call npm ci --omit=dev
)

call npm run sync:customers >> "%~dp0sync-customers.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

rmdir "%LOCKDIR%" 2>nul
if not "%EXITCODE%"=="0" exit /b %EXITCODE%

endlocal
