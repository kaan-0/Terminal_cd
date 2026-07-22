ACTUALIZAR UNA INSTALACION SSH QUE YA FUNCIONA

1. Descomprime este paquete.
2. Abre PowerShell en la raiz del proyecto CDTerminal.
3. Ejecuta:

Set-ExecutionPolicy -Scope Process Bypass
C:\RUTA\CDTerminal_SSH_MVP_V3_TAB\Scripts\Aplicar-TAB-SSH.ps1 -ProjectRoot (Get-Location).Path

El script crea un respaldo, actualiza el componente SSH, copia el JavaScript,
registra el script en wwwroot\\index.html y compila el proyecto.

PRUEBA
- Conecta a un servidor.
- Escribe: syst
- Presiona TAB.
- Debe completar systemctl cuando sea una coincidencia unica.

Ejemplo con varias coincidencias:
- Escribe: docker-
- Presiona TAB.
- Se mostraran coincidencias disponibles en el servidor.
