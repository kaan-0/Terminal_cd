using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Ports;
using System.Linq;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using CDTerminal.Models;

namespace CDTerminal.Services;

public sealed class SerialPortService : ISerialPortService
{
    private const int IntervaloMonitorConexionMs = 750;
    private const int IntervaloVerificacionTransaccionMs = 150;

    private readonly SemaphoreSlim _bloqueoTransaccion = new(1, 1);
    private readonly object _bloqueoLectura = new();
    private readonly object _bloqueoEstado = new();

    private SerialPort? _puertoSerial;
    private CancellationTokenSource? _conexionCancellation;
    private Timer? _monitorConexion;
    private volatile bool _transaccionBinariaEnCurso;
    private int _conexionPerdidaNotificada;
    private bool _disposed;

    public bool EstaConectado
    {
        get
        {
            lock (_bloqueoEstado)
            {
                return PuertoAbiertoSeguro(_puertoSerial) &&
                       _conexionCancellation?.IsCancellationRequested != true;
            }
        }
    }

    public string? PuertoActual
    {
        get
        {
            lock (_bloqueoEstado)
            {
                return PuertoAbiertoSeguro(_puertoSerial)
                    ? _puertoSerial?.PortName
                    : null;
            }
        }
    }

    public event EventHandler<string>? DatosRecibidos;

    public event EventHandler<string>? ErrorOcurrido;

    public event EventHandler<string>? ConexionPerdida;

    public IReadOnlyList<string> ObtenerPuertosDisponibles()
    {
        ThrowIfDisposed();

        try
        {
            return SerialPort
                .GetPortNames()
                .OrderBy(ObtenerNumeroPuerto)
                .ToArray();
        }
        catch (Exception ex)
        {
            throw new IOException(
                $"No fue posible consultar los puertos COM: {ex.Message}",
                ex
            );
        }
    }

    public void Conectar(ConfiguracionSerial configuracion)
    {
        ThrowIfDisposed();
        ArgumentNullException.ThrowIfNull(configuracion);

        if (string.IsNullOrWhiteSpace(configuracion.NombrePuerto))
        {
            throw new ArgumentException(
                "Debes seleccionar un puerto COM.",
                nameof(configuracion)
            );
        }

        lock (_bloqueoEstado)
        {
            if (PuertoAbiertoSeguro(_puertoSerial))
            {
                throw new InvalidOperationException(
                    $"Ya existe una conexión activa en {_puertoSerial?.PortName}."
                );
            }
        }

        if (!PuertoExiste(configuracion.NombrePuerto))
        {
            throw new IOException(
                $"El puerto {configuracion.NombrePuerto} no está disponible. " +
                "Actualiza la lista de puertos y vuelve a intentarlo."
            );
        }

        SerialPort nuevoPuerto = new()
        {
            PortName = configuracion.NombrePuerto,
            BaudRate = configuracion.Velocidad,
            DataBits = configuracion.BitsDeDatos,
            Parity = configuracion.Paridad,
            StopBits = configuracion.BitsDeParada,
            Handshake = configuracion.ControlDeFlujo,
            Encoding = Encoding.ASCII,
            ReceivedBytesThreshold = 1,
            DtrEnable = configuracion.HabilitarDtr,
            RtsEnable = configuracion.HabilitarRts,
            ReadTimeout = 1000,
            WriteTimeout = 1000
        };

        CancellationTokenSource conexionCancellation = new();

        try
        {
            nuevoPuerto.DataReceived += PuertoSerial_DataReceived;
            nuevoPuerto.ErrorReceived += PuertoSerial_ErrorReceived;
            nuevoPuerto.Open();

            lock (_bloqueoEstado)
            {
                _puertoSerial = nuevoPuerto;
                _conexionCancellation = conexionCancellation;
                Interlocked.Exchange(
                    ref _conexionPerdidaNotificada,
                    0
                );

                _monitorConexion = new Timer(
                    MonitorearConexion,
                    nuevoPuerto,
                    IntervaloMonitorConexionMs,
                    IntervaloMonitorConexionMs
                );
            }
        }
        catch
        {
            nuevoPuerto.DataReceived -= PuertoSerial_DataReceived;
            nuevoPuerto.ErrorReceived -= PuertoSerial_ErrorReceived;

            try
            {
                if (nuevoPuerto.IsOpen)
                {
                    nuevoPuerto.Close();
                }
            }
            catch
            {
                // La limpieza no debe ocultar el error original.
            }

            nuevoPuerto.Dispose();
            conexionCancellation.Dispose();
            throw;
        }
    }

    public void EnviarTexto(string texto)
    {
        ThrowIfDisposed();

        if (string.IsNullOrEmpty(texto))
        {
            return;
        }

        (SerialPort puerto, _) = ObtenerConexionActiva();

        try
        {
            puerto.Write(texto);
        }
        catch (Exception ex) when (EsErrorDeConexion(ex))
        {
            ManejarConexionPerdida(
                puerto,
                CrearMensajeConexionPerdida(puerto.PortName, ex)
            );

            throw new IOException(
                "No fue posible enviar porque se perdió la conexión serial.",
                ex
            );
        }
    }

    public async Task<byte[]> TransaccionModbusAsync(
        byte[] solicitud,
        int timeoutMs,
        CancellationToken cancellationToken = default)
    {
        ThrowIfDisposed();
        ArgumentNullException.ThrowIfNull(solicitud);

        if (solicitud.Length == 0)
        {
            throw new ArgumentException(
                "La trama Modbus no puede estar vacía.",
                nameof(solicitud)
            );
        }

        if (timeoutMs < 100)
        {
            throw new ArgumentOutOfRangeException(
                nameof(timeoutMs),
                "El tiempo de espera debe ser de al menos 100 ms."
            );
        }

        await _bloqueoTransaccion.WaitAsync(cancellationToken);

        try
        {
            (SerialPort puerto, CancellationToken tokenConexion) =
                ObtenerConexionActiva();

            using CancellationTokenSource linkedCancellation =
                CancellationTokenSource.CreateLinkedTokenSource(
                    cancellationToken,
                    tokenConexion
                );

            CancellationToken tokenOperacion =
                linkedCancellation.Token;

            _transaccionBinariaEnCurso = true;
            puerto.DataReceived -= PuertoSerial_DataReceived;

            try
            {
                return await Task.Run(
                    () => EjecutarTransaccionModbus(
                        puerto,
                        solicitud,
                        timeoutMs,
                        tokenOperacion
                    ),
                    tokenOperacion
                );
            }
            catch (OperationCanceledException ex)
                when (!cancellationToken.IsCancellationRequested &&
                      tokenConexion.IsCancellationRequested)
            {
                throw new IOException(
                    "Se perdió la conexión serial durante la operación Modbus.",
                    ex
                );
            }
            catch (Exception ex) when (EsErrorDeConexion(ex))
            {
                ManejarConexionPerdida(
                    puerto,
                    CrearMensajeConexionPerdida(puerto.PortName, ex)
                );

                throw;
            }
            finally
            {
                if (!_disposed && EsPuertoActualYAbierto(puerto))
                {
                    puerto.DataReceived += PuertoSerial_DataReceived;
                }

                _transaccionBinariaEnCurso = false;
            }
        }
        finally
        {
            _bloqueoTransaccion.Release();
        }
    }

    public void Desconectar()
    {
        ThrowIfDisposed();
        CerrarPuerto(null);
    }

    private byte[] EjecutarTransaccionModbus(
        SerialPort puerto,
        byte[] solicitud,
        int timeoutMs,
        CancellationToken cancellationToken)
    {
        lock (_bloqueoLectura)
        {
            VerificarPuertoDisponible(puerto);

            puerto.DiscardInBuffer();
            puerto.DiscardOutBuffer();
            puerto.Write(solicitud, 0, solicitud.Length);

            List<byte> respuesta = new();
            Stopwatch reloj = Stopwatch.StartNew();
            long siguienteVerificacion =
                IntervaloVerificacionTransaccionMs;

            while (reloj.ElapsedMilliseconds < timeoutMs)
            {
                cancellationToken.ThrowIfCancellationRequested();

                if (reloj.ElapsedMilliseconds >= siguienteVerificacion)
                {
                    VerificarPuertoDisponible(puerto);
                    siguienteVerificacion +=
                        IntervaloVerificacionTransaccionMs;
                }

                int disponibles = puerto.BytesToRead;

                if (disponibles > 0)
                {
                    byte[] bloque = new byte[disponibles];
                    int leidos = puerto.Read(
                        bloque,
                        0,
                        bloque.Length
                    );

                    for (int i = 0; i < leidos; i++)
                    {
                        respuesta.Add(bloque[i]);
                    }

                    int longitudEsperada =
                        ObtenerLongitudRespuestaModbus(respuesta);

                    if (longitudEsperada > 0 &&
                        respuesta.Count >= longitudEsperada)
                    {
                        return respuesta
                            .Take(longitudEsperada)
                            .ToArray();
                    }
                }
                else
                {
                    Thread.Sleep(3);
                }
            }

            throw new TimeoutException(
                $"El dispositivo no respondió en {timeoutMs} ms."
            );
        }
    }

    private static int ObtenerLongitudRespuestaModbus(
        IReadOnlyList<byte> respuesta)
    {
        if (respuesta.Count < 2)
        {
            return 0;
        }

        byte funcion = respuesta[1];

        if ((funcion & 0x80) != 0)
        {
            return 5;
        }

        if (funcion is 0x05 or 0x06 or 0x0F or 0x10)
        {
            return 8;
        }

        if (funcion is 0x01 or 0x02 or 0x03 or 0x04)
        {
            if (respuesta.Count < 3)
            {
                return 0;
            }

            int cantidadBytes = respuesta[2];
            return cantidadBytes + 5;
        }

        return 0;
    }

    private (SerialPort Puerto, CancellationToken TokenConexion)
        ObtenerConexionActiva()
    {
        lock (_bloqueoEstado)
        {
            if (!PuertoAbiertoSeguro(_puertoSerial) ||
                _conexionCancellation is null ||
                _conexionCancellation.IsCancellationRequested)
            {
                throw new InvalidOperationException(
                    "No existe una conexión serial activa."
                );
            }

            return (
                _puertoSerial!,
                _conexionCancellation.Token
            );
        }
    }

    private void MonitorearConexion(object? state)
    {
        if (_disposed || state is not SerialPort puerto)
        {
            return;
        }

        if (!EsPuertoActual(puerto))
        {
            return;
        }

        bool abierto = PuertoAbiertoSeguro(puerto);
        bool disponible = PuertoExiste(puerto.PortName);

        if (abierto && disponible)
        {
            return;
        }

        ManejarConexionPerdida(
            puerto,
            $"Se perdió la conexión con {puerto.PortName}. " +
            "El adaptador pudo haberse desconectado físicamente."
        );
    }

    private void ManejarConexionPerdida(
        SerialPort puerto,
        string mensaje)
    {
        if (!EsPuertoActual(puerto))
        {
            return;
        }

        if (Interlocked.CompareExchange(
                ref _conexionPerdidaNotificada,
                1,
                0
            ) != 0)
        {
            return;
        }

        CerrarPuerto(puerto);

        try
        {
            ConexionPerdida?.Invoke(this, mensaje);
        }
        catch
        {
            // Un consumidor no debe derribar el monitor serial.
        }
    }

    private void CerrarPuerto(SerialPort? puertoEsperado)
    {
        SerialPort? puerto;
        CancellationTokenSource? conexionCancellation;
        Timer? monitorConexion;

        lock (_bloqueoEstado)
        {
            if (puertoEsperado is not null &&
                !ReferenceEquals(_puertoSerial, puertoEsperado))
            {
                return;
            }

            puerto = _puertoSerial;
            conexionCancellation = _conexionCancellation;
            monitorConexion = _monitorConexion;

            _puertoSerial = null;
            _conexionCancellation = null;
            _monitorConexion = null;
        }

        monitorConexion?.Dispose();

        try
        {
            conexionCancellation?.Cancel();
        }
        catch (ObjectDisposedException)
        {
            // Ya fue liberado por otra ruta de cierre.
        }

        if (puerto is not null)
        {
            puerto.DataReceived -= PuertoSerial_DataReceived;
            puerto.ErrorReceived -= PuertoSerial_ErrorReceived;

            try
            {
                if (puerto.IsOpen)
                {
                    puerto.Close();
                }
            }
            catch
            {
                // En una extracción USB el controlador puede fallar al cerrar.
            }
            finally
            {
                puerto.Dispose();
            }
        }

    }

    private bool EsPuertoActual(SerialPort puerto)
    {
        lock (_bloqueoEstado)
        {
            return ReferenceEquals(_puertoSerial, puerto);
        }
    }

    private bool EsPuertoActualYAbierto(SerialPort puerto)
    {
        lock (_bloqueoEstado)
        {
            return ReferenceEquals(_puertoSerial, puerto) &&
                   PuertoAbiertoSeguro(puerto) &&
                   _conexionCancellation?.IsCancellationRequested != true;
        }
    }

    private static void VerificarPuertoDisponible(SerialPort puerto)
    {
        if (!PuertoAbiertoSeguro(puerto))
        {
            throw new IOException(
                "El puerto serial se cerró durante la operación."
            );
        }

        if (!PuertoExiste(puerto.PortName))
        {
            throw new IOException(
                $"El puerto {puerto.PortName} dejó de estar disponible."
            );
        }
    }

    private static bool PuertoExiste(string nombrePuerto)
    {
        try
        {
            return SerialPort.GetPortNames().Contains(
                nombrePuerto,
                StringComparer.OrdinalIgnoreCase
            );
        }
        catch
        {
            // Una falla temporal de enumeración no debe declarar una
            // desconexión física por sí sola.
            return true;
        }
    }

    private static bool PuertoAbiertoSeguro(SerialPort? puerto)
    {
        if (puerto is null)
        {
            return false;
        }

        try
        {
            return puerto.IsOpen;
        }
        catch
        {
            return false;
        }
    }

    private static bool EsErrorDeConexion(Exception ex) =>
        ex is IOException or
        InvalidOperationException or
        UnauthorizedAccessException or
        ObjectDisposedException;

    private static string CrearMensajeConexionPerdida(
        string nombrePuerto,
        Exception ex) =>
        $"Se perdió la conexión con {nombrePuerto}: {ex.Message}";

    private static int ObtenerNumeroPuerto(string nombrePuerto)
    {
        if (nombrePuerto.StartsWith(
                "COM",
                StringComparison.OrdinalIgnoreCase
            ) &&
            int.TryParse(nombrePuerto[3..], out int numero))
        {
            return numero;
        }

        return int.MaxValue;
    }

    private void ThrowIfDisposed()
    {
        ObjectDisposedException.ThrowIf(_disposed, this);
    }

    public void Dispose()
    {
        if (_disposed)
        {
            return;
        }

        _disposed = true;
        CerrarPuerto(null);
    }

    private void PuertoSerial_DataReceived(
        object sender,
        SerialDataReceivedEventArgs e)
    {
        if (_transaccionBinariaEnCurso ||
            sender is not SerialPort puerto)
        {
            return;
        }

        try
        {
            lock (_bloqueoLectura)
            {
                if (_transaccionBinariaEnCurso ||
                    !EsPuertoActualYAbierto(puerto))
                {
                    return;
                }

                int cantidadDisponible = puerto.BytesToRead;

                if (cantidadDisponible <= 0)
                {
                    return;
                }

                byte[] buffer = new byte[cantidadDisponible];
                int cantidadLeida = puerto.Read(
                    buffer,
                    0,
                    buffer.Length
                );

                if (cantidadLeida <= 0)
                {
                    return;
                }

                string datos = Encoding.ASCII.GetString(
                    buffer,
                    0,
                    cantidadLeida
                );

                DatosRecibidos?.Invoke(this, datos);
            }
        }
        catch (Exception ex) when (EsErrorDeConexion(ex))
        {
            ManejarConexionPerdida(
                puerto,
                CrearMensajeConexionPerdida(puerto.PortName, ex)
            );
        }
        catch (Exception ex)
        {
            ErrorOcurrido?.Invoke(
                this,
                $"Error al recibir datos: {ex.Message}"
            );
        }
    }

    private void PuertoSerial_ErrorReceived(
        object sender,
        SerialErrorReceivedEventArgs e)
    {
        if (sender is SerialPort puerto &&
            (!PuertoAbiertoSeguro(puerto) ||
             !PuertoExiste(puerto.PortName)))
        {
            ManejarConexionPerdida(
                puerto,
                $"Se perdió la conexión con {puerto.PortName}."
            );
            return;
        }

        ErrorOcurrido?.Invoke(
            this,
            $"Error serial detectado: {e.EventType}"
        );
    }
}
