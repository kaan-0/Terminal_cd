using System;

namespace CDTerminal.Models;

public sealed record ResultadoServidorRest(
    bool Exitoso,
    int? CodigoEstado,
    string Mensaje,
    TimeSpan Duracion
);
