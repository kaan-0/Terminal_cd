[CmdletBinding()]
param()

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$project = Get-ChildItem -Path $root -Filter "CDTerminal.csproj" -File | Select-Object -First 1

if (-not $project) {
    $project = Get-ChildItem -Path $root -Filter "CDTerminal.csproj" -File -Recurse |
        Where-Object { $_.FullName -notmatch '\\(bin|obj|artifacts|Backups|Payload_V17)\\' } |
        Select-Object -First 1
}

if (-not $project) {
    throw "No se encontro CDTerminal.csproj. Copia el kit en la raiz del proyecto."
}

$projectRoot = $project.Directory.FullName
$sourceIcon = Join-Path $root "Assets\CDTerminal.ico"
$sourcePng = Join-Path $root "Assets\CDTerminal.png"
$targetAssets = Join-Path $projectRoot "Assets"
$targetIcon = Join-Path $targetAssets "CDTerminal.ico"
$targetPng = Join-Path $targetAssets "CDTerminal.png"

if (-not (Test-Path $sourceIcon)) {
    throw "No se encontro el icono del kit: $sourceIcon"
}

New-Item -ItemType Directory -Path $targetAssets -Force | Out-Null

if ((Resolve-Path $sourceIcon).Path -ne [System.IO.Path]::GetFullPath($targetIcon)) {
    Copy-Item $sourceIcon $targetIcon -Force
}

if (Test-Path $sourcePng) {
    if ((Resolve-Path $sourcePng).Path -ne [System.IO.Path]::GetFullPath($targetPng)) {
        Copy-Item $sourcePng $targetPng -Force
    }
}

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupDir = Join-Path $projectRoot "Backups\antes-icono-v17-$stamp"
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
Copy-Item $project.FullName (Join-Path $backupDir $project.Name) -Force

Add-Type -AssemblyName System.Xml.Linq
$document = [System.Xml.Linq.XDocument]::Load($project.FullName)
$ns = $document.Root.Name.Namespace
$propertyGroup = $document.Root.Elements($ns + "PropertyGroup") |
    Where-Object { -not $_.Attribute("Condition") } |
    Select-Object -First 1

if (-not $propertyGroup) {
    $propertyGroup = [System.Xml.Linq.XElement]::new($ns + "PropertyGroup")
    $document.Root.AddFirst($propertyGroup)
}

$applicationIcon = $propertyGroup.Element($ns + "ApplicationIcon")
if (-not $applicationIcon) {
    $applicationIcon = [System.Xml.Linq.XElement]::new($ns + "ApplicationIcon")
    $propertyGroup.Add($applicationIcon)
}

$applicationIcon.Value = "Assets\CDTerminal.ico"
$document.Save($project.FullName)

Write-Host "Nuevo icono aplicado al proyecto." -ForegroundColor Green
Write-Host "Icono: $targetIcon"
Write-Host "Respaldo: $backupDir"
