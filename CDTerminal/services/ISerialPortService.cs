namespace CDTerminal.Services;

using CDTerminal.Models;
public interface ISerialPortService : IDisposable
{
    bool EstaConectado { get; }

    string? PuertoActual { get; }

    event EventHandler<string>? DatosRecibidos;

    event EventHandler<string>? ErrorOcurrido;

    IReadOnlyList<string> ObtenerPuertosDisponibles();

    void Conectar(ConfiguracionSerial configuracion);
    void EnviarTexto(string texto);
    void Desconectar();
}