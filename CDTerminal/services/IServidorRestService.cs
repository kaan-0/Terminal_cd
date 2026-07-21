using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;
using CDTerminal.Models;

namespace CDTerminal.Services;

public interface IServidorRestService
{
    IReadOnlyList<ConfiguracionServidorRest> ObtenerDestinos();

    Task GuardarDestinoAsync(
        ConfiguracionServidorRest destino,
        CancellationToken cancellationToken = default);

    Task EliminarDestinoAsync(
        Guid destinoId,
        CancellationToken cancellationToken = default);

    Task<ResultadoServidorRest> ProbarConexionAsync(
        ConfiguracionServidorRest configuracion,
        CancellationToken cancellationToken = default);

    Task<ResultadoServidorRest> EnviarLecturaAsync(
        ConfiguracionServidorRest configuracion,
        LecturaServidorRest lectura,
        CancellationToken cancellationToken = default);
}
