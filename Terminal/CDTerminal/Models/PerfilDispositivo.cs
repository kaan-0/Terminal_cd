using System;
using System.IO.Ports;

namespace CDTerminal.Models;

public sealed class PerfilDispositivo
{
    public Guid Id { get; set; } = Guid.NewGuid();

    public string Nombre { get; set; } = string.Empty;

    public string Descripcion { get; set; } = string.Empty;

    public string TipoSesion { get; set; } = "Serial";

    public string PuertoPreferido { get; set; } = string.Empty;

    public int Velocidad { get; set; } = 9600;

    public int BitsDeDatos { get; set; } = 8;

    public Parity Paridad { get; set; } = Parity.None;

    public StopBits BitsDeParada { get; set; } = StopBits.One;

    public Handshake ControlDeFlujo { get; set; } = Handshake.None;

    public bool HabilitarDtr { get; set; } = true;

    public bool HabilitarRts { get; set; } = true;

    public int IdEsclavoModbus { get; set; } = 1;

    public int TimeoutModbusMs { get; set; } = 1000;

    public int ReintentosModbus { get; set; } = 1;

    public string FuncionModbus { get; set; } = "03";

    public int DireccionInicialModbus { get; set; }

    public int CantidadRegistrosModbus { get; set; } = 1;

    public int IntervaloPollingModbusMs { get; set; } = 1000;

    public int DireccionRegistroGrafica { get; set; }

    public int MaxPuntosGrafica { get; set; } = 120;

    public DateTime CreadoEn { get; set; } = DateTime.Now;

    public DateTime ActualizadoEn { get; set; } = DateTime.Now;
}