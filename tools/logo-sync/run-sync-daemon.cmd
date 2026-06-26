@echo off
setlocal

cd /d "%~dp0"

set "LOCKDIR=%~dp0.sync-daemon.lock"
mkdir "%LOCKDIR%" 2>nul
if errorlevel 1 (
  echo [%date% %time%] previous daemon is still running; skipped. >> "%~dp0sync-daemon.log"
  exit /b 0
)

if not exist node_modules (
  call npm ci --omit=dev >> "%~dp0sync-daemon.log" 2>&1
  if errorlevel 1 (
    set "EXITCODE=%ERRORLEVEL%"
    rmdir "%LOCKDIR%" 2>nul
    exit /b %EXITCODE%
  )
)

node logo-sync-daemon.mjs >> "%~dp0sync-daemon.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

rmdir "%LOCKDIR%" 2>nul
exit /b %EXITCODE%
