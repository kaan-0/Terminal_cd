using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
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
    private readonly string _rutaDestinos;
    private readonly string _rutaConfiguracionAnterior;
    private List<ConfiguracionServidorRest> _destinos;

    public ServidorRestService()
    {
        string carpeta = Path.Combine(
            Environment.GetFolderPath(
                Environment.SpecialFolder.LocalApplicationData),
            "CDTerminal"
        );

        Directory.CreateDirectory(carpeta);

        _rutaDestinos = Path.Combine(
            carpeta,
            "destinos-rest.json"
        );

        _rutaConfiguracionAnterior = Path.Combine(
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

        _destinos = CargarDestinos();
    }

    public IReadOnlyList<ConfiguracionServidorRest> ObtenerDestinos()
    {
        return _destinos
            .OrderByDescending(destino => destino.Activo)
            .ThenBy(destino => destino.Nombre)
            .Select(destino => destino.CrearCopia())
            .ToList();
    }

    public async Task GuardarDestinoAsync(
        ConfiguracionServidorRest destino,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(destino);
        ValidarConfiguracion(destino, exigirRutaLecturas: false);

        ConfiguracionServidorRest copia = Normalizar(destino);

        await _bloqueoArchivo.WaitAsync(cancellationToken);

        try
        {
            List<ConfiguracionServidorRest> actualizados = _destinos
                .Select(item => item.CrearCopia())
                .ToList();

            int indice = actualizados.FindIndex(
                item => item.Id == copia.Id
            );

            if (indice >= 0)
            {
                actualizados[indice] = copia;
            }
            else
            {
                actualizados.Add(copia);
            }

            await GuardarListaAsync(
                actualizados,
                cancellationToken
            );

            _destinos = actualizados;
        }
        finally
        {
            _bloqueoArchivo.Release();
        }
    }

    public async Task EliminarDestinoAsync(
        Guid destinoId,
        CancellationToken cancellationToken = default)
    {
        await _bloqueoArchivo.WaitAsync(cancellationToken);

        try
        {
            List<ConfiguracionServidorRest> actualizados = _destinos
                .Where(destino => destino.Id != destinoId)
                .Select(destino => destino.CrearCopia())
                .ToList();

            await GuardarListaAsync(
                actualizados,
                cancellationToken
            );

            _destinos = actualizados;
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
                "CDTerminal/1.1"
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

    private List<ConfiguracionServidorRest> CargarDestinos()
    {
        if (File.Exists(_rutaDestinos))
        {
            try
            {
                string json = File.ReadAllText(_rutaDestinos);

                return JsonSerializer
                           .Deserialize<List<ConfiguracionServidorRest>>(
                               json,
                               _opcionesJson
                           )?
                           .Where(destino => destino is not null)
                           .Select(Normalizar)
                           .ToList()
                       ?? new List<ConfiguracionServidorRest>();
            }
            catch
            {
                return new List<ConfiguracionServidorRest>();
            }
        }

        return MigrarConfiguracionAnterior();
    }

    private List<ConfiguracionServidorRest> MigrarConfiguracionAnterior()
    {
        if (!File.Exists(_rutaConfiguracionAnterior))
        {
            return new List<ConfiguracionServidorRest>();
        }

        try
        {
            string json = File.ReadAllText(_rutaConfiguracionAnterior);
            ConfiguracionServidorRest? anterior =
                JsonSerializer.Deserialize<ConfiguracionServidorRest>(
                    json,
                    _opcionesJson
                );

            if (anterior is null ||
                string.IsNullOrWhiteSpace(anterior.UrlBase))
            {
                return new List<ConfiguracionServidorRest>();
            }

            anterior.Id = anterior.Id == Guid.Empty
                ? Guid.NewGuid()
                : anterior.Id;
            anterior.Nombre = "Servidor REST anterior";
            anterior.Activo = true;

            List<ConfiguracionServidorRest> migrados =
                new() { Normalizar(anterior) };

            string jsonMigrado = JsonSerializer.Serialize(
                migrados,
                _opcionesJson
            );

            File.WriteAllText(_rutaDestinos, jsonMigrado);
            return migrados;
        }
        catch
        {
            return new List<ConfiguracionServidorRest>();
        }
    }

    private async Task GuardarListaAsync(
        IReadOnlyList<ConfiguracionServidorRest> destinos,
        CancellationToken cancellationToken)
    {
        string temporal = _rutaDestinos + ".tmp";
        string json = JsonSerializer.Serialize(
            destinos,
            _opcionesJson
        );

        await File.WriteAllTextAsync(
            temporal,
            json,
            cancellationToken
        );

        File.Move(
            temporal,
            _rutaDestinos,
            overwrite: true
        );
    }

    private static ConfiguracionServidorRest Normalizar(
        ConfiguracionServidorRest destino)
    {
        ConfiguracionServidorRest copia = destino.CrearCopia();
        copia.Id = copia.Id == Guid.Empty
            ? Guid.NewGuid()
            : copia.Id;
        copia.Nombre = copia.Nombre.Trim();
        copia.UrlBase = copia.UrlBase.Trim();
        copia.RutaPrueba = copia.RutaPrueba.Trim();
        copia.RutaLecturas = copia.RutaLecturas.Trim();
        copia.IdDispositivo = copia.IdDispositivo.Trim();
        copia.TokenBearer = copia.TokenBearer.Trim();
        copia.TimeoutSegundos = Math.Clamp(
            copia.TimeoutSegundos,
            1,
            60
        );
        copia.ActualizadoEn = DateTime.Now;
        return copia;
    }

    private static void ValidarConfiguracion(
        ConfiguracionServidorRest configuracion,
        bool exigirRutaLecturas)
    {
        ArgumentNullException.ThrowIfNull(configuracion);

        if (string.IsNullOrWhiteSpace(configuracion.Nombre))
        {
            throw new InvalidOperationException(
                "Escribe un nombre para el destino."
            );
        }

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
