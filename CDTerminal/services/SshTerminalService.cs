using System.IO;
using System.Linq;
using System.Text;
using CDTerminal.Models;
using Renci.SshNet;
using Renci.SshNet.Common;

namespace CDTerminal.Services;

public sealed class SshTerminalService : IAsyncDisposable
{
    private readonly SshKnownHostsStore _knownHostsStore;
    private readonly SemaphoreSlim _bloqueoEnvio = new(1, 1);
    private readonly SemaphoreSlim _bloqueoConexion = new(1, 1);
    private readonly SemaphoreSlim _bloqueoAutocompletado = new(1, 1);

    private SshClient? _cliente;
    private ShellStream? _shell;
    private CancellationTokenSource? _lecturaCancellation;
    private Task? _tareaLectura;
    private IReadOnlyList<string>? _comandosDisponibles;
    private bool _disposed;

    public SshTerminalService(
        SshKnownHostsStore? knownHostsStore = null)
    {
        _knownHostsStore = knownHostsStore ?? new SshKnownHostsStore();
    }

    public event EventHandler<string>? DatosRecibidos;
    public event EventHandler<string>? ErrorOcurrido;
    public event EventHandler? ConexionCerrada;

    public bool EstaConectado =>
        _cliente?.IsConnected == true &&
        _shell is { CanWrite: true };

    public HostSshPendiente? HostPendiente { get; private set; }

    public async Task<ResultadoConexionSsh> ConectarAsync(
        ConfiguracionSsh configuracion,
        CancellationToken cancellationToken = default)
    {
        ThrowIfDisposed();
        ArgumentNullException.ThrowIfNull(configuracion);
        ValidarConfiguracion(configuracion);

        await _bloqueoConexion.WaitAsync(cancellationToken);

        try
        {
            await DesconectarInternoAsync(notificar: false);
            HostPendiente = null;

            ConfiguracionSsh copia = configuracion.CrearCopia();

            HostSshConocido? conocido =
                await _knownHostsStore.ObtenerAsync(
                    copia.Servidor,
                    copia.Puerto,
                    cancellationToken
                );

            HostSshPendiente? presentado = null;

            SshClient cliente = new(
                copia.Servidor.Trim(),
                copia.Puerto,
                copia.Usuario.Trim(),
                copia.Contrasena
            );

            cliente.ConnectionInfo.Timeout = TimeSpan.FromSeconds(
                Math.Clamp(copia.TimeoutSegundos, 3, 120)
            );

            cliente.KeepAliveInterval = TimeSpan.FromSeconds(20);

            cliente.HostKeyReceived += (_, evento) =>
            {
                string huella = evento.FingerPrintSHA256;
                string algoritmo = evento.HostKeyName;

                bool coincide = conocido is not null &&
                    string.Equals(
                        conocido.HuellaSha256,
                        huella,
                        StringComparison.Ordinal
                    );

                if (coincide)
                {
                    evento.CanTrust = true;
                    return;
                }

                presentado = new HostSshPendiente
                {
                    Servidor = copia.Servidor.Trim(),
                    Puerto = copia.Puerto,
                    Algoritmo = algoritmo,
                    HuellaSha256 = huella,
                    HuellaAnteriorSha256 = conocido?.HuellaSha256
                };

                evento.CanTrust = false;
            };

            cliente.ErrorOccurred += (_, evento) =>
            {
                ErrorOcurrido?.Invoke(
                    this,
                    evento.Exception.Message
                );
            };

            try
            {
                await cliente.ConnectAsync(cancellationToken);
            }
            catch (Exception ex) when (presentado is not null)
            {
                cliente.Dispose();
                HostPendiente = presentado;

                return new ResultadoConexionSsh
                {
                    Estado = presentado.EsCambioDeHuella
                        ? EstadoResultadoConexionSsh.HuellaModificada
                        : EstadoResultadoConexionSsh.RequiereConfianza,
                    Mensaje = presentado.EsCambioDeHuella
                        ? "La huella del servidor cambió. Verifica el equipo antes de actualizar la confianza."
                        : "El servidor es desconocido. Confirma su huella antes de conectar.",
                    HostPendiente = presentado
                };
            }
            catch (Exception ex)
            {
                cliente.Dispose();

                return new ResultadoConexionSsh
                {
                    Estado = EstadoResultadoConexionSsh.Error,
                    Mensaje = TraducirErrorConexion(ex)
                };
            }

            ShellStream shell;

            try
            {
                shell = cliente.CreateShellStream(
                    "xterm-256color",
                    120,
                    40,
                    0,
                    0,
                    32768
                );
            }
            catch (Exception ex)
            {
                cliente.Disconnect();
                cliente.Dispose();

                return new ResultadoConexionSsh
                {
                    Estado = EstadoResultadoConexionSsh.Error,
                    Mensaje = $"La conexión abrió, pero no fue posible crear la terminal: {ex.Message}"
                };
            }

            _cliente = cliente;
            _shell = shell;
            _lecturaCancellation = new CancellationTokenSource();

            _tareaLectura = LeerSalidaAsync(
                shell,
                _lecturaCancellation.Token
            );

            return new ResultadoConexionSsh
            {
                Estado = EstadoResultadoConexionSsh.Conectado,
                Mensaje = "Conexión SSH establecida."
            };
        }
        finally
        {
            _bloqueoConexion.Release();
        }
    }

    public async Task ConfiarHostPendienteAsync(
        CancellationToken cancellationToken = default)
    {
        ThrowIfDisposed();

        HostSshPendiente host = HostPendiente ??
            throw new InvalidOperationException(
                "No existe una huella SSH pendiente."
            );

        await _knownHostsStore.GuardarAsync(host, cancellationToken);
        HostPendiente = null;
    }

    public void DescartarHostPendiente()
    {
        HostPendiente = null;
    }

    public async Task EnviarAsync(
        string texto,
        CancellationToken cancellationToken = default)
    {
        ThrowIfDisposed();

        if (string.IsNullOrEmpty(texto))
        {
            return;
        }

        await _bloqueoEnvio.WaitAsync(cancellationToken);

        try
        {
            ShellStream shell = _shell ??
                throw new InvalidOperationException(
                    "No existe una terminal SSH conectada."
                );

            if (!EstaConectado)
            {
                throw new InvalidOperationException(
                    "La conexión SSH no está activa."
                );
            }

            shell.Write(texto);
        }
        finally
        {
            _bloqueoEnvio.Release();
        }
    }


    public async Task<IReadOnlyList<string>> ObtenerComandosDisponiblesAsync(
        CancellationToken cancellationToken = default)
    {
        ThrowIfDisposed();

        if (!EstaConectado)
        {
            throw new InvalidOperationException(
                "La conexión SSH no está activa."
            );
        }

        if (_comandosDisponibles is { Count: > 0 })
        {
            return _comandosDisponibles;
        }

        await _bloqueoAutocompletado.WaitAsync(cancellationToken);

        try
        {
            if (_comandosDisponibles is { Count: > 0 })
            {
                return _comandosDisponibles;
            }

            SshClient cliente = _cliente ??
                throw new InvalidOperationException(
                    "No existe un cliente SSH conectado."
                );

            const string script = """
                if command -v bash >/dev/null 2>&1; then
                    LC_ALL=C bash -lc 'compgen -A command 2>/dev/null | sort -u'
                else
                    printf '%s\n' cd echo exit export pwd set unset alias command
                    oldifs=$IFS
                    IFS=:
                    for d in $PATH; do
                        [ -d "$d" ] || continue
                        for f in "$d"/*; do
                            [ -f "$f" ] && [ -x "$f" ] && basename "$f"
                        done
                    done
                    IFS=$oldifs
                fi
                """;

            using SshCommand comandoRemoto =
                cliente.CreateCommand(script);

            comandoRemoto.CommandTimeout = TimeSpan.FromSeconds(10);

            await comandoRemoto.ExecuteAsync(cancellationToken);

            string[] comandos = (comandoRemoto.Result ?? string.Empty)
                .Replace("\r\n", "\n")
                .Split(
                    '\n',
                    StringSplitOptions.RemoveEmptyEntries |
                    StringSplitOptions.TrimEntries
                )
                .Where(comando =>
                    comando.Length is > 0 and <= 160 &&
                    !comando.Any(char.IsWhiteSpace)
                )
                .Distinct(StringComparer.Ordinal)
                .OrderBy(comando => comando, StringComparer.Ordinal)
                .Take(12_000)
                .ToArray();

            _comandosDisponibles = comandos;
            return _comandosDisponibles;
        }
        finally
        {
            _bloqueoAutocompletado.Release();
        }
    }



    public async Task<string> ObtenerDirectorioTrabajoInicialAsync(
        CancellationToken cancellationToken = default)
    {
        ThrowIfDisposed();

        if (!EstaConectado)
        {
            throw new InvalidOperationException(
                "La conexión SSH no está activa."
            );
        }

        SshClient cliente = _cliente ??
            throw new InvalidOperationException(
                "No existe un cliente SSH conectado."
            );

        using SshCommand comandoRemoto = cliente.CreateCommand("pwd -P");

        comandoRemoto.CommandTimeout = TimeSpan.FromSeconds(5);
        await comandoRemoto.ExecuteAsync(cancellationToken);

        string directorio = (comandoRemoto.Result ?? string.Empty).Trim();

        return string.IsNullOrWhiteSpace(directorio)
            ? "~"
            : directorio;
    }

    public async Task<string?> ResolverDirectorioTrabajoAsync(
        string directorioActual,
        string argumentoCd,
        CancellationToken cancellationToken = default)
    {
        ThrowIfDisposed();

        if (!EstaConectado)
        {
            throw new InvalidOperationException(
                "La conexión SSH no está activa."
            );
        }

        if (string.Equals(argumentoCd.Trim(), "-", StringComparison.Ordinal))
        {
            // OLDPWD pertenece al shell interactivo y no al canal auxiliar.
            return null;
        }

        SshClient cliente = _cliente ??
            throw new InvalidOperationException(
                "No existe un cliente SSH conectado."
            );

        string cwd = EscaparArgumentoShell(
            string.IsNullOrWhiteSpace(directorioActual)
                ? "~"
                : directorioActual
        );

        string objetivo = EscaparArgumentoShell(argumentoCd.Trim());

        string script = $$"""
            cwd={{cwd}}
            objetivo={{objetivo}}

            if [ -z "$cwd" ] || [ "$cwd" = "~" ]; then
                cwd="$HOME"
            fi

            cd -- "$cwd" 2>/dev/null || exit 1

            if [ -z "$objetivo" ] || [ "$objetivo" = "~" ]; then
                objetivo="$HOME"
            else
                case "$objetivo" in
                    "~/"*) objetivo="$HOME/${objetivo#~/}" ;;
                esac
            fi

            cd -- "$objetivo" 2>/dev/null || exit 1
            pwd -P
            """;

        using SshCommand comandoRemoto = cliente.CreateCommand(script);
        comandoRemoto.CommandTimeout = TimeSpan.FromSeconds(5);
        await comandoRemoto.ExecuteAsync(cancellationToken);

        string resultado = (comandoRemoto.Result ?? string.Empty).Trim();

        return string.IsNullOrWhiteSpace(resultado)
            ? null
            : resultado;
    }

    public async Task<IReadOnlyList<string>> ObtenerRutasDisponiblesAsync(
        string directorioTrabajo,
        string prefijoRuta,
        CancellationToken cancellationToken = default)
    {
        ThrowIfDisposed();

        if (!EstaConectado)
        {
            throw new InvalidOperationException(
                "La conexión SSH no está activa."
            );
        }

        await _bloqueoAutocompletado.WaitAsync(cancellationToken);

        try
        {
            SshClient cliente = _cliente ??
                throw new InvalidOperationException(
                    "No existe un cliente SSH conectado."
                );

            string cwd = EscaparArgumentoShell(
                string.IsNullOrWhiteSpace(directorioTrabajo)
                    ? "~"
                    : directorioTrabajo
            );

            string token = EscaparArgumentoShell(prefijoRuta ?? string.Empty);

            string script = $$"""
                cwd={{cwd}}
                token={{token}}

                if [ -z "$cwd" ] || [ "$cwd" = "~" ]; then
                    cwd="$HOME"
                fi

                cd -- "$cwd" 2>/dev/null || exit 0

                case "$token" in
                    "~") expanded="$HOME" ;;
                    "~/"*) expanded="$HOME/${token#~/}" ;;
                    *) expanded="$token" ;;
                esac

                case "$token" in
                    */*) display_dir="${token%/*}/" ;;
                    *) display_dir="" ;;
                esac

                case "$expanded" in
                    */*)
                        search_dir="${expanded%/*}"
                        base="${expanded##*/}"
                        [ -n "$search_dir" ] || search_dir="/"
                        ;;
                    *)
                        search_dir="."
                        base="$expanded"
                        ;;
                esac

                [ -d "$search_dir" ] || exit 0

                for item in "$search_dir"/"$base"*; do
                    if [ ! -e "$item" ] && [ ! -L "$item" ]; then
                        continue
                    fi

                    name="${item##*/}"
                    [ "$name" = "." ] && continue
                    [ "$name" = ".." ] && continue

                    if [ -d "$item" ]; then
                        kind="D"
                    else
                        kind="F"
                    fi

                    printf '%s\t%s\n' "$display_dir$name" "$kind"
                done
                """;

            using SshCommand comandoRemoto = cliente.CreateCommand(script);
            comandoRemoto.CommandTimeout = TimeSpan.FromSeconds(8);
            await comandoRemoto.ExecuteAsync(cancellationToken);

            List<string> resultados = new();

            foreach (string linea in (comandoRemoto.Result ?? string.Empty)
                .Replace("\r\n", "\n")
                .Split(
                    '\n',
                    StringSplitOptions.RemoveEmptyEntries |
                    StringSplitOptions.TrimEntries
                ))
            {
                int separador = linea.LastIndexOf('\t');

                if (separador <= 0 || separador >= linea.Length - 1)
                {
                    continue;
                }

                string ruta = linea[..separador];
                bool esDirectorio = linea[(separador + 1)..] == "D";
                string rutaEscapada = EscaparRutaParaTerminal(ruta);

                if (esDirectorio)
                {
                    rutaEscapada += "/";
                }

                resultados.Add(rutaEscapada);
            }

            return resultados
                .Distinct(StringComparer.Ordinal)
                .OrderBy(ruta => ruta, StringComparer.Ordinal)
                .Take(500)
                .ToArray();
        }
        finally
        {
            _bloqueoAutocompletado.Release();
        }
    }

    private static string EscaparArgumentoShell(string valor)
    {
        return "'" + valor.Replace("'", "'\"'\"'") + "'";
    }

    private static string EscaparRutaParaTerminal(string ruta)
    {
        const string especiales = " \t\\'\"$`!()[]{}&;|<>*?";
        StringBuilder resultado = new(ruta.Length + 8);

        foreach (char caracter in ruta)
        {
            if (especiales.Contains(caracter))
            {
                resultado.Append('\\');
            }

            resultado.Append(caracter);
        }

        return resultado.ToString();
    }

    public async Task DesconectarAsync()
    {
        ThrowIfDisposed();

        await _bloqueoConexion.WaitAsync();

        try
        {
            await DesconectarInternoAsync(notificar: false);
        }
        finally
        {
            _bloqueoConexion.Release();
        }
    }

    private async Task LeerSalidaAsync(
        ShellStream shell,
        CancellationToken cancellationToken)
    {
        byte[] buffer = new byte[4096];

        try
        {
            while (!cancellationToken.IsCancellationRequested)
            {
                int cantidad = await shell.ReadAsync(
                    buffer.AsMemory(0, buffer.Length),
                    cancellationToken
                );

                if (cantidad <= 0)
                {
                    break;
                }

                string texto = Encoding.UTF8.GetString(
                    buffer,
                    0,
                    cantidad
                );

                DatosRecibidos?.Invoke(this, texto);
            }
        }
        catch (OperationCanceledException)
        {
            // Cierre solicitado por el usuario.
        }
        catch (ObjectDisposedException)
        {
            // El stream se cerró mientras esperaba datos.
        }
        catch (IOException ex)
        {
            if (!cancellationToken.IsCancellationRequested)
            {
                ErrorOcurrido?.Invoke(this, ex.Message);
            }
        }
        catch (Exception ex)
        {
            if (!cancellationToken.IsCancellationRequested)
            {
                ErrorOcurrido?.Invoke(this, ex.Message);
            }
        }
        finally
        {
            if (!cancellationToken.IsCancellationRequested)
            {
                ConexionCerrada?.Invoke(this, EventArgs.Empty);
            }
        }
    }

    private async Task DesconectarInternoAsync(bool notificar)
    {
        CancellationTokenSource? cancellation = _lecturaCancellation;
        Task? tareaLectura = _tareaLectura;
        ShellStream? shell = _shell;
        SshClient? cliente = _cliente;

        _lecturaCancellation = null;
        _tareaLectura = null;
        _shell = null;
        _cliente = null;
        _comandosDisponibles = null;

        try
        {
            cancellation?.Cancel();
        }
        catch
        {
            // La cancelación es de mejor esfuerzo.
        }

        try
        {
            shell?.Dispose();
        }
        catch
        {
            // El canal puede haberse cerrado desde el servidor.
        }

        try
        {
            if (cliente?.IsConnected == true)
            {
                cliente.Disconnect();
            }
        }
        catch
        {
            // El servidor puede haber desaparecido.
        }
        finally
        {
            cliente?.Dispose();
            cancellation?.Dispose();
        }

        if (tareaLectura is not null)
        {
            try
            {
                await tareaLectura.WaitAsync(TimeSpan.FromSeconds(1));
            }
            catch
            {
                // No bloquear el cierre de la interfaz.
            }
        }

        if (notificar)
        {
            ConexionCerrada?.Invoke(this, EventArgs.Empty);
        }
    }

    private static void ValidarConfiguracion(
        ConfiguracionSsh configuracion)
    {
        if (string.IsNullOrWhiteSpace(configuracion.Servidor))
        {
            throw new ArgumentException(
                "Debes escribir la IP o el nombre del servidor."
            );
        }

        if (configuracion.Puerto is < 1 or > 65535)
        {
            throw new ArgumentOutOfRangeException(
                nameof(configuracion.Puerto),
                "El puerto SSH debe estar entre 1 y 65535."
            );
        }

        if (string.IsNullOrWhiteSpace(configuracion.Usuario))
        {
            throw new ArgumentException(
                "Debes escribir el usuario SSH."
            );
        }

        if (string.IsNullOrEmpty(configuracion.Contrasena))
        {
            throw new ArgumentException(
                "Debes escribir la contraseña SSH."
            );
        }
    }

    private static string TraducirErrorConexion(Exception ex)
    {
        return ex switch
        {
            SshAuthenticationException =>
                "El servidor rechazó el usuario o la contraseña.",
            SshOperationTimeoutException =>
                "La conexión SSH agotó el tiempo de espera.",
            SshConnectionException =>
                $"No fue posible establecer la conexión SSH: {ex.Message}",
            System.Net.Sockets.SocketException =>
                $"No fue posible alcanzar el servidor: {ex.Message}",
            OperationCanceledException =>
                "La conexión SSH fue cancelada.",
            _ => $"No fue posible conectar por SSH: {ex.Message}"
        };
    }

    private void ThrowIfDisposed()
    {
        ObjectDisposedException.ThrowIf(_disposed, this);
    }

    public async ValueTask DisposeAsync()
    {
        if (_disposed)
        {
            return;
        }

        await _bloqueoConexion.WaitAsync();

        try
        {
            await DesconectarInternoAsync(notificar: false);
            _disposed = true;
        }
        finally
        {
            _bloqueoConexion.Release();
        }

        _bloqueoEnvio.Dispose();
        _bloqueoConexion.Dispose();
        _bloqueoAutocompletado.Dispose();
    }
}
