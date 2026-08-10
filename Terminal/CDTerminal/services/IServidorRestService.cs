using System.Threading;
using System.Threading.Tasks;
using CDTerminal.Models;

namespace CDTerminal.Services;

public interface IServidorRestService
{
    ConfiguracionServidorRest ObtenerConfiguracion();

    Task GuardarConfiguracionAsync(
        ConfiguracionServidorRest configuracion,
        CancellationToken cancellationToken = default);

    Task<ResultadoServidorRest> ProbarConexionAsync(
        ConfiguracionServidorRest configuracion,
        CancellationToken cancellationToken = default);

    Task<ResultadoServidorRest> EnviarLecturaAsync(
        ConfiguracionServidorRest configuracion,
        LecturaServidorRest lectura,
        CancellationToken cancellationToken = default);
}
