@echo off
setlocal

cd /d "%~dp0"

set "LOCKDIR=%~dp0.sync-product-catalog-fast.lock"
mkdir "%LOCKDIR%" 2>nul
if errorlevel 1 (
  echo [%date% %time%] previous fast product catalog sync is still running; skipped. >> "%~dp0sync-product-catalog-fast.log"
  exit /b 0
)

if not exist ".env" (
  echo [logo-sync] .env bulunamadi. Bu dosyayi C:\PowersaB2B\tools\logo-sync\.env altinda olusturun. >> "%~dp0sync-product-catalog-fast.log"
  rmdir "%LOCKDIR%" 2>nul
  exit /b 1
)

if not exist node_modules (
  call npm ci --omit=dev >> "%~dp0sync-product-catalog-fast.log" 2>&1
  if errorlevel 1 (
    set "EXITCODE=%ERRORLEVEL%"
    rmdir "%LOCKDIR%" 2>nul
    exit /b %EXITCODE%
  )
)

set "SYNC_DISABLE_LOCK=true"
set "SYNC_PRODUCTS_CATALOG_INCREMENTAL=true"
set "SYNC_PRODUCTS_CATALOG_LOOKBACK_MINUTES=10"
set "SYNC_PRODUCTS_CATALOG_RECENT_LIMIT=1000"
set "SYNC_PRODUCTS_CATALOG_ROLLING_LIMIT=1000"
set "SYNC_PRODUCTS_CATALOG_STATE_FILE=.sync-state\products-catalog-fast-state.json"
set "SYNC_PRODUCTS_STOCK_FAST=false"
set "SYNC_PRODUCTS_STOCK_INCREMENTAL=false"
set "SYNC_PRODUCTS_STOCK_ONLY=false"
set "SYNC_PRODUCTS_IMAGES_ONLY=false"
set "SYNC_RESUME=false"
set "SYNC_BATCH_SIZE=25"
set "SYNC_RETRY_MAX=3"
set "SYNC_RETRY_BASE_DELAY_MS=3000"
set "SYNC_CONTINUE_ON_ERROR=true"

call npm run sync:products >> "%~dp0sync-product-catalog-fast.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

rmdir "%LOCKDIR%" 2>nul
if not "%EXITCODE%"=="0" exit /b %EXITCODE%

endlocal
