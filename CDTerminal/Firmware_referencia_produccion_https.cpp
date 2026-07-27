#include <SPI.h>
#include <W5500lwIP.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <stdlib.h>
#include <ctype.h>

// ==================================================
// EEPROM AT24C256
// ==================================================

#define EEPROM_SDA   4
#define EEPROM_SCL   5
#define EEPROM_ADDR  0x50

// La EEPROM queda alimentada permanentemente a 3.3 V.
// Ya no existe control de energia mediante GPIO.
#define EEPROM_STARTUP_DELAY_MS  10

// --------------------------------------------------
// CONFIGURACION DEL SERVIDOR
// --------------------------------------------------

#define EEPROM_POS_SERVER_IP       0
#define EEPROM_POS_SERVER_MASCARA  4

// --------------------------------------------------
// CONFIGURACION DE LA RED LOCAL
// --------------------------------------------------

#define EEPROM_POS_MODO_RED        8
#define EEPROM_POS_IP_LOCAL        9
#define EEPROM_POS_MASCARA_LOCAL   13

// --------------------------------------------------
// CONFIGURACIONES ADICIONALES
// --------------------------------------------------

#define EEPROM_POS_PUERTO_HTTP     17
#define EEPROM_POS_TIEMPO_ENVIO    19
#define EEPROM_POS_URL_LONGITUD    21
#define EEPROM_POS_URL_DATOS       23
#define URL_MAX_LONGITUD           180

// La URL ocupa como máximo las posiciones 23 a 202.
// Las credenciales se almacenan después de esa zona.
#define EEPROM_POS_ID_LONGITUD     203
#define EEPROM_POS_ID_DATOS        205
#define ID_MAX_LONGITUD            32

#define EEPROM_POS_TOKEN_LONGITUD  237
#define EEPROM_POS_TOKEN_DATOS     239
#define TOKEN_MAX_LONGITUD         64

// --------------------------------------------------
// CONFIGURACION UNIVERSAL RS485 / MODBUS
//
// El bus RS485 comparte baudrate y paridad.
// Se admiten hasta cuatro sensores Modbus RTU.
//
// Cada sensor ocupa 8 bytes:
//   +0 activo
//   +1 ID esclavo
//   +2 funcion
//   +3..+4 registro inicial
//   +5 cantidad de registros
//   +6..+7 reservados
//
// Zona utilizada:
//   303..342
//
// Se escribe solamente al instalar o cambiar sensores.
// Las mediciones nunca se almacenan en EEPROM.
// --------------------------------------------------

#define EEPROM_POS_RS485_MAGIC_1       303
#define EEPROM_POS_RS485_MAGIC_2       304
#define EEPROM_POS_RS485_VERSION       305
#define EEPROM_POS_RS485_BAUDRATE      306
#define EEPROM_POS_RS485_PARIDAD       310

#define EEPROM_POS_SENSORES_BASE       311
#define EEPROM_SENSOR_TAMANO           8
#define EEPROM_MAX_SENSORES            4

#define EEPROM_SENSOR_OFFSET_ACTIVO    0
#define EEPROM_SENSOR_OFFSET_SLAVE     1
#define EEPROM_SENSOR_OFFSET_FUNCION   2
#define EEPROM_SENSOR_OFFSET_REGISTRO  3
#define EEPROM_SENSOR_OFFSET_CANTIDAD  5
#define EEPROM_SENSOR_OFFSET_RESERVA_1 6
#define EEPROM_SENSOR_OFFSET_RESERVA_2 7

// Posiciones del formato anterior, utilizadas solo para migrar
// automáticamente la configuración de un sensor a la ranura 1.
#define EEPROM_V1_POS_MODBUS_SLAVE     311
#define EEPROM_V1_POS_MODBUS_FUNCION   312
#define EEPROM_V1_POS_MODBUS_REGISTRO  313
#define EEPROM_V1_POS_MODBUS_CANTIDAD  315

#define EEPROM_RS485_MAGIC_1           0x52  // R
#define EEPROM_RS485_MAGIC_2           0x53  // S
#define EEPROM_RS485_VERSION_ANTERIOR  1
#define EEPROM_RS485_VERSION_ACTUAL    2

#define PUERTO_HTTP_DEFAULT        80
#define TIEMPO_ENVIO_DEFAULT       1

// Endpoint de produccion.
// Si la EEPROM todavia no contiene una URL, se utilizara esta.
#define URL_API_PRODUCCION \
  "https://rs485.cdtechnologia.net/api/v1/mediciones"

// HTTPS mediante HTTPClient.
// Se utiliza setInsecure() para permitir TLS sin instalar
// temporalmente una autoridad certificadora en el firmware.
// La comunicacion queda cifrada, pero el certificado remoto
// no se valida. Para produccion definitiva se recomienda
// instalar la CA raiz del certificado del subdominio.
#define HTTPS_USAR_SET_INSECURE    true

// Tiempo maximo de espera para cada solicitud HTTP.
#define HTTP_TIMEOUT_MS            15000

// Reintentos de envio en RAM.
// No se guarda ninguna medicion pendiente en EEPROM.
#define HTTP_MAX_INTENTOS_ENVIO    3
#define HTTP_PAUSA_REINTENTO_2_MS  3000UL
#define HTTP_PAUSA_REINTENTO_3_MS  10000UL

#define MODO_DHCP      0
#define MODO_ESTATICO  1

// Configuracion del servidor recuperada desde EEPROM
IPAddress serverIP(0, 0, 0, 0);
IPAddress serverMask(0, 0, 0, 0);

// Configuracion local recuperada desde EEPROM
uint8_t modoRed = MODO_DHCP;
IPAddress ipLocal(0, 0, 0, 0);
IPAddress mascaraLocal(0, 0, 0, 0);

// Configuraciones adicionales recuperadas desde EEPROM
uint16_t puertoHTTP = PUERTO_HTTP_DEFAULT;
uint16_t tiempoEnvioMinutos = TIEMPO_ENVIO_DEFAULT;
String urlConector = URL_API_PRODUCCION;

// Buffer para recibir comandos
String bufferComando = "";

// ==================================================
// W5500-EVB-PICO
// ==================================================

#define W5500_MISO  16
#define W5500_CS    17
#define W5500_SCK   18
#define W5500_MOSI  19
#define W5500_RST   20
#define W5500_INT   21

// ==================================================
// IDENTIDAD DEL DISPOSITIVO
//
// Se carga desde la EEPROM para utilizar el mismo firmware
// en todos los controladores.
// ==================================================

String idDevice = "";
String deviceToken = "";

// ==================================================
// OBJETO ETHERNET
// ==================================================

Wiznet5500lwIP ethernet(W5500_CS, SPI, W5500_INT);

// ==================================================
// SENSOR MODBUS RS485
// ==================================================

// El convertidor TTL-RS485 queda alimentado permanentemente.
// Las líneas A y B están conectadas directamente al sensor.
// GP15 controla únicamente el step-up/alimentación del sensor.
#define RS485_TX_PIN          12
#define RS485_RX_PIN          13
#define SENSOR_POWER_PIN      15

#define SENSOR_POWER_ON       HIGH
#define SENSOR_POWER_OFF      LOW

#define RS485_BAUDRATE_DEFAULT       9600UL
#define RS485_PARIDAD_DEFAULT        'N'
#define RS485_TIMEOUT_MS             1500
#define RS485_STARTUP_MS             2000

#define MODBUS_SLAVE_DEFAULT         0x0D
#define MODBUS_FUNCION_DEFAULT       0x03
#define MODBUS_REGISTRO_DEFAULT      0x0000
#define MODBUS_CANTIDAD_DEFAULT      0x0001
#define MODBUS_MAX_REGISTROS         16

// Configuracion fisica compartida por todos los sensores.
uint32_t rs485Baudrate = RS485_BAUDRATE_DEFAULT;
char rs485Paridad = RS485_PARIDAD_DEFAULT;

#define MAX_SENSORES_MODBUS 4

struct ConfigSensorModbus {
  bool activo;
  uint8_t slave;
  uint8_t funcion;
  uint16_t registroInicial;
  uint16_t cantidadRegistros;
};

ConfigSensorModbus sensoresModbus[MAX_SENSORES_MODBUS];

// Contexto heredado utilizado por el motor Modbus existente.
// Antes de consultar un sensor, estos valores se sincronizan
// con la ranura correspondiente.
uint8_t modbusSlave = MODBUS_SLAVE_DEFAULT;
uint8_t modbusFuncion = MODBUS_FUNCION_DEFAULT;
uint16_t modbusRegistroInicial = MODBUS_REGISTRO_DEFAULT;
uint16_t modbusCantidadRegistros = MODBUS_CANTIDAD_DEFAULT;

bool configuracionRS485Guardada = false;

// Lectura directa del sensor:
// - 10 lecturas Modbus validas.
// - 500 ms entre lecturas.
// - Sin promedios, medianas ni filtros.
// - Resultado final: valor exacto mas repetido.
#define SENSOR_NUM_MUESTRAS          10
#define SENSOR_MAX_INTENTOS          15
#define SENSOR_PAUSA_MUESTRAS_MS    500

uint16_t ultimaLecturaSensor = 0;

// Resultado final por sensor. Se conserva solamente en RAM.
struct ResultadoSensorModbus {
  bool valido;
  uint8_t ranura;
  uint8_t slave;
  uint8_t funcion;
  uint16_t registroInicial;
  uint16_t cantidadRegistros;
  uint16_t valores[MODBUS_MAX_REGISTROS];
  uint8_t repeticiones[MODBUS_MAX_REGISTROS];
};

ResultadoSensorModbus resultadosSensores[MAX_SENSORES_MODBUS];
uint8_t cantidadResultadosValidos = 0;

// Compatibilidad con el formato de un solo sensor.
uint16_t ultimosRegistros[MODBUS_MAX_REGISTROS] = {0};
uint8_t ultimasRepeticiones[MODBUS_MAX_REGISTROS] = {0};
uint16_t ultimaCantidadRegistros = 0;

bool ultimaLecturaValida = false;

// Declaraciones para los comandos universales.
void aplicarConfiguracionRS485();
void mostrarConfiguracionModbus();
void probarConsultaModbus();
void probarSensorModbus(uint8_t indiceSensor);
void probarTodosSensoresModbus();
void sincronizarContextoSensor(uint8_t indiceSensor);

// Momento de inicio de la ultima lectura manual o automatica.
uint32_t ultimoTiempoLecturaSensorMs = 0;

// ==================================================
// REINICIAR COMPLETAMENTE LA PLACA
//
// Comando serial: <ID>resetZ
// Ejemplo: CDT-HN-000001resetZ
// Utiliza el reinicio por software del RP2040.
// ==================================================

void reiniciarPlaca() {
  Serial.println();
  Serial.println("REINICIANDO SISTEMA...");
  Serial.flush();
  delay(100);

  rp2040.reboot();

  // Seguridad: normalmente reboot() no retorna.
  while (true) {
    delay(1000);
  }
}

// ==================================================
// REINICIAR W5500
// ==================================================

void reiniciarW5500() {
  pinMode(W5500_RST, OUTPUT);

  digitalWrite(W5500_RST, LOW);
  delay(100);

  digitalWrite(W5500_RST, HIGH);
  delay(500);
}

// ==================================================
// COMPROBAR EEPROM
// ==================================================

bool comprobarEEPROM() {
  Wire.beginTransmission(EEPROM_ADDR);
  return Wire.endTransmission() == 0;
}

// ==================================================
// ESCRIBIR UN BYTE EN EEPROM
// ==================================================

bool escribirEEPROM(uint16_t posicion, uint8_t dato) {
  Wire.beginTransmission(EEPROM_ADDR);

  Wire.write((posicion >> 8) & 0xFF);
  Wire.write(posicion & 0xFF);
  Wire.write(dato);

  uint8_t resultado = Wire.endTransmission();

  // Tiempo del ciclo interno de escritura de la AT24C256.
  delay(6);

  return resultado == 0;
}

// ==================================================
// LEER UN BYTE DE EEPROM
// ==================================================

bool leerEEPROM(uint16_t posicion, uint8_t &dato) {
  Wire.beginTransmission(EEPROM_ADDR);

  Wire.write((posicion >> 8) & 0xFF);
  Wire.write(posicion & 0xFF);

  if (Wire.endTransmission(false) != 0) {
    return false;
  }

  if (Wire.requestFrom((uint8_t)EEPROM_ADDR, (uint8_t)1) != 1) {
    return false;
  }

  dato = (uint8_t)Wire.read();
  return true;
}

// ==================================================
// GUARDAR UN ENTERO DE 16 BITS EN EEPROM
// ==================================================

bool guardarUint16(uint16_t posicion, uint16_t valor) {
  if (!escribirEEPROM(posicion, (valor >> 8) & 0xFF)) {
    return false;
  }

  if (!escribirEEPROM(posicion + 1, valor & 0xFF)) {
    return false;
  }

  return true;
}

// ==================================================
// LEER UN ENTERO DE 16 BITS DESDE EEPROM
// ==================================================

bool leerUint16(uint16_t posicion, uint16_t &valor) {
  uint8_t byteAlto;
  uint8_t byteBajo;

  if (!leerEEPROM(posicion, byteAlto)) {
    return false;
  }

  if (!leerEEPROM(posicion + 1, byteBajo)) {
    return false;
  }

  valor = ((uint16_t)byteAlto << 8) | byteBajo;
  return true;
}

// ==================================================
// GUARDAR / LEER ENTEROS DE 32 BITS EN EEPROM
// ==================================================

bool guardarUint32(uint16_t posicion, uint32_t valor) {
  for (uint8_t i = 0; i < 4; i++) {
    uint8_t desplazamiento = (uint8_t)((3 - i) * 8);

    if (!escribirEEPROM(
          posicion + i,
          (uint8_t)((valor >> desplazamiento) & 0xFF)
        )) {
      return false;
    }
  }

  return true;
}

bool leerUint32(uint16_t posicion, uint32_t &valor) {
  valor = 0;

  for (uint8_t i = 0; i < 4; i++) {
    uint8_t dato = 0;

    if (!leerEEPROM(posicion + i, dato)) {
      return false;
    }

    valor = (valor << 8) | dato;
  }

  return true;
}

// ==================================================
// GUARDAR TEXTO EN EEPROM
//
// Primero guarda el contenido y al final la longitud. De esta
// forma una escritura incompleta no se considera válida.
// ==================================================

bool guardarTextoEEPROM(
  uint16_t posicionLongitud,
  uint16_t posicionDatos,
  uint16_t longitudMaxima,
  String texto
) {
  texto.trim();

  if (texto.length() == 0 || texto.length() > longitudMaxima) {
    return false;
  }

  for (uint16_t i = 0; i < texto.length(); i++) {
    uint8_t dato = (uint8_t)texto.charAt(i);

    if (dato < 32 || dato > 126) {
      return false;
    }

    if (!escribirEEPROM(posicionDatos + i, dato)) {
      return false;
    }
  }

  return guardarUint16(
    posicionLongitud,
    (uint16_t)texto.length()
  );
}

// ==================================================
// LEER TEXTO DESDE EEPROM
// ==================================================

bool leerTextoEEPROM(
  uint16_t posicionLongitud,
  uint16_t posicionDatos,
  uint16_t longitudMaxima,
  String &texto
) {
  uint16_t longitud = 0;

  if (!leerUint16(posicionLongitud, longitud)) {
    return false;
  }

  if (
    longitud == 0 ||
    longitud == 0xFFFF ||
    longitud > longitudMaxima
  ) {
    texto = "";
    return true;
  }

  String resultado;
  resultado.reserve(longitud);

  for (uint16_t i = 0; i < longitud; i++) {
    uint8_t dato = 0;

    if (!leerEEPROM(posicionDatos + i, dato)) {
      return false;
    }

    if (dato < 32 || dato > 126) {
      texto = "";
      return true;
    }

    resultado += (char)dato;
  }

  texto = resultado;
  return true;
}

// ==================================================
// URL DEL ENDPOINT
// ==================================================

bool guardarURLConector(String url) {
  return guardarTextoEEPROM(
    EEPROM_POS_URL_LONGITUD,
    EEPROM_POS_URL_DATOS,
    URL_MAX_LONGITUD,
    url
  );
}

bool leerURLConector(String &url) {
  return leerTextoEEPROM(
    EEPROM_POS_URL_LONGITUD,
    EEPROM_POS_URL_DATOS,
    URL_MAX_LONGITUD,
    url
  );
}

// ==================================================
// ID Y TOKEN DEL DISPOSITIVO
// ==================================================

bool idDispositivoValido(String id) {
  id.trim();

  if (id.length() == 0 || id.length() > ID_MAX_LONGITUD) {
    return false;
  }

  for (uint16_t i = 0; i < id.length(); i++) {
    char caracter = id.charAt(i);

    if (
      !isAlphaNumeric(caracter) &&
      caracter != '-' &&
      caracter != '_'
    ) {
      return false;
    }
  }

  return true;
}

bool tokenDispositivoValido(String token) {
  token.trim();

  if (token.length() < 32 || token.length() > TOKEN_MAX_LONGITUD) {
    return false;
  }

  for (uint16_t i = 0; i < token.length(); i++) {
    char caracter = token.charAt(i);

    if (!isAlphaNumeric(caracter)) {
      return false;
    }
  }

  return true;
}

bool guardarIDDispositivo(String id) {
  id.trim();
  id.toUpperCase();

  if (!idDispositivoValido(id)) {
    return false;
  }

  return guardarTextoEEPROM(
    EEPROM_POS_ID_LONGITUD,
    EEPROM_POS_ID_DATOS,
    ID_MAX_LONGITUD,
    id
  );
}

bool leerIDDispositivo(String &id) {
  if (!leerTextoEEPROM(
        EEPROM_POS_ID_LONGITUD,
        EEPROM_POS_ID_DATOS,
        ID_MAX_LONGITUD,
        id
      )) {
    return false;
  }

  id.trim();
  id.toUpperCase();

  if (id.length() > 0 && !idDispositivoValido(id)) {
    id = "";
  }

  return true;
}

bool guardarTokenDispositivo(String token) {
  token.trim();

  if (!tokenDispositivoValido(token)) {
    return false;
  }

  return guardarTextoEEPROM(
    EEPROM_POS_TOKEN_LONGITUD,
    EEPROM_POS_TOKEN_DATOS,
    TOKEN_MAX_LONGITUD,
    token
  );
}

bool leerTokenDispositivo(String &token) {
  if (!leerTextoEEPROM(
        EEPROM_POS_TOKEN_LONGITUD,
        EEPROM_POS_TOKEN_DATOS,
        TOKEN_MAX_LONGITUD,
        token
      )) {
    return false;
  }

  token.trim();

  if (token.length() > 0 && !tokenDispositivoValido(token)) {
    token = "";
  }

  return true;
}

String tokenOculto(const String &token) {
  if (token.length() < 10) {
    return "NO CONFIGURADO";
  }

  return token.substring(0, 6) + "..." +
         token.substring(token.length() - 4);
}

bool credencialesConfiguradas() {
  return idDispositivoValido(idDevice) &&
         tokenDispositivoValido(deviceToken);
}

// ==================================================
// LEER CONFIGURACIONES ADICIONALES
// ==================================================

void leerConfiguracionesAdicionales() {
  uint16_t puertoGuardado;
  uint16_t tiempoGuardado;

  if (leerUint16(EEPROM_POS_PUERTO_HTTP, puertoGuardado) &&
      puertoGuardado != 0xFFFF &&
      puertoGuardado >= 1) {
    puertoHTTP = puertoGuardado;
  } else {
    puertoHTTP = PUERTO_HTTP_DEFAULT;
  }

  if (leerUint16(EEPROM_POS_TIEMPO_ENVIO, tiempoGuardado) &&
      tiempoGuardado != 0xFFFF &&
      tiempoGuardado >= 1) {
    tiempoEnvioMinutos = tiempoGuardado;
  } else {
    tiempoEnvioMinutos = TIEMPO_ENVIO_DEFAULT;
  }

  if (
    !leerURLConector(urlConector) ||
    urlConector.length() == 0
  ) {
    urlConector = URL_API_PRODUCCION;
  }

  if (!leerIDDispositivo(idDevice)) {
    idDevice = "";
  }

  if (!leerTokenDispositivo(deviceToken)) {
    deviceToken = "";
  }
}

// ==================================================
// CONFIGURACION RS485 Y SENSORES MODBUS
// ==================================================

bool configuracionSerialRS485Valida(
  uint32_t baudrate,
  char paridad
) {
  paridad = toupper(paridad);

  if (baudrate < 1200UL || baudrate > 115200UL) {
    return false;
  }

  return paridad == 'N' || paridad == 'E' || paridad == 'O';
}

bool configuracionSensorModbusValida(
  const ConfigSensorModbus &sensor
) {
  if (sensor.slave < 1 || sensor.slave > 247) {
    return false;
  }

  if (sensor.funcion != 0x03 && sensor.funcion != 0x04) {
    return false;
  }

  if (
    sensor.cantidadRegistros < 1 ||
    sensor.cantidadRegistros > MODBUS_MAX_REGISTROS
  ) {
    return false;
  }

  uint32_t ultimoRegistro =
    (uint32_t)sensor.registroInicial +
    (uint32_t)sensor.cantidadRegistros -
    1UL;

  return ultimoRegistro <= 0xFFFFUL;
}

// Compatibilidad con las validaciones del firmware anterior.
bool configuracionRS485Valida(
  uint32_t baudrate,
  char paridad,
  uint8_t slave,
  uint8_t funcion,
  uint16_t registro,
  uint16_t cantidad
) {
  ConfigSensorModbus sensor = {
    true,
    slave,
    funcion,
    registro,
    cantidad
  };

  return configuracionSerialRS485Valida(baudrate, paridad) &&
         configuracionSensorModbusValida(sensor);
}

ConfigSensorModbus configuracionSensorPredeterminada(
  uint8_t indiceSensor
) {
  ConfigSensorModbus sensor;

  sensor.activo = indiceSensor == 0;
  sensor.slave = indiceSensor == 0
    ? MODBUS_SLAVE_DEFAULT
    : (uint8_t)(indiceSensor + 1);
  sensor.funcion = MODBUS_FUNCION_DEFAULT;
  sensor.registroInicial = MODBUS_REGISTRO_DEFAULT;
  sensor.cantidadRegistros = MODBUS_CANTIDAD_DEFAULT;

  return sensor;
}

void cargarSensoresPredeterminados() {
  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    sensoresModbus[i] = configuracionSensorPredeterminada(i);
  }

  sincronizarContextoSensor(0);
}

void sincronizarContextoSensor(uint8_t indiceSensor) {
  if (indiceSensor >= MAX_SENSORES_MODBUS) {
    return;
  }

  modbusSlave = sensoresModbus[indiceSensor].slave;
  modbusFuncion = sensoresModbus[indiceSensor].funcion;
  modbusRegistroInicial =
    sensoresModbus[indiceSensor].registroInicial;
  modbusCantidadRegistros =
    sensoresModbus[indiceSensor].cantidadRegistros;
}

uint16_t posicionSensorEEPROM(uint8_t indiceSensor) {
  return
    EEPROM_POS_SENSORES_BASE +
    (uint16_t)indiceSensor * EEPROM_SENSOR_TAMANO;
}

// ==================================================
// GUARDAR CONFIGURACION COMPLETA DE CUATRO SENSORES
// ==================================================

bool guardarConfiguracionRS485CompletaEEPROM() {
  if (!configuracionSerialRS485Valida(
        rs485Baudrate,
        rs485Paridad
      )) {
    return false;
  }

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    if (
      sensoresModbus[i].activo &&
      !configuracionSensorModbusValida(sensoresModbus[i])
    ) {
      return false;
    }
  }

  // Invalidar primero el bloque para evitar aceptar una
  // escritura interrumpida como configuración completa.
  if (!escribirEEPROM(EEPROM_POS_RS485_MAGIC_1, 0x00)) {
    return false;
  }

  if (
    !guardarUint32(
      EEPROM_POS_RS485_BAUDRATE,
      rs485Baudrate
    ) ||
    !escribirEEPROM(
      EEPROM_POS_RS485_PARIDAD,
      (uint8_t)toupper(rs485Paridad)
    )
  ) {
    return false;
  }

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    uint16_t base = posicionSensorEEPROM(i);
    const ConfigSensorModbus &sensor = sensoresModbus[i];

    if (
      !escribirEEPROM(
        base + EEPROM_SENSOR_OFFSET_ACTIVO,
        sensor.activo ? 1 : 0
      ) ||
      !escribirEEPROM(
        base + EEPROM_SENSOR_OFFSET_SLAVE,
        sensor.slave
      ) ||
      !escribirEEPROM(
        base + EEPROM_SENSOR_OFFSET_FUNCION,
        sensor.funcion
      ) ||
      !guardarUint16(
        base + EEPROM_SENSOR_OFFSET_REGISTRO,
        sensor.registroInicial
      ) ||
      !escribirEEPROM(
        base + EEPROM_SENSOR_OFFSET_CANTIDAD,
        (uint8_t)sensor.cantidadRegistros
      ) ||
      !escribirEEPROM(
        base + EEPROM_SENSOR_OFFSET_RESERVA_1,
        0
      ) ||
      !escribirEEPROM(
        base + EEPROM_SENSOR_OFFSET_RESERVA_2,
        0
      )
    ) {
      return false;
    }
  }

  if (
    !escribirEEPROM(
      EEPROM_POS_RS485_VERSION,
      EEPROM_RS485_VERSION_ACTUAL
    ) ||
    !escribirEEPROM(
      EEPROM_POS_RS485_MAGIC_2,
      EEPROM_RS485_MAGIC_2
    ) ||
    !escribirEEPROM(
      EEPROM_POS_RS485_MAGIC_1,
      EEPROM_RS485_MAGIC_1
    )
  ) {
    return false;
  }

  configuracionRS485Guardada = true;
  return true;
}

// Compatibilidad con SETMODBUS del firmware anterior.
// Este comando modifica únicamente la ranura 1.
bool guardarConfiguracionRS485EEPROM(
  uint32_t baudrate,
  char paridad,
  uint8_t slave,
  uint8_t funcion,
  uint16_t registro,
  uint16_t cantidad
) {
  ConfigSensorModbus sensor = {
    true,
    slave,
    funcion,
    registro,
    cantidad
  };

  if (
    !configuracionSerialRS485Valida(baudrate, paridad) ||
    !configuracionSensorModbusValida(sensor)
  ) {
    return false;
  }

  uint32_t baudAnterior = rs485Baudrate;
  char paridadAnterior = rs485Paridad;
  ConfigSensorModbus sensorAnterior = sensoresModbus[0];

  rs485Baudrate = baudrate;
  rs485Paridad = (char)toupper(paridad);
  sensoresModbus[0] = sensor;

  if (!guardarConfiguracionRS485CompletaEEPROM()) {
    rs485Baudrate = baudAnterior;
    rs485Paridad = paridadAnterior;
    sensoresModbus[0] = sensorAnterior;
    sincronizarContextoSensor(0);
    return false;
  }

  sincronizarContextoSensor(0);
  return true;
}

// ==================================================
// LEER CONFIGURACION DE CUATRO SENSORES
// ==================================================

bool leerConfiguracionRS485Version2() {
  uint32_t baudrateGuardado = 0;
  uint8_t paridadGuardada = 0;

  if (
    !leerUint32(
      EEPROM_POS_RS485_BAUDRATE,
      baudrateGuardado
    ) ||
    !leerEEPROM(
      EEPROM_POS_RS485_PARIDAD,
      paridadGuardada
    ) ||
    !configuracionSerialRS485Valida(
      baudrateGuardado,
      (char)paridadGuardada
    )
  ) {
    return false;
  }

  ConfigSensorModbus sensoresLeidos[MAX_SENSORES_MODBUS];

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    uint16_t base = posicionSensorEEPROM(i);
    uint8_t activo = 0;
    uint8_t slave = 0;
    uint8_t funcion = 0;
    uint8_t cantidad = 0;
    uint16_t registro = 0;

    if (
      !leerEEPROM(
        base + EEPROM_SENSOR_OFFSET_ACTIVO,
        activo
      ) ||
      !leerEEPROM(
        base + EEPROM_SENSOR_OFFSET_SLAVE,
        slave
      ) ||
      !leerEEPROM(
        base + EEPROM_SENSOR_OFFSET_FUNCION,
        funcion
      ) ||
      !leerUint16(
        base + EEPROM_SENSOR_OFFSET_REGISTRO,
        registro
      ) ||
      !leerEEPROM(
        base + EEPROM_SENSOR_OFFSET_CANTIDAD,
        cantidad
      )
    ) {
      return false;
    }

    sensoresLeidos[i].activo = activo == 1;
    sensoresLeidos[i].slave = slave;
    sensoresLeidos[i].funcion = funcion;
    sensoresLeidos[i].registroInicial = registro;
    sensoresLeidos[i].cantidadRegistros = cantidad;

    if (
      sensoresLeidos[i].activo &&
      !configuracionSensorModbusValida(sensoresLeidos[i])
    ) {
      return false;
    }

    // Una ranura inactiva dañada se normaliza en RAM.
    if (
      !sensoresLeidos[i].activo &&
      !configuracionSensorModbusValida(sensoresLeidos[i])
    ) {
      sensoresLeidos[i] = configuracionSensorPredeterminada(i);
      sensoresLeidos[i].activo = false;
    }
  }

  rs485Baudrate = baudrateGuardado;
  rs485Paridad = (char)toupper((char)paridadGuardada);

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    sensoresModbus[i] = sensoresLeidos[i];
  }

  configuracionRS485Guardada = true;
  sincronizarContextoSensor(0);
  return true;
}

// ==================================================
// MIGRAR AUTOMATICAMENTE EL FORMATO DE UN SENSOR
// ==================================================

bool migrarConfiguracionRS485Version1() {
  uint32_t baudrateGuardado = 0;
  uint8_t paridadGuardada = 0;
  uint8_t slaveGuardado = 0;
  uint8_t funcionGuardada = 0;
  uint16_t registroGuardado = 0;
  uint16_t cantidadGuardada = 0;

  bool lecturaCompleta =
    leerUint32(
      EEPROM_POS_RS485_BAUDRATE,
      baudrateGuardado
    ) &&
    leerEEPROM(
      EEPROM_POS_RS485_PARIDAD,
      paridadGuardada
    ) &&
    leerEEPROM(
      EEPROM_V1_POS_MODBUS_SLAVE,
      slaveGuardado
    ) &&
    leerEEPROM(
      EEPROM_V1_POS_MODBUS_FUNCION,
      funcionGuardada
    ) &&
    leerUint16(
      EEPROM_V1_POS_MODBUS_REGISTRO,
      registroGuardado
    ) &&
    leerUint16(
      EEPROM_V1_POS_MODBUS_CANTIDAD,
      cantidadGuardada
    );

  if (
    !lecturaCompleta ||
    !configuracionRS485Valida(
      baudrateGuardado,
      (char)paridadGuardada,
      slaveGuardado,
      funcionGuardada,
      registroGuardado,
      cantidadGuardada
    )
  ) {
    return false;
  }

  rs485Baudrate = baudrateGuardado;
  rs485Paridad = (char)toupper((char)paridadGuardada);
  cargarSensoresPredeterminados();

  sensoresModbus[0].activo = true;
  sensoresModbus[0].slave = slaveGuardado;
  sensoresModbus[0].funcion = funcionGuardada;
  sensoresModbus[0].registroInicial = registroGuardado;
  sensoresModbus[0].cantidadRegistros = cantidadGuardada;

  if (!guardarConfiguracionRS485CompletaEEPROM()) {
    return false;
  }

  sincronizarContextoSensor(0);

  Serial.println(
    "Configuracion RS485 migrada automaticamente "
    "de 1 a 4 sensores."
  );

  return true;
}

bool leerConfiguracionRS485EEPROM() {
  uint8_t magic1 = 0;
  uint8_t magic2 = 0;
  uint8_t version = 0;

  bool encabezadoLeido =
    leerEEPROM(EEPROM_POS_RS485_MAGIC_1, magic1) &&
    leerEEPROM(EEPROM_POS_RS485_MAGIC_2, magic2) &&
    leerEEPROM(EEPROM_POS_RS485_VERSION, version);

  if (
    encabezadoLeido &&
    magic1 == EEPROM_RS485_MAGIC_1 &&
    magic2 == EEPROM_RS485_MAGIC_2
  ) {
    if (
      version == EEPROM_RS485_VERSION_ACTUAL &&
      leerConfiguracionRS485Version2()
    ) {
      return true;
    }

    if (
      version == EEPROM_RS485_VERSION_ANTERIOR &&
      migrarConfiguracionRS485Version1()
    ) {
      configuracionRS485Guardada = true;
      return true;
    }
  }

  rs485Baudrate = RS485_BAUDRATE_DEFAULT;
  rs485Paridad = RS485_PARIDAD_DEFAULT;
  cargarSensoresPredeterminados();
  configuracionRS485Guardada = false;
  return false;
}

// ==================================================
// GUARDAR UNA DIRECCION IP EN EEPROM
// ==================================================

bool guardarIPAddress(uint16_t posicion, IPAddress ip) {
  for (uint8_t i = 0; i < 4; i++) {
    if (!escribirEEPROM(posicion + i, ip[i])) {
      return false;
    }
  }

  return true;
}

// ==================================================
// LEER UNA DIRECCION IP DESDE EEPROM
// ==================================================

bool leerIPAddress(uint16_t posicion, IPAddress &ip) {
  uint8_t datos[4];

  for (uint8_t i = 0; i < 4; i++) {
    if (!leerEEPROM(posicion + i, datos[i])) {
      return false;
    }
  }

  ip = IPAddress(datos[0], datos[1], datos[2], datos[3]);
  return true;
}

// ==================================================
// GUARDAR IP Y MASCARA DEL SERVIDOR
// ==================================================

bool guardarConfiguracionServidor(IPAddress ip, IPAddress mascara) {
  if (!guardarIPAddress(EEPROM_POS_SERVER_IP, ip)) {
    return false;
  }

  if (!guardarIPAddress(EEPROM_POS_SERVER_MASCARA, mascara)) {
    return false;
  }

  return true;
}

// ==================================================
// LEER IP Y MASCARA DEL SERVIDOR
// ==================================================

bool leerConfiguracionServidor(IPAddress &ip, IPAddress &mascara) {
  if (!leerIPAddress(EEPROM_POS_SERVER_IP, ip)) {
    return false;
  }

  if (!leerIPAddress(EEPROM_POS_SERVER_MASCARA, mascara)) {
    return false;
  }

  return true;
}

// ==================================================
// GUARDAR MODO DHCP
// ==================================================

bool guardarModoDHCP() {
  return escribirEEPROM(EEPROM_POS_MODO_RED, MODO_DHCP);
}

// ==================================================
// GUARDAR CONFIGURACION DE IP LOCAL ESTATICA
// ==================================================

bool guardarConfiguracionEstatica(IPAddress ip, IPAddress mascara) {
  // Primero se guardan los datos.
  if (!guardarIPAddress(EEPROM_POS_IP_LOCAL, ip)) {
    return false;
  }

  if (!guardarIPAddress(EEPROM_POS_MASCARA_LOCAL, mascara)) {
    return false;
  }

  // El modo se guarda al final para evitar activar una
  // configuracion incompleta si ocurre un error de escritura.
  if (!escribirEEPROM(EEPROM_POS_MODO_RED, MODO_ESTATICO)) {
    return false;
  }

  return true;
}

// ==================================================
// LEER CONFIGURACION DE RED LOCAL
// ==================================================

bool leerConfiguracionRed(
  uint8_t &modo,
  IPAddress &ip,
  IPAddress &mascara
) {
  uint8_t modoGuardado;

  if (!leerEEPROM(EEPROM_POS_MODO_RED, modoGuardado)) {
    return false;
  }

  // EEPROM vacia, valor desconocido o configuracion antigua:
  // utilizar DHCP por defecto.
  if (modoGuardado != MODO_ESTATICO) {
    modo = MODO_DHCP;
    ip = IPAddress(0, 0, 0, 0);
    mascara = IPAddress(0, 0, 0, 0);
    return true;
  }

  if (!leerIPAddress(EEPROM_POS_IP_LOCAL, ip)) {
    return false;
  }

  if (!leerIPAddress(EEPROM_POS_MASCARA_LOCAL, mascara)) {
    return false;
  }

  modo = MODO_ESTATICO;
  return true;
}

// ==================================================
// COMPROBAR SI UNA IP NO ESTA VACIA
// ==================================================

bool direccionIPValida(IPAddress ip) {
  bool todoCero = true;
  bool todoFF = true;

  for (uint8_t i = 0; i < 4; i++) {
    if (ip[i] != 0) {
      todoCero = false;
    }

    if (ip[i] != 255) {
      todoFF = false;
    }
  }

  return !todoCero && !todoFF;
}

// ==================================================
// COMPROBAR MASCARA DE RED
// ==================================================

bool mascaraValida(IPAddress mascara) {
  uint32_t valor =
    ((uint32_t)mascara[0] << 24) |
    ((uint32_t)mascara[1] << 16) |
    ((uint32_t)mascara[2] << 8) |
    (uint32_t)mascara[3];

  if (valor == 0 || valor == 0xFFFFFFFF) {
    return false;
  }

  // Una mascara valida contiene unos consecutivos
  // seguidos solamente por ceros.
  uint32_t inversa = ~valor;
  return (inversa & (inversa + 1)) == 0;
}

// ==================================================
// COMPROBAR IP Y MASCARA
// ==================================================

bool configuracionValida(IPAddress ip, IPAddress mascara) {
  return direccionIPValida(ip) && mascaraValida(mascara);
}

// ==================================================
// CONVERTIR TEXTO A IPAddress
// ==================================================

bool convertirIP(String texto, IPAddress &resultado) {
  texto.trim();

  int a;
  int b;
  int c;
  int d;
  char caracterExtra;

  int cantidad = sscanf(
    texto.c_str(),
    "%d.%d.%d.%d%c",
    &a,
    &b,
    &c,
    &d,
    &caracterExtra
  );

  if (cantidad != 4) {
    return false;
  }

  if (a < 0 || a > 255 ||
      b < 0 || b > 255 ||
      c < 0 || c > 255 ||
      d < 0 || d > 255) {
    return false;
  }

  resultado = IPAddress(a, b, c, d);
  return true;
}

// ==================================================
// APROVISIONAMIENTO Y CONFIGURACION
//
// Comandos generales, procesados al presionar Enter:
//   SETID CDT-HN-000001
//   SETTOKEN TOKEN_ORIGINAL
//   SHOWAUTH
//
// Bus RS485 compartido:
//   SETSERIAL 9600 N
//
// Sensores Modbus, ranuras 1 a 4:
//   SETSENSOR 1 13 3 0x0000 1
//   ENABLESENSOR 1
//   DISABLESENSOR 1
//   CLEARSENSOR 1
//   SHOWSENSORS
//   TESTSENSOR 1
//   TESTALL
//
// Compatibilidad anterior:
//   SETMODBUS 13 3 0x0000 1
//   SHOWMODBUS
//   TESTMODBUS
// ==================================================

bool esPrefijoAprovisionamiento(String comando) {
  comando.trim();
  comando.toUpperCase();

  if (comando.length() == 0) {
    return false;
  }

  const String comandos[] = {
    "SETID",
    "SETTOKEN",
    "SHOWAUTH",
    "SETSERIAL",
    "SETSENSOR",
    "ENABLESENSOR",
    "DISABLESENSOR",
    "CLEARSENSOR",
    "SHOWSENSORS",
    "TESTSENSOR",
    "TESTALL",
    "SETMODBUS",
    "SHOWMODBUS",
    "TESTMODBUS"
  };

  const uint8_t cantidadComandos =
    sizeof(comandos) / sizeof(comandos[0]);

  for (uint8_t i = 0; i < cantidadComandos; i++) {
    if (
      comandos[i].startsWith(comando) ||
      comando.startsWith(comandos[i])
    ) {
      return true;
    }
  }

  return false;
}

String valorDespuesDelComando(String comando, String nombre) {
  String resto = comando.substring(nombre.length());
  resto.trim();

  if (resto.startsWith("=")) {
    resto.remove(0, 1);
    resto.trim();
  }

  return resto;
}

// ==================================================
// DIVIDIR Y CONVERTIR ARGUMENTOS
// ==================================================

uint8_t dividirArgumentos(
  String texto,
  String argumentos[],
  uint8_t maximo
) {
  texto.trim();
  uint8_t cantidad = 0;

  while (texto.length() > 0 && cantidad < maximo) {
    int espacio = texto.indexOf(' ');

    if (espacio < 0) {
      argumentos[cantidad++] = texto;
      break;
    }

    String parte = texto.substring(0, espacio);
    parte.trim();

    if (parte.length() > 0) {
      argumentos[cantidad++] = parte;
    }

    texto = texto.substring(espacio + 1);
    texto.trim();
  }

  return cantidad;
}

bool convertirNumeroFlexible(String texto, uint32_t &valor) {
  texto.trim();

  if (texto.length() == 0) {
    return false;
  }

  char *fin = nullptr;
  unsigned long resultado = strtoul(texto.c_str(), &fin, 0);

  if (fin == texto.c_str() || *fin != '\0') {
    return false;
  }

  valor = (uint32_t)resultado;
  return true;
}

bool obtenerRanuraDesdeComando(
  String comando,
  String nombreComando,
  uint8_t &indiceSensor
) {
  String texto = valorDespuesDelComando(
    comando,
    nombreComando
  );

  uint32_t ranura = 0;

  if (
    !convertirNumeroFlexible(texto, ranura) ||
    ranura < 1 ||
    ranura > MAX_SENSORES_MODBUS
  ) {
    return false;
  }

  indiceSensor = (uint8_t)(ranura - 1);
  return true;
}

void imprimirHex16(uint16_t valor) {
  if (valor < 0x1000) Serial.print("0");
  if (valor < 0x0100) Serial.print("0");
  if (valor < 0x0010) Serial.print("0");
  Serial.print(valor, HEX);
}

// ==================================================
// MOSTRAR CONFIGURACION DE LOS CUATRO SENSORES
// ==================================================

void mostrarConfiguracionModbus() {
  Serial.println();
  Serial.println("[ CONFIGURACION RS485 UNIVERSAL ]");
  Serial.println("---------------------------------------------------");
  Serial.print(" Origen             : ");
  Serial.println(
    configuracionRS485Guardada
      ? "EEPROM"
      : "VALORES PREDETERMINADOS"
  );
  Serial.print(" Baudrate compartido: ");
  Serial.println(rs485Baudrate);
  Serial.print(" Paridad compartida : ");
  Serial.println(rs485Paridad);
  Serial.println("---------------------------------------------------");

  uint8_t activos = 0;

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    const ConfigSensorModbus &sensor = sensoresModbus[i];

    Serial.print(" SENSOR ");
    Serial.println(i + 1);
    Serial.print(" Estado             : ");
    Serial.println(sensor.activo ? "ACTIVO" : "INACTIVO");
    Serial.print(" ID esclavo         : ");
    Serial.println(sensor.slave);
    Serial.print(" Funcion            : ");
    Serial.println(sensor.funcion);
    Serial.print(" Registro inicial   : 0x");
    imprimirHex16(sensor.registroInicial);
    Serial.println();
    Serial.print(" Cantidad registros : ");
    Serial.println(sensor.cantidadRegistros);
    Serial.println("---------------------------------------------------");

    if (sensor.activo) {
      activos++;
    }
  }

  Serial.print(" Sensores activos   : ");
  Serial.print(activos);
  Serial.print(" de ");
  Serial.println(MAX_SENSORES_MODBUS);
  Serial.println();
}

bool configurarSensorDesdeArgumentos(
  uint8_t indiceSensor,
  uint32_t slave,
  uint32_t funcion,
  uint32_t registro,
  uint32_t cantidad
) {
  if (
    indiceSensor >= MAX_SENSORES_MODBUS ||
    slave > 255UL ||
    funcion > 255UL ||
    registro > 65535UL ||
    cantidad > 65535UL
  ) {
    return false;
  }

  ConfigSensorModbus sensor = {
    true,
    (uint8_t)slave,
    (uint8_t)funcion,
    (uint16_t)registro,
    (uint16_t)cantidad
  };

  if (!configuracionSensorModbusValida(sensor)) {
    return false;
  }

  ConfigSensorModbus anterior = sensoresModbus[indiceSensor];
  sensoresModbus[indiceSensor] = sensor;

  if (!guardarConfiguracionRS485CompletaEEPROM()) {
    sensoresModbus[indiceSensor] = anterior;
    sincronizarContextoSensor(0);
    return false;
  }

  sincronizarContextoSensor(0);
  return true;
}

bool procesarComandoAprovisionamiento(String comando) {
  comando.trim();

  String comandoMayusculas = comando;
  comandoMayusculas.toUpperCase();

  if (comandoMayusculas == "SHOWAUTH") {
    Serial.println();
    Serial.println("[ IDENTIDAD DEL DISPOSITIVO ]");
    Serial.println("---------------------------------------------------");
    Serial.print(" ID                 : ");
    Serial.println(
      idDevice.length() > 0
        ? idDevice
        : "NO CONFIGURADO"
    );
    Serial.print(" Token              : ");
    Serial.println(tokenOculto(deviceToken));
    Serial.print(" Estado             : ");
    Serial.println(
      credencialesConfiguradas()
        ? "APROVISIONADO"
        : "INCOMPLETO"
    );
    Serial.println();
    return true;
  }

  if (
    comandoMayusculas.startsWith("SETID ") ||
    comandoMayusculas.startsWith("SETID=")
  ) {
    String nuevoID = valorDespuesDelComando(comando, "SETID");
    nuevoID.toUpperCase();

    if (!idDispositivoValido(nuevoID)) {
      Serial.println(
        "ERROR: ID invalido. Use letras, numeros, "
        "guion o guion bajo."
      );
      return true;
    }

    if (!guardarIDDispositivo(nuevoID)) {
      Serial.println("ERROR: No se pudo guardar el ID.");
      return true;
    }

    idDevice = nuevoID;

    Serial.println();
    Serial.println("ID DEL DISPOSITIVO GUARDADO");
    Serial.print("ID: ");
    Serial.println(idDevice);
    Serial.println();
    return true;
  }

  if (
    comandoMayusculas.startsWith("SETTOKEN ") ||
    comandoMayusculas.startsWith("SETTOKEN=")
  ) {
    String nuevoToken = valorDespuesDelComando(
      comando,
      "SETTOKEN"
    );

    if (!tokenDispositivoValido(nuevoToken)) {
      Serial.println(
        "ERROR: El token debe tener de 32 a 64 "
        "caracteres alfanumericos."
      );
      return true;
    }

    if (!guardarTokenDispositivo(nuevoToken)) {
      Serial.println("ERROR: No se pudo guardar el token.");
      return true;
    }

    deviceToken = nuevoToken;

    Serial.println();
    Serial.println("TOKEN DEL DISPOSITIVO GUARDADO");
    Serial.print("Token: ");
    Serial.println(tokenOculto(deviceToken));
    Serial.println();
    return true;
  }

  if (
    comandoMayusculas == "SHOWSENSORS" ||
    comandoMayusculas == "SHOWMODBUS"
  ) {
    mostrarConfiguracionModbus();
    return true;
  }

  if (comandoMayusculas == "TESTALL") {
    probarTodosSensoresModbus();
    return true;
  }

  if (comandoMayusculas == "TESTMODBUS") {
    probarConsultaModbus();
    return true;
  }

  if (
    comandoMayusculas.startsWith("TESTSENSOR ") ||
    comandoMayusculas.startsWith("TESTSENSOR=")
  ) {
    uint8_t indiceSensor = 0;

    if (!obtenerRanuraDesdeComando(
          comando,
          "TESTSENSOR",
          indiceSensor
        )) {
      Serial.println("ERROR: Use TESTSENSOR <1-4>.");
      return true;
    }

    probarSensorModbus(indiceSensor);
    return true;
  }

  if (
    comandoMayusculas.startsWith("SETSERIAL ") ||
    comandoMayusculas.startsWith("SETSERIAL=")
  ) {
    String texto = valorDespuesDelComando(
      comando,
      "SETSERIAL"
    );
    String argumentos[2];

    if (dividirArgumentos(texto, argumentos, 2) != 2) {
      Serial.println("ERROR: Uso SETSERIAL 9600 N");
      return true;
    }

    uint32_t nuevoBaudrate = 0;

    if (!convertirNumeroFlexible(
          argumentos[0],
          nuevoBaudrate
        )) {
      Serial.println("ERROR: Baudrate incorrecto.");
      return true;
    }

    argumentos[1].trim();
    argumentos[1].toUpperCase();

    if (argumentos[1].length() != 1) {
      Serial.println("ERROR: Paridad valida: N, E u O.");
      return true;
    }

    char nuevaParidad = argumentos[1].charAt(0);

    if (!configuracionSerialRS485Valida(
          nuevoBaudrate,
          nuevaParidad
        )) {
      Serial.println("ERROR: Configuracion serial no valida.");
      return true;
    }

    uint32_t baudAnterior = rs485Baudrate;
    char paridadAnterior = rs485Paridad;

    rs485Baudrate = nuevoBaudrate;
    rs485Paridad = (char)toupper(nuevaParidad);

    if (!guardarConfiguracionRS485CompletaEEPROM()) {
      rs485Baudrate = baudAnterior;
      rs485Paridad = paridadAnterior;
      Serial.println("ERROR: No se pudo guardar en EEPROM.");
      return true;
    }

    aplicarConfiguracionRS485();

    Serial.println(
      "CONFIGURACION SERIAL GUARDADA Y APLICADA"
    );
    mostrarConfiguracionModbus();
    return true;
  }

  if (
    comandoMayusculas.startsWith("SETSENSOR ") ||
    comandoMayusculas.startsWith("SETSENSOR=")
  ) {
    String texto = valorDespuesDelComando(
      comando,
      "SETSENSOR"
    );
    String argumentos[5];

    if (dividirArgumentos(texto, argumentos, 5) != 5) {
      Serial.println(
        "ERROR: Uso SETSENSOR "
        "<ranura> <slave> <funcion> <registro> <cantidad>"
      );
      Serial.println(
        "Ejemplo: SETSENSOR 2 2 3 0x0000 2"
      );
      return true;
    }

    uint32_t ranura = 0;
    uint32_t slave = 0;
    uint32_t funcion = 0;
    uint32_t registro = 0;
    uint32_t cantidad = 0;

    if (
      !convertirNumeroFlexible(argumentos[0], ranura) ||
      !convertirNumeroFlexible(argumentos[1], slave) ||
      !convertirNumeroFlexible(argumentos[2], funcion) ||
      !convertirNumeroFlexible(argumentos[3], registro) ||
      !convertirNumeroFlexible(argumentos[4], cantidad)
    ) {
      Serial.println("ERROR: Uno o mas numeros son invalidos.");
      return true;
    }

    if (
      ranura < 1 ||
      ranura > MAX_SENSORES_MODBUS ||
      !configurarSensorDesdeArgumentos(
        (uint8_t)(ranura - 1),
        slave,
        funcion,
        registro,
        cantidad
      )
    ) {
      Serial.println("ERROR: Configuracion de sensor no valida.");
      Serial.println("Ranura: 1-4");
      Serial.println("Slave: 1-247");
      Serial.println("Funcion: 3 o 4");
      Serial.print("Cantidad: 1-");
      Serial.println(MODBUS_MAX_REGISTROS);
      return true;
    }

    Serial.print("SENSOR ");
    Serial.print(ranura);
    Serial.println(" GUARDADO Y ACTIVADO");
    mostrarConfiguracionModbus();
    return true;
  }

  if (
    comandoMayusculas.startsWith("SETMODBUS ") ||
    comandoMayusculas.startsWith("SETMODBUS=")
  ) {
    String texto = valorDespuesDelComando(
      comando,
      "SETMODBUS"
    );
    String argumentos[4];

    if (dividirArgumentos(texto, argumentos, 4) != 4) {
      Serial.println(
        "ERROR: Uso SETMODBUS "
        "<slave> <funcion> <registro> <cantidad>"
      );
      return true;
    }

    uint32_t slave = 0;
    uint32_t funcion = 0;
    uint32_t registro = 0;
    uint32_t cantidad = 0;

    if (
      !convertirNumeroFlexible(argumentos[0], slave) ||
      !convertirNumeroFlexible(argumentos[1], funcion) ||
      !convertirNumeroFlexible(argumentos[2], registro) ||
      !convertirNumeroFlexible(argumentos[3], cantidad) ||
      !configurarSensorDesdeArgumentos(
        0,
        slave,
        funcion,
        registro,
        cantidad
      )
    ) {
      Serial.println(
        "ERROR: Configuracion Modbus no valida."
      );
      return true;
    }

    Serial.println(
      "SENSOR 1 GUARDADO MEDIANTE COMANDO COMPATIBLE"
    );
    mostrarConfiguracionModbus();
    return true;
  }

  const String comandosEstado[] = {
    "ENABLESENSOR",
    "DISABLESENSOR",
    "CLEARSENSOR"
  };

  for (uint8_t comandoIndice = 0;
       comandoIndice < 3;
       comandoIndice++) {
    String nombre = comandosEstado[comandoIndice];

    if (
      comandoMayusculas.startsWith(nombre + " ") ||
      comandoMayusculas.startsWith(nombre + "=")
    ) {
      uint8_t indiceSensor = 0;

      if (!obtenerRanuraDesdeComando(
            comando,
            nombre,
            indiceSensor
          )) {
        Serial.print("ERROR: Use ");
        Serial.print(nombre);
        Serial.println(" <1-4>.");
        return true;
      }

      ConfigSensorModbus anterior =
        sensoresModbus[indiceSensor];

      if (nombre == "ENABLESENSOR") {
        if (!configuracionSensorModbusValida(
              sensoresModbus[indiceSensor]
            )) {
          Serial.println(
            "ERROR: Configure primero la ranura "
            "con SETSENSOR."
          );
          return true;
        }

        sensoresModbus[indiceSensor].activo = true;
      } else if (nombre == "DISABLESENSOR") {
        sensoresModbus[indiceSensor].activo = false;
      } else {
        sensoresModbus[indiceSensor] =
          configuracionSensorPredeterminada(indiceSensor);
        sensoresModbus[indiceSensor].activo = false;
      }

      if (!guardarConfiguracionRS485CompletaEEPROM()) {
        sensoresModbus[indiceSensor] = anterior;
        Serial.println("ERROR: No se pudo guardar en EEPROM.");
        return true;
      }

      sincronizarContextoSensor(0);

      Serial.print("SENSOR ");
      Serial.print(indiceSensor + 1);

      if (nombre == "ENABLESENSOR") {
        Serial.println(" ACTIVADO");
      } else if (nombre == "DISABLESENSOR") {
        Serial.println(" DESACTIVADO");
      } else {
        Serial.println(" BORRADO");
      }

      mostrarConfiguracionModbus();
      return true;
    }
  }

  return false;
}

// ==================================================
// PROCESAR COMANDO DEL SERVIDOR
// Formato: <ID>_IP/MASCARAZ
// Ejemplo: CDT-HN-000001_192.168.1.200/255.255.255.0Z
// ==================================================

void procesarComandoServidor(String comandoSinZ) {
  String prefijo = idDevice + "_";

  if (!comandoSinZ.startsWith(prefijo)) {
    Serial.println("ERROR: Formato del servidor incorrecto.");
    return;
  }

  String contenido = comandoSinZ.substring(prefijo.length());
  int posicionSlash = contenido.indexOf('/');

  if (posicionSlash <= 0 || posicionSlash != contenido.lastIndexOf('/') || posicionSlash >= contenido.length() - 1) {
    Serial.println("ERROR: Formato del servidor incorrecto.");
    Serial.println("Formato: <ID>_IP/MASCARAZ");
    return;
  }

  String textoIP = contenido.substring(0, posicionSlash);
  String textoMascara = contenido.substring(posicionSlash + 1);

  IPAddress nuevaIP;
  IPAddress nuevaMascara;

  if (!convertirIP(textoIP, nuevaIP)) {
    Serial.println("ERROR: IP del servidor incorrecta.");
    return;
  }

  if (!convertirIP(textoMascara, nuevaMascara)) {
    Serial.println("ERROR: Mascara del servidor incorrecta.");
    return;
  }

  if (!configuracionValida(nuevaIP, nuevaMascara)) {
    Serial.println("ERROR: Configuracion del servidor no valida.");
    return;
  }

  if (!guardarConfiguracionServidor(nuevaIP, nuevaMascara)) {
    Serial.println("ERROR: No se pudo guardar el servidor en EEPROM.");
    return;
  }

  serverIP = nuevaIP;
  serverMask = nuevaMascara;

  Serial.println();
  Serial.println("CONFIGURACION DEL SERVIDOR GUARDADA");
  Serial.print("IP servidor: ");
  Serial.println(serverIP);
  Serial.print("Mascara servidor: ");
  Serial.println(serverMask);
  Serial.println();
}

// ==================================================
// PROCESAR COMANDO DHCP
// Formato: <ID>dhcpZ
// Ejemplo: CDT-HN-000001dhcpZ
// ==================================================

void procesarComandoDHCP() {
  if (!guardarModoDHCP()) {
    Serial.println("ERROR: No se pudo guardar el modo DHCP.");
    return;
  }

  modoRed = MODO_DHCP;

  Serial.println();
  Serial.println("MODO DHCP GUARDADO");
  Serial.println("Reinicia el dispositivo para aplicar el cambio.");
  Serial.println();
}

// ==================================================
// PROCESAR COMANDO DE IP LOCAL ESTATICA
// Formato: <ID>IP/MASCARAZ
// Ejemplo: CDT-HN-000001192.168.1.50/255.255.255.0Z
// ==================================================

void procesarComandoIPLocal(String comandoSinZ) {
  String contenido = comandoSinZ.substring(idDevice.length());
  int posicionSlash = contenido.indexOf('/');

  if (posicionSlash <= 0 || posicionSlash != contenido.lastIndexOf('/') || posicionSlash >= contenido.length() - 1) {
    Serial.println("ERROR: Formato de IP local incorrecto.");
    Serial.println("Formato: <ID>IP/MASCARAZ");
    return;
  }

  String textoIP = contenido.substring(0, posicionSlash);
  String textoMascara = contenido.substring(posicionSlash + 1);

  IPAddress nuevaIP;
  IPAddress nuevaMascara;

  if (!convertirIP(textoIP, nuevaIP)) {
    Serial.println("ERROR: IP local incorrecta.");
    return;
  }

  if (!convertirIP(textoMascara, nuevaMascara)) {
    Serial.println("ERROR: Mascara local incorrecta.");
    return;
  }

  if (!configuracionValida(nuevaIP, nuevaMascara)) {
    Serial.println("ERROR: Configuracion local no valida.");
    return;
  }

  if (!guardarConfiguracionEstatica(nuevaIP, nuevaMascara)) {
    Serial.println("ERROR: No se pudo guardar la red estatica.");
    return;
  }

  modoRed = MODO_ESTATICO;
  ipLocal = nuevaIP;
  mascaraLocal = nuevaMascara;

  Serial.println();
  Serial.println("CONFIGURACION DE RED ESTATICA GUARDADA");
  Serial.print("IP local: ");
  Serial.println(ipLocal);
  Serial.print("Mascara local: ");
  Serial.println(mascaraLocal);
  Serial.println("Reinicia el dispositivo para aplicar el cambio.");
  Serial.println();
}

// ==================================================
// COMPROBAR SI UN TEXTO CONTIENE SOLO NUMEROS
// ==================================================

bool textoSoloNumeros(String texto) {
  if (texto.length() == 0) {
    return false;
  }

  for (uint16_t i = 0; i < texto.length(); i++) {
    if (!isDigit(texto.charAt(i))) {
      return false;
    }
  }

  return true;
}

// ==================================================
// COMPROBAR SI EL TEXTO PARECE IP/MASCARA
// ==================================================

bool textoPareceIPMascara(String texto) {
  if (texto.length() == 0 || texto.indexOf('/') == -1) {
    return false;
  }

  for (uint16_t i = 0; i < texto.length(); i++) {
    char caracter = texto.charAt(i);

    if (!isDigit(caracter) &&
        caracter != '.' &&
        caracter != '/') {
      return false;
    }
  }

  return true;
}

// ==================================================
// PROCESAR COMANDO DE PUERTO HTTP
// Formato: <ID>PUERTOZ
// Ejemplo: CDT-HN-0000018080Z
// ==================================================

void procesarComandoPuertoHTTP(String comandoSinZ) {
  String textoPuerto =
    comandoSinZ.substring(idDevice.length());

  if (!textoSoloNumeros(textoPuerto)) {
    Serial.println("ERROR: Puerto HTTP incorrecto.");
    Serial.println("Formato: <ID>PUERTOZ");
    return;
  }

  unsigned long valor = textoPuerto.toInt();

  if (valor < 1 || valor > 65535) {
    Serial.println("ERROR: El puerto debe estar entre 1 y 65535.");
    return;
  }

  if (!guardarUint16(
        EEPROM_POS_PUERTO_HTTP,
        (uint16_t)valor
      )) {
    Serial.println("ERROR: No se pudo guardar el puerto HTTP.");
    return;
  }

  puertoHTTP = (uint16_t)valor;

  Serial.println();
  Serial.println("PUERTO HTTP GUARDADO");
  Serial.print("Puerto HTTP: ");
  Serial.println(puertoHTTP);
  Serial.println();
}

// ==================================================
// PROCESAR COMANDO DE TIEMPO DE ENVIO
// Formato: <ID>TIEMPOM
// Ejemplo: CDT-HN-0000015m
// El valor se guarda en minutos.
// ==================================================

void procesarComandoTiempoEnvio(String comandoSinM) {
  String textoTiempo =
    comandoSinM.substring(idDevice.length());

  if (!textoSoloNumeros(textoTiempo)) {
    Serial.println("ERROR: Tiempo de envio incorrecto.");
    Serial.println("Formato: <ID>TIEMPOM");
    return;
  }

  unsigned long valor = textoTiempo.toInt();

  if (valor < 1 || valor > 65535) {
    Serial.println(
      "ERROR: El tiempo debe estar entre 1 y 65535 minutos."
    );
    return;
  }

  if (!guardarUint16(
        EEPROM_POS_TIEMPO_ENVIO,
        (uint16_t)valor
      )) {
    Serial.println("ERROR: No se pudo guardar el tiempo de envio.");
    return;
  }

  tiempoEnvioMinutos = (uint16_t)valor;

  // Reiniciar el temporizador para que el nuevo intervalo
  // empiece a contar desde este momento.
  ultimoTiempoLecturaSensorMs = millis();

  Serial.println();
  Serial.println("TIEMPO DE ENVIO GUARDADO");
  Serial.print("Tiempo de envio: ");
  Serial.print(tiempoEnvioMinutos);
  Serial.println(" minuto(s)");
  Serial.println();
}

// ==================================================
// PROCESAR COMANDO DE URL DE LA API
// Formato: <ID>RUTA!
// Ejemplo: CDT-HN-000001/api/v1/mediciones!
// ==================================================

void procesarComandoURL(String comandoSinExclamacion) {
  String nuevaURL =
    comandoSinExclamacion.substring(idDevice.length());

  nuevaURL.trim();

  if (nuevaURL.length() == 0) {
    Serial.println("ERROR: La URL no puede estar vacia.");
    return;
  }

  if (nuevaURL.length() > URL_MAX_LONGITUD) {
    Serial.print("ERROR: La URL supera ");
    Serial.print(URL_MAX_LONGITUD);
    Serial.println(" caracteres.");
    return;
  }

  if (!guardarURLConector(nuevaURL)) {
    Serial.println("ERROR: No se pudo guardar la URL.");
    return;
  }

  urlConector = nuevaURL;

  Serial.println();
  Serial.println("URL DE LA API GUARDADA");
  Serial.print("URL: ");
  Serial.println(urlConector);
  Serial.println();
}

// ==================================================
// MOSTRAR CONFIGURACION GUARDADA EN EEPROM
// Comando: <ID>config
// Ejemplo: CDT-HN-000001config
// ==================================================

void mostrarConfiguracionEEPROM() {
  IPAddress ipServidorGuardada;
  IPAddress mascaraServidorGuardada;

  uint8_t modoGuardado = MODO_DHCP;
  IPAddress ipLocalGuardada;
  IPAddress mascaraLocalGuardada;

  uint16_t puertoGuardado;
  uint16_t tiempoGuardado;
  String urlGuardada;
  String idGuardado;
  String tokenGuardado;

  bool servidorValido =
    leerConfiguracionServidor(
      ipServidorGuardada,
      mascaraServidorGuardada
    ) &&
    configuracionValida(
      ipServidorGuardada,
      mascaraServidorGuardada
    );

  bool redLeida =
    leerConfiguracionRed(
      modoGuardado,
      ipLocalGuardada,
      mascaraLocalGuardada
    );

  bool redEstaticaValida =
    redLeida &&
    modoGuardado == MODO_ESTATICO &&
    configuracionValida(
      ipLocalGuardada,
      mascaraLocalGuardada
    );

  bool puertoValido =
    leerUint16(
      EEPROM_POS_PUERTO_HTTP,
      puertoGuardado
    ) &&
    puertoGuardado >= 1 &&
    puertoGuardado <= 65535;

  bool tiempoValido =
    leerUint16(
      EEPROM_POS_TIEMPO_ENVIO,
      tiempoGuardado
    ) &&
    tiempoGuardado >= 1 &&
    tiempoGuardado <= 65535;

  bool urlLeida = leerURLConector(urlGuardada);
  bool idLeido = leerIDDispositivo(idGuardado);
  bool tokenLeido = leerTokenDispositivo(tokenGuardado);

  Serial.println();
  Serial.println("===================================================");
  Serial.println(" CONFIGURACION GUARDADA EN EEPROM");
  Serial.println("===================================================");
  Serial.print(" ID del dispositivo : ");
  if (idLeido && idGuardado.length() > 0) {
    Serial.println(idGuardado);
  } else {
    Serial.println("No configurado");
  }

  Serial.print(" Token              : ");
  if (tokenLeido && tokenGuardado.length() > 0) {
    Serial.println(tokenOculto(tokenGuardado));
  } else {
    Serial.println("No configurado");
  }

  Serial.println();

  // ------------------------------------------------
  // SERVIDOR
  // ------------------------------------------------

  Serial.println("[ SERVIDOR ]");
  Serial.println("---------------------------------------------------");

  if (servidorValido) {
    Serial.println(" Estado             : CONFIGURADO");

    Serial.print(" IP del servidor    : ");
    Serial.println(ipServidorGuardada);

    Serial.print(" Mascara servidor   : ");
    Serial.println(mascaraServidorGuardada);
  } else {
    Serial.println(" Estado             : SIN CONFIGURAR");
    Serial.println(" IP del servidor    : No disponible");
    Serial.println(" Mascara servidor   : No disponible");
  }

  Serial.println("---------------------------------------------------");
  Serial.println();

  // ------------------------------------------------
  // RED LOCAL
  // ------------------------------------------------

  Serial.println("[ RED LOCAL ]");
  Serial.println("---------------------------------------------------");

  if (redEstaticaValida) {
    Serial.println(" Modo de red        : IP ESTATICA");

    Serial.print(" IP local           : ");
    Serial.println(ipLocalGuardada);

    Serial.print(" Mascara local      : ");
    Serial.println(mascaraLocalGuardada);
  } else {
    Serial.println(" Modo de red        : DHCP");
    Serial.println(" IP local           : Asignada automaticamente");
    Serial.println(" Mascara local      : Asignada automaticamente");
  }

  Serial.println("---------------------------------------------------");
  Serial.println();

  // ------------------------------------------------
  // COMUNICACION
  // ------------------------------------------------

  Serial.println("[ COMUNICACION ]");
  Serial.println("---------------------------------------------------");

  Serial.print(" Puerto HTTP        : ");

  if (puertoValido) {
    Serial.println(puertoGuardado);
  } else {
    Serial.print(PUERTO_HTTP_DEFAULT);
    Serial.println(" (predeterminado)");
  }

  Serial.print(" Tiempo de envio    : ");

  if (tiempoValido) {
    Serial.print(tiempoGuardado);
  } else {
    Serial.print(TIEMPO_ENVIO_DEFAULT);
  }

  Serial.println(" minuto(s)");

  Serial.print(" URL de la API   : ");

  if (urlLeida && urlGuardada.length() > 0) {
    Serial.println(urlGuardada);
  } else {
    Serial.println("No configurada");
  }

  Serial.println("---------------------------------------------------");
  Serial.println();
  mostrarConfiguracionModbus();

  Serial.println("===================================================");
  Serial.println(" FIN DE LA CONFIGURACION");
  Serial.println("===================================================");
  Serial.println();
}

// ==================================================
// CALCULAR CRC MODBUS RTU
// ==================================================

uint16_t calcularCRCModbus(const uint8_t *datos, size_t longitud) {
  uint16_t crc = 0xFFFF;

  for (size_t i = 0; i < longitud; i++) {
    crc ^= datos[i];

    for (uint8_t bit = 0; bit < 8; bit++) {
      if (crc & 0x0001) {
        crc = (crc >> 1) ^ 0xA001;
      } else {
        crc >>= 1;
      }
    }
  }

  return crc;
}

// ==================================================
// LIMPIAR BUFFER UART DEL SENSOR
// ==================================================

void limpiarBufferRS485() {
  while (Serial1.available() > 0) {
    Serial1.read();
  }
}

// ==================================================
// CONFIGURAR UART RS485
// ==================================================

uint16_t obtenerConfiguracionSerialRS485() {
  switch (toupper(rs485Paridad)) {
    case 'E':
      return SERIAL_8E1;

    case 'O':
      return SERIAL_8O1;

    default:
      return SERIAL_8N1;
  }
}

void aplicarConfiguracionRS485() {
  Serial1.end();
  delay(20);

  Serial1.setTX(RS485_TX_PIN);
  Serial1.setRX(RS485_RX_PIN);
  Serial1.setFIFOSize(128);
  Serial1.begin(
    rs485Baudrate,
    obtenerConfiguracionSerialRS485()
  );

  limpiarBufferRS485();
}

// ==================================================
// INICIAR HARDWARE RS485
// ==================================================

void iniciarRS485() {
  // Unico control de energia restante: step-up del sensor.
  pinMode(SENSOR_POWER_PIN, OUTPUT);
  digitalWrite(SENSOR_POWER_PIN, SENSOR_POWER_OFF);

  aplicarConfiguracionRS485();
}

// ==================================================
// ENCENDER EL SENSOR
// ==================================================

void encenderRS485() {
  // Encender únicamente el step-up y el sensor.
  digitalWrite(SENSOR_POWER_PIN, SENSOR_POWER_ON);

  delay(RS485_STARTUP_MS);
  limpiarBufferRS485();
}

// ==================================================
// APAGAR EL SENSOR
// ==================================================

void apagarRS485() {
  delay(20);

  // Apagar únicamente el step-up y el sensor.
  digitalWrite(SENSOR_POWER_PIN, SENSOR_POWER_OFF);
}

// ==================================================
// IMPRIMIR TRAMA EN HEXADECIMAL
// ==================================================

void imprimirTramaHex(const uint8_t *datos, uint16_t longitud) {
  for (uint16_t i = 0; i < longitud; i++) {
    if (datos[i] < 0x10) {
      Serial.print("0");
    }

    Serial.print(datos[i], HEX);

    if (i + 1 < longitud) {
      Serial.print(" ");
    }
  }

  Serial.println();
}

// ==================================================
// MOTOR MODBUS UNIVERSAL
//
// Ejecuta la consulta configurada y devuelve registros crudos.
// No interpreta temperatura, humedad, presion ni unidades.
// Soporta funciones 03 y 04, de 1 a 16 registros.
// ==================================================

bool leerConsultaModbus(
  uint16_t valores[],
  uint16_t capacidad,
  uint16_t &cantidadLeida,
  uint8_t &codigoExcepcion,
  bool mostrarTramas
) {
  cantidadLeida = 0;
  codigoExcepcion = 0;

  if (
    capacidad < modbusCantidadRegistros ||
    !configuracionRS485Valida(
      rs485Baudrate,
      rs485Paridad,
      modbusSlave,
      modbusFuncion,
      modbusRegistroInicial,
      modbusCantidadRegistros
    )
  ) {
    return false;
  }

  uint8_t solicitud[8] = {
    modbusSlave,
    modbusFuncion,
    highByte(modbusRegistroInicial),
    lowByte(modbusRegistroInicial),
    highByte(modbusCantidadRegistros),
    lowByte(modbusCantidadRegistros),
    0x00,
    0x00
  };

  uint16_t crcSolicitud = calcularCRCModbus(solicitud, 6);
  solicitud[6] = lowByte(crcSolicitud);
  solicitud[7] = highByte(crcSolicitud);

  uint8_t respuesta[5 + MODBUS_MAX_REGISTROS * 2] = {0};
  uint16_t recibidos = 0;
  uint16_t longitudEsperada = 0;

  limpiarBufferRS485();

  if (mostrarTramas) {
    Serial.print("TX: ");
    imprimirTramaHex(solicitud, sizeof(solicitud));
  }

  Serial1.write(solicitud, sizeof(solicitud));
  Serial1.flush();

  uint32_t inicioLectura = millis();

  while (
    (uint32_t)(millis() - inicioLectura) < RS485_TIMEOUT_MS &&
    recibidos < sizeof(respuesta)
  ) {
    if (Serial1.available() <= 0) {
      continue;
    }

    respuesta[recibidos++] = (uint8_t)Serial1.read();

    if (
      recibidos >= 2 &&
      respuesta[1] == (uint8_t)(modbusFuncion | 0x80)
    ) {
      longitudEsperada = 5;
    } else if (recibidos >= 3) {
      uint16_t posibleLongitud = 5U + respuesta[2];

      if (posibleLongitud > sizeof(respuesta)) {
        return false;
      }

      longitudEsperada = posibleLongitud;
    }

    if (
      longitudEsperada > 0 &&
      recibidos >= longitudEsperada
    ) {
      break;
    }
  }

  if (mostrarTramas) {
    Serial.print("RX: ");

    if (recibidos > 0) {
      imprimirTramaHex(respuesta, recibidos);
    } else {
      Serial.println("(sin respuesta)");
    }
  }

  if (recibidos < 5 || respuesta[0] != modbusSlave) {
    return false;
  }

  if (respuesta[1] == (uint8_t)(modbusFuncion | 0x80)) {
    uint16_t crcCalculado = calcularCRCModbus(respuesta, 3);
    uint16_t crcRecibido =
      (uint16_t)respuesta[3] |
      ((uint16_t)respuesta[4] << 8);

    if (crcCalculado != crcRecibido) {
      return false;
    }

    codigoExcepcion = respuesta[2];
    return false;
  }

  if (respuesta[1] != modbusFuncion) {
    return false;
  }

  uint16_t bytesEsperados = modbusCantidadRegistros * 2U;
  uint16_t longitudNormal = 5U + bytesEsperados;

  if (
    respuesta[2] != bytesEsperados ||
    recibidos != longitudNormal
  ) {
    return false;
  }

  uint16_t crcCalculado =
    calcularCRCModbus(respuesta, longitudNormal - 2U);

  uint16_t crcRecibido =
    (uint16_t)respuesta[longitudNormal - 2U] |
    ((uint16_t)respuesta[longitudNormal - 1U] << 8);

  if (crcCalculado != crcRecibido) {
    return false;
  }

  for (uint16_t i = 0; i < modbusCantidadRegistros; i++) {
    uint16_t posicion = 3U + i * 2U;

    valores[i] =
      ((uint16_t)respuesta[posicion] << 8) |
      (uint16_t)respuesta[posicion + 1U];
  }

  cantidadLeida = modbusCantidadRegistros;
  return true;
}

// ==================================================
// PRUEBAS DE SENSORES CONFIGURADOS
// ==================================================

bool probarSensorModbusEnergizado(uint8_t indiceSensor) {
  if (indiceSensor >= MAX_SENSORES_MODBUS) {
    return false;
  }

  const ConfigSensorModbus &sensor =
    sensoresModbus[indiceSensor];

  Serial.println();
  Serial.print("PRUEBA SENSOR ");
  Serial.println(indiceSensor + 1);
  Serial.println("---------------------------------------------------");

  if (!sensor.activo) {
    Serial.println("Estado: INACTIVO");
    Serial.println(
      "Use ENABLESENSOR o SETSENSOR para activarlo."
    );
    Serial.println();
    return false;
  }

  if (!configuracionSensorModbusValida(sensor)) {
    Serial.println("ERROR: Configuracion del sensor no valida.");
    Serial.println();
    return false;
  }

  sincronizarContextoSensor(indiceSensor);

  Serial.print("ID esclavo         : ");
  Serial.println(sensor.slave);
  Serial.print("Funcion            : ");
  Serial.println(sensor.funcion);
  Serial.print("Registro inicial   : 0x");
  imprimirHex16(sensor.registroInicial);
  Serial.println();
  Serial.print("Cantidad registros : ");
  Serial.println(sensor.cantidadRegistros);
  Serial.println("---------------------------------------------------");

  const uint8_t MAX_INTENTOS_PRUEBA = 5;
  const uint32_t PAUSA_ENTRE_INTENTOS_MS = 700;

  uint16_t valores[MODBUS_MAX_REGISTROS] = {0};
  uint16_t cantidadLeida = 0;
  uint8_t codigoExcepcion = 0;
  bool lecturaCorrecta = false;

  for (
    uint8_t intento = 1;
    intento <= MAX_INTENTOS_PRUEBA;
    intento++
  ) {
    cantidadLeida = 0;
    codigoExcepcion = 0;

    Serial.println();
    Serial.print("Intento Modbus ");
    Serial.print(intento);
    Serial.print(" de ");
    Serial.println(MAX_INTENTOS_PRUEBA);

    lecturaCorrecta = leerConsultaModbus(
      valores,
      MODBUS_MAX_REGISTROS,
      cantidadLeida,
      codigoExcepcion,
      true
    );

    if (lecturaCorrecta) {
      break;
    }

    if (codigoExcepcion > 0) {
      break;
    }

    if (intento < MAX_INTENTOS_PRUEBA) {
      Serial.print("Sin respuesta valida. Esperando ");
      Serial.print(PAUSA_ENTRE_INTENTOS_MS);
      Serial.println(" ms...");
      delay(PAUSA_ENTRE_INTENTOS_MS);
      limpiarBufferRS485();
    }
  }

  if (!lecturaCorrecta) {
    if (codigoExcepcion > 0) {
      Serial.print("EXCEPCION MODBUS: 0x");
      if (codigoExcepcion < 0x10) Serial.print("0");
      Serial.println(codigoExcepcion, HEX);
    } else {
      Serial.println(
        "ERROR: El sensor no respondio correctamente."
      );
    }

    Serial.println();
    return false;
  }

  Serial.println();
  Serial.println("REGISTROS RECIBIDOS");

  for (uint16_t i = 0; i < cantidadLeida; i++) {
    Serial.print("[");
    Serial.print(i);
    Serial.print("] Registro 0x");

    uint16_t registroActual =
      sensor.registroInicial + i;

    imprimirHex16(registroActual);
    Serial.print(" = ");
    Serial.print(valores[i]);
    Serial.print(" (0x");
    imprimirHex16(valores[i]);
    Serial.println(")");
  }

  Serial.println();
  Serial.print("PRUEBA SENSOR ");
  Serial.print(indiceSensor + 1);
  Serial.println(" OK");
  Serial.println();
  return true;
}

void probarSensorModbus(uint8_t indiceSensor) {
  if (indiceSensor >= MAX_SENSORES_MODBUS) {
    Serial.println("ERROR: Ranura de sensor invalida.");
    return;
  }

  Serial.println();
  Serial.println("Encendiendo bus de sensores...");
  encenderRS485();
  Serial.println("Sensores energizados.");

  probarSensorModbusEnergizado(indiceSensor);

  apagarRS485();
  Serial.println("Sensores apagados.");
  Serial.println();

  sincronizarContextoSensor(0);
}

void probarTodosSensoresModbus() {
  Serial.println();
  Serial.println("PRUEBA DE TODOS LOS SENSORES ACTIVOS");
  Serial.println("===================================================");

  uint8_t activos = 0;
  uint8_t correctos = 0;

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    if (sensoresModbus[i].activo) {
      activos++;
    }
  }

  if (activos == 0) {
    Serial.println("No hay sensores activos.");
    Serial.println();
    return;
  }

  encenderRS485();

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    if (!sensoresModbus[i].activo) {
      continue;
    }

    if (probarSensorModbusEnergizado(i)) {
      correctos++;
    }

    delay(150);
    limpiarBufferRS485();
  }

  apagarRS485();
  sincronizarContextoSensor(0);

  Serial.println("===================================================");
  Serial.print("Resultado: ");
  Serial.print(correctos);
  Serial.print(" de ");
  Serial.print(activos);
  Serial.println(" sensores respondieron correctamente.");
  Serial.println();
}

// Comando compatible con la versión anterior.
void probarConsultaModbus() {
  probarSensorModbus(0);
}

// ==================================================
// COMPATIBILIDAD CON EL FLUJO ACTUAL
//
// Funcion conservada por compatibilidad interna.
// El flujo principal multirregistro usa leerConsultaModbus().
// ==================================================

bool leerRegistroSensorRS485(uint16_t &valorSensor) {
  if (modbusCantidadRegistros != 1) {
    return false;
  }

  uint16_t valores[MODBUS_MAX_REGISTROS] = {0};
  uint16_t cantidadLeida = 0;
  uint8_t codigoExcepcion = 0;

  if (!leerConsultaModbus(
        valores,
        MODBUS_MAX_REGISTROS,
        cantidadLeida,
        codigoExcepcion,
        false
      )) {
    return false;
  }

  if (cantidadLeida != 1) {
    return false;
  }

  valorSensor = valores[0];
  return true;
}

// ==================================================
// OBTENER LA MODA DE LAS LECTURAS
//
// Devuelve el valor exacto que aparece mas veces.
// Si hay empate, conserva el que aparecio primero.
// ==================================================

uint16_t obtenerModaSensor(
  const uint16_t lecturas[],
  uint8_t cantidad,
  uint8_t &repeticiones
) {
  uint16_t moda = lecturas[0];
  uint8_t frecuenciaMayor = 0;

  for (uint8_t i = 0; i < cantidad; i++) {
    uint8_t frecuenciaActual = 0;

    for (uint8_t j = 0; j < cantidad; j++) {
      if (lecturas[j] == lecturas[i]) {
        frecuenciaActual++;
      }
    }

    if (frecuenciaActual > frecuenciaMayor) {
      frecuenciaMayor = frecuenciaActual;
      moda = lecturas[i];
    }
  }

  repeticiones = frecuenciaMayor;
  return moda;
}

// ==================================================
// CONVERTIR IPAddress A TEXTO
// ==================================================

String convertirIPAddressATexto(const IPAddress &ip) {
  return String(ip[0]) + "." +
         String(ip[1]) + "." +
         String(ip[2]) + "." +
         String(ip[3]);
}

// ==================================================
// CONSTRUIR URL DE LA API
//
// urlConector puede contener:
//
// 1. Una URL completa HTTPS:
//    https://rs485.cdtechnologia.net/api/v1/mediciones
//
// 2. Una URL completa HTTP.
//
// 3. Solamente una ruta local. En ese caso se combina con la
//    IP y el puerto almacenados en EEPROM.
//
// Para produccion se recomienda guardar siempre la URL HTTPS
// completa. Asi no se utiliza serverIP ni puertoHTTP para
// construir el destino.
// ==================================================

String construirURLAPI() {
  String url = urlConector;
  url.trim();

  if (
    !url.startsWith("http://") &&
    !url.startsWith("https://")
  ) {
    if (!direccionIPValida(serverIP)) {
      return "";
    }

    if (!url.startsWith("/")) {
      url = String("/") + url;
    }

    String base = String("http://") +
                  convertirIPAddressATexto(serverIP);

    if (puertoHTTP != 80) {
      base += String(":") + String(puertoHTTP);
    }

    url = base + url;
  }

  // El endpoint POST no necesita los terminadores usados por
  // el antiguo envío GET.
  while (url.endsWith("?") || url.endsWith("&")) {
    url.remove(url.length() - 1);
  }

  return url;
}

// ==================================================
// CONSTRUIR JSON PARA HASTA CUATRO SENSORES
//
// Se conserva "valor" y "modbus" con el primer sensor válido
// para compatibilidad con la API anterior.
//
// La API nueva utiliza:
//   rs485
//   sensores[]
// ==================================================

String construirCuerpoMedicionJSON() {
  if (
    !ultimaLecturaValida ||
    cantidadResultadosValidos == 0
  ) {
    return "";
  }

  int8_t primerIndiceValido = -1;

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    if (resultadosSensores[i].valido) {
      primerIndiceValido = (int8_t)i;
      break;
    }
  }

  if (primerIndiceValido < 0) {
    return "";
  }

  const ResultadoSensorModbus &primero =
    resultadosSensores[(uint8_t)primerIndiceValido];

  String cuerpo;
  cuerpo.reserve(1600);

  cuerpo += "{\"valor\":";
  cuerpo += String(primero.valores[0]);

  // Configuración física compartida.
  cuerpo += ",\"rs485\":{";
  cuerpo += "\"baudrate\":";
  cuerpo += String(rs485Baudrate);
  cuerpo += ",\"paridad\":\"";
  cuerpo += rs485Paridad;
  cuerpo += "\"}";

  // Bloque compatible con la API multirregistro anterior.
  cuerpo += ",\"modbus\":{";
  cuerpo += "\"baudrate\":";
  cuerpo += String(rs485Baudrate);
  cuerpo += ",\"paridad\":\"";
  cuerpo += rs485Paridad;
  cuerpo += "\"";
  cuerpo += ",\"slave\":";
  cuerpo += String(primero.slave);
  cuerpo += ",\"funcion\":";
  cuerpo += String(primero.funcion);
  cuerpo += ",\"registro_inicial\":";
  cuerpo += String(primero.registroInicial);
  cuerpo += ",\"cantidad\":";
  cuerpo += String(primero.cantidadRegistros);
  cuerpo += ",\"registros\":[";

  for (
    uint16_t i = 0;
    i < primero.cantidadRegistros;
    i++
  ) {
    if (i > 0) {
      cuerpo += ",";
    }

    cuerpo += String(primero.valores[i]);
  }

  cuerpo += "]}";

  // Bloque completo para cuatro sensores.
  cuerpo += ",\"sensores\":[";

  bool primeroEnArreglo = true;

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    const ResultadoSensorModbus &resultado =
      resultadosSensores[i];

    if (!resultado.valido) {
      continue;
    }

    if (!primeroEnArreglo) {
      cuerpo += ",";
    }

    primeroEnArreglo = false;

    cuerpo += "{";
    cuerpo += "\"ranura\":";
    cuerpo += String(resultado.ranura);
    cuerpo += ",\"slave\":";
    cuerpo += String(resultado.slave);
    cuerpo += ",\"funcion\":";
    cuerpo += String(resultado.funcion);
    cuerpo += ",\"registro_inicial\":";
    cuerpo += String(resultado.registroInicial);
    cuerpo += ",\"cantidad\":";
    cuerpo += String(resultado.cantidadRegistros);
    cuerpo += ",\"registros\":[";

    for (
      uint16_t registro = 0;
      registro < resultado.cantidadRegistros;
      registro++
    ) {
      if (registro > 0) {
        cuerpo += ",";
      }

      cuerpo += String(resultado.valores[registro]);
    }

    cuerpo += "]}";
  }

  cuerpo += "]}";
  return cuerpo;
}

// ==================================================
// ENVIAR MEDICION A LARAVEL MEDIANTE HTTP POST
//
// Ejemplo:
// {
//   "valor": 632,
//   "modbus": {
//     "baudrate": 9600,
//     "paridad": "N",
//     "slave": 1,
//     "funcion": 3,
//     "registro_inicial": 0,
//     "cantidad": 2,
//     "registros": [632, 247]
//   }
// }
// ==================================================

int enviarMedicionHTTP() {
  if (!ultimaLecturaValida) {
    return -100;
  }

  if (!ethernet.connected()) {
    return -101;
  }

  if (urlConector.length() == 0) {
    return -102;
  }

  if (!credencialesConfiguradas()) {
    Serial.println("ERROR: ID o token no configurados.");
    return -105;
  }

  String url = construirURLAPI();

  if (url.length() == 0) {
    return -103;
  }

  String cuerpo = construirCuerpoMedicionJSON();

  if (cuerpo.length() == 0) {
    Serial.println("ERROR: No se pudo construir el JSON.");
    return -106;
  }

  Serial.println("Enviando medicion multisensor a la API...");
  Serial.print("POST: ");
  Serial.println(url);
  Serial.print("Dispositivo: ");
  Serial.println(idDevice);
  Serial.print("JSON: ");
  Serial.println(cuerpo);

  HTTPClient http;
  http.setTimeout(HTTP_TIMEOUT_MS);

  bool conexionHTTPS = url.startsWith("https://");

  if (conexionHTTPS) {
    if (HTTPS_USAR_SET_INSECURE) {
      // Necesario mientras no se cargue la CA raiz.
      // Debe ejecutarse antes de http.begin().
      http.setInsecure();

      Serial.println(
        "TLS: HTTPS activo, certificado remoto no verificado."
      );
    }
  }

  if (!http.begin(url)) {
    Serial.println("Error al iniciar HTTP/HTTPS");
    return -104;
  }

  http.addHeader("Accept", "application/json");
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-ID", idDevice);
  http.addHeader("X-Device-Token", deviceToken);

  int codigoHTTP = http.POST(cuerpo);

  Serial.print("Codigo HTTP: ");
  Serial.println(codigoHTTP);

  String respuestaServidor = http.getString();

  if (respuestaServidor.length() > 0) {
    Serial.print("Respuesta: ");
    Serial.println(respuestaServidor);
  }

  http.end();
  return codigoHTTP;
}

// ==================================================
// DETERMINAR SI UN ERROR HTTP ES TEMPORAL
//
// Se reintenta cuando:
// - No se pudo establecer la conexion: codigo negativo.
// - El servidor indico espera o saturacion: 408 o 429.
// - El servidor tuvo un error temporal: 500 a 599.
//
// No se reintentan errores de autenticacion, permisos,
// ruta o validacion, porque repetirlos no los corrige.
// ==================================================

bool codigoHTTPReintentable(int codigoHTTP) {
  if (codigoHTTP < 0) {
    return true;
  }

  if (codigoHTTP == 408 || codigoHTTP == 429) {
    return true;
  }

  return codigoHTTP >= 500 && codigoHTTP <= 599;
}

// ==================================================
// ENVIAR MEDICION CON REINTENTOS EN RAM
//
// Intento 1: inmediato.
// Intento 2: despues de 3 segundos.
// Intento 3: despues de 10 segundos.
//
// No escribe la medicion en EEPROM.
// ==================================================

int enviarMedicionConReintentos() {
  const uint32_t pausas[HTTP_MAX_INTENTOS_ENVIO] = {
    0,
    HTTP_PAUSA_REINTENTO_2_MS,
    HTTP_PAUSA_REINTENTO_3_MS
  };

  int ultimoCodigoHTTP = -1;

  for (
    uint8_t intento = 0;
    intento < HTTP_MAX_INTENTOS_ENVIO;
    intento++
  ) {
    if (pausas[intento] > 0) {
      Serial.print("Esperando ");
      Serial.print(pausas[intento] / 1000UL);
      Serial.println(" segundo(s) antes de reintentar...");
      delay(pausas[intento]);
    }

    Serial.println();
    Serial.print("Intento de envio ");
    Serial.print(intento + 1);
    Serial.print(" de ");
    Serial.println(HTTP_MAX_INTENTOS_ENVIO);

    ultimoCodigoHTTP = enviarMedicionHTTP();

    if (ultimoCodigoHTTP >= 200 && ultimoCodigoHTTP < 300) {
      return ultimoCodigoHTTP;
    }

    if (!codigoHTTPReintentable(ultimoCodigoHTTP)) {
      Serial.println(
        "Error no reintentable. Se cancela el envio."
      );
      return ultimoCodigoHTTP;
    }

    if (intento + 1 < HTTP_MAX_INTENTOS_ENVIO) {
      Serial.println(
        "Fallo temporal. Se realizara otro intento."
      );
    }
  }

  Serial.println(
    "No se pudo enviar la medicion despues de 3 intentos."
  );
  Serial.println(
    "La medicion no se guardo en EEPROM."
  );

  return ultimoCodigoHTTP;
}

// ==================================================
// ADQUIRIR DIEZ MUESTRAS DE UN SENSOR
// ==================================================

bool adquirirSensorModbus(
  uint8_t indiceSensor,
  ResultadoSensorModbus &resultado
) {
  resultado.valido = false;

  if (
    indiceSensor >= MAX_SENSORES_MODBUS ||
    !sensoresModbus[indiceSensor].activo ||
    !configuracionSensorModbusValida(
      sensoresModbus[indiceSensor]
    )
  ) {
    return false;
  }

  sincronizarContextoSensor(indiceSensor);

  const ConfigSensorModbus &sensor =
    sensoresModbus[indiceSensor];

  uint16_t muestras[
    SENSOR_NUM_MUESTRAS
  ][
    MODBUS_MAX_REGISTROS
  ] = {{0}};

  uint8_t muestrasValidas = 0;
  uint8_t intentos = 0;

  Serial.println();
  Serial.print("SENSOR ");
  Serial.print(indiceSensor + 1);
  Serial.print(" | ID ");
  Serial.print(sensor.slave);
  Serial.print(" | F");
  Serial.print(sensor.funcion);
  Serial.print(" | Registro 0x");
  imprimirHex16(sensor.registroInicial);
  Serial.print(" | Cantidad ");
  Serial.println(sensor.cantidadRegistros);
  Serial.println("---------------------------------------------------");

  while (
    muestrasValidas < SENSOR_NUM_MUESTRAS &&
    intentos < SENSOR_MAX_INTENTOS
  ) {
    intentos++;

    uint16_t valoresActuales[MODBUS_MAX_REGISTROS] = {0};
    uint16_t cantidadLeida = 0;
    uint8_t codigoExcepcion = 0;

    bool lecturaCorrecta = leerConsultaModbus(
      valoresActuales,
      MODBUS_MAX_REGISTROS,
      cantidadLeida,
      codigoExcepcion,
      false
    );

    if (
      lecturaCorrecta &&
      cantidadLeida == sensor.cantidadRegistros
    ) {
      for (
        uint16_t registro = 0;
        registro < cantidadLeida;
        registro++
      ) {
        muestras[muestrasValidas][registro] =
          valoresActuales[registro];
      }

      muestrasValidas++;

      Serial.print("Muestra ");
      Serial.print(muestrasValidas);
      Serial.print(": [");

      for (
        uint16_t registro = 0;
        registro < cantidadLeida;
        registro++
      ) {
        if (registro > 0) {
          Serial.print(", ");
        }

        Serial.print(valoresActuales[registro]);
      }

      Serial.println("]");
    } else {
      Serial.print("Intento ");
      Serial.print(intentos);
      Serial.print(": lectura no valida");

      if (codigoExcepcion > 0) {
        Serial.print(" - excepcion 0x");
        if (codigoExcepcion < 0x10) Serial.print("0");
        Serial.print(codigoExcepcion, HEX);
      }

      Serial.println();
    }

    if (
      muestrasValidas < SENSOR_NUM_MUESTRAS &&
      intentos < SENSOR_MAX_INTENTOS
    ) {
      delay(SENSOR_PAUSA_MUESTRAS_MS);
    }
  }

  if (muestrasValidas != SENSOR_NUM_MUESTRAS) {
    Serial.print("ERROR SENSOR ");
    Serial.print(indiceSensor + 1);
    Serial.print(": solo ");
    Serial.print(muestrasValidas);
    Serial.print(" de ");
    Serial.print(SENSOR_NUM_MUESTRAS);
    Serial.println(" muestras validas.");
    return false;
  }

  resultado.valido = true;
  resultado.ranura = indiceSensor + 1;
  resultado.slave = sensor.slave;
  resultado.funcion = sensor.funcion;
  resultado.registroInicial = sensor.registroInicial;
  resultado.cantidadRegistros = sensor.cantidadRegistros;

  Serial.println("RESULTADOS FINALES");

  for (
    uint16_t registro = 0;
    registro < sensor.cantidadRegistros;
    registro++
  ) {
    uint16_t lecturasRegistro[SENSOR_NUM_MUESTRAS] = {0};

    for (
      uint8_t muestra = 0;
      muestra < SENSOR_NUM_MUESTRAS;
      muestra++
    ) {
      lecturasRegistro[muestra] =
        muestras[muestra][registro];
    }

    uint8_t repeticiones = 0;

    resultado.valores[registro] = obtenerModaSensor(
      lecturasRegistro,
      SENSOR_NUM_MUESTRAS,
      repeticiones
    );

    resultado.repeticiones[registro] = repeticiones;

    Serial.print("Registro 0x");
    imprimirHex16(sensor.registroInicial + registro);
    Serial.print(" = ");
    Serial.print(resultado.valores[registro]);
    Serial.print(" | repeticiones: ");
    Serial.print(repeticiones);
    Serial.print(" de ");
    Serial.println(SENSOR_NUM_MUESTRAS);

    if (repeticiones == 1) {
      Serial.println(
        "  Aviso: todos fueron diferentes; "
        "se conservo la primera lectura."
      );
    }
  }

  Serial.println();
  return true;
}

// ==================================================
// LEER TODOS LOS SENSORES Y ENVIAR UNA SOLA MEDICION
// Comando: <ID>sensorZ
// ==================================================

void procesarComandoSensor() {
  ultimoTiempoLecturaSensorMs = millis();
  ultimaLecturaValida = false;
  ultimaCantidadRegistros = 0;
  cantidadResultadosValidos = 0;

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    resultadosSensores[i].valido = false;
  }

  uint8_t sensoresActivos = 0;

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    if (sensoresModbus[i].activo) {
      sensoresActivos++;
    }
  }

  Serial.println();
  Serial.println("LECTURA DE SENSORES RS485");
  Serial.println("===================================================");
  Serial.print("Sensores activos: ");
  Serial.println(sensoresActivos);

  if (sensoresActivos == 0) {
    Serial.println("ERROR: No hay sensores activos.");
    Serial.println();
    return;
  }

  encenderRS485();

  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    if (!sensoresModbus[i].activo) {
      continue;
    }

    if (adquirirSensorModbus(i, resultadosSensores[i])) {
      cantidadResultadosValidos++;
    }

    delay(150);
    limpiarBufferRS485();
  }

  apagarRS485();
  sincronizarContextoSensor(0);

  Serial.println("===================================================");
  Serial.print("Sensores leidos correctamente: ");
  Serial.print(cantidadResultadosValidos);
  Serial.print(" de ");
  Serial.println(sensoresActivos);

  if (cantidadResultadosValidos == 0) {
    Serial.println(
      "ERROR: Ningun sensor produjo una medicion valida."
    );
    Serial.println();
    return;
  }

  // Mantener compatibilidad con el resultado de un sensor.
  for (
    uint8_t i = 0;
    i < MAX_SENSORES_MODBUS;
    i++
  ) {
    if (!resultadosSensores[i].valido) {
      continue;
    }

    ultimaCantidadRegistros =
      resultadosSensores[i].cantidadRegistros;
    ultimaLecturaSensor =
      resultadosSensores[i].valores[0];

    for (
      uint16_t registro = 0;
      registro < ultimaCantidadRegistros;
      registro++
    ) {
      ultimosRegistros[registro] =
        resultadosSensores[i].valores[registro];
      ultimasRepeticiones[registro] =
        resultadosSensores[i].repeticiones[registro];
    }

    break;
  }

  ultimaLecturaValida = true;

  Serial.println();
  int codigoHTTP = enviarMedicionConReintentos();

  if (codigoHTTP >= 200 && codigoHTTP < 300) {
    Serial.println("ENVIO MULTISENSOR OK");
  } else {
    Serial.print("HTTP ERROR: ");
    Serial.println(codigoHTTP);
  }

  Serial.println();
}

// ==================================================
// COMPROBAR SI UNA Z CIERRA UN COMANDO CONOCIDO
// ==================================================

bool esFinalComandoZ(String comando) {
  if (idDevice.length() == 0 || comando.length() <= idDevice.length()) {
    return false;
  }

  char final = comando.charAt(comando.length() - 1);

  if (final != 'Z' && final != 'z') {
    return false;
  }

  String sinZ = comando.substring(0, comando.length() - 1);

  if (!sinZ.startsWith(idDevice)) {
    return false;
  }

  String comandoDHCP = idDevice + "dhcp";
  String comandoConfig = idDevice + "config";
  String comandoSensor = idDevice + "sensor";
  String comandoReset = idDevice + "reset";

  if (
    sinZ.equalsIgnoreCase(comandoDHCP) ||
    sinZ.equalsIgnoreCase(comandoConfig) ||
    sinZ.equalsIgnoreCase(comandoSensor) ||
    sinZ.equalsIgnoreCase(comandoReset)
  ) {
    return true;
  }

  String contenido = sinZ.substring(idDevice.length());

  if (textoSoloNumeros(contenido)) {
    return true;
  }

  if (contenido.startsWith("_")) {
    return textoPareceIPMascara(contenido.substring(1));
  }

  return textoPareceIPMascara(contenido);
}

// ==================================================
// COMPROBAR SI UNA M CIERRA EL COMANDO DE TIEMPO
// ==================================================

bool esFinalComandoTiempo(String comando) {
  if (
    idDevice.length() == 0 ||
    comando.length() <= idDevice.length() + 1
  ) {
    return false;
  }

  char final = comando.charAt(comando.length() - 1);

  if (final != 'm' && final != 'M') {
    return false;
  }

  String sinM = comando.substring(0, comando.length() - 1);

  if (!sinM.startsWith(idDevice)) {
    return false;
  }

  String contenido = sinM.substring(idDevice.length());
  return textoSoloNumeros(contenido);
}

// ==================================================
// PROCESAR COMANDO
// ==================================================

void procesarComando(String comando) {
  comando.trim();

  if (procesarComandoAprovisionamiento(comando)) {
    return;
  }

  if (idDevice.length() == 0) {
    Serial.println("ERROR: Configure primero el ID con SETID.");
    return;
  }

  String comandoConfig = idDevice + "config";

  // Este comando no escribe ni modifica la EEPROM.
  // Solamente vuelve a leer y mostrar la configuracion.
  if (comando.equalsIgnoreCase(comandoConfig)) {
    mostrarConfiguracionEEPROM();
    return;
  }

  if (comando.length() < 2) {
    Serial.println("ERROR: Comando vacio.");
    return;
  }

  char terminador = comando.charAt(comando.length() - 1);

  // ------------------------------------------------
  // URL DE LA API: termina con !
  // ------------------------------------------------

  if (terminador == '!') {
    String comandoSinExclamacion =
      comando.substring(0, comando.length() - 1);

    if (!comandoSinExclamacion.startsWith(idDevice)) {
      Serial.println("ERROR: ID incorrecto.");
      return;
    }

    procesarComandoURL(comandoSinExclamacion);
    return;
  }

  // ------------------------------------------------
  // TIEMPO DE ENVIO: termina con m
  // ------------------------------------------------

  if (terminador == 'm' || terminador == 'M') {
    String comandoSinM =
      comando.substring(0, comando.length() - 1);

    if (!comandoSinM.startsWith(idDevice)) {
      Serial.println("ERROR: ID incorrecto.");
      return;
    }

    procesarComandoTiempoEnvio(comandoSinM);
    return;
  }

  // ------------------------------------------------
  // COMANDOS EXISTENTES Y PUERTO HTTP: terminan con Z
  // ------------------------------------------------

  if (terminador != 'Z' && terminador != 'z') {
    Serial.println("ERROR: Terminador de comando incorrecto.");
    return;
  }

  String comandoSinZ =
    comando.substring(0, comando.length() - 1);

  if (!comandoSinZ.startsWith(idDevice)) {
    Serial.println("ERROR: ID incorrecto.");
    return;
  }

  String comandoDHCP = idDevice + "dhcp";
  String comandoConfigZ = idDevice + "config";
  String comandoSensor = idDevice + "sensor";
  String comandoReset = idDevice + "reset";

  if (comandoSinZ.equalsIgnoreCase(comandoReset)) {
    reiniciarPlaca();
    return;
  }

  if (comandoSinZ.equalsIgnoreCase(comandoDHCP)) {
    procesarComandoDHCP();
    return;
  }

  // Variante opcional compatible con los comandos terminados en Z.
  if (comandoSinZ.equalsIgnoreCase(comandoConfigZ)) {
    mostrarConfiguracionEEPROM();
    return;
  }

  if (comandoSinZ.equalsIgnoreCase(comandoSensor)) {
    procesarComandoSensor();
    return;
  }

  String prefijoServidor = idDevice + "_";

  if (comandoSinZ.startsWith(prefijoServidor)) {
    procesarComandoServidor(comandoSinZ);
    return;
  }

  String contenido =
    comandoSinZ.substring(idDevice.length());

  // Un contenido formado solamente por numeros se interpreta
  // como puerto HTTP.
  if (textoSoloNumeros(contenido)) {
    procesarComandoPuertoHTTP(comandoSinZ);
    return;
  }

  // Se conserva exactamente el comportamiento anterior:
  // si no es DHCP, servidor ni puerto, se procesa como IP local.
  procesarComandoIPLocal(comandoSinZ);
}

// ==================================================
// LEER COMANDO DESDE EL MONITOR SERIAL
// ==================================================

void recibirComandoSerial() {
  while (Serial.available()) {
    char caracter = Serial.read();

    if (caracter == '\r' || caracter == '\n') {
      bufferComando.trim();

      if (bufferComando.length() > 0) {
        procesarComando(bufferComando);
        bufferComando = "";
      }

      continue;
    }

    if (bufferComando.length() >= 220) {
      bufferComando = "";
      Serial.println("ERROR: Comando demasiado largo.");
      return;
    }

    bufferComando += caracter;

    // SETID, SETTOKEN y SHOWAUTH solamente se procesan al
    // presionar Enter. Así el token no se corta si contiene z o m.
    if (esPrefijoAprovisionamiento(bufferComando)) {
      continue;
    }

    bool comandoCompleto = false;

    if (caracter == '!') {
      comandoCompleto = true;
    } else if (
      (caracter == 'm' || caracter == 'M') &&
      esFinalComandoTiempo(bufferComando)
    ) {
      comandoCompleto = true;
    } else if (
      (caracter == 'Z' || caracter == 'z') &&
      esFinalComandoZ(bufferComando)
    ) {
      comandoCompleto = true;
    }

    if (comandoCompleto) {
      procesarComando(bufferComando);
      bufferComando = "";
    }
  }
}

// ==================================================
// SETUP
// ==================================================

void setup() {
  Serial.begin(9600);
  delay(2000);

  // ==================================================
  // ENCABEZADO
  // ==================================================

  Serial.println();
  Serial.println("===================================================");
  Serial.println(" CIRCUITOS Y DESARROLLOS EN TECNOLOGIA S. DE R.L.");
  Serial.println(" Sistema Controlador de Sensores RS485");
  Serial.println("===================================================");

  // ==================================================
  // INICIAR SENSOR Y UART RS485
  // ==================================================

  iniciarRS485();

  // ==================================================
  // INICIAR EEPROM
  // ==================================================

  Wire.setSDA(EEPROM_SDA);
  Wire.setSCL(EEPROM_SCL);
  Wire.begin();
  Wire.setClock(100000);
  delay(EEPROM_STARTUP_DELAY_MS);

  if (!comprobarEEPROM()) {
    Serial.println(" ERROR: No se encontro la memoria de configuracion.");
    Serial.println("===================================================");

    while (true) {
      delay(1000);
    }
  }

  // ==================================================
  // CARGAR CONFIGURACION DEL SERVIDOR
  // ==================================================

  bool servidorConfigurado =
    leerConfiguracionServidor(serverIP, serverMask) &&
    configuracionValida(serverIP, serverMask);

  if (!servidorConfigurado) {
    serverIP = IPAddress(0, 0, 0, 0);
    serverMask = IPAddress(0, 0, 0, 0);
  }

  // ==================================================
  // CARGAR CONFIGURACION DE RED LOCAL
  // ==================================================

  bool redLeida = leerConfiguracionRed(
    modoRed,
    ipLocal,
    mascaraLocal
  );

  if (
    !redLeida ||
    (
      modoRed == MODO_ESTATICO &&
      !configuracionValida(ipLocal, mascaraLocal)
    )
  ) {
    modoRed = MODO_DHCP;
    ipLocal = IPAddress(0, 0, 0, 0);
    mascaraLocal = IPAddress(0, 0, 0, 0);
  }

  // ==================================================
  // CARGAR CONFIGURACIONES ADICIONALES
  // ==================================================

  leerConfiguracionesAdicionales();

  // ==================================================
  // CARGAR Y APLICAR CONFIGURACION RS485 / MODBUS
  // ==================================================

  leerConfiguracionRS485EEPROM();
  aplicarConfiguracionRS485();

  // ==================================================
  // INICIAR ETHERNET
  // ==================================================

  SPI.setRX(W5500_MISO);
  SPI.setCS(W5500_CS);
  SPI.setSCK(W5500_SCK);
  SPI.setTX(W5500_MOSI);
  SPI.begin();

  reiniciarW5500();

  if (modoRed == MODO_ESTATICO) {
    ethernet.config(
      ipLocal,
      IPAddress(0, 0, 0, 0),
      mascaraLocal,
      IPAddress(0, 0, 0, 0)
    );
  }

  if (!ethernet.begin()) {
    Serial.println(" ERROR: No se pudo iniciar el controlador Ethernet.");
    Serial.println("===================================================");

    while (true) {
      delay(1000);
    }
  }

  unsigned long inicioConexion = millis();

  while (
    !ethernet.connected() &&
    millis() - inicioConexion < 20000
  ) {
    delay(250);
  }

  // ==================================================
  // MOSTRAR CONFIGURACION ACTUAL
  // ==================================================

  Serial.print(" ID del dispositivo : ");
  if (idDevice.length() > 0) {
    Serial.println(idDevice);
  } else {
    Serial.println("NO CONFIGURADO");
  }

  Serial.print(" Token              : ");
  Serial.println(tokenOculto(deviceToken));
  Serial.println("---------------------------------------------------");

  Serial.print(" Modo de red        : ");

  if (modoRed == MODO_ESTATICO) {
    Serial.println("IP ESTATICA");
  } else {
    Serial.println("DHCP");
  }

  if (ethernet.connected()) {
    Serial.print(" IP local           : ");
    Serial.println(ethernet.localIP());

    Serial.print(" Mascara local      : ");
    Serial.println(ethernet.subnetMask());
  } else {
    Serial.println(" IP local           : No disponible");
    Serial.println(" Mascara local      : No disponible");
  }

  Serial.println("---------------------------------------------------");

  if (servidorConfigurado) {
    Serial.print(" IP del servidor    : ");
    Serial.println(serverIP);

    Serial.print(" Mascara servidor   : ");
    Serial.println(serverMask);
  } else {
    Serial.println(" IP del servidor    : No configurada");
    Serial.println(" Mascara servidor   : No configurada");
  }

  Serial.println("---------------------------------------------------");

  Serial.print(" Puerto HTTP        : ");
  Serial.println(puertoHTTP);

  Serial.print(" Tiempo de envio    : ");
  Serial.print(tiempoEnvioMinutos);
  Serial.println(" minuto(s)");

  Serial.print(" URL de la API      : ");

  if (urlConector.length() > 0) {
    Serial.println(urlConector);
  } else {
    Serial.println("No configurada");
  }

  Serial.print(" Protocolo API      : ");
  Serial.println(
    urlConector.startsWith("https://")
      ? "HTTPS"
      : "HTTP"
  );

  Serial.print(" Puerto efectivo    : ");
  Serial.println(
    urlConector.startsWith("https://")
      ? 443
      : puertoHTTP
  );

  Serial.println("---------------------------------------------------");

  mostrarConfiguracionModbus();

  Serial.print(" Estado Ethernet    : ");

  if (ethernet.connected()) {
    Serial.println(" SISTEMA LISTO");
  } else {
    Serial.println(" REVISA EL CABLE O LA CONFIGURACION DE RED");
  }

  // La primera lectura automatica se realizara cuando transcurra
  // el tiempo recuperado desde la EEPROM.
  ultimoTiempoLecturaSensorMs = millis();
}

void loop() {
  // Los comandos manuales siguen disponibles en todo momento.
  recibirComandoSerial();

  // Convertir el intervalo guardado en EEPROM de minutos a ms.
  uint32_t intervaloLecturaMs =
    (uint32_t)tiempoEnvioMinutos * 60000UL;

  // La resta con millis() sigue funcionando cuando el contador
  // de milisegundos se desborda.
  if (tiempoEnvioMinutos > 0 &&
      (uint32_t)(millis() - ultimoTiempoLecturaSensorMs) >=
        intervaloLecturaMs) {
    procesarComandoSensor();
  }
}