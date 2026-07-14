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
    private readonly SemaphoreSlim _bloqueoTransaccion = new(1, 1);
    private readonly object _bloqueoLectura = new();

    private SerialPort? _puertoSerial;
    private volatile bool _transaccionBinariaEnCurso;
    private bool _disposed;

    public bool EstaConectado =>
        _puertoSerial?.IsOpen == true;

    public string? PuertoActual =>
        EstaConectado
            ? _puertoSerial?.PortName
            : null;

    public event EventHandler<string>? DatosRecibidos;

    public event EventHandler<string>? ErrorOcurrido;

    public IReadOnlyList<string> ObtenerPuertosDisponibles()
    {
        ThrowIfDisposed();

        return SerialPort
            .GetPortNames()
            .OrderBy(ObtenerNumeroPuerto)
            .ToArray();
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

        if (EstaConectado)
        {
            throw new InvalidOperationException(
                $"Ya existe una conexión activa en {_puertoSerial?.PortName}."
            );
        }

        _puertoSerial = new SerialPort
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

        try
        {
            _puertoSerial.DataReceived += PuertoSerial_DataReceived;
            _puertoSerial.ErrorReceived += PuertoSerial_ErrorReceived;

            _puertoSerial.Open();
        }
        catch
        {
            _puertoSerial.DataReceived -= PuertoSerial_DataReceived;
            _puertoSerial.ErrorReceived -= PuertoSerial_ErrorReceived;

            _puertoSerial.Dispose();
            _puertoSerial = null;

            throw;
        }
    }

    public void EnviarTexto(string texto)
    {
        ThrowIfDisposed();

        SerialPort puerto = ObtenerPuertoAbierto();

        if (string.IsNullOrEmpty(texto))
        {
            return;
        }

        puerto.Write(texto);
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
            SerialPort puerto = ObtenerPuertoAbierto();

            _transaccionBinariaEnCurso = true;
            puerto.DataReceived -= PuertoSerial_DataReceived;

            try
            {
                return await Task.Run(
                    () => EjecutarTransaccionModbus(
                        puerto,
                        solicitud,
                        timeoutMs,
                        cancellationToken
                    ),
                    cancellationToken
                );
            }
            finally
            {
                if (!_disposed &&
                    ReferenceEquals(_puertoSerial, puerto) &&
                    puerto.IsOpen)
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

        CerrarPuerto();
    }

    private byte[] EjecutarTransaccionModbus(
        SerialPort puerto,
        byte[] solicitud,
        int timeoutMs,
        CancellationToken cancellationToken)
    {
        lock (_bloqueoLectura)
        {
            if (!puerto.IsOpen)
            {
                throw new InvalidOperationException(
                    "El puerto serial se cerró antes de iniciar la consulta."
                );
            }

            puerto.DiscardInBuffer();
            puerto.DiscardOutBuffer();

            puerto.Write(
                solicitud,
                0,
                solicitud.Length
            );

            List<byte> respuesta = new();
            Stopwatch reloj = Stopwatch.StartNew();

            while (reloj.ElapsedMilliseconds < timeoutMs)
            {
                cancellationToken.ThrowIfCancellationRequested();

                if (!puerto.IsOpen)
                {
                    throw new IOException(
                        "El puerto serial se cerró mientras se esperaba la respuesta."
                    );
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

        if (respuesta.Count < 3)
        {
            return 0;
        }

        int cantidadBytes = respuesta[2];

        return cantidadBytes + 5;
    }

    private SerialPort ObtenerPuertoAbierto()
    {
        if (_puertoSerial?.IsOpen != true)
        {
            throw new InvalidOperationException(
                "No existe una conexión serial activa."
            );
        }

        return _puertoSerial;
    }

    private void CerrarPuerto()
    {
        SerialPort? puerto = _puertoSerial;

        if (puerto is null)
        {
            return;
        }

        _puertoSerial = null;

        try
        {
            puerto.DataReceived -= PuertoSerial_DataReceived;
            puerto.ErrorReceived -= PuertoSerial_ErrorReceived;

            if (puerto.IsOpen)
            {
                puerto.Close();
            }
        }
        finally
        {
            puerto.Dispose();
        }
    }

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
        ObjectDisposedException.ThrowIf(
            _disposed,
            this
        );
    }

    public void Dispose()
    {
        if (_disposed)
        {
            return;
        }

        CerrarPuerto();

        _disposed = true;
    }

    private void PuertoSerial_DataReceived(
        object sender,
        SerialDataReceivedEventArgs e)
    {
        if (_transaccionBinariaEnCurso)
        {
            return;
        }

        try
        {
            lock (_bloqueoLectura)
            {
                if (_transaccionBinariaEnCurso ||
                    sender is not SerialPort puerto ||
                    !puerto.IsOpen)
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
        ErrorOcurrido?.Invoke(
            this,
            $"Error serial detectado: {e.EventType}"
        );
    }
}