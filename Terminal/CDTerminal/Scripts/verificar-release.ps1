[CmdletBinding()]
param(
    [string]$Version = "1.0.0"
)

$ErrorActionPreference = "Stop"
$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$publish = Join-Path $root "artifacts\publish\win-x64"
$installer = Join-Path $root "artifacts\installer\CDTerminal-Local-Setup-$Version-x64.exe"

$required = @(
    (Join-Path $publish "CDTerminal.exe"),
    (Join-Path $publish "CDTerminal.dll"),
    (Join-Path $publish "wwwroot")
)

$missing = $required | Where-Object { -not (Test-Path $_) }
if ($missing) {
    Write-Host "Faltan elementos en la publicación:" -ForegroundColor Red
    $missing | ForEach-Object { Write-Host " - $_" }
    exit 1
}

Write-Host "Publicación básica: OK" -ForegroundColor Green

if (Test-Path $installer) {
    $hash = Get-FileHash $installer -Algorithm SHA256
    Write-Host "Instalador: OK" -ForegroundColor Green
    Write-Host "SHA-256: $($hash.Hash)"
} else {
    Write-Warning "No se encontró el Setup.exe. Puede que todavía no hayas instalado/ejecutado Inno Setup."
}
