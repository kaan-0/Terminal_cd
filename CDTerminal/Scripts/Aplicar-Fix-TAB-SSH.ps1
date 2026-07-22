param(
    [string]$ProjectRoot = (Get-Location).Path,
    [switch]$NoBuild
)

$ErrorActionPreference = 'Stop'

function Find-OneFile {
    param(
        [string]$Root,
        [string]$Name,
        [string]$PreferredPath = ''
    )

    if (-not [string]::IsNullOrWhiteSpace($PreferredPath)) {
        $preferred = Join-Path $Root $PreferredPath
        if (Test-Path $preferred) {
            return (Resolve-Path $preferred).Path
        }
    }

    $items = @(Get-ChildItem -Path $Root -Recurse -File -Filter $Name |
        Where-Object { $_.FullName -notmatch '\\(bin|obj|artifacts|Backups)\\' })

    if ($items.Count -eq 0) {
        throw "No se encontro $Name dentro de $Root"
    }

    return $items[0].FullName
}

$kitRoot = Split-Path -Parent $PSScriptRoot
$projectFile = Find-OneFile -Root $ProjectRoot -Name 'CDTerminal.csproj'
$projectDir = Split-Path -Parent $projectFile

$razorTarget = Find-OneFile `
    -Root $projectDir `
    -Name 'TerminalSsh.razor' `
    -PreferredPath 'Components\Pages\Modulos\TerminalSsh.razor'

$jsDir = Join-Path $projectDir 'wwwroot\js'
$jsTarget = Join-Path $jsDir 'cdterminal-ssh.js'
$indexTarget = Join-Path $projectDir 'wwwroot\index.html'

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupRoot = Join-Path $projectDir "Backups\ssh-tab-fix-$stamp"
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null

Copy-Item $razorTarget (Join-Path $backupRoot 'TerminalSsh.razor') -Force
if (Test-Path $jsTarget) {
    Copy-Item $jsTarget (Join-Path $backupRoot 'cdterminal-ssh.js') -Force
}
if (Test-Path $indexTarget) {
    Copy-Item $indexTarget (Join-Path $backupRoot 'index.html') -Force
}

Copy-Item `
    (Join-Path $kitRoot 'Components\Pages\Modulos\TerminalSsh.razor') `
    $razorTarget -Force

New-Item -ItemType Directory -Path $jsDir -Force | Out-Null
Copy-Item `
    (Join-Path $kitRoot 'wwwroot\js\cdterminal-ssh.js') `
    $jsTarget -Force

# La V3 agregaba el archivo como script global. La V4 lo importa como modulo ES,
# por eso se retira la etiqueta antigua para evitar cargar un modulo como script clasico.
if (Test-Path $indexTarget) {
    $indexContent = Get-Content $indexTarget -Raw
    $indexContent = $indexContent -replace '(?im)^\s*<script\s+src=["''](?:\./)?js/cdterminal-ssh\.js["'']\s*></script>\s*', ''
    Set-Content -Path $indexTarget -Value $indexContent -Encoding UTF8
}

Write-Host ''
Write-Host 'Fix TAB SSH aplicado.' -ForegroundColor Green
Write-Host 'El JavaScript ahora se carga como modulo desde TerminalSsh.razor.'
Write-Host 'Ya no depende de una etiqueta script en index.html.'
Write-Host "Respaldo: $backupRoot"

if (-not $NoBuild) {
    Push-Location $projectDir
    try {
        Remove-Item '.\bin' -Recurse -Force -ErrorAction SilentlyContinue
        Remove-Item '.\obj' -Recurse -Force -ErrorAction SilentlyContinue

        dotnet restore $projectFile
        if ($LASTEXITCODE -ne 0) { throw 'dotnet restore fallo.' }

        dotnet build $projectFile -c Debug --no-restore
        if ($LASTEXITCODE -ne 0) { throw 'dotnet build fallo.' }
    }
    finally {
        Pop-Location
    }

    Write-Host 'Compilacion SSH TAB FIX: OK' -ForegroundColor Green
}
