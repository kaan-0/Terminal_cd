[CmdletBinding()]
param()

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$payload = Join-Path $root "Payload_V9\Components\Pages"
$destino = Join-Path $root "Components\Pages"

if (-not (Test-Path (Join-Path $root "CDTerminal.csproj"))) {
    throw "Copia este kit en la raíz del proyecto, al mismo nivel que CDTerminal.csproj."
}

if (-not (Test-Path $payload)) {
    throw "No se encontró el payload V9 en: $payload"
}

New-Item -ItemType Directory -Path $destino -Force | Out-Null
$marca = Get-Date -Format "yyyyMMdd-HHmmss"
$respaldo = Join-Path $root "Backups\antes-configurador-v9-$marca"
New-Item -ItemType Directory -Path $respaldo -Force | Out-Null

foreach ($nombre in @("Inicio.razor", "Inicio.razor.css")) {
    $actual = Join-Path $destino $nombre
    if (Test-Path $actual) {
        Copy-Item $actual (Join-Path $respaldo $nombre) -Force
    }
    Copy-Item (Join-Path $payload $nombre) $actual -Force
}

Write-Host "Configurador IoT V9 aplicado." -ForegroundColor Green
Write-Host "Respaldo: $respaldo"
