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

$serviceTarget = Find-OneFile `
    -Root $projectDir `
    -Name 'SshTerminalService.cs' `
    -PreferredPath 'Services\SshTerminalService.cs'

$jsDir = Join-Path $projectDir 'wwwroot\js'
$jsTarget = Join-Path $jsDir 'cdterminal-ssh.js'

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupRoot = Join-Path $projectDir "Backups\ssh-historial-rutas-$stamp"
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null

Copy-Item $razorTarget (Join-Path $backupRoot 'TerminalSsh.razor') -Force
Copy-Item $serviceTarget (Join-Path $backupRoot 'SshTerminalService.cs') -Force
if (Test-Path $jsTarget) {
    Copy-Item $jsTarget (Join-Path $backupRoot 'cdterminal-ssh.js') -Force
}

Copy-Item `
    (Join-Path $kitRoot 'Components\Pages\Modulos\TerminalSsh.razor') `
    $razorTarget -Force

Copy-Item `
    (Join-Path $kitRoot 'Services\SshTerminalService.cs') `
    $serviceTarget -Force

New-Item -ItemType Directory -Path $jsDir -Force | Out-Null
Copy-Item `
    (Join-Path $kitRoot 'wwwroot\js\cdterminal-ssh.js') `
    $jsTarget -Force

Write-Host ''
Write-Host 'SSH V5 aplicado.' -ForegroundColor Green
Write-Host 'Incluye historial con flechas y autocompletado de rutas/archivos.'
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

    Write-Host 'Compilacion SSH V5: OK' -ForegroundColor Green
}
