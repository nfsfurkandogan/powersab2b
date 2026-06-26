@echo off
setlocal

cd /d "%~dp0"

set "LOCKDIR=%~dp0.sync-pos-sales.lock"
mkdir "%LOCKDIR%" 2>nul
if errorlevel 1 (
  echo [%date% %time%] previous pos sales export is still running; skipped. >> "%~dp0sync-pos-sales.log"
  exit /b 0
)

if not exist node_modules (
  call npm ci --omit=dev >> "%~dp0sync-pos-sales.log" 2>&1
  if errorlevel 1 (
    set "EXITCODE=%ERRORLEVEL%"
    rmdir "%LOCKDIR%" 2>nul
    exit /b %EXITCODE%
  )
)

call npm run sync:pos-sales >> "%~dp0sync-pos-sales.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

rmdir "%LOCKDIR%" 2>nul
if not "%EXITCODE%"=="0" exit /b %EXITCODE%

endlocal
