<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispositivo;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MedicionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $dispositivo = $this->autenticarDispositivo($request);

        if ($dispositivo instanceof JsonResponse) {
            return $dispositivo;
        }

        $validator = Validator::make(
            $request->all(),
            $this->reglasValidacion()
        );

        $validator->after(function ($validator) use ($request): void {
            $sensores = $request->input('sensores');
            $modbus = $request->input('modbus');
            $tieneValor =
                $request->exists('valor') &&
                $request->input('valor') !== null;

            if (!is_array($sensores) && !is_array($modbus) && !$tieneValor) {
                $validator->errors()->add(
                    'medicion',
                    'Debe enviar sensores, modbus o valor'
                );
            }

            if (is_array($sensores)) {
                $ranuras = [];

                foreach ($sensores as $indice => $sensor) {
                    if (!is_array($sensor)) {
                        continue;
                    }

                    $ranura = $sensor['ranura'] ?? null;
                    $cantidad = $sensor['cantidad'] ?? null;
                    $registros = $sensor['registros'] ?? null;

                    if (is_numeric($ranura)) {
                        $ranura = (int) $ranura;

                        if (in_array($ranura, $ranuras, true)) {
                            $validator->errors()->add(
                                "sensores.$indice.ranura",
                                'No puede repetir la misma ranura'
                            );
                        }

                        $ranuras[] = $ranura;
                    }

                    if (
                        is_numeric($cantidad) &&
                        is_array($registros) &&
                        (int) $cantidad !== count($registros)
                    ) {
                        $validator->errors()->add(
                            "sensores.$indice.registros",
                            'La cantidad no coincide con los registros recibidos'
                        );
                    }
                }
            }

            if (is_array($modbus)) {
                $cantidad = $modbus['cantidad'] ?? null;
                $registros = $modbus['registros'] ?? null;

                if (
                    is_numeric($cantidad) &&
                    is_array($registros) &&
                    (int) $cantidad !== count($registros)
                ) {
                    $validator->errors()->add(
                        'modbus.registros',
                        'La cantidad no coincide con los registros recibidos'
                    );
                }
            }
        });

        $datos = $validator->validate();
        $lecturas = $this->normalizarLecturas($datos);
        $fechaRecepcion = now();

        $mediciones = DB::transaction(function () use (
            $dispositivo,
            $lecturas,
            $fechaRecepcion
        ): Collection {
            $mediciones = collect();

            foreach ($lecturas as $lectura) {
                $sensor = $this->registrarSensor(
                    $dispositivo,
                    $lectura,
                    $fechaRecepcion
                );

                $registros = array_values($lectura['registros']);
                $valorPrincipal = $registros[0];

                $medicion = $sensor->mediciones()->create([
                    'dispositivo_id' => $dispositivo->id,
                    'valor' => $valorPrincipal,
                    'baudrate' => $lectura['baudrate'],
                    'paridad' => $lectura['paridad'],
                    'slave' => $lectura['slave'],
                    'funcion' => $lectura['funcion'],
                    'registro_inicial' => $lectura['registro_inicial'],
                    'cantidad_registros' => count($registros),
                    'fecha_recepcion' => $fechaRecepcion,
                ]);

                foreach ($registros as $indice => $valor) {
                    $medicion->valores()->create([
                        'indice' => $indice,
                        'registro' =>
                            ($lectura['registro_inicial'] ?? 0) + $indice,
                        'valor' => $valor,
                    ]);
                }

                $mediciones->push(
                    $medicion->load(['sensor', 'valores'])
                );
            }

            $dispositivo->update([
                'ultima_conexion' => $fechaRecepcion,
            ]);

            return $mediciones;
        });

        $respuestaSensores = $mediciones
            ->map(fn ($medicion): array => $this->formatearMedicion($medicion))
            ->values();

        return response()->json([
            'ok' => true,
            'mensaje' => $mediciones->count().' sensor(es) almacenado(s)',
            'dispositivo' => $dispositivo->codigo,
            'sensores_recibidos' => $mediciones->count(),
            'sensores' => $respuestaSensores,
            // Compatibilidad con integraciones que esperaban una sola medición.
            'medicion' => $respuestaSensores->first(),
            'fecha_recepcion' => $fechaRecepcion->format('Y-m-d H:i:s'),
        ], 201);
    }

    private function autenticarDispositivo(
        Request $request
    ): Dispositivo|JsonResponse {
        $codigo = trim((string) $request->header('X-Device-ID'));
        $token = trim((string) $request->header('X-Device-Token'));

        if ($codigo === '' || $token === '') {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Credenciales del dispositivo incompletas',
            ], 401);
        }

        $dispositivo = Dispositivo::query()
            ->with('cliente')
            ->where('codigo', $codigo)
            ->first();

        if (
            !$dispositivo ||
            !Hash::check($token, $dispositivo->token_hash)
        ) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Credenciales del dispositivo incorrectas',
            ], 401);
        }

        if (!$dispositivo->activo) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'El dispositivo esta desactivado',
            ], 403);
        }

        if (!$dispositivo->cliente?->activo) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'El cliente esta desactivado',
            ], 403);
        }

        return $dispositivo;
    }

    private function reglasValidacion(): array
    {
        return [
            'valor' => ['nullable', 'integer', 'between:0,65535'],

            'rs485' => ['nullable', 'array', 'required_with:sensores'],
            'rs485.baudrate' => [
                'required_with:rs485',
                'integer',
                'between:1200,115200',
            ],
            'rs485.paridad' => [
                'required_with:rs485',
                'string',
                'in:N,E,O',
            ],

            'sensores' => ['nullable', 'array', 'min:1', 'max:4'],
            'sensores.*' => ['array'],
            'sensores.*.ranura' => [
                'required',
                'integer',
                'between:1,4',
            ],
            'sensores.*.slave' => [
                'required',
                'integer',
                'between:1,247',
            ],
            'sensores.*.funcion' => [
                'required',
                'integer',
                'in:3,4',
            ],
            'sensores.*.registro_inicial' => [
                'required',
                'integer',
                'between:0,65535',
            ],
            'sensores.*.cantidad' => [
                'required',
                'integer',
                'between:1,16',
            ],
            'sensores.*.registros' => [
                'required',
                'array',
                'min:1',
                'max:16',
            ],
            'sensores.*.registros.*' => [
                'integer',
                'between:0,65535',
            ],

            // Formato anterior de un solo sensor.
            'modbus' => ['nullable', 'array'],
            'modbus.baudrate' => [
                'required_with:modbus',
                'integer',
                'between:1200,115200',
            ],
            'modbus.paridad' => [
                'required_with:modbus',
                'string',
                'in:N,E,O',
            ],
            'modbus.slave' => [
                'required_with:modbus',
                'integer',
                'between:1,247',
            ],
            'modbus.funcion' => [
                'required_with:modbus',
                'integer',
                'in:3,4',
            ],
            'modbus.registro_inicial' => [
                'required_with:modbus',
                'integer',
                'between:0,65535',
            ],
            'modbus.cantidad' => [
                'required_with:modbus',
                'integer',
                'between:1,16',
            ],
            'modbus.registros' => [
                'required_with:modbus',
                'array',
                'min:1',
                'max:16',
            ],
            'modbus.registros.*' => [
                'integer',
                'between:0,65535',
            ],
        ];
    }

    private function normalizarLecturas(array $datos): array
    {
        if (!empty($datos['sensores'])) {
            $baudrate = $datos['rs485']['baudrate'];
            $paridad = $datos['rs485']['paridad'];

            return array_map(
                fn (array $sensor): array => [
                    'ranura' => (int) $sensor['ranura'],
                    'baudrate' => (int) $baudrate,
                    'paridad' => $paridad,
                    'slave' => (int) $sensor['slave'],
                    'funcion' => (int) $sensor['funcion'],
                    'registro_inicial' =>
                        (int) $sensor['registro_inicial'],
                    'registros' => array_map(
                        'intval',
                        array_values($sensor['registros'])
                    ),
                ],
                array_values($datos['sensores'])
            );
        }

        if (!empty($datos['modbus'])) {
            $modbus = $datos['modbus'];

            return [[
                'ranura' => 1,
                'baudrate' => (int) $modbus['baudrate'],
                'paridad' => $modbus['paridad'],
                'slave' => (int) $modbus['slave'],
                'funcion' => (int) $modbus['funcion'],
                'registro_inicial' =>
                    (int) $modbus['registro_inicial'],
                'registros' => array_map(
                    'intval',
                    array_values($modbus['registros'])
                ),
            ]];
        }

        return [[
            'ranura' => 1,
            'baudrate' => null,
            'paridad' => null,
            'slave' => null,
            'funcion' => null,
            'registro_inicial' => 0,
            'registros' => [(int) $datos['valor']],
        ]];
    }

    private function registrarSensor(
        Dispositivo $dispositivo,
        array $lectura,
        $fechaRecepcion
    ): Sensor {
        $sensor = $dispositivo->sensores()->firstOrNew([
            'ranura' => $lectura['ranura'],
        ]);

        if (!$sensor->exists) {
            $sensor->nombre = 'Sensor '.$lectura['ranura'];
            $sensor->activo = true;
        }

        $sensor->fill([
            'slave' => $lectura['slave'],
            'funcion' => $lectura['funcion'],
            'registro_inicial' => $lectura['registro_inicial'],
            'cantidad_registros' => count($lectura['registros']),
            'ultima_conexion' => $fechaRecepcion,
        ]);

        $sensor->save();

        $this->sincronizarLecturasSensor(
            $sensor,
            count($lectura['registros'])
        );

        return $sensor;
    }

    private function sincronizarLecturasSensor(
        Sensor $sensor,
        int $cantidad
    ): void {
        $cantidad = max(1, min(16, $cantidad));

        for ($indice = 0; $indice < $cantidad; $indice++) {
            $sensor->lecturas()->firstOrCreate(
                ['indice' => $indice],
                [
                    'nombre' => $cantidad === 1
                        ? $sensor->nombre
                        : 'Lectura '.($indice + 1),
                    'unidad' => $indice === 0
                        ? $sensor->unidad
                        : null,
                    'factor' => 1,
                    'ajuste' => 0,
                    'decimales' => 0,
                    'activo' => true,
                ]
            );
        }
    }

    private function formatearMedicion($medicion): array
    {
        return [
            'id' => $medicion->id,
            'sensor_id' => $medicion->sensor_id,
            'ranura' => $medicion->sensor?->ranura,
            'sensor' => $medicion->sensor?->nombre,
            'valor' => $medicion->valor,
            'modbus' => [
                'baudrate' => $medicion->baudrate,
                'paridad' => $medicion->paridad,
                'slave' => $medicion->slave,
                'funcion' => $medicion->funcion,
                'registro_inicial' => $medicion->registro_inicial,
                'cantidad' => $medicion->cantidad_registros,
                'registros' => $medicion
                    ->valores
                    ->pluck('valor')
                    ->values(),
            ],
            'fecha_recepcion' =>
                $medicion->fecha_recepcion?->format('Y-m-d H:i:s'),
        ];
    }
}
