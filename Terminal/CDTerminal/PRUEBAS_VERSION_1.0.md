# Lista de pruebas para CD Terminal Local 1.0

## Instalación

- [ ] Instalar en una computadora Windows 10 x64 limpia.
- [ ] Instalar en una computadora Windows 11 x64 limpia.
- [ ] Confirmar que aparece en el menú Inicio.
- [ ] Confirmar que el acceso directo opcional funciona.
- [ ] Verificar que la aplicación abre sin tener instalado el SDK de .NET.
- [ ] Verificar el comportamiento cuando WebView2 no está instalado.

## Comunicación serial

- [ ] Detectar puertos COM disponibles.
- [ ] Conectar y desconectar sin bloquear la interfaz.
- [ ] Enviar y recibir texto serial.
- [ ] Abrir dos sesiones en puertos diferentes.
- [ ] Impedir dos sesiones conectadas al mismo puerto.
- [ ] Retirar físicamente el adaptador USB durante una conexión.
- [ ] Reconectar manualmente sin reiniciar la aplicación.

## Modbus RTU

- [ ] Leer función 03.
- [ ] Leer función 04.
- [ ] Validar respuesta CRC correcta.
- [ ] Mostrar error ante CRC inválido.
- [ ] Ejecutar polling durante al menos 30 minutos.
- [ ] Enviar una trama manual de lectura.
- [ ] Enviar una trama de escritura solamente sobre un registro documentado.

## Datos locales

- [ ] Exportar terminal a TXT.
- [ ] Registrar polling en CSV.
- [ ] Abrir el CSV en Excel y comprobar columnas.
- [ ] Verificar que el archivo se conserva después de retirar el USB.
- [ ] Guardar y cargar un perfil de dispositivo.
- [ ] Confirmar que la gráfica recibe datos y puede limpiarse.

## Cierre y desinstalación

- [ ] Cerrar la aplicación mientras existe polling activo.
- [ ] Cerrar una pestaña conectada y comprobar que libera el COM.
- [ ] Desinstalar desde Configuración de Windows.
- [ ] Confirmar que se eliminan los archivos de programa.
- [ ] Confirmar que los perfiles del usuario se conservan o eliminar manualmente según la política elegida.
