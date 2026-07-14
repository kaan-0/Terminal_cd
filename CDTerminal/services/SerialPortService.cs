using System.IO.Ports;
using System.Text;
using CDTerminal.Models;
namespace CDTerminal.Services;

public sealed class SerialPortService : ISerialPortService
{
    private SerialPort? _puertoSerial;
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

        if (_puertoSerial?.IsOpen != true)
        {
            throw new InvalidOperationException(
                "No existe una conexión serial activa."
            );
        }

        if (string.IsNullOrEmpty(texto))
        {
            return;
        }

        _puertoSerial.Write(texto);
    }

    public void Desconectar()
    {
        ThrowIfDisposed();

        CerrarPuerto();
    }

    private void CerrarPuerto()
    {
        if (_puertoSerial is null)
        {
            return;
        }

        try
        {
            if (_puertoSerial.IsOpen)
            {
                _puertoSerial.DataReceived -= PuertoSerial_DataReceived;
                _puertoSerial.ErrorReceived -= PuertoSerial_ErrorReceived;
                _puertoSerial.Close();
            }
        }
        finally
        {
            _puertoSerial.Dispose();
            _puertoSerial = null;
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
        try
        {
            if (sender is not SerialPort puerto ||
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