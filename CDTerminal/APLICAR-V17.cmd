@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\Scripts\aplicar-configurador-v17.ps1"
pause
