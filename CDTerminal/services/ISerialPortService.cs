using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;
using CDTerminal.Models;

namespace CDTerminal.Services;

public interface ISerialPortService : IDisposable
{
    bool EstaConectado { get; }

    string? PuertoActual { get; }

    event EventHandler<string>? DatosRecibidos;

    event EventHandler<string>? ErrorOcurrido;

    event EventHandler<string>? ConexionPerdida;

    IReadOnlyList<string> ObtenerPuertosDisponibles();

    void Conectar(ConfiguracionSerial configuracion);

    void EnviarTexto(string texto);

    Task<byte[]> TransaccionModbusAsync(
        byte[] solicitud,
        int timeoutMs,
        CancellationToken cancellationToken = default
    );

    void Desconectar();
}
