using System;

namespace CDTerminal.Models;

public sealed class ConfiguracionServidorRest
{
    public Guid Id { get; set; } = Guid.NewGuid();

    public string Nombre { get; set; } = "Servidor REST";

    public bool Activo { get; set; } = true;

    public string UrlBase { get; set; } = string.Empty;

    public string RutaPrueba { get; set; } = "/health";

    public string RutaLecturas { get; set; } = "/api/readings";

    public string IdDispositivo { get; set; } = "cd-terminal-01";

    public string TokenBearer { get; set; } = string.Empty;

    public int TimeoutSegundos { get; set; } = 10;

    public bool EnvioAutomatico { get; set; }

    public DateTime ActualizadoEn { get; set; } = DateTime.Now;

    public ConfiguracionServidorRest CrearCopia()
    {
        return new ConfiguracionServidorRest
        {
            Id = Id,
            Nombre = Nombre,
            Activo = Activo,
            UrlBase = UrlBase,
            RutaPrueba = RutaPrueba,
            RutaLecturas = RutaLecturas,
            IdDispositivo = IdDispositivo,
            TokenBearer = TokenBearer,
            TimeoutSegundos = TimeoutSegundos,
            EnvioAutomatico = EnvioAutomatico,
            ActualizadoEn = ActualizadoEn
        };
    }
}
