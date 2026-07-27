[CmdletBinding()]
param()

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$payload = Join-Path $root "Payload_V16\Components\Pages"

$project = Get-ChildItem -Path $root -Filter "CDTerminal.csproj" -File | Select-Object -First 1
if (-not $project) {
    throw "Copia este kit en la raiz del proyecto, junto a CDTerminal.csproj."
}

$targetRazor = Get-ChildItem -Path $root -Filter "Inicio.razor" -File -Recurse |
    Where-Object { $_.FullName -notmatch '\\(bin|obj|artifacts|Backups|Payload_V16)\\' } |
    Sort-Object @{ Expression = { if ($_.FullName -match "Components\\Pages") { 0 } else { 1 } } }, FullName |
    Select-Object -First 1

if (-not $targetRazor) {
    throw "No se encontro Inicio.razor dentro del proyecto."
}

$targetCssPath = $targetRazor.FullName + ".css"
if (-not (Test-Path $targetCssPath)) {
    throw "No se encontro Inicio.razor.css junto a Inicio.razor."
}

foreach ($name in @("Inicio.razor", "Inicio.razor.css")) {
    if (-not (Test-Path (Join-Path $payload $name))) {
        throw "No se encontro el archivo V16: $name"
    }
}

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupDir = Join-Path $root "Backups\antes-configurador-v16-$stamp"
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

Copy-Item $targetRazor.FullName (Join-Path $backupDir "Inicio.razor") -Force
Copy-Item $targetCssPath (Join-Path $backupDir "Inicio.razor.css") -Force

Copy-Item (Join-Path $payload "Inicio.razor") $targetRazor.FullName -Force
Copy-Item (Join-Path $payload "Inicio.razor.css") $targetCssPath -Force

Write-Host "Configurador IoT V16 aplicado." -ForegroundColor Green
Write-Host "Respaldo: $backupDir"
