using System.Threading;
using System.Threading.Tasks;

namespace CDTerminal.Services;

public interface IFileExportService
{
    string? SeleccionarRutaGuardado(
        string nombreSugerido,
        string extensionPredeterminada,
        string filtro
    );

    Task EscribirTextoAsync(
        string ruta,
        string contenido,
        bool anexar,
        CancellationToken cancellationToken = default
    );
}