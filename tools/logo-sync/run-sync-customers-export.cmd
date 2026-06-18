@echo off
setlocal

cd /d "%~dp0"
call npm run sync:customers-export >> "%~dp0sync-customers-export.log" 2>&1

endlocal
