@echo off
setlocal

cd /d "%~dp0"

if "%SYNC_RESUME%"=="" set "SYNC_RESUME=true"
if "%SYNC_BATCH_SIZE%"=="" set "SYNC_BATCH_SIZE=25"
if "%SYNC_RETRY_MAX%"=="" set "SYNC_RETRY_MAX=3"
if "%SYNC_RETRY_BASE_DELAY_MS%"=="" set "SYNC_RETRY_BASE_DELAY_MS=3000"
if "%SYNC_CONTINUE_ON_ERROR%"=="" set "SYNC_CONTINUE_ON_ERROR=false"

if not exist node_modules (
  call npm ci --omit=dev
)

call npm run sync:products >> "%~dp0sync-products.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

if not "%EXITCODE%"=="0" exit /b %EXITCODE%

endlocal
