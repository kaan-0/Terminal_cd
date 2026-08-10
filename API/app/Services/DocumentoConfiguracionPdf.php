<?php

namespace App\Services;

use RuntimeException;

final class DocumentoConfiguracionPdf
{
    /**
     * Genera una ficha con únicamente la información registrada
     * en el panel administrativo. No añade comandos ni valores de red.
     *
     * @param array<string, mixed> $datos
     */
    public function generar(array $datos): string
    {
        $pdf = new PdfProvisionamientoSimple();

        $cliente = (array) ($datos['cliente'] ?? []);
        $equipo = (array) ($datos['equipo'] ?? []);
        $acceso = (array) ($datos['acceso'] ?? []);
        $sensores = array_values(array_filter(
            (array) ($datos['sensores'] ?? []),
            static fn ($sensor): bool => is_array($sensor)
        ));

        $azul = [0.04, 0.25, 0.47];
        $verde = [0.05, 0.55, 0.38];
        $grisTexto = [0.20, 0.24, 0.29];
        $grisSuave = [0.95, 0.97, 0.98];
        $amarillo = [1.00, 0.96, 0.82];
        $totalPaginas = 2;

        // -------------------------------------------------------------
        // PÁGINA 1: cliente, equipo y credenciales creadas en el panel
        // -------------------------------------------------------------
        $pdf->nuevaPagina();
        $pdf->rectangulo(0, 758, 595.28, 83.89, $azul, $azul);
        $pdf->texto(42, 811, 'CIRCUITOS Y DESARROLLOS EN TECNOLOGIA', 10, true, [1, 1, 1]);
        $pdf->texto(42, 784, 'FICHA DE CONFIGURACION', 23, true, [1, 1, 1]);
        $pdf->texto(42, 765, 'Datos registrados en el panel administrativo', 11, false, [0.83, 0.91, 0.97]);
        $pdf->texto(430, 790, 'EMITIDO', 8, true, [0.83, 0.91, 0.97]);
        $pdf->texto(430, 775, (string) ($datos['emitido_en'] ?? ''), 9, false, [1, 1, 1]);

        $pdf->tituloSeccion(42, 724, '1. Cliente', $azul);
        $pdf->tarjeta(42, 645, 511, 68, $grisSuave);
        $pdf->etiquetaValor(58, 688, 'Nombre o razón social', (string) ($cliente['nombre'] ?? ''), 300);
        $pdf->etiquetaValor(390, 688, 'Código', (string) ($cliente['codigo'] ?? ''), 145, true);
        $pdf->etiquetaValor(58, 659, 'Estado', !empty($cliente['activo']) ? 'Activo' : 'Inactivo', 200);

        $pdf->tituloSeccion(42, 610, '2. Equipo controlador', $azul);
        $pdf->tarjeta(42, 500, 511, 98, [1, 1, 1], [0.80, 0.85, 0.89]);
        $pdf->etiquetaValor(58, 570, 'Nombre del equipo', (string) ($equipo['nombre'] ?? ''), 230);
        $pdf->etiquetaValor(310, 570, 'Ubicación', (string) ($equipo['ubicacion'] ?? ''), 225);
        $pdf->etiquetaValor(58, 532, 'ID del equipo', (string) ($equipo['codigo'] ?? ''), 300, true);
        $pdf->etiquetaValor(390, 532, 'Estado', !empty($equipo['activo']) ? 'Activo' : 'Inactivo', 145);

        $pdf->tituloSeccion(42, 465, '3. Credenciales para la terminal', $azul);
        $pdf->tarjeta(42, 310, 511, 142, [1, 1, 1], [0.80, 0.85, 0.89]);
        $pdf->texto(58, 424, 'ID del dispositivo', 8, true, [0.39, 0.45, 0.52]);
        $pdf->bloqueCodigo(58, 387, 479, 29, (string) ($equipo['codigo'] ?? ''));
        $pdf->texto(58, 364, 'Token del dispositivo', 8, true, [0.39, 0.45, 0.52]);
        $pdf->bloqueCodigo(58, 319, 479, 37, (string) ($equipo['token'] ?? ''), 9);

        $pdf->tarjeta(42, 242, 511, 52, $amarillo, [0.92, 0.73, 0.20]);
        $pdf->texto(58, 276, 'DATO CONFIDENCIAL', 9, true, [0.55, 0.38, 0.00]);
        $pdf->parrafo(
            58,
            260,
            'El token se muestra completo únicamente en esta ficha. Entréguelo solamente al personal autorizado.',
            9,
            470,
            12,
            [0.40, 0.31, 0.05]
        );

        if (!empty($acceso['email']) || !empty($acceso['nombre'])) {
            $pdf->tituloSeccion(42, 207, '4. Acceso al dashboard', $azul);
            $pdf->tarjeta(42, 92, 511, 102, $grisSuave);
            $pdf->etiquetaValor(58, 164, 'Usuario', (string) ($acceso['nombre'] ?? ''), 230);
            $pdf->etiquetaValor(310, 164, 'Correo', (string) ($acceso['email'] ?? ''), 225);
            if (!empty($acceso['password_temporal'])) {
                $pdf->etiquetaValor(58, 126, 'Contraseña entregada', (string) $acceso['password_temporal'], 479, true);
            }
        }

        $pdf->piePagina(1, $totalPaginas, 'Ficha de datos creada desde el panel administrativo');

        // -------------------------------------------------------------
        // PÁGINA 2: sensores y lecturas realmente configurados
        // -------------------------------------------------------------
        $pdf->nuevaPagina();
        $pdf->encabezadoPagina(
            'SENSORES CONFIGURADOS',
            'Se muestran únicamente los sensores y lecturas guardados en el panel.',
            $azul
        );

        if ($sensores === []) {
            $pdf->tarjeta(42, 480, 511, 165, $grisSuave, [0.80, 0.85, 0.89]);
            $pdf->texto(58, 610, 'Sin sensores registrados', 15, true, $azul);
            $pdf->parrafo(
                58,
                580,
                'Al momento de emitir esta ficha no había sensores configurados para este equipo en el panel administrativo.',
                10,
                470,
                15,
                $grisTexto
            );
        } else {
            $tops = [720, 560, 400, 240];

            foreach (array_slice($sensores, 0, 4) as $indice => $sensor) {
                $top = $tops[$indice];
                $bottom = $top - 135;
                $activo = !empty($sensor['activo']);
                $ranura = (int) ($sensor['ranura'] ?? ($indice + 1));
                $nombre = trim((string) ($sensor['nombre'] ?? ''));
                $tipo = trim((string) ($sensor['tipo'] ?? ''));
                $lecturas = array_values(array_filter(
                    (array) ($sensor['lecturas'] ?? []),
                    static fn ($lectura): bool => is_array($lectura) && !empty($lectura['activo'])
                ));

                $pdf->tarjeta(42, $bottom, 511, 135, [1, 1, 1], [0.80, 0.85, 0.89]);
                $pdf->rectangulo(42, $top - 34, 511, 34, $activo ? [0.94, 0.98, 0.96] : $grisSuave);
                $pdf->texto(58, $top - 22, 'RANURA '.$ranura, 8, true, $activo ? $verde : [0.43, 0.48, 0.54]);
                $pdf->texto(126, $top - 22, $nombre, 12, true, $azul);
                $pdf->texto(482, $top - 22, $activo ? 'ACTIVO' : 'OCULTO', 8, true, $activo ? $verde : [0.43, 0.48, 0.54]);

                if ($tipo !== '') {
                    $pdf->texto(58, $top - 52, 'Tipo', 7.5, true, [0.42, 0.47, 0.53]);
                    $pdf->parrafo(58, $top - 68, $tipo, 8.5, 155, 10, $grisTexto);
                }
                if (($sensor['slave'] ?? null) !== null) {
                    $pdf->etiquetaValor(235, $top - 52, 'ID esclavo', (string) (int) $sensor['slave'], 70, true);
                }
                if (($sensor['funcion'] ?? null) !== null) {
                    $pdf->etiquetaValor(
                        330,
                        $top - 52,
                        'Función',
                        str_pad((string) (int) $sensor['funcion'], 2, '0', STR_PAD_LEFT),
                        70,
                        true
                    );
                }
                if (($sensor['cantidad_registros'] ?? null) !== null) {
                    $pdf->etiquetaValor(430, $top - 52, 'Cantidad', (string) (int) $sensor['cantidad_registros'], 80, true);
                }
                if (($sensor['registro_inicial'] ?? null) !== null) {
                    $pdf->etiquetaValor(
                        58,
                        $top - 96,
                        'Registro inicial',
                        '0x'.strtoupper(str_pad(dechex((int) $sensor['registro_inicial']), 4, '0', STR_PAD_LEFT)),
                        145,
                        true
                    );
                }

                $nombresLecturas = [];
                foreach ($lecturas as $lectura) {
                    $texto = trim((string) ($lectura['nombre'] ?? ''));
                    $unidad = trim((string) ($lectura['unidad'] ?? ''));
                    if ($texto === '') {
                        continue;
                    }
                    $nombresLecturas[] = $unidad === '' ? $texto : $texto.' ('.$unidad.')';
                }

                $pdf->texto(235, $top - 84, 'Lecturas visibles', 8, true, [0.42, 0.47, 0.53]);
                $pdf->parrafo(
                    235,
                    $top - 101,
                    $nombresLecturas === [] ? 'Ninguna lectura visible configurada.' : implode('  |  ', $nombresLecturas),
                    8.2,
                    295,
                    10,
                    $grisTexto
                );
            }
        }

        $pdf->piePagina(2, $totalPaginas, 'No se añadieron comandos ni valores no registrados');

        return $pdf->salida();
    }
}

final class PdfProvisionamientoSimple
{
    private const ANCHO = 595.28;
    private const ALTO = 841.89;

    /** @var array<int, string> */
    private array $paginas = [];
    private int $paginaActual = -1;

    public function nuevaPagina(): void
    {
        $this->paginas[] = '';
        $this->paginaActual = count($this->paginas) - 1;
    }

    /** @param array{0:float,1:float,2:float} $color */
    public function texto(float $x, float $y, string $texto, float $tamano = 10, bool $negrita = false, array $color = [0, 0, 0], bool $mono = false): void
    {
        $fuente = $mono ? 'F3' : ($negrita ? 'F2' : 'F1');
        $this->comando(sprintf(
            "q %.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET Q\n",
            $color[0],
            $color[1],
            $color[2],
            $fuente,
            $tamano,
            $x,
            $y,
            $this->escaparTexto($texto)
        ));
    }

    /** @param array{0:float,1:float,2:float} $relleno @param array{0:float,1:float,2:float}|null $borde */
    public function rectangulo(float $x, float $y, float $ancho, float $alto, array $relleno, ?array $borde = null, float $grosor = 0.7): void
    {
        $operador = $borde === null ? 'f' : 'B';
        $trazo = $borde ?? $relleno;
        $this->comando(sprintf(
            "q %.3F %.3F %.3F rg %.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re %s Q\n",
            $relleno[0], $relleno[1], $relleno[2],
            $trazo[0], $trazo[1], $trazo[2],
            $grosor,
            $x, $y, $ancho, $alto,
            $operador
        ));
    }

    /** @param array{0:float,1:float,2:float} $relleno @param array{0:float,1:float,2:float}|null $borde */
    public function tarjeta(float $x, float $y, float $ancho, float $alto, array $relleno, ?array $borde = null): void
    {
        $this->rectangulo($x, $y, $ancho, $alto, $relleno, $borde, 0.6);
    }

    /** @param array{0:float,1:float,2:float} $color */
    public function tituloSeccion(float $x, float $y, string $texto, array $color): void
    {
        $this->texto($x, $y, $texto, 12, true, $color);
        $this->rectangulo($x, $y - 9, 34, 2, $color);
    }

    public function etiquetaValor(float $x, float $y, string $etiqueta, string $valor, float $ancho, bool $mono = false): void
    {
        $this->texto($x, $y, $etiqueta, 7.5, true, [0.42, 0.47, 0.53]);
        $lineas = $this->envolver($valor, $ancho, 10, $mono);
        $this->texto($x, $y - 14, $lineas[0] ?? '', 10, false, [0.12, 0.16, 0.20], $mono);
    }

    public function bloqueCodigo(float $x, float $y, float $ancho, float $alto, string $texto, float $tamano = 9.5): void
    {
        $this->tarjeta($x, $y, $ancho, $alto, [0.93, 0.95, 0.97], [0.81, 0.85, 0.89]);
        $lineas = $this->envolver($texto, $ancho - 22, $tamano, true);
        $cursor = $y + $alto - 18;
        foreach (array_slice($lineas, 0, 3) as $linea) {
            $this->texto($x + 11, $cursor, $linea, $tamano, false, [0.12, 0.19, 0.25], true);
            $cursor -= $tamano + 3;
        }
    }

    /** @param array{0:float,1:float,2:float} $color */
    public function parrafo(float $x, float $y, string $texto, float $tamano, float $ancho, float $interlineado, array $color = [0, 0, 0]): float
    {
        foreach ($this->envolver($texto, $ancho, $tamano, false) as $linea) {
            $this->texto($x, $y, $linea, $tamano, false, $color);
            $y -= $interlineado;
        }

        return $y;
    }

    /** @param list<string> $elementos @param array{0:float,1:float,2:float} $color */
    public function lista(float $x, float $y, array $elementos, float $tamano, float $ancho, float $interlineado, array $color): float
    {
        foreach ($elementos as $elemento) {
            $this->texto($x, $y, '•', $tamano + 1, true, [0.05, 0.55, 0.38]);
            $lineas = $this->envolver($elemento, $ancho - 18, $tamano, false);
            foreach ($lineas as $indice => $linea) {
                $this->texto($x + 16, $y - ($indice * ($tamano + 3)), $linea, $tamano, false, $color);
            }
            $y -= max($interlineado, count($lineas) * ($tamano + 3));
        }

        return $y;
    }

    /** @param array{0:float,1:float,2:float} $azul */
    public function encabezadoPagina(string $titulo, string $subtitulo, array $azul): void
    {
        $this->rectangulo(0, 772, self::ANCHO, 69.89, $azul, $azul);
        $this->texto(42, 808, $titulo, 19, true, [1, 1, 1]);
        $this->texto(42, 787, $subtitulo, 9.5, false, [0.84, 0.92, 0.98]);
    }

    /** @param list<string> $comandos @param array{0:float,1:float,2:float} $azul @param array{0:float,1:float,2:float} $verde */
    public function pasoComando(float $y, string $numero, string $titulo, array $comandos, array $azul, array $verde): float
    {
        $alto = 54 + count($comandos) * 32;
        $this->tarjeta(42, $y - $alto + 8, 511, $alto, [1, 1, 1], [0.82, 0.86, 0.90]);
        $this->rectangulo(56, $y - 17, 26, 26, $verde, $verde);
        $this->texto(65, $y - 8, $numero, 11, true, [1, 1, 1]);
        $this->texto(94, $y - 5, $titulo, 11, true, $azul);

        $cursor = $y - 40;
        foreach ($comandos as $comando) {
            $this->bloqueCodigo(58, $cursor - 20, 479, 26, $comando, 8.5);
            $cursor -= 32;
        }

        return $y - $alto - 16;
    }

    public function tablaSensores(float $x, float $y, float $ancho): void
    {
        $columnas = [52, 76, 68, 84, 67, 164];
        $cabeceras = ['Ranura', 'ID slave', 'Función', 'Registro', 'Cantidad', 'Nombre / lecturas'];
        $altoCabecera = 28;
        $altoFila = 36;
        $this->rectangulo($x, $y + 4 * $altoFila, $ancho, $altoCabecera, [0.04, 0.25, 0.47], [0.04, 0.25, 0.47]);

        $cursorX = $x;
        foreach ($cabeceras as $i => $cabecera) {
            $this->texto($cursorX + 5, $y + 4 * $altoFila + 10, $cabecera, 7.5, true, [1, 1, 1]);
            $cursorX += $columnas[$i];
        }

        for ($fila = 0; $fila < 4; $fila++) {
            $filaY = $y + (3 - $fila) * $altoFila;
            $relleno = $fila % 2 === 0 ? [0.97, 0.98, 0.99] : [1, 1, 1];
            $this->rectangulo($x, $filaY, $ancho, $altoFila, $relleno, [0.82, 0.86, 0.90], 0.4);
            $valores = [(string) ($fila + 1), '____', '03 / 04', '0x____', '____', '____________________________'];
            $cursorX = $x;
            foreach ($valores as $i => $valor) {
                $this->texto($cursorX + 6, $filaY + 13, $valor, 8, false, [0.22, 0.27, 0.32], $i === 3);
                $cursorX += $columnas[$i];
            }
        }
    }

    /** @param list<string> $elementos */
    public function checklist(float $x, float $y, array $elementos, int $columnas, float $anchoColumna, float $interlineado): void
    {
        $porColumna = (int) ceil(count($elementos) / $columnas);
        foreach ($elementos as $indice => $elemento) {
            $columna = intdiv($indice, $porColumna);
            $fila = $indice % $porColumna;
            $posX = $x + $columna * ($anchoColumna + 18);
            $posY = $y - $fila * 32;
            $this->rectangulo($posX, $posY - 3, 10, 10, [1, 1, 1], [0.39, 0.50, 0.58], 0.7);
            $lineas = $this->envolver($elemento, $anchoColumna - 20, 8.5, false);
            foreach (array_slice($lineas, 0, 2) as $i => $linea) {
                $this->texto($posX + 17, $posY - $i * 11, $linea, 8.5, false, [0.22, 0.27, 0.32]);
            }
        }
    }

    public function piePagina(int $numero, int $total, string $texto): void
    {
        $this->rectangulo(42, 54, 511, 0.7, [0.82, 0.86, 0.90]);
        $this->texto(42, 37, $texto, 7.5, false, [0.43, 0.48, 0.54]);
        $this->texto(520, 37, 'Página '.$numero.' de '.$total, 7.5, false, [0.43, 0.48, 0.54]);
    }

    public function salida(): string
    {
        if ($this->paginas === []) {
            throw new RuntimeException('No hay páginas para generar el PDF.');
        }

        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $idsPaginas = [];
        foreach ($this->paginas as $indice => $_contenido) {
            $idsPaginas[] = (6 + $indice * 2).' 0 R';
        }
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $idsPaginas).'] /Count '.count($this->paginas).' >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objetos[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';

        foreach ($this->paginas as $indice => $contenido) {
            $paginaId = 6 + $indice * 2;
            $contenidoId = $paginaId + 1;
            $objetos[$paginaId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents %d 0 R >>',
                self::ANCHO,
                self::ALTO,
                $contenidoId
            );
            $objetos[$contenidoId] = "<< /Length ".strlen($contenido)." >>\nstream\n".$contenido."endstream";
        }

        ksort($objetos);
        $salida = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objetos as $id => $objeto) {
            $offsets[$id] = strlen($salida);
            $salida .= $id." 0 obj\n".$objeto."\nendobj\n";
        }

        $inicioXref = strlen($salida);
        $cantidad = max(array_keys($objetos)) + 1;
        $salida .= "xref\n0 {$cantidad}\n";
        $salida .= "0000000000 65535 f \n";
        for ($id = 1; $id < $cantidad; $id++) {
            $salida .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $salida .= "trailer\n<< /Size {$cantidad} /Root 1 0 R >>\n";
        $salida .= "startxref\n{$inicioXref}\n%%EOF";

        return $salida;
    }

    /** @return list<string> */
    private function envolver(string $texto, float $ancho, float $tamano, bool $mono): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
        if ($texto === '') {
            return [''];
        }

        $factor = $mono ? 0.61 : 0.52;
        $maximo = max(5, (int) floor($ancho / ($tamano * $factor)));
        $palabras = preg_split('/\s+/u', $texto) ?: [$texto];
        $lineas = [];
        $linea = '';

        foreach ($palabras as $palabra) {
            if ($this->longitud($palabra) > $maximo) {
                if ($linea !== '') {
                    $lineas[] = $linea;
                    $linea = '';
                }
                while ($this->longitud($palabra) > $maximo) {
                    $lineas[] = $this->cortar($palabra, 0, $maximo);
                    $palabra = $this->cortar($palabra, $maximo);
                }
            }

            $candidata = $linea === '' ? $palabra : $linea.' '.$palabra;
            if ($this->longitud($candidata) <= $maximo) {
                $linea = $candidata;
            } else {
                $lineas[] = $linea;
                $linea = $palabra;
            }
        }

        if ($linea !== '') {
            $lineas[] = $linea;
        }

        return $lineas;
    }

    private function escaparTexto(string $texto): string
    {
        $convertido = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);
        if ($convertido === false) {
            $convertido = $texto;
        }

        return str_replace(
            ["\\", "(", ")", "\r", "\n"],
            ["\\\\", "\\(", "\\)", ' ', ' '],
            $convertido
        );
    }

    private function longitud(string $texto): int
    {
        return count(preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function cortar(string $texto, int $inicio, ?int $longitud = null): string
    {
        $caracteres = preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($caracteres, $inicio, $longitud));
    }

    private function comando(string $comando): void
    {
        if ($this->paginaActual < 0) {
            throw new RuntimeException('Debe crear una página antes de dibujar.');
        }

        $this->paginas[$this->paginaActual] .= $comando;
    }
}
