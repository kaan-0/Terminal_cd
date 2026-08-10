<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Sensor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $esAdministrador = $usuario->esAdministrador();

        $clientes = $this->obtenerClientesPermitidos(
            $usuario,
            $esAdministrador
        );

        $cliente = $this->seleccionarCliente(
            $request,
            $clientes,
            $esAdministrador
        );

        $dispositivos = $cliente
            ? $cliente
                ->dispositivos()
                ->where('activo', true)
                ->orderBy('nombre')
                ->orderBy('codigo')
                ->get()
            : collect();

        $dispositivoSeleccionado = $this->seleccionarDispositivo(
            $request,
            $dispositivos
        );

        $sensores = collect();
        $resumenSensores = collect();
        $sensorSeleccionado = null;
        $ultimaMedicion = null;
        $lecturasActuales = collect();
        $lecturaSeleccionada = null;
        $lecturaSeleccionadaDatos = null;
        $medicionesGrafica = collect();
        $historial = null;

        if ($dispositivoSeleccionado) {
            $sensores = $dispositivoSeleccionado
                ->sensores()
                ->where('activo', true)
                ->with(['lecturas', 'ultimaMedicion.valores'])
                ->orderBy('ranura')
                ->get();

            $resumenSensores = $sensores->mapWithKeys(
                function (Sensor $sensor): array {
                    $lecturas = $this
                        ->obtenerLecturasMedicion(
                            $sensor->ultimaMedicion,
                            $sensor
                        )
                        ->where('activo', true)
                        ->values();

                    return [
                        $sensor->id => [
                            'lecturas' => $lecturas->take(2)->values(),
                            'total' => $lecturas->count(),
                        ],
                    ];
                }
            );

            $sensorSeleccionado = $this->seleccionarSensor(
                $request,
                $sensores
            );
        }

        if ($sensorSeleccionado) {
            $sensorSeleccionado->loadMissing('lecturas');

            $ultimaMedicion = $sensorSeleccionado
                ->mediciones()
                ->with('valores')
                ->latest('fecha_recepcion')
                ->first();

            $lecturasActuales = $this
                ->obtenerLecturasMedicion(
                    $ultimaMedicion,
                    $sensorSeleccionado
                )
                ->where('activo', true)
                ->values();

            $lecturaSeleccionada = $this->seleccionarLectura(
                $request,
                $lecturasActuales
            );

            $lecturaSeleccionadaDatos = $lecturasActuales
                ->firstWhere('indice', $lecturaSeleccionada);

            $medicionesRecientes = $sensorSeleccionado
                ->mediciones()
                ->with('valores')
                ->orderByDesc('fecha_recepcion')
                ->limit(50)
                ->get()
                ->sortBy('fecha_recepcion')
                ->values();

            if ($lecturaSeleccionada !== null) {
                $medicionesGrafica = $medicionesRecientes
                    ->map(function ($medicion) use (
                        $sensorSeleccionado,
                        $lecturaSeleccionada
                    ): ?array {
                        $lectura = $this
                            ->obtenerLecturasMedicion(
                                $medicion,
                                $sensorSeleccionado
                            )
                            ->firstWhere(
                                'indice',
                                $lecturaSeleccionada
                            );

                        if (!$lectura || !$lectura['activo']) {
                            return null;
                        }

                        return [
                            'fecha' => $medicion->fecha_recepcion,
                            'valor' => $lectura['valor'],
                        ];
                    })
                    ->filter()
                    ->values();
            }

            $historial = $sensorSeleccionado
                ->mediciones()
                ->with('valores')
                ->orderByDesc('fecha_recepcion')
                ->paginate(20)
                ->withQueryString();
        }

        return view('dashboard.index', [
            'usuario' => $usuario,
            'esAdministrador' => $esAdministrador,
            'clientes' => $clientes,
            'cliente' => $cliente,
            'dispositivos' => $dispositivos,
            'dispositivoSeleccionado' =>
                $dispositivoSeleccionado,
            'sensores' => $sensores,
            'resumenSensores' => $resumenSensores,
            'sensorSeleccionado' => $sensorSeleccionado,
            'ultimaMedicion' => $ultimaMedicion,
            'lecturasActuales' => $lecturasActuales,
            'lecturaSeleccionada' => $lecturaSeleccionada,
            'lecturaSeleccionadaDatos' =>
                $lecturaSeleccionadaDatos,
            'medicionesGrafica' => $medicionesGrafica,
            'historial' => $historial,
        ]);
    }

    private function obtenerClientesPermitidos(
        $usuario,
        bool $esAdministrador
    ): Collection {
        if ($esAdministrador) {
            return Cliente::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->orderBy('codigo')
                ->get();
        }

        $cliente = $usuario->cliente;

        return $cliente && $cliente->activo
            ? collect([$cliente])
            : collect();
    }

    private function seleccionarCliente(
        Request $request,
        Collection $clientes,
        bool $esAdministrador
    ) {
        if ($clientes->isEmpty()) {
            return null;
        }

        if (!$esAdministrador) {
            return $clientes->first();
        }

        $codigoSolicitado = $request->query('cliente');
        $codigoPredeterminado = env(
            'DASHBOARD_CLIENT_CODE',
            'CLI-HN-0001'
        );

        $cliente = $codigoSolicitado
            ? $clientes->firstWhere('codigo', $codigoSolicitado)
            : null;

        $cliente ??= $clientes->firstWhere(
            'codigo',
            $codigoPredeterminado
        );

        return $cliente ?? $clientes->first();
    }

    private function seleccionarDispositivo(
        Request $request,
        Collection $dispositivos
    ) {
        if ($dispositivos->isEmpty()) {
            return null;
        }

        $codigo = $request->query('dispositivo');

        return ($codigo
            ? $dispositivos->firstWhere('codigo', $codigo)
            : null) ?? $dispositivos->first();
    }

    private function seleccionarSensor(
        Request $request,
        Collection $sensores
    ): ?Sensor {
        if ($sensores->isEmpty()) {
            return null;
        }

        $ranura = $request->query('sensor');

        if ($ranura !== null && is_numeric($ranura)) {
            $sensor = $sensores->firstWhere('ranura', (int) $ranura);

            if ($sensor) {
                return $sensor;
            }
        }

        return $sensores->first();
    }

    private function seleccionarLectura(
        Request $request,
        Collection $lecturas
    ): ?int {
        if ($lecturas->isEmpty()) {
            return null;
        }

        $indice = $request->query('lectura');

        if (
            $indice !== null &&
            is_numeric($indice) &&
            $lecturas->contains('indice', (int) $indice)
        ) {
            return (int) $indice;
        }

        // Compatibilidad con enlaces de la versión anterior.
        $registro = $request->query('registro');

        if ($registro !== null && is_numeric($registro)) {
            $lectura = $lecturas->firstWhere(
                'registro',
                (int) $registro
            );

            if ($lectura) {
                return (int) $lectura['indice'];
            }
        }

        return (int) $lecturas->first()['indice'];
    }

    private function obtenerLecturasMedicion(
        $medicion,
        ?Sensor $sensor
    ): Collection {
        if (!$medicion || !$sensor) {
            return collect();
        }

        $sensor->loadMissing('lecturas');
        $configuraciones = $sensor->lecturas->keyBy('indice');

        if ($medicion->valores->isNotEmpty()) {
            return $medicion->valores
                ->sortBy('indice')
                ->map(function ($valor) use (
                    $sensor,
                    $configuraciones
                ): array {
                    return $this->crearLecturaPresentacion(
                        $sensor,
                        (int) $valor->indice,
                        (int) $valor->registro,
                        (int) $valor->valor,
                        $configuraciones->get((int) $valor->indice)
                    );
                })
                ->values();
        }

        return collect([
            $this->crearLecturaPresentacion(
                $sensor,
                0,
                (int) ($medicion->registro_inicial ?? 0),
                (int) $medicion->valor,
                $configuraciones->get(0)
            ),
        ]);
    }

    private function crearLecturaPresentacion(
        Sensor $sensor,
        int $indice,
        int $registro,
        int $valorCrudo,
        $configuracion
    ): array {
        $factor = $configuracion
            ? (float) $configuracion->factor
            : 1.0;
        $ajuste = $configuracion
            ? (float) $configuracion->ajuste
            : 0.0;

        return [
            'indice' => $indice,
            'registro' => $registro,
            'nombre' => $configuracion?->nombre
                ?: ($sensor->cantidad_registros === 1
                    ? $sensor->nombre
                    : 'Lectura '.($indice + 1)),
            'unidad' => $configuracion?->unidad
                ?: ($indice === 0 ? $sensor->unidad : null),
            'valor_crudo' => $valorCrudo,
            'valor' => ($valorCrudo * $factor) + $ajuste,
            'factor' => $factor,
            'ajuste' => $ajuste,
            'decimales' => (int) ($configuracion?->decimales ?? 0),
            'activo' => (bool) ($configuracion?->activo ?? true),
        ];
    }
}
