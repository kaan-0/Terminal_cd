# Instalador de CD Terminal 1.1.0

Este kit publica la versión Windows x64 y crea el instalador de la versión que incluye el Configurador IoT V9 adaptable para laptop.

## Resultado

```text
artifacts\publish\win-x64\
artifacts\installer\CDTerminal-Setup-1.1.0-x64.exe
artifacts\installer\SHA256SUMS.txt
```

## 1. Copiar el kit al proyecto

Copia el contenido de esta carpeta en:

```text
C:\Users\almen\source\repos\CDTerminal\CDTerminal
```

Debe quedar al mismo nivel que `CDTerminal.csproj`.

## 2. Confirmar la interfaz V9

Si la versión V9 ya está aplicada y funcionando, omite este paso.

Para aplicarla desde el payload incluido:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\Scripts\aplicar-configurador-v9.ps1
```

El script crea una copia de seguridad de `Inicio.razor` y `Inicio.razor.css`.

## 3. Crear el instalador

Cierra CD Terminal y Visual Studio si mantienen archivos bloqueados. Luego abre PowerShell en la raíz del proyecto:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\Scripts\crear-instalador.ps1 -Version 1.1.0
```

El script detecta Inno Setup también en la instalación por usuario:

```text
%LOCALAPPDATA%\Programs\Inno Setup 6\ISCC.exe
```

## 4. Verificar

```powershell
.\Scripts\verificar-release.ps1 -Version 1.1.0
```

## 5. Actualización sobre 1.0.0

El instalador conserva el mismo `AppId`, por lo que puede actualizar la instalación anterior de CD Terminal en lugar de crear un producto separado.

## WebView2 offline

Para incluir WebView2 dentro del instalador, coloca:

```text
Installer\Dependencies\MicrosoftEdgeWebView2RuntimeInstallerX64.exe
```

Sin ese archivo, el instalador utiliza el WebView2 ya instalado en Windows.
