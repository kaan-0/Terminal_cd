param(
    [string]$ProjectRoot = (Get-Location).Path,
    [switch]$NoBuild
)

$ErrorActionPreference = 'Stop'

function Find-OneFile {
    param(
        [string]$Root,
        [string]$Name
    )

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
$inicioTarget = Find-OneFile -Root $ProjectRoot -Name 'Inicio.razor'
$cssTarget = Find-OneFile -Root $ProjectRoot -Name 'Inicio.razor.css'
$indexTarget = Find-OneFile -Root $ProjectRoot -Name 'index.html'

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupRoot = Join-Path $ProjectRoot "Backups\ssh-$stamp"
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null

Copy-Item $projectFile (Join-Path $backupRoot 'CDTerminal.csproj') -Force
Copy-Item $inicioTarget (Join-Path $backupRoot 'Inicio.razor') -Force
Copy-Item $cssTarget (Join-Path $backupRoot 'Inicio.razor.css') -Force
Copy-Item $indexTarget (Join-Path $backupRoot 'index.html') -Force

Copy-Item (Join-Path $kitRoot 'Inicio.razor') $inicioTarget -Force
Copy-Item (Join-Path $kitRoot 'Inicio.razor.css') $cssTarget -Force

$projectDir = Split-Path -Parent $projectFile

$folders = @(
    'Components\Pages\Modulos',
    'Models',
    'Services',
    'wwwroot\js'
)

foreach ($folder in $folders) {
    New-Item -ItemType Directory -Path (Join-Path $projectDir $folder) -Force | Out-Null
}

Copy-Item (Join-Path $kitRoot 'Components\Pages\Modulos\TerminalSsh.razor') `
    (Join-Path $projectDir 'Components\Pages\Modulos\TerminalSsh.razor') -Force
Copy-Item (Join-Path $kitRoot 'Components\Pages\Modulos\TerminalSsh.razor.css') `
    (Join-Path $projectDir 'Components\Pages\Modulos\TerminalSsh.razor.css') -Force
Copy-Item (Join-Path $kitRoot 'Models\ConfiguracionSsh.cs') `
    (Join-Path $projectDir 'Models\ConfiguracionSsh.cs') -Force
Copy-Item (Join-Path $kitRoot 'Models\HostSshConocido.cs') `
    (Join-Path $projectDir 'Models\HostSshConocido.cs') -Force
Copy-Item (Join-Path $kitRoot 'Models\ResultadoConexionSsh.cs') `
    (Join-Path $projectDir 'Models\ResultadoConexionSsh.cs') -Force
Copy-Item (Join-Path $kitRoot 'Services\SshKnownHostsStore.cs') `
    (Join-Path $projectDir 'Services\SshKnownHostsStore.cs') -Force
Copy-Item (Join-Path $kitRoot 'Services\SshTerminalService.cs') `
    (Join-Path $projectDir 'Services\SshTerminalService.cs') -Force
Copy-Item (Join-Path $kitRoot 'wwwroot\js\cdterminal-ssh.js') `
    (Join-Path $projectDir 'wwwroot\js\cdterminal-ssh.js') -Force

$indexContent = Get-Content $indexTarget -Raw
$scriptTag = '<script src="js/cdterminal-ssh.js"></script>'

if ($indexContent -notmatch [regex]::Escape('js/cdterminal-ssh.js')) {
    if ($indexContent -notmatch '</body>') {
        throw 'No se encontro </body> en wwwroot\index.html.'
    }

    $indexContent = $indexContent -replace '</body>', "    $scriptTag`r`n</body>"
    Set-Content -Path $indexTarget -Value $indexContent -Encoding UTF8
}

Write-Host 'Instalando dependencia SSH.NET...' -ForegroundColor Cyan

dotnet add $projectFile package SSH.NET --version 2025.1.0
if ($LASTEXITCODE -ne 0) {
    throw 'No se pudo agregar el paquete SSH.NET al proyecto.'
}

Write-Host ''
Write-Host 'Modulo SSH aplicado.' -ForegroundColor Green
Write-Host "Respaldo: $backupRoot"
Write-Host "Proyecto: $projectFile"

if (-not $NoBuild) {
    Push-Location $projectDir

    try {
        dotnet clean
        if ($LASTEXITCODE -ne 0) { throw 'dotnet clean fallo.' }

        dotnet restore
        if ($LASTEXITCODE -ne 0) { throw 'dotnet restore fallo.' }

        dotnet build .\CDTerminal.csproj -c Debug --no-restore
        if ($LASTEXITCODE -ne 0) { throw 'dotnet build fallo.' }
    }
    finally {
        Pop-Location
    }

    Write-Host 'Compilacion SSH: OK' -ForegroundColor Green
}
