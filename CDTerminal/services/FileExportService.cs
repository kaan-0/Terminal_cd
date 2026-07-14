using System;
using System.IO;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using Microsoft.Win32;

namespace CDTerminal.Services;

public sealed class FileExportService : IFileExportService
{
    private static readonly Encoding Utf8ConBom =
        new UTF8Encoding(encoderShouldEmitUTF8Identifier: true);

    public string? SeleccionarRutaGuardado(
        string nombreSugerido,
        string extensionPredeterminada,
        string filtro)
    {
        SaveFileDialog dialogo = new()
        {
            Title = "Guardar archivo de CD Terminal",
            FileName = nombreSugerido,
            DefaultExt = extensionPredeterminada,
            Filter = filtro,
            AddExtension = true,
            OverwritePrompt = true,
            CheckPathExists = true
        };

        bool? resultado = System.Windows.Application.Current?.MainWindow
            is { } ventana
                ? dialogo.ShowDialog(ventana)
                : dialogo.ShowDialog();

        return resultado == true
            ? dialogo.FileName
            : null;
    }

    public async Task EscribirTextoAsync(
        string ruta,
        string contenido,
        bool anexar,
        CancellationToken cancellationToken = default)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(ruta);
        ArgumentNullException.ThrowIfNull(contenido);

        string? directorio = Path.GetDirectoryName(ruta);

        if (!string.IsNullOrWhiteSpace(directorio))
        {
            Directory.CreateDirectory(directorio);
        }

        FileMode modo = anexar
            ? FileMode.Append
            : FileMode.Create;

        await using FileStream flujo = new(
            ruta,
            modo,
            FileAccess.Write,
            FileShare.Read,
            bufferSize: 4096,
            useAsync: true
        );

        await using StreamWriter escritor = new(
            flujo,
            Utf8ConBom
        );

        await escritor.WriteAsync(
            contenido.AsMemory(),
            cancellationToken
        );
    }
}