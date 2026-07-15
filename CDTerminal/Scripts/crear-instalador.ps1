[CmdletBinding()]
param(
    [string]$Version = "1.0.0",
    [switch]$SoloPublicar
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$project = Get-ChildItem -Path $projectRoot -Filter "CDTerminal.csproj" -File | Select-Object -First 1

if (-not $project) {
    throw "No se encontró CDTerminal.csproj en: $projectRoot"
}

if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) {
    throw "No se encontró dotnet. Instala el SDK de .NET usado por el proyecto."
}

$publishDir = Join-Path $projectRoot "artifacts\publish\win-x64"
$installerDir = Join-Path $projectRoot "artifacts\installer"

Write-Host ""
Write-Host "CD Terminal Local $Version" -ForegroundColor Cyan
Write-Host "Proyecto: $($project.FullName)"
Write-Host "Publicación: $publishDir"
Write-Host ""

if (Test-Path $publishDir) {
    Remove-Item $publishDir -Recurse -Force
}
New-Item -ItemType Directory -Path $publishDir -Force | Out-Null
New-Item -ItemType Directory -Path $installerDir -Force | Out-Null

Push-Location $projectRoot
try {
    dotnet clean $project.FullName -c Release
    if ($LASTEXITCODE -ne 0) { throw "Falló dotnet clean." }

    dotnet restore $project.FullName
    if ($LASTEXITCODE -ne 0) { throw "Falló dotnet restore." }

    dotnet publish $project.FullName `
        -c Release `
        -r win-x64 `
        --self-contained true `
        -p:Version=$Version `
        -p:FileVersion="$Version.0" `
        -p:AssemblyVersion="$Version.0" `
        -p:InformationalVersion="${Version}-local" `
        -p:PublishSingleFile=false `
        -p:PublishReadyToRun=true `
        -p:PublishTrimmed=false `
        -p:DebugType=None `
        -p:DebugSymbols=false `
        -o $publishDir

    if ($LASTEXITCODE -ne 0) { throw "Falló dotnet publish." }
}
finally {
    Pop-Location
}

$exePath = Join-Path $publishDir "CDTerminal.exe"
if (-not (Test-Path $exePath)) {
    throw "La publicación terminó, pero no apareció CDTerminal.exe en $publishDir"
}

Write-Host "Publicación win-x64 creada correctamente." -ForegroundColor Green

if ($SoloPublicar) {
    Write-Host "Se omitió la compilación del instalador por -SoloPublicar."
    exit 0
}

$isccCandidates = @(
    (Get-Command ISCC.exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source -ErrorAction SilentlyContinue),
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
) | Where-Object { $_ -and (Test-Path $_) }

$iscc = $isccCandidates | Select-Object -First 1
if (-not $iscc) {
    Write-Warning "No se encontró Inno Setup 6. La aplicación publicada ya está lista en: $publishDir"
    Write-Warning "Instala Inno Setup 6 y vuelve a ejecutar este script para generar el Setup.exe."
    exit 0
}

$iss = Join-Path $projectRoot "Installer\CDTerminal.iss"
if (-not (Test-Path $iss)) {
    throw "No se encontró el script del instalador: $iss"
}

& $iscc "/DMyAppVersion=$Version" $iss
if ($LASTEXITCODE -ne 0) {
    throw "Inno Setup no pudo compilar el instalador."
}

$setup = Get-ChildItem -Path $installerDir -Filter "CDTerminal-Local-Setup-$Version-x64.exe" -File | Select-Object -First 1
if (-not $setup) {
    throw "No se encontró el instalador compilado en: $installerDir"
}

$hash = Get-FileHash -Path $setup.FullName -Algorithm SHA256
$checksumPath = Join-Path $installerDir "SHA256SUMS.txt"
"$($hash.Hash.ToLower())  $($setup.Name)" | Set-Content -Path $checksumPath -Encoding ASCII

Write-Host ""
Write-Host "Instalador creado:" -ForegroundColor Green
Write-Host $setup.FullName
Write-Host "SHA-256: $($hash.Hash)"
