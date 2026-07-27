@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\Scripts\aplicar-icono.ps1"
pause
