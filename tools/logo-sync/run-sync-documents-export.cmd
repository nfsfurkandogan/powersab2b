@echo off
setlocal

cd /d "%~dp0"

set "LOCKDIR=%~dp0.sync-documents-export.lock"
mkdir "%LOCKDIR%" 2>nul
if errorlevel 1 (
  echo [%date% %time%] previous documents export is still running; skipped. >> "%~dp0sync-documents-export.log"
  exit /b 0
)

if not exist node_modules (
  call npm ci --omit=dev >> "%~dp0sync-documents-export.log" 2>&1
  if errorlevel 1 (
    set "EXITCODE=%ERRORLEVEL%"
    rmdir "%LOCKDIR%" 2>nul
    exit /b %EXITCODE%
  )
)

call npm run sync:documents-export >> "%~dp0sync-documents-export.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

rmdir "%LOCKDIR%" 2>nul
if not "%EXITCODE%"=="0" exit /b %EXITCODE%

endlocal
