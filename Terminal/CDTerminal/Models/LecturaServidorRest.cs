using System;
using System.Collections.Generic;

namespace CDTerminal.Models;

public sealed class LecturaServidorRest
{
    public int SchemaVersion { get; set; } = 1;

    public string DeviceId { get; set; } = string.Empty;

    public DateTime TimestampUtc { get; set; } = DateTime.UtcNow;

    public string Source { get; set; } = "manual";

    public string Session { get; set; } = string.Empty;

    public string Communication { get; set; } = "modbus-rtu";

    public string Port { get; set; } = string.Empty;

    public int BaudRate { get; set; }

    public int SlaveId { get; set; }

    public int Function { get; set; }

    public int StartAddress { get; set; }

    public int RequestedQuantity { get; set; }

    public bool CrcValid { get; set; }

    public string Tx { get; set; } = string.Empty;

    public string Rx { get; set; } = string.Empty;

    public List<RegistroServidorRest> Registers { get; set; } = new();
}

public sealed class RegistroServidorRest
{
    public int Address { get; set; }

    public string Hex { get; set; } = string.Empty;

    public ushort Unsigned { get; set; }

    public short Signed { get; set; }
}
