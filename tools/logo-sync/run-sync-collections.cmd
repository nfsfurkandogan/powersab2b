@echo off
setlocal

cd /d "%~dp0"

if not exist node_modules (
  call npm ci --omit=dev
)

call npm run sync:collections >> "%~dp0sync-collections.log" 2>&1
if errorlevel 1 exit /b %errorlevel%

endlocal
