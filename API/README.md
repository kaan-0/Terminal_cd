# Acceso por cliente para RS485-API

Actualización incremental para Laravel 12 que agrega autenticación y aislamiento de datos por cliente.

## Seguridad aplicada

- Las rutas web requieren autenticación.
- Las rutas administrativas requieren rol `admin`.
- Los usuarios con rol `cliente` quedan vinculados mediante `users.cliente_id`.
- El controlador del dashboard ignora parámetros de otro cliente y consulta únicamente dispositivos pertenecientes al usuario autenticado.
- La API de mediciones conserva su autenticación independiente mediante `X-Device-ID` y `X-Device-Token`.
