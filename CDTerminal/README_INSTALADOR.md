# Instalador de CD Terminal 1.4.0

Este kit genera la version Windows x64 de CD Terminal con el Configurador IoT V17 para produccion HTTPS.

## Contenido de la version

- Mantiene Terminal serial, Modbus RTU y Terminal SSH.
- Configurador IoT multisensor con desplegables personalizados.
- Aprovisionamiento mediante ID y token.
- Red local por DHCP o IP estatica.
- Hasta cuatro sensores Modbus RTU.
- Destino de produccion preconfigurado:
  `https://rs485.cdtechnologia.net/api/v1/mediciones`
- Envio HTTP POST JSON con encabezados `X-Device-ID` y `X-Device-Token`.
- Puerto efectivo HTTPS 443.
- Modo personalizado para servidores alternativos o instalaciones locales.
- Conserva el icono personalizado de CD Terminal.

## Preparacion

Descomprime el kit y copia todo su contenido en la raiz del proyecto, junto a `CDTerminal.csproj`.

Ruta habitual:

```text
C:\Users\almen\source\repos\CDTerminal\CDTerminal
```

## Crear el instalador

Cuando V17 ya esta aplicada, haz doble clic en:

```text
CREAR-INSTALADOR.cmd
```

## Aplicar V17 y crear el instalador

Para aplicar primero la copia incluida de V17 y luego compilar:

```text
APLICAR-V17-Y-CREAR-INSTALADOR.cmd
```

Este flujo crea respaldos antes de modificar `Inicio.razor`, `Inicio.razor.css` y `CDTerminal.csproj`.

## Resultado

```text
artifacts\publish\win-x64\
artifacts\installer\CdTecHNologia-CDTerminal-1.4.0-x64.exe
artifacts\installer\SHA256SUMS.txt
```

## WebView2 offline

Para incluir WebView2 dentro del instalador, coloca:

```text
Installer\Dependencies\MicrosoftEdgeWebView2RuntimeInstallerX64.exe
```

El instalador conserva el mismo AppId y actualiza versiones anteriores.
