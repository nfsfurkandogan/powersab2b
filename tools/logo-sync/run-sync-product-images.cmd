@echo off
setlocal

cd /d "%~dp0"

set "LOCKDIR=%~dp0.sync-product-images.lock"
mkdir "%LOCKDIR%" 2>nul
if errorlevel 1 (
  echo [%date% %time%] previous product images sync is still running; skipped. >> "%~dp0sync-product-images.log"
  exit /b 0
)

if not exist ".env" (
  echo [logo-sync] .env bulunamadi. Bu dosyayi C:\PowersaB2B\tools\logo-sync\.env altinda olusturun.
  rmdir "%LOCKDIR%" 2>nul
  exit /b 1
)

if not exist node_modules (
  call npm ci --omit=dev >> "%~dp0sync-product-images.log" 2>&1
  if errorlevel 1 (
    set "EXITCODE=%ERRORLEVEL%"
    rmdir "%LOCKDIR%" 2>nul
    exit /b %EXITCODE%
  )
)

set SYNC_PRODUCTS_IMAGES_ONLY=true
set SYNC_PRODUCTS_STOCK_ONLY=false
set SYNC_PRODUCTS_SKIP_ALIASES=1
set SYNC_RESUME=false
if "%SYNC_BATCH_SIZE%"=="" set "SYNC_BATCH_SIZE=1"
set SYNC_PRODUCT_IMAGE_OPTIMIZE=true
set SYNC_PRODUCT_IMAGE_MAX_WIDTH=1200
set SYNC_PRODUCT_IMAGE_JPEG_QUALITY=80
set SYNC_PRODUCT_IMAGE_TARGET_MAX_BYTES=1500000
set SYNC_PRODUCT_IMAGE_OUTPUT_FORMAT=jpeg
set SYNC_PRODUCT_IMAGE_ALLOW_ORIGINAL_IF_SMALL=true
set SYNC_PRODUCT_IMAGE_ORIGINAL_MAX_BYTES=1500000
set SYNC_STATE_FILE=.sync-state\products-images-state.json
set SYNC_FAILED_FILE=.sync-state\products-images-failed.jsonl
set SYNC_RETRY_MAX=3
set SYNC_RETRY_BASE_DELAY_MS=3000
set SYNC_CONTINUE_ON_ERROR=false

call npm run sync:products >> "%~dp0sync-product-images.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"

rmdir "%LOCKDIR%" 2>nul
if not "%EXITCODE%"=="0" exit /b %EXITCODE%

endlocal
