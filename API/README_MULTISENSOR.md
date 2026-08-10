# RS485-API multisensor

Esta actualización alinea Laravel con el firmware que consulta hasta cuatro sensores Modbus RTU y los envía juntos en `sensores[]`.

## Cambios principales

- Tabla `sensores` con una restricción única por controlador y ranura.
- Máximo de cuatro ranuras por controlador.
- Una fila de `mediciones` por sensor recibido.
- Conservación de todos los registros Modbus de cada sensor.
- Dashboard con selector visual de sensores, gráfica e historial independiente.
- Administración de nombre, tipo, unidad y visibilidad por ranura.
- Compatibilidad con el formato anterior `valor` y `modbus`.
- Migración automática de mediciones existentes a la ranura 1.

Consulte `INSTRUCCIONES_ACTUALIZACION_MULTISENSOR.txt` para instalar.
