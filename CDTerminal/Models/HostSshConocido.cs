namespace CDTerminal.Models;

public sealed class HostSshConocido
{
    public string Servidor { get; set; } = string.Empty;

    public int Puerto { get; set; } = 22;

    public string Algoritmo { get; set; } = string.Empty;

    public string HuellaSha256 { get; set; } = string.Empty;

    public DateTime ConfiadoUtc { get; set; } = DateTime.UtcNow;
}

public sealed class HostSshPendiente
{
    public string Servidor { get; init; } = string.Empty;

    public int Puerto { get; init; } = 22;

    public string Algoritmo { get; init; } = string.Empty;

    public string HuellaSha256 { get; init; } = string.Empty;

    public string? HuellaAnteriorSha256 { get; init; }

    public bool EsCambioDeHuella =>
        !string.IsNullOrWhiteSpace(HuellaAnteriorSha256);
}
