[CmdletBinding()]
param(
    [string]$Version = "1.1.0",
    [switch]$SoloPublicar,
    [switch]$OmitirVerificacionV9
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path

# Buscar el proyecto primero en la raiz y, si no aparece, de forma recursiva.
$project = Get-ChildItem -Path $projectRoot -Filter "CDTerminal.csproj" -File |
    Select-Object -First 1

if (-not $project) {
    $project = Get-ChildItem -Path $projectRoot -Filter "CDTerminal.csproj" -File -Recurse |
        Where-Object {
            $_.FullName -notmatch '\\(bin|obj|artifacts|Backups|Payload_V9)\\'
        } |
        Select-Object -First 1
}

if (-not $project) {
    throw "No se encontro CDTerminal.csproj dentro de: $projectRoot"
}

$projectRoot = $project.Directory.FullName

if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) {
    throw "No se encontro dotnet. Instala el SDK de .NET usado por el proyecto."
}

# Verificacion flexible: no supone que Inicio.razor.css este en una ruta fija.
if (-not $OmitirVerificacionV9) {
    $archivosInicio = Get-ChildItem -Path $projectRoot -File -Recurse |
        Where-Object {
            $_.Name -in @("Inicio.razor", "Inicio.razor.css") -and
            $_.FullName -notmatch '\\(bin|obj|artifacts|Backups|Payload_V9)\\'
        }

    $v9Detectada = $false

    foreach ($archivoInicio in $archivosInicio) {
        try {
            $contenido = Get-Content -Path $archivoInicio.FullName -Raw -ErrorAction Stop

            if ($archivoInicio.Name -eq "Inicio.razor.css" -and
                $contenido -match "Configurador IoT V9") {
                $v9Detectada = $true
                break
            }

            if ($archivoInicio.Name -eq "Inicio.razor" -and
                $contenido -match "Configurar equipo IoT" -and
                $contenido -match "resetZ") {
                $v9Detectada = $true
                break
            }
        }
        catch {
            Write-Warning "No se pudo revisar $($archivoInicio.FullName): $($_.Exception.Message)"
        }
    }

    if ($v9Detectada) {
        Write-Host "Configurador IoT V9 detectado." -ForegroundColor Green
    }
    else {
        Write-Warning "No se pudo confirmar automaticamente la interfaz V9. Se publicara el codigo actual del proyecto."
        Write-Warning "Si ya probaste visualmente esta version, puedes continuar con seguridad."
    }
}

$publishDir = Join-Path $projectRoot "artifacts\publish\win-x64"
$installerDir = Join-Path $projectRoot "artifacts\installer"

Write-Host ""
Write-Host "CD Terminal $Version" -ForegroundColor Cyan
Write-Host "Proyecto: $($project.FullName)"
Write-Host "Publicacion: $publishDir"
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

    # Eliminar assets anteriores para evitar que project.assets.json conserve
    # una restauracion sin el RuntimeIdentifier win-x64.
    $objDir = Join-Path $projectRoot "obj"
    if (Test-Path $objDir) {
        Remove-Item $objDir -Recurse -Force
    }

    # Restaurar especificamente para Windows x64. Esto genera el target:
    # net10.0-windows.../win-x64 requerido por dotnet publish.
    & dotnet restore $project.FullName -r win-x64
    if ($LASTEXITCODE -ne 0) { throw "Fallo dotnet restore para win-x64." }

    & dotnet build $project.FullName -c Release -r win-x64 --no-restore
    if ($LASTEXITCODE -ne 0) { throw "Fallo dotnet build para win-x64." }

    & dotnet publish $project.FullName `
        -c Release `
        -r win-x64 `
        --self-contained true `
        --no-restore `
        -p:Version=$Version `
        -p:FileVersion="$Version.0" `
        -p:AssemblyVersion="$Version.0" `
        -p:InformationalVersion="${Version}-configurador-iot" `
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
    throw "La publicacion termino, pero no aparecio CDTerminal.exe en $publishDir"
}

Write-Host "Publicacion win-x64 creada correctamente." -ForegroundColor Green

if ($SoloPublicar) {
    Write-Host "Se omitio la compilacion del instalador por -SoloPublicar."
    exit 0
}

$isccCandidates = @(
    (Get-Command ISCC.exe -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty Source -ErrorAction SilentlyContinue),
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe"
) | Where-Object { $_ -and (Test-Path $_) }

$iscc = $isccCandidates | Select-Object -First 1
if (-not $iscc) {
    Write-Warning "No se encontro Inno Setup 6. La aplicacion publicada esta lista en: $publishDir"
    Write-Warning "Instala Inno Setup 6 y vuelve a ejecutar este script para generar el Setup.exe."
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

$setupName = "CDTerminal-Setup-$Version-x64.exe"
$setup = Get-ChildItem -Path $installerDir -Filter $setupName -File |
    Select-Object -First 1

if (-not $setup) {
    throw "No se encontro el instalador compilado en: $installerDir"
}

$hash = Get-FileHash -Path $setup.FullName -Algorithm SHA256
$checksumPath = Join-Path $installerDir "SHA256SUMS.txt"
"$($hash.Hash.ToLower())  $($setup.Name)" |
    Set-Content -Path $checksumPath -Encoding ASCII

Write-Host ""
Write-Host "Instalador creado:" -ForegroundColor Green
Write-Host $setup.FullName
Write-Host "SHA-256: $($hash.Hash)"