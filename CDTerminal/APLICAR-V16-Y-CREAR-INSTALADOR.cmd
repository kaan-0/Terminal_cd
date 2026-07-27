@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\Scripts\aplicar-configurador-v16.ps1"
if errorlevel 1 goto :end
powershell -NoProfile -ExecutionPolicy Bypass -File ".\Scripts\crear-instalador.ps1" -Version 1.3.0
:end
pause
