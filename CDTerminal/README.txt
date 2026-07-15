CD TERMINAL - CLIENTE HTTP REST (ETAPA 1)
=========================================

Esta versión parte de la interfaz que ya incluye:
- sesiones seriales y Modbus RTU;
- polling automático;
- CSV/TXT;
- gráfica en tiempo real;
- perfiles de dispositivos;
- menú Ver.

ARCHIVOS QUE DEBES REEMPLAZAR
-----------------------------
1. Components/Pages/Inicio.razor
   Usa la ruta real donde ya tienes Inicio.razor.

2. Components/Pages/Inicio.razor.css
   Colócalo junto a Inicio.razor.

3. MainWindow.xaml.cs

ARCHIVOS NUEVOS
---------------
Models/ConfiguracionServidorRest.cs
Models/LecturaServidorRest.cs
Models/ResultadoServidorRest.cs

Services/IServidorRestService.cs
Services/ServidorRestService.cs

No se necesitan paquetes NuGet adicionales.

FUNCIONES AGREGADAS
-------------------
Herramientas > Conexión al servidor

La ventana permite configurar:
- URL base del servidor;
- endpoint GET de prueba;
- endpoint POST de lecturas;
- ID único del dispositivo;
- token Bearer opcional;
- timeout de 1 a 60 segundos;
- envío automático de lecturas válidas.

También incluye:
- botón Probar conexión;
- botón Enviar última lectura;
- contador de envíos correctos y fallidos;
- último código HTTP;
- estado REST en la barra inferior;
- envío automático después de cada lectura Modbus correcta.

CONTRATO HTTP
-------------
Prueba:
    GET {URL_BASE}{RUTA_PRUEBA}

Envío de lectura:
    POST {URL_BASE}{RUTA_LECTURAS}
    Content-Type: application/json
    X-Device-Id: <ID DEL DISPOSITIVO>
    Authorization: Bearer <TOKEN>   (solo cuando existe token)

Se considera correcto cualquier código HTTP entre 200 y 299.
El archivo ejemplo-payload.json contiene el cuerpo JSON esperado.

CONFIGURACIÓN LOCAL
-------------------
La configuración se almacena en:

%LOCALAPPDATA%\CDTerminal\servidor-rest.json

Nota de seguridad: en esta primera etapa el token se guarda en ese archivo
local sin cifrado. No uses todavía un token administrativo o de alto privilegio.
Más adelante conviene protegerlo con las credenciales de Windows.

PRUEBA RÁPIDA SIN UN SERVIDOR REAL
----------------------------------
Se incluye un servidor de laboratorio escrito en Python y sin dependencias:

ServidorPrueba/servidor_prueba.py

1. Abre PowerShell en la carpeta del paquete.
2. Ejecuta:

   python .\ServidorPrueba\servidor_prueba.py

3. En CD Terminal configura:

   URL base:          http://127.0.0.1:5080
   Ruta de prueba:    /health
   Ruta de lecturas:  /api/readings
   ID del dispositivo: sensor-tgu-01
   Token:             vacío

4. Presiona Probar conexión.
5. Realiza una lectura Modbus.
6. Presiona Enviar última lectura o activa el envío automático.

Las lecturas quedarán en:

ServidorPrueba/data/lecturas.ndjson

El servidor Python es solo para pruebas locales. No debe publicarse en Internet.

COMPILACIÓN
-----------
dotnet clean
dotnet restore
dotnet build .\CDTerminal.csproj -c Debug

ALCANCE ACTUAL
--------------
Esta etapa envía las lecturas directamente al servidor. Todavía no existe una
cola persistente para reintentar cuando se pierde Internet. Si el servidor no
responde, CD Terminal informa el error y continúa con las siguientes lecturas.
La cola local store-and-forward será una etapa posterior.
