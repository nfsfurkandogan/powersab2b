@echo off
setlocal

cd /d "%~dp0"

set "LOCKDIR=%~dp0.sync-all.lock"
mkdir "%LOCKDIR%" 2>nul
if errorlevel 1 (
  echo [%date% %time%] previous sync-all is still running; skipped. >> "%~dp0sync-all.log"
  exit /b 0
)

if not exist node_modules (
  call npm ci --omit=dev
)

call npm run sync:all >> "%~dp0sync-all.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

rmdir "%LOCKDIR%" 2>nul
if not "%EXITCODE%"=="0" exit /b %EXITCODE%

endlocal
