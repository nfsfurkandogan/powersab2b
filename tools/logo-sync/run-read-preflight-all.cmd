@echo off
cd /d "%~dp0"
echo == B2B write queue status ==
npm run write:queue-status
echo.
echo == Logo SQL write preflight ==
npm run write:preflight
