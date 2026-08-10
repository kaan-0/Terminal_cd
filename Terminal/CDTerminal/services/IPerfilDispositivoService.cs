using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;
using CDTerminal.Models;

namespace CDTerminal.Services;

public interface IPerfilDispositivoService
{
    IReadOnlyList<PerfilDispositivo> ObtenerPerfiles();

    Task GuardarAsync(
        PerfilDispositivo perfil,
        CancellationToken cancellationToken = default);

    Task EliminarAsync(
        Guid id,
        CancellationToken cancellationToken = default);
}