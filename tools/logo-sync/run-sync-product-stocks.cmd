@echo off
setlocal

cd /d "%~dp0"

set "LOCKDIR=%~dp0.sync-products.lock"
mkdir "%LOCKDIR%" 2>nul
if errorlevel 1 (
  echo [%date% %time%] previous products sync is still running; skipped. >> "%~dp0sync-products.log"
  exit /b 0
)

if not exist node_modules (
  call npm ci --omit=dev
)

set "SYNC_PRODUCTS_STOCK_ONLY=true"
if "%SYNC_BATCH_SIZE%"=="" set "SYNC_BATCH_SIZE=500"
if "%LOGO_STOCK_TABLE%"=="" set "LOGO_STOCK_TABLE=dbo.LV_003_01_STINVTOT"
if "%POWERSA_PRODUCTS_SYNC_URL%"=="" set "POWERSA_PRODUCTS_SYNC_URL=https://powersab2b.com/backend/api/integrations/logo/products/sync"

call npm run sync:products >> "%~dp0sync-products.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

rmdir "%LOCKDIR%" 2>nul
if not "%EXITCODE%"=="0" exit /b %EXITCODE%

endlocal
