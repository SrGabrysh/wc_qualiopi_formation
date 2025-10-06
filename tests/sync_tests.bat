@echo off
REM Script de synchronisation des tests vers DDEV
REM Usage : Double-clic ou executer depuis cmd/powershell

echo.
echo ========================================
echo   SYNCHRONISATION TESTS vers DDEV
echo ========================================
echo.

cd /d "%~dp0"

python sync_tests_to_ddev.py

echo.
pause

