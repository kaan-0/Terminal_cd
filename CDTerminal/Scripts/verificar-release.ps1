[CmdletBinding()]
param(
    [string]$Version = "1.4.0"
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$publish = Join-Path $root "artifacts\publish\win-x64"
$installer = Join-Path $root "artifacts\installer\CdTecHNologia-CDTerminal-$Version-x64.exe"
$icon = Join-Path $root "Assets\CDTerminal.ico"

$required = @(
    (Join-Path $publish "CDTerminal.exe"),
    (Join-Path $publish "CDTerminal.dll"),
    (Join-Path $publish "wwwroot")
)

$missing = $required | Where-Object { -not (Test-Path $_) }
if ($missing) {
    Write-Host "Faltan elementos en la publicacion:" -ForegroundColor Red
    $missing | ForEach-Object { Write-Host " - $_" }
    exit 1
}

Write-Host "Publicacion basica: OK" -ForegroundColor Green

if (Test-Path $icon) {
    Write-Host "Icono: OK" -ForegroundColor Green
}
else {
    Write-Warning "No se encontro Assets\CDTerminal.ico."
}

$sshAssembly = Join-Path $publish "Renci.SshNet.dll"
if (Test-Path $sshAssembly) {
    Write-Host "SSH.NET: OK" -ForegroundColor Green
}
else {
    Write-Warning "No se encontro Renci.SshNet.dll."
}

$inicio = Get-ChildItem -Path $root -Filter "Inicio.razor" -File -Recurse |
    Where-Object { $_.FullName -notmatch '\\(bin|obj|artifacts|Backups|Payload_V17)\\' } |
    Select-Object -First 1
if ($inicio) {
    $contenido = Get-Content $inicio.FullName -Raw
    if ($contenido -match "https://rs485\.cdtechnologia\.net/api/v1/mediciones") {
        Write-Host "Destino HTTPS de produccion: OK" -ForegroundColor Green
    }
    else {
        Write-Warning "No se detecto el endpoint HTTPS de produccion en Inicio.razor."
    }
}

$exe = Get-Item (Join-Path $publish "CDTerminal.exe")
Write-Host "Version publicada: $($exe.VersionInfo.FileVersion)"

if (Test-Path $installer) {
    $hash = Get-FileHash $installer -Algorithm SHA256
    Write-Host "Instalador: OK" -ForegroundColor Green
    Write-Host "SHA-256: $($hash.Hash)"
}
else {
    Write-Warning "No se encontro el instalador. Revisa Inno Setup 6."
}
