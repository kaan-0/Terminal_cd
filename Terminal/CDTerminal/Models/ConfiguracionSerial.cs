using System.IO.Ports;

namespace CDTerminal.Models;

public sealed class ConfiguracionSerial
{
    public string NombrePuerto { get; set; } = string.Empty;

    public int Velocidad { get; set; } = 9600;

    public int BitsDeDatos { get; set; } = 8;

    public Parity Paridad { get; set; } = Parity.None;

    public StopBits BitsDeParada { get; set; } = StopBits.One;

    public Handshake ControlDeFlujo { get; set; } = Handshake.None;

    public bool HabilitarDtr { get; set; } = true;

    public bool HabilitarRts { get; set; } = true;
}