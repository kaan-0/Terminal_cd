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

    if ($items.Count -gt 1) {
        Write-Host "Se encontraron varios $Name. Se usara: $($items[0].FullName)" -ForegroundColor Yellow
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

$cssTarget = Find-OneFile `
    -Root $projectDir `
    -Name 'TerminalSsh.razor.css' `
    -PreferredPath 'Components\Pages\Modulos\TerminalSsh.razor.css'

$serviceTarget = Find-OneFile `
    -Root $projectDir `
    -Name 'SshTerminalService.cs' `
    -PreferredPath 'Services\SshTerminalService.cs'

$indexTarget = Find-OneFile `
    -Root $projectDir `
    -Name 'index.html' `
    -PreferredPath 'wwwroot\index.html'

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupRoot = Join-Path $projectDir "Backups\ssh-tab-$stamp"
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null

Copy-Item $razorTarget (Join-Path $backupRoot 'TerminalSsh.razor') -Force
Copy-Item $cssTarget (Join-Path $backupRoot 'TerminalSsh.razor.css') -Force
Copy-Item $serviceTarget (Join-Path $backupRoot 'SshTerminalService.cs') -Force
Copy-Item $indexTarget (Join-Path $backupRoot 'index.html') -Force

Copy-Item `
    (Join-Path $kitRoot 'Components\Pages\Modulos\TerminalSsh.razor') `
    $razorTarget -Force

Copy-Item `
    (Join-Path $kitRoot 'Components\Pages\Modulos\TerminalSsh.razor.css') `
    $cssTarget -Force

Copy-Item `
    (Join-Path $kitRoot 'Services\SshTerminalService.cs') `
    $serviceTarget -Force

$jsDir = Join-Path $projectDir 'wwwroot\js'
New-Item -ItemType Directory -Path $jsDir -Force | Out-Null

Copy-Item `
    (Join-Path $kitRoot 'wwwroot\js\cdterminal-ssh.js') `
    (Join-Path $jsDir 'cdterminal-ssh.js') -Force

$indexContent = Get-Content $indexTarget -Raw
$scriptTag = '<script src="js/cdterminal-ssh.js"></script>'

if ($indexContent -notmatch [regex]::Escape('js/cdterminal-ssh.js')) {
    if ($indexContent -notmatch '</body>') {
        throw 'No se encontro </body> en wwwroot\index.html.'
    }

    $indexContent = $indexContent -replace '</body>', "    $scriptTag`r`n</body>"
    Set-Content -Path $indexTarget -Value $indexContent -Encoding UTF8
}

Write-Host ''
Write-Host 'Autocompletado TAB aplicado al modulo SSH.' -ForegroundColor Green
Write-Host "Respaldo: $backupRoot"
Write-Host 'TAB completa comandos disponibles en el servidor y muestra coincidencias.'

if (-not $NoBuild) {
    Push-Location $projectDir

    try {
        dotnet clean
        if ($LASTEXITCODE -ne 0) { throw 'dotnet clean fallo.' }

        dotnet restore $projectFile
        if ($LASTEXITCODE -ne 0) { throw 'dotnet restore fallo.' }

        dotnet build $projectFile -c Debug --no-restore
        if ($LASTEXITCODE -ne 0) { throw 'dotnet build fallo.' }
    }
    finally {
        Pop-Location
    }

    Write-Host 'Compilacion SSH TAB: OK' -ForegroundColor Green
}
