namespace CDTerminal.Models;

public sealed class ConfiguracionSsh
{
    public string Servidor { get; set; } = string.Empty;

    public int Puerto { get; set; } = 22;

    public string Usuario { get; set; } = string.Empty;

    // La contraseña vive únicamente en memoria durante la sesión.
    public string Contrasena { get; set; } = string.Empty;

    public int TimeoutSegundos { get; set; } = 15;

    public ConfiguracionSsh CrearCopia()
    {
        return new ConfiguracionSsh
        {
            Servidor = Servidor,
            Puerto = Puerto,
            Usuario = Usuario,
            Contrasena = Contrasena,
            TimeoutSegundos = TimeoutSegundos
        };
    }
}
