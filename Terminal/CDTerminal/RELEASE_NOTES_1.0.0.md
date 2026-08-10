# CD Terminal Local 1.0.0

Primera versión instalable de la fase Local para Windows de 64 bits.

## Funciones principales

- Comunicación serial con configuración de puerto, velocidad, paridad, bits de datos y parada.
- Sesiones múltiples e independientes.
- Modbus RTU con lectura de registros mediante funciones 03 y 04.
- Polling automático, timeout, reintentos y validación CRC.
- Envío manual de tramas Modbus hexadecimales con CRC automático opcional.
- Terminal con registros RX, TX, SYS y ERROR.
- Exportación de terminal a TXT.
- Registro continuo de lecturas en CSV.
- Gráfica Modbus en tiempo real.
- Perfiles locales de dispositivos.
- Detección de desconexión física del adaptador serial.
- Interfaz adaptable y menú Ver.

## Vista previa técnica

La conexión HTTP REST incluida en el código actual se considera una función experimental de la futura fase Pro. No forma parte del soporte formal de CD Terminal Local 1.0.

## Requisitos

- Windows 10 u 11 de 64 bits.
- Microsoft Edge WebView2 Runtime.
- Puerto COM o adaptador compatible para usar las funciones seriales.

## Observaciones

- La publicación se genera como self-contained, por lo que no exige instalar por separado el runtime de .NET.
- El instalador es por usuario y no requiere privilegios administrativos para la instalación normal.
- El software aún no incluye firma digital del ejecutable ni del instalador.
