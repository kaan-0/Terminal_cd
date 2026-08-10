# Kit de publicación e instalador de CD Terminal Local 1.0

Este paquete prepara una publicación de Windows x64 y genera un instalador EXE mediante Inno Setup 6.

## 1. Copiar al proyecto

Copia el contenido de este paquete en la raíz del proyecto, al mismo nivel que `CDTerminal.csproj`.

La estructura debe quedar así:

```text
CDTerminal.csproj
Directory.Build.props
Assets/
Installer/
Properties/PublishProfiles/
Scripts/
```

## 2. Instalar herramientas en la computadora de desarrollo

Necesitas:

- El SDK de .NET usado por el proyecto.
- Inno Setup 6 para producir el archivo Setup.exe.

La publicación es self-contained, así que la computadora del cliente no necesitará instalar el runtime de .NET.

## 3. Crear la publicación y el instalador

Abre PowerShell en la raíz del proyecto y ejecuta:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\Scripts\crear-instalador.ps1 -Version 1.0.0
```

Resultados esperados:

```text
artifacts\publish\win-x64\
artifacts\installer\CDTerminal-Local-Setup-1.0.0-x64.exe
artifacts\installer\SHA256SUMS.txt
```

Para crear únicamente la carpeta publicada:

```powershell
.\Scripts\crear-instalador.ps1 -Version 1.0.0 -SoloPublicar
```

## 4. WebView2

El instalador comprueba si Microsoft Edge WebView2 Runtime está instalado.

Para un instalador completamente offline, coloca el instalador x64 de WebView2 en:

```text
Installer\Dependencies\MicrosoftEdgeWebView2RuntimeInstallerX64.exe
```

## 5. Verificar la salida

```powershell
.\Scripts\verificar-release.ps1 -Version 1.0.0
```

## 6. Firma digital

Esta primera versión no firma digitalmente el ejecutable ni el instalador. Antes de distribuir comercialmente, conviene adquirir un certificado de firma de código y agregar la firma al proceso de publicación.

## 7. Nota sobre el icono

El icono incluido es una propuesta inicial basada en la identidad azul, verde y blanca de la empresa. Puede reemplazarse conservando el nombre:

```text
Assets\CDTerminal.ico
```
