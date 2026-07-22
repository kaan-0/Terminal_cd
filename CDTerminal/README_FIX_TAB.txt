CD TERMINAL SSH V4 - CORRECCION DEL CRASH TAB

PROBLEMA
Al abrir Terminal SSH aparecia:
Could not find 'cdTerminalSsh.enableCommandTab' ('cdTerminalSsh' was undefined).

CAUSA
El componente dependia de que cdterminal-ssh.js estuviera cargado globalmente desde index.html.
En Blazor Hybrid la etiqueta no estaba disponible al renderizar el componente.

SOLUCION
TerminalSsh.razor ahora importa directamente el archivo JavaScript como modulo ES:
./js/cdterminal-ssh.js

Esto elimina la dependencia del objeto global window.cdTerminalSsh y evita que la terminal se cierre si falla TAB.
El script de aplicacion tambien elimina de index.html la etiqueta global agregada por la V3.

APLICAR
Desde la raiz del proyecto:

Set-ExecutionPolicy -Scope Process Bypass
C:\RUTA\CDTerminal_SSH_MVP_V4_TAB_FIX\Scripts\Aplicar-Fix-TAB-SSH.ps1 -ProjectRoot (Get-Location).Path

PRUEBAS
1. Abrir Comunicacion > Terminal SSH.
2. Confirmar que no se cierre la aplicacion.
3. Conectar a un servidor.
4. Escribir syst y presionar TAB.
5. Probar Ctrl+C, Enter, limpiar y desconectar.
