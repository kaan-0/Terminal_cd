using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading;
using System.Threading.Tasks;
using CDTerminal.Models;

namespace CDTerminal.Services;

public sealed class PerfilDispositivoService : IPerfilDispositivoService
{
    private readonly SemaphoreSlim _bloqueo = new(1, 1);
    private readonly List<PerfilDispositivo> _perfiles;
    private readonly string _rutaArchivo;
    private readonly JsonSerializerOptions _opcionesJson;

    public PerfilDispositivoService()
    {
        string carpeta = Path.Combine(
            Environment.GetFolderPath(
                Environment.SpecialFolder.LocalApplicationData),
            "CDTerminal"
        );

        Directory.CreateDirectory(carpeta);

        _rutaArchivo = Path.Combine(
            carpeta,
            "perfiles-dispositivos.json"
        );

        _opcionesJson = new JsonSerializerOptions
        {
            WriteIndented = true,
            PropertyNameCaseInsensitive = true
        };

        _opcionesJson.Converters.Add(
            new JsonStringEnumConverter()
        );

        _perfiles = CargarPerfiles();
    }

    public IReadOnlyList<PerfilDispositivo> ObtenerPerfiles()
    {
        return _perfiles
            .OrderBy(perfil => perfil.Nombre)
            .Select(CrearCopia)
            .ToArray();
    }

    public async Task GuardarAsync(
        PerfilDispositivo perfil,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(perfil);

        if (string.IsNullOrWhiteSpace(perfil.Nombre))
        {
            throw new ArgumentException(
                "El perfil debe tener un nombre.",
                nameof(perfil)
            );
        }

        await _bloqueo.WaitAsync(cancellationToken);

        try
        {
            int indice = _perfiles.FindIndex(
                item => item.Id == perfil.Id
            );

            PerfilDispositivo copia = CrearCopia(perfil);
            copia.Nombre = copia.Nombre.Trim();
            copia.Descripcion = copia.Descripcion.Trim();
            copia.ActualizadoEn = DateTime.Now;

            if (indice >= 0)
            {
                copia.CreadoEn = _perfiles[indice].CreadoEn;
                _perfiles[indice] = copia;
            }
            else
            {
                if (copia.Id == Guid.Empty)
                {
                    copia.Id = Guid.NewGuid();
                }

                copia.CreadoEn = DateTime.Now;
                _perfiles.Add(copia);
            }

            await PersistirAsync(cancellationToken);
        }
        finally
        {
            _bloqueo.Release();
        }
    }

    public async Task EliminarAsync(
        Guid id,
        CancellationToken cancellationToken = default)
    {
        await _bloqueo.WaitAsync(cancellationToken);

        try
        {
            _perfiles.RemoveAll(perfil => perfil.Id == id);
            await PersistirAsync(cancellationToken);
        }
        finally
        {
            _bloqueo.Release();
        }
    }

    private List<PerfilDispositivo> CargarPerfiles()
    {
        if (!File.Exists(_rutaArchivo))
        {
            return new List<PerfilDispositivo>();
        }

        try
        {
            string json = File.ReadAllText(_rutaArchivo);

            return JsonSerializer.Deserialize<List<PerfilDispositivo>>(
                       json,
                       _opcionesJson
                   )
                   ?? new List<PerfilDispositivo>();
        }
        catch
        {
            return new List<PerfilDispositivo>();
        }
    }

    private async Task PersistirAsync(
        CancellationToken cancellationToken)
    {
        string temporal = _rutaArchivo + ".tmp";

        string json = JsonSerializer.Serialize(
            _perfiles.OrderBy(perfil => perfil.Nombre),
            _opcionesJson
        );

        await File.WriteAllTextAsync(
            temporal,
            json,
            cancellationToken
        );

        File.Move(
            temporal,
            _rutaArchivo,
            overwrite: true
        );
    }

    private static PerfilDispositivo CrearCopia(
        PerfilDispositivo perfil)
    {
        return new PerfilDispositivo
        {
            Id = perfil.Id,
            Nombre = perfil.Nombre,
            Descripcion = perfil.Descripcion,
            TipoSesion = perfil.TipoSesion,
            PuertoPreferido = perfil.PuertoPreferido,
            Velocidad = perfil.Velocidad,
            BitsDeDatos = perfil.BitsDeDatos,
            Paridad = perfil.Paridad,
            BitsDeParada = perfil.BitsDeParada,
            ControlDeFlujo = perfil.ControlDeFlujo,
            HabilitarDtr = perfil.HabilitarDtr,
            HabilitarRts = perfil.HabilitarRts,
            IdEsclavoModbus = perfil.IdEsclavoModbus,
            TimeoutModbusMs = perfil.TimeoutModbusMs,
            ReintentosModbus = perfil.ReintentosModbus,
            FuncionModbus = perfil.FuncionModbus,
            DireccionInicialModbus = perfil.DireccionInicialModbus,
            CantidadRegistrosModbus = perfil.CantidadRegistrosModbus,
            IntervaloPollingModbusMs = perfil.IntervaloPollingModbusMs,
            DireccionRegistroGrafica = perfil.DireccionRegistroGrafica,
            MaxPuntosGrafica = perfil.MaxPuntosGrafica,
            CreadoEn = perfil.CreadoEn,
            ActualizadoEn = perfil.ActualizadoEn
        };
    }
}