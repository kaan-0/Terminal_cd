[CmdletBinding()]
param(
    [string]$Version = "1.4.0",
    [switch]$SoloPublicar,
    [switch]$OmitirVerificacionV17,
    [switch]$OmitirAplicacionIcono
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$kitRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path

$project = Get-ChildItem -Path $kitRoot -Filter "CDTerminal.csproj" -File | Select-Object -First 1
if (-not $project) {
    $project = Get-ChildItem -Path $kitRoot -Filter "CDTerminal.csproj" -File -Recurse |
        Where-Object { $_.FullName -notmatch '\\(bin|obj|artifacts|Backups|Payload_V17)\\' } |
        Select-Object -First 1
}

if (-not $project) {
    throw "No se encontro CDTerminal.csproj dentro de: $kitRoot"
}

$projectRoot = $project.Directory.FullName

if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) {
    throw "No se encontro dotnet. Instala el SDK usado por el proyecto."
}

if (-not $OmitirAplicacionIcono) {
    & (Join-Path $PSScriptRoot "aplicar-icono.ps1")
}

if (-not $OmitirVerificacionV17) {
    $inicio = Get-ChildItem -Path $projectRoot -Filter "Inicio.razor" -File -Recurse |
        Where-Object { $_.FullName -notmatch '\\(bin|obj|artifacts|Backups|Payload_V17)\\' } |
        Select-Object -First 1

    $v17Detectada = $false
    if ($inicio) {
        try {
            $contenido = Get-Content -Path $inicio.FullName -Raw -ErrorAction Stop
            $v17Detectada =
                $contenido -match "https://rs485\.cdtechnologia\.net/api/v1/mediciones" -and
                $contenido -match "Produccion HTTPS" -and
                $contenido -match "Puerto efectivo" -and
                $contenido -match "iot-v16-dropdown"
        }
        catch {
            Write-Warning "No se pudo revisar Inicio.razor: $($_.Exception.Message)"
        }
    }

    if ($v17Detectada) {
        Write-Host "Configurador IoT V17 Produccion HTTPS detectado." -ForegroundColor Green
    }
    else {
        Write-Warning "No se pudo confirmar automaticamente el Configurador IoT V17."
        Write-Warning "El instalador publicara exactamente el codigo actual del proyecto."
    }

    $terminalSsh = Get-ChildItem -Path $projectRoot -Filter "TerminalSsh.razor" -File -Recurse |
        Where-Object { $_.FullName -notmatch '\\(bin|obj|artifacts|Backups)\\' } |
        Select-Object -First 1

    if ($terminalSsh) {
        Write-Host "Modulo SSH detectado." -ForegroundColor Green
    }
    else {
        Write-Warning "No se encontro TerminalSsh.razor."
    }
}

$publishDir = Join-Path $projectRoot "artifacts\publish\win-x64"
$installerDir = Join-Path $projectRoot "artifacts\installer"
$appIcon = Join-Path $projectRoot "Assets\CDTerminal.ico"

if (-not (Test-Path $appIcon)) {
    throw "No se encontro el icono en: $appIcon"
}

Write-Host ""
Write-Host "CD Terminal $Version" -ForegroundColor Cyan
Write-Host "Proyecto: $($project.FullName)"
Write-Host "Publicacion: $publishDir"
Write-Host "Icono: $appIcon"
Write-Host "API produccion: https://rs485.cdtechnologia.net/api/v1/mediciones"
Write-Host ""

if (Test-Path $publishDir) {
    Remove-Item $publishDir -Recurse -Force
}

New-Item -ItemType Directory -Path $publishDir -Force | Out-Null
New-Item -ItemType Directory -Path $installerDir -Force | Out-Null

Push-Location $projectRoot
try {
    & dotnet clean $project.FullName -c Release
    if ($LASTEXITCODE -ne 0) { throw "Fallo dotnet clean." }

    $objDir = Join-Path $projectRoot "obj"
    if (Test-Path $objDir) {
        Remove-Item $objDir -Recurse -Force
    }

    & dotnet restore $project.FullName -r win-x64
    if ($LASTEXITCODE -ne 0) { throw "Fallo dotnet restore para win-x64." }

    & dotnet build $project.FullName -c Release -r win-x64 --no-restore "-p:ApplicationIcon=$appIcon"
    if ($LASTEXITCODE -ne 0) { throw "Fallo dotnet build para win-x64." }

    & dotnet publish $project.FullName `
        -c Release `
        -r win-x64 `
        --self-contained true `
        --no-restore `
        "-p:ApplicationIcon=$appIcon" `
        -p:Version=$Version `
        -p:FileVersion="$Version.0" `
        -p:AssemblyVersion="$Version.0" `
        -p:InformationalVersion="${Version}-ssh-iot-v17-https-production" `
        -p:PublishSingleFile=false `
        -p:PublishReadyToRun=true `
        -p:PublishTrimmed=false `
        -p:DebugType=None `
        -p:DebugSymbols=false `
        -o $publishDir

    if ($LASTEXITCODE -ne 0) { throw "Fallo dotnet publish." }
}
finally {
    Pop-Location
}

$exePath = Join-Path $publishDir "CDTerminal.exe"
if (-not (Test-Path $exePath)) {
    throw "La publicacion termino, pero no aparecio CDTerminal.exe."
}

Write-Host "Publicacion win-x64 creada correctamente." -ForegroundColor Green

if ($SoloPublicar) {
    Write-Host "Se omitio Inno Setup por -SoloPublicar."
    exit 0
}

$isccCandidates = @(
    (Get-Command ISCC.exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source -ErrorAction SilentlyContinue),
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe"
) | Where-Object { $_ -and (Test-Path $_) }

$iscc = $isccCandidates | Select-Object -First 1
if (-not $iscc) {
    Write-Warning "No se encontro Inno Setup 6."
    Write-Warning "La aplicacion publicada esta lista en: $publishDir"
    exit 0
}

$iss = Join-Path $projectRoot "Installer\CDTerminal.iss"
if (-not (Test-Path $iss)) {
    throw "No se encontro el script del instalador: $iss"
}

& $iscc "/DMyAppVersion=$Version" $iss
if ($LASTEXITCODE -ne 0) {
    throw "Inno Setup no pudo compilar el instalador."
}

$setupName = "CdTecHNologia-CDTerminal-$Version-x64.exe"
$setup = Get-ChildItem -Path $installerDir -Filter $setupName -File | Select-Object -First 1
if (-not $setup) {
    throw "No se encontro el instalador compilado en: $installerDir"
}

$hash = Get-FileHash -Path $setup.FullName -Algorithm SHA256
$checksumPath = Join-Path $installerDir "SHA256SUMS.txt"
"$($hash.Hash.ToLower())  $($setup.Name)" | Set-Content -Path $checksumPath -Encoding ASCII

Write-Host ""
Write-Host "Instalador creado:" -ForegroundColor Green
Write-Host $setup.FullName
Write-Host "SHA-256: $($hash.Hash)"
