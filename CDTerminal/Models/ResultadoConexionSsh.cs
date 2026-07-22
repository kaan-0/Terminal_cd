namespace CDTerminal.Models;

public enum EstadoResultadoConexionSsh
{
    Conectado,
    RequiereConfianza,
    HuellaModificada,
    Error
}

public sealed class ResultadoConexionSsh
{
    public EstadoResultadoConexionSsh Estado { get; init; }

    public string Mensaje { get; init; } = string.Empty;

    public HostSshPendiente? HostPendiente { get; init; }

    public bool Exitoso =>
        Estado == EstadoResultadoConexionSsh.Conectado;
}
