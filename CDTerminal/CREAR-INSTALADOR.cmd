@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\Scripts\crear-instalador.ps1" -Version 1.4.0
pause
