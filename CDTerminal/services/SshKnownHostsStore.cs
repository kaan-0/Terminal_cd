using System.IO;
using System.Text.Json;
using CDTerminal.Models;

namespace CDTerminal.Services;

public sealed class SshKnownHostsStore
{
    private readonly SemaphoreSlim _bloqueo = new(1, 1);
    private readonly string _rutaArchivo;
    private readonly JsonSerializerOptions _opcionesJson = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
        PropertyNameCaseInsensitive = true,
        WriteIndented = true
    };

    public SshKnownHostsStore(string? rutaArchivo = null)
    {
        string carpetaBase = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "CDTerminal"
        );

        _rutaArchivo = rutaArchivo ?? Path.Combine(
            carpetaBase,
            "ssh-known-hosts.json"
        );
    }

    public async Task<HostSshConocido?> ObtenerAsync(
        string servidor,
        int puerto,
        CancellationToken cancellationToken = default)
    {
        string servidorNormalizado = NormalizarServidor(servidor);

        await _bloqueo.WaitAsync(cancellationToken);

        try
        {
            IReadOnlyList<HostSshConocido> hosts =
                await LeerTodosInternoAsync(cancellationToken);

            return hosts.FirstOrDefault(host =>
                string.Equals(
                    NormalizarServidor(host.Servidor),
                    servidorNormalizado,
                    StringComparison.OrdinalIgnoreCase
                ) &&
                host.Puerto == puerto
            );
        }
        finally
        {
            _bloqueo.Release();
        }
    }

    public async Task GuardarAsync(
        HostSshPendiente hostPendiente,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(hostPendiente);

        await _bloqueo.WaitAsync(cancellationToken);

        try
        {
            List<HostSshConocido> hosts =
                (await LeerTodosInternoAsync(cancellationToken)).ToList();

            string servidorNormalizado =
                NormalizarServidor(hostPendiente.Servidor);

            HostSshConocido? existente = hosts.FirstOrDefault(host =>
                string.Equals(
                    NormalizarServidor(host.Servidor),
                    servidorNormalizado,
                    StringComparison.OrdinalIgnoreCase
                ) &&
                host.Puerto == hostPendiente.Puerto
            );

            if (existente is null)
            {
                hosts.Add(new HostSshConocido
                {
                    Servidor = hostPendiente.Servidor.Trim(),
                    Puerto = hostPendiente.Puerto,
                    Algoritmo = hostPendiente.Algoritmo,
                    HuellaSha256 = hostPendiente.HuellaSha256,
                    ConfiadoUtc = DateTime.UtcNow
                });
            }
            else
            {
                existente.Algoritmo = hostPendiente.Algoritmo;
                existente.HuellaSha256 = hostPendiente.HuellaSha256;
                existente.ConfiadoUtc = DateTime.UtcNow;
            }

            string? carpeta = Path.GetDirectoryName(_rutaArchivo);

            if (!string.IsNullOrWhiteSpace(carpeta))
            {
                Directory.CreateDirectory(carpeta);
            }

            string json = JsonSerializer.Serialize(hosts, _opcionesJson);
            string temporal = _rutaArchivo + ".tmp";

            await File.WriteAllTextAsync(
                temporal,
                json,
                cancellationToken
            );

            File.Move(temporal, _rutaArchivo, true);
        }
        finally
        {
            _bloqueo.Release();
        }
    }

    private async Task<IReadOnlyList<HostSshConocido>> LeerTodosInternoAsync(
        CancellationToken cancellationToken)
    {
        if (!File.Exists(_rutaArchivo))
        {
            return Array.Empty<HostSshConocido>();
        }

        try
        {
            string json = await File.ReadAllTextAsync(
                _rutaArchivo,
                cancellationToken
            );

            if (string.IsNullOrWhiteSpace(json))
            {
                return Array.Empty<HostSshConocido>();
            }

            return JsonSerializer.Deserialize<List<HostSshConocido>>(
                json,
                _opcionesJson
            ) ?? new List<HostSshConocido>();
        }
        catch (JsonException)
        {
            return Array.Empty<HostSshConocido>();
        }
        catch (IOException)
        {
            return Array.Empty<HostSshConocido>();
        }
    }

    private static string NormalizarServidor(string servidor) =>
        servidor.Trim().ToLowerInvariant();
}
