CD TERMINAL SSH V5
HISTORIAL DE COMANDOS Y AUTOCOMPLETADO DE RUTAS

NOVEDADES
1. Flecha arriba: muestra comandos anteriores.
2. Flecha abajo: avanza por el historial y restaura el texto que estaba escribiendo.
3. Historial en memoria de hasta 200 comandos.
4. TAB completa comandos cuando se escribe el primer token.
5. TAB completa archivos y directorios en los argumentos.
6. Soporta rutas relativas, absolutas, ./, ../ y ~/.
7. Los directorios terminan en / para seguir navegando con TAB.
8. Los nombres con espacios se insertan con barra invertida.
9. El directorio de trabajo se actualiza para comandos cd simples.

APLICAR
Desde la raiz del proyecto:

Set-ExecutionPolicy -Scope Process Bypass
C:\RUTA\CDTerminal_SSH_MVP_V5_HISTORIAL_RUTAS\Scripts\Aplicar-Historial-Rutas-SSH.ps1 `
    -ProjectRoot (Get-Location).Path

PRUEBAS
1. Conectar por SSH.
2. Ejecutar: pwd
3. Ejecutar: ls
4. Presionar flecha arriba y flecha abajo.
5. Escribir: syst y presionar TAB.
6. Escribir: cd /va y presionar TAB.
7. Escribir: ls /etc/ho y presionar TAB.
8. Escribir: cat ./ y presionar TAB.
9. Probar una carpeta con espacios.

NOTAS
- El historial no se guarda en disco.
- La contraseña tampoco se guarda.
- El seguimiento del directorio funciona con cd simples, por ejemplo:
  cd /var/log
  cd ..
  cd "Mi Carpeta"
- Comandos complejos como cd /tmp && ls se envian normalmente, pero no actualizan el directorio interno usado por el autocompletado.
