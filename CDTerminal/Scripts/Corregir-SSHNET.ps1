param(
    [string]$ProjectRoot = (Get-Location).Path,
    [string]$Version = "2025.1.0"
)

$ErrorActionPreference = 'Stop'

function Find-ProjectFile {
    param([string]$Root)

    $projects = @(Get-ChildItem -Path $Root -Recurse -File -Filter 'CDTerminal.csproj' |
        Where-Object { $_.FullName -notmatch '\\(bin|obj|artifacts|Backups)\\' })

    if ($projects.Count -eq 0) {
        throw "No se encontro CDTerminal.csproj dentro de $Root"
    }

    if ($projects.Count -gt 1) {
        Write-Host "Se encontraron varios proyectos. Se usara: $($projects[0].FullName)" -ForegroundColor Yellow
    }

    return $projects[0].FullName
}

$projectFile = Find-ProjectFile -Root $ProjectRoot
$projectDir = Split-Path -Parent $projectFile

Write-Host "Proyecto: $projectFile" -ForegroundColor Cyan
Write-Host "Instalando SSH.NET $Version..." -ForegroundColor Cyan

Push-Location $projectDir
try {
    dotnet add $projectFile package SSH.NET --version $Version
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo agregar el paquete SSH.NET."
    }

    dotnet clean $projectFile
    if ($LASTEXITCODE -ne 0) {
        throw "dotnet clean fallo."
    }

    if (Test-Path (Join-Path $projectDir 'obj')) {
        Remove-Item (Join-Path $projectDir 'obj') -Recurse -Force
    }

    dotnet restore $projectFile
    if ($LASTEXITCODE -ne 0) {
        throw "dotnet restore fallo."
    }

    dotnet build $projectFile -c Debug --no-restore
    if ($LASTEXITCODE -ne 0) {
        throw "dotnet build fallo."
    }
}
finally {
    Pop-Location
}

$projectText = Get-Content -Path $projectFile -Raw
if ($projectText -notmatch 'PackageReference\s+Include="SSH\.NET"') {
    throw "La referencia SSH.NET no aparece en CDTerminal.csproj despues de instalarla."
}

Write-Host "" 
Write-Host "SSH.NET instalado y compilacion completada." -ForegroundColor Green
Write-Host "Si Visual Studio mantiene subrayados rojos, cierre y abra nuevamente la solucion." -ForegroundColor Yellow
