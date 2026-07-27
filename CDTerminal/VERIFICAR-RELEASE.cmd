@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\Scripts\verificar-release.ps1" -Version 1.4.0
pause
