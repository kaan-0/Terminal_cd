using System;
using System.Diagnostics;
using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using CDTerminal.Models;

namespace CDTerminal.Services;

public sealed class ServidorRestService : IServidorRestService, IDisposable
{
    private readonly HttpClient _httpClient;
    private readonly SemaphoreSlim _bloqueoArchivo = new(1, 1);
    private readonly JsonSerializerOptions _opcionesJson;
    private readonly string _rutaConfiguracion;
    private ConfiguracionServidorRest _configuracion;

    public ServidorRestService()
    {
        string carpeta = Path.Combine(
            Environment.GetFolderPath(
                Environment.SpecialFolder.LocalApplicationData),
            "CDTerminal"
        );

        Directory.CreateDirectory(carpeta);

        _rutaConfiguracion = Path.Combine(
            carpeta,
            "servidor-rest.json"
        );

        _opcionesJson = new JsonSerializerOptions
        {
            PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
            PropertyNameCaseInsensitive = true,
            WriteIndented = true
        };

        _httpClient = new HttpClient
        {
            Timeout = System.Threading.Timeout.InfiniteTimeSpan
        };

        _configuracion = CargarConfiguracion();
    }

    public ConfiguracionServidorRest ObtenerConfiguracion()
    {
        return _configuracion.CrearCopia();
    }

    public async Task GuardarConfiguracionAsync(
        ConfiguracionServidorRest configuracion,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(configuracion);
        ValidarConfiguracion(configuracion, exigirRutaLecturas: false);

        ConfiguracionServidorRest copia = configuracion.CrearCopia();
        copia.UrlBase = copia.UrlBase.Trim();
        copia.RutaPrueba = copia.RutaPrueba.Trim();
        copia.RutaLecturas = copia.RutaLecturas.Trim();
        copia.IdDispositivo = copia.IdDispositivo.Trim();
        copia.TokenBearer = copia.TokenBearer.Trim();
        copia.TimeoutSegundos = Math.Clamp(copia.TimeoutSegundos, 1, 60);
        copia.ActualizadoEn = DateTime.Now;

        await _bloqueoArchivo.WaitAsync(cancellationToken);

        try
        {
            string temporal = _rutaConfiguracion + ".tmp";
            string json = JsonSerializer.Serialize(
                copia,
                _opcionesJson
            );

            await File.WriteAllTextAsync(
                temporal,
                json,
                cancellationToken
            );

            File.Move(
                temporal,
                _rutaConfiguracion,
                overwrite: true
            );

            _configuracion = copia;
        }
        finally
        {
            _bloqueoArchivo.Release();
        }
    }

    public Task<ResultadoServidorRest> ProbarConexionAsync(
        ConfiguracionServidorRest configuracion,
        CancellationToken cancellationToken = default)
    {
        return EjecutarSolicitudAsync(
            configuracion,
            HttpMethod.Get,
            configuracion.RutaPrueba,
            contenidoJson: null,
            exigirRutaLecturas: false,
            cancellationToken: cancellationToken
        );
    }

    public Task<ResultadoServidorRest> EnviarLecturaAsync(
        ConfiguracionServidorRest configuracion,
        LecturaServidorRest lectura,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(lectura);

        string json = JsonSerializer.Serialize(
            lectura,
            _opcionesJson
        );

        return EjecutarSolicitudAsync(
            configuracion,
            HttpMethod.Post,
            configuracion.RutaLecturas,
            json,
            exigirRutaLecturas: true,
            cancellationToken: cancellationToken
        );
    }

    private async Task<ResultadoServidorRest> EjecutarSolicitudAsync(
        ConfiguracionServidorRest configuracion,
        HttpMethod metodo,
        string ruta,
        string? contenidoJson,
        bool exigirRutaLecturas,
        CancellationToken cancellationToken)
    {
        Stopwatch cronometro = Stopwatch.StartNew();

        try
        {
            ValidarConfiguracion(
                configuracion,
                exigirRutaLecturas
            );

            Uri uri = CrearUri(configuracion.UrlBase, ruta);

            using HttpRequestMessage solicitud = new(metodo, uri);
            solicitud.Headers.Accept.Add(
                new MediaTypeWithQualityHeaderValue(
                    "application/json"
                )
            );

            solicitud.Headers.UserAgent.ParseAdd(
                "CDTerminal/1.0"
            );

            if (!string.IsNullOrWhiteSpace(
                    configuracion.IdDispositivo))
            {
                solicitud.Headers.TryAddWithoutValidation(
                    "X-Device-Id",
                    configuracion.IdDispositivo.Trim()
                );
            }

            if (!string.IsNullOrWhiteSpace(
                    configuracion.TokenBearer))
            {
                solicitud.Headers.Authorization =
                    new AuthenticationHeaderValue(
                        "Bearer",
                        configuracion.TokenBearer.Trim()
                    );
            }

            if (contenidoJson is not null)
            {
                solicitud.Content = new StringContent(
                    contenidoJson,
                    Encoding.UTF8,
                    "application/json"
                );
            }

            using CancellationTokenSource timeout =
                CancellationTokenSource.CreateLinkedTokenSource(
                    cancellationToken
                );

            timeout.CancelAfter(
                TimeSpan.FromSeconds(
                    Math.Clamp(
                        configuracion.TimeoutSegundos,
                        1,
                        60
                    )
                )
            );

            using HttpResponseMessage respuesta =
                await _httpClient.SendAsync(
                    solicitud,
                    HttpCompletionOption.ResponseHeadersRead,
                    timeout.Token
                );

            string detalle = await LeerDetalleRespuestaAsync(
                respuesta,
                timeout.Token
            );

            cronometro.Stop();

            if (respuesta.IsSuccessStatusCode)
            {
                string mensaje = string.IsNullOrWhiteSpace(detalle)
                    ? $"HTTP {(int)respuesta.StatusCode} " +
                      respuesta.ReasonPhrase
                    : detalle;

                return new ResultadoServidorRest(
                    true,
                    (int)respuesta.StatusCode,
                    mensaje,
                    cronometro.Elapsed
                );
            }

            string error =
                $"HTTP {(int)respuesta.StatusCode} " +
                $"{respuesta.ReasonPhrase}";

            if (!string.IsNullOrWhiteSpace(detalle))
            {
                error += $": {detalle}";
            }

            return new ResultadoServidorRest(
                false,
                (int)respuesta.StatusCode,
                error,
                cronometro.Elapsed
            );
        }
        catch (OperationCanceledException)
            when (!cancellationToken.IsCancellationRequested)
        {
            cronometro.Stop();

            return new ResultadoServidorRest(
                false,
                null,
                $"Tiempo de espera agotado después de " +
                $"{Math.Clamp(configuracion.TimeoutSegundos, 1, 60)} s.",
                cronometro.Elapsed
            );
        }
        catch (OperationCanceledException)
        {
            throw;
        }
        catch (Exception ex)
        {
            cronometro.Stop();

            return new ResultadoServidorRest(
                false,
                null,
                ex.Message,
                cronometro.Elapsed
            );
        }
    }

    private ConfiguracionServidorRest CargarConfiguracion()
    {
        if (!File.Exists(_rutaConfiguracion))
        {
            return new ConfiguracionServidorRest();
        }

        try
        {
            string json = File.ReadAllText(_rutaConfiguracion);

            return JsonSerializer.Deserialize<ConfiguracionServidorRest>(
                       json,
                       _opcionesJson
                   )
                   ?? new ConfiguracionServidorRest();
        }
        catch
        {
            return new ConfiguracionServidorRest();
        }
    }

    private static void ValidarConfiguracion(
        ConfiguracionServidorRest configuracion,
        bool exigirRutaLecturas)
    {
        ArgumentNullException.ThrowIfNull(configuracion);

        if (!Uri.TryCreate(
                configuracion.UrlBase?.Trim(),
                UriKind.Absolute,
                out Uri? urlBase) ||
            (urlBase.Scheme != Uri.UriSchemeHttp &&
             urlBase.Scheme != Uri.UriSchemeHttps))
        {
            throw new InvalidOperationException(
                "La URL base debe comenzar con http:// o https://."
            );
        }

        if (configuracion.TimeoutSegundos is < 1 or > 60)
        {
            throw new InvalidOperationException(
                "El timeout debe estar entre 1 y 60 segundos."
            );
        }

        if (string.IsNullOrWhiteSpace(
                configuracion.IdDispositivo))
        {
            throw new InvalidOperationException(
                "Escribe un identificador para el dispositivo."
            );
        }

        if (exigirRutaLecturas &&
            string.IsNullOrWhiteSpace(
                configuracion.RutaLecturas))
        {
            throw new InvalidOperationException(
                "Escribe la ruta del endpoint de lecturas."
            );
        }
    }

    private static Uri CrearUri(
        string urlBase,
        string ruta)
    {
        if (Uri.TryCreate(
                ruta?.Trim(),
                UriKind.Absolute,
                out Uri? absoluta))
        {
            return absoluta;
        }

        string baseNormalizada = urlBase.TrimEnd('/') + "/";
        string rutaNormalizada = (ruta ?? string.Empty).TrimStart('/');

        return new Uri(
            new Uri(baseNormalizada, UriKind.Absolute),
            rutaNormalizada
        );
    }

    private static async Task<string> LeerDetalleRespuestaAsync(
        HttpResponseMessage respuesta,
        CancellationToken cancellationToken)
    {
        if (respuesta.Content is null)
        {
            return string.Empty;
        }

        string contenido = await respuesta.Content
            .ReadAsStringAsync(cancellationToken);

        contenido = contenido.Trim();

        const int limite = 500;

        return contenido.Length <= limite
            ? contenido
            : contenido[..limite] + "…";
    }

    public void Dispose()
    {
        _httpClient.Dispose();
        _bloqueoArchivo.Dispose();
    }
}
