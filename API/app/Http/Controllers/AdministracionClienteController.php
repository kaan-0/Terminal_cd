<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Dispositivo;
use App\Models\Sensor;
use App\Models\User;
use App\Services\DocumentoConfiguracionPdf;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

class AdministracionClienteController extends Controller
{
    use ValidatesRequests;

    public function index(): View
    {
        $clientes = Cliente::query()
            ->with([
                'dispositivos' => fn ($query) => $query
                    ->with(['sensores' => fn ($sensorQuery) =>
                        $sensorQuery
                            ->with('lecturas')
                            ->orderBy('ranura')
                    ])
                    ->orderBy('nombre')
                    ->orderBy('codigo'),
                'usuarios' => fn ($query) => $query
                    ->where('rol', 'cliente')
                    ->orderBy('name')
                    ->orderBy('email'),
            ])
            ->orderBy('nombre')
            ->orderBy('codigo')
            ->get();

        $pdfPendiente = $this->documentoPendienteActual();

        return view('administracion.clientes.index', [
            'clientes' => $clientes,
            'tokenGenerado' => session('token_generado'),
            'clienteAbierto' => session('cliente_abierto'),
            'adminTab' => session('admin_tab', 'equipos'),
            'pdfPendiente' => $pdfPendiente,
        ]);
    }

    public function storeCliente(Request $request): RedirectResponse
    {
        $this->normalizarCodigos($request);
        $this->normalizarEmail($request, 'acceso_email');

        $datos = $this->validateWithBag('nuevoCliente', $request, [
            'cliente_codigo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('clientes', 'codigo'),
            ],
            'cliente_nombre' => [
                'required',
                'string',
                'max:150',
            ],
            'dispositivo_codigo' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('dispositivos', 'codigo'),
            ],
            'dispositivo_nombre' => [
                'required',
                'string',
                'max:150',
            ],
            'dispositivo_ubicacion' => [
                'nullable',
                'string',
                'max:200',
            ],
            'acceso_nombre' => [
                'nullable',
                'required_with:acceso_email,acceso_password',
                'string',
                'max:255',
            ],
            'acceso_email' => [
                'nullable',
                'required_with:acceso_nombre,acceso_password',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'acceso_password' => [
                'nullable',
                'required_with:acceso_nombre,acceso_email',
                'string',
                'min:8',
                'confirmed',
            ],
        ], $this->mensajesValidacion());

        $token = Str::random(64);

        [$cliente, $dispositivo] = DB::transaction(
            function () use ($datos, $token): array {
                $cliente = Cliente::create([
                    'codigo' => $datos['cliente_codigo'],
                    'nombre' => trim($datos['cliente_nombre']),
                    'activo' => true,
                ]);

                $dispositivo = $cliente->dispositivos()->create([
                    'codigo' => $datos['dispositivo_codigo'],
                    'nombre' => trim($datos['dispositivo_nombre']),
                    'ubicacion' => $this->textoOpcional(
                        $datos['dispositivo_ubicacion'] ?? null
                    ),
                    'token_hash' => Hash::make($token),
                    'activo' => true,
                ]);

                if (!empty($datos['acceso_email'])) {
                    $cliente->usuarios()->create([
                        'name' => trim($datos['acceso_nombre']),
                        'email' => $datos['acceso_email'],
                        'password' => $datos['acceso_password'],
                        'rol' => 'cliente',
                        'activo' => true,
                    ]);
                }

                return [$cliente, $dispositivo];
            }
        );

        $this->guardarDocumentoPendiente(
            $cliente,
            $dispositivo,
            $token,
            $datos
        );

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Cliente {$cliente->nombre} y su primer equipo fueron creados correctamente."
            )
            ->with('cliente_abierto', $cliente->id)
            ->with('admin_tab', 'equipos')
            ->with('token_generado', $this->datosToken(
                $cliente,
                $dispositivo,
                $token,
                'Token inicial generado'
            ));
    }

    public function storeDispositivo(
        Request $request,
        Cliente $cliente
    ): RedirectResponse {
        $this->normalizarCodigos($request);

        $datos = $this->validateWithBag(
            'dispositivo_'.$cliente->id,
            $request,
            [
                'codigo' => [
                    'required',
                    'string',
                    'max:50',
                    'regex:/^[A-Z0-9_-]+$/',
                    Rule::unique('dispositivos', 'codigo'),
                ],
                'nombre' => [
                    'required',
                    'string',
                    'max:150',
                ],
                'ubicacion' => [
                    'nullable',
                    'string',
                    'max:200',
                ],
            ],
            $this->mensajesValidacion()
        );

        $token = Str::random(64);

        $dispositivo = $cliente->dispositivos()->create([
            'codigo' => $datos['codigo'],
            'nombre' => trim($datos['nombre']),
            'ubicacion' => $this->textoOpcional(
                $datos['ubicacion'] ?? null
            ),
            'token_hash' => Hash::make($token),
            'activo' => true,
        ]);

        $this->guardarDocumentoPendiente(
            $cliente,
            $dispositivo,
            $token,
            []
        );

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Dispositivo {$dispositivo->nombre} agregado correctamente."
            )
            ->with('cliente_abierto', $cliente->id)
            ->with('admin_tab', 'equipos')
            ->with('token_generado', $this->datosToken(
                $cliente,
                $dispositivo,
                $token,
                'Token inicial generado'
            ));
    }

    public function storeUsuario(
        Request $request,
        Cliente $cliente
    ): RedirectResponse {
        $this->normalizarEmail($request, 'email');

        $datos = $this->validateWithBag(
            'usuario_'.$cliente->id,
            $request,
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email'),
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ],
            $this->mensajesValidacion()
        );

        $usuario = $cliente->usuarios()->create([
            'name' => trim($datos['name']),
            'email' => $datos['email'],
            'password' => $datos['password'],
            'rol' => 'cliente',
            'activo' => true,
        ]);

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Acceso creado para {$usuario->email}."
            )
            ->with('cliente_abierto', $cliente->id)
            ->with('admin_tab', 'accesos');
    }

    public function regenerarToken(
        Dispositivo $dispositivo
    ): RedirectResponse {
        $token = Str::random(64);

        $dispositivo->update([
            'token_hash' => Hash::make($token),
        ]);

        $this->guardarDocumentoPendiente(
            $dispositivo->cliente,
            $dispositivo,
            $token,
            []
        );

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Se regeneró el token de {$dispositivo->nombre}. El token anterior dejó de funcionar."
            )
            ->with('cliente_abierto', $dispositivo->cliente_id)
            ->with('admin_tab', 'equipos')
            ->with('token_generado', $this->datosToken(
                $dispositivo->cliente,
                $dispositivo,
                $token,
                'Token regenerado'
            ));
    }

    public function descargarDocumentoConfiguracion(
        Request $request,
        DocumentoConfiguracionPdf $generador
    ): Response {
        $pendiente = $this->documentoPendienteActual();

        abort_unless(
            is_array($pendiente),
            410,
            'La ficha ya no está disponible. Genere un nuevo token para crear otra.'
        );

        try {
            $token = Crypt::decryptString((string) $pendiente['token_cifrado']);
            $passwordTemporal = !empty($pendiente['password_cifrado'])
                ? Crypt::decryptString((string) $pendiente['password_cifrado'])
                : null;
        } catch (\Throwable) {
            session()->forget('documento_configuracion_pendiente');
            abort(403, 'No fue posible recuperar las credenciales de la ficha.');
        }

        $cliente = Cliente::query()->findOrFail((int) $pendiente['cliente_id']);
        $dispositivo = Dispositivo::query()
            ->with(['sensores' => fn ($query) => $query
                ->with('lecturas')
                ->orderBy('ranura')])
            ->where('cliente_id', $cliente->id)
            ->findOrFail((int) $pendiente['dispositivo_id']);

        $datos = $this->datosDocumentoConfiguracion(
            $cliente,
            $dispositivo,
            $token,
            [
                'acceso_nombre' => $pendiente['acceso_nombre'] ?? null,
                'acceso_email' => $pendiente['acceso_email'] ?? null,
                'acceso_password' => $passwordTemporal,
            ]
        );

        $pdf = $generador->generar($datos);
        $codigo = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            $dispositivo->codigo
        ) ?: 'equipo';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Ficha-'.$codigo.'.pdf"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function cambiarEstadoCliente(
        Cliente $cliente
    ): RedirectResponse {
        $cliente->update([
            'activo' => !$cliente->activo,
        ]);

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Cliente {$cliente->nombre} "
                .($cliente->activo ? 'activado.' : 'desactivado.')
            )
            ->with('cliente_abierto', $cliente->id)
            ->with('admin_tab', 'equipos');
    }

    public function cambiarEstadoDispositivo(
        Dispositivo $dispositivo
    ): RedirectResponse {
        $dispositivo->update([
            'activo' => !$dispositivo->activo,
        ]);

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Dispositivo {$dispositivo->nombre} "
                .($dispositivo->activo ? 'activado.' : 'desactivado.')
            )
            ->with('cliente_abierto', $dispositivo->cliente_id)
            ->with('admin_tab', 'equipos');
    }

    public function guardarSensor(
        Request $request,
        Dispositivo $dispositivo,
        int $ranura
    ): RedirectResponse {
        abort_unless($ranura >= 1 && $ranura <= 4, 404);

        $datos = $this->validateWithBag(
            'sensor_'.$dispositivo->id.'_'.$ranura,
            $request,
            [
                'nombre' => [
                    'required',
                    'string',
                    'max:150',
                ],
                'tipo' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
                'unidad' => [
                    'nullable',
                    'string',
                    'max:30',
                ],
                'slave' => [
                    'required',
                    'integer',
                    'between:1,247',
                ],
                'funcion' => [
                    'required',
                    Rule::in([3, 4]),
                ],
                'registro_inicial' => [
                    'required',
                    'integer',
                    'between:0,65535',
                ],
                'cantidad_registros' => [
                    'required',
                    'integer',
                    'between:1,16',
                ],
            ],
            $this->mensajesValidacion()
        );

        $sensor = $dispositivo->sensores()->firstOrNew([
            'ranura' => $ranura,
        ]);

        if (!$sensor->exists) {
            $sensor->activo = true;
        }

        $sensor->fill([
            'nombre' => trim($datos['nombre']),
            'tipo' => $this->textoOpcional($datos['tipo'] ?? null),
            'unidad' => $this->textoOpcional($datos['unidad'] ?? null),
            'slave' => (int) $datos['slave'],
            'funcion' => (int) $datos['funcion'],
            'registro_inicial' => (int) $datos['registro_inicial'],
            'cantidad_registros' => (int) $datos['cantidad_registros'],
        ]);

        $sensor->save();

        $sensor->lecturas()->firstOrCreate(
            ['indice' => 0],
            [
                'nombre' => $sensor->nombre,
                'unidad' => $sensor->unidad,
                'factor' => 1,
                'ajuste' => 0,
                'decimales' => 0,
                'activo' => true,
            ]
        );

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Ranura {$ranura} de {$dispositivo->nombre} guardada correctamente."
            )
            ->with('cliente_abierto', $dispositivo->cliente_id)
            ->with('admin_tab', 'equipos');
    }

    public function guardarLecturasSensor(
        Request $request,
        Sensor $sensor
    ): RedirectResponse {
        $cantidadPermitida = max(
            1,
            min(16, (int) ($sensor->cantidad_registros ?: 1))
        );

        $datos = $this->validateWithBag(
            'lecturas_sensor_'.$sensor->id,
            $request,
            [
                'lecturas' => [
                    'required',
                    'array',
                    'min:1',
                    'max:16',
                ],
                'lecturas.*.indice' => [
                    'required',
                    'integer',
                    'between:0,15',
                    'distinct',
                ],
                'lecturas.*.nombre' => [
                    'required',
                    'string',
                    'max:100',
                ],
                'lecturas.*.unidad' => [
                    'nullable',
                    'string',
                    'max:30',
                ],
                'lecturas.*.factor' => [
                    'required',
                    'numeric',
                    'between:-1000000,1000000',
                ],
                'lecturas.*.ajuste' => [
                    'required',
                    'numeric',
                    'between:-1000000,1000000',
                ],
                'lecturas.*.decimales' => [
                    'required',
                    'integer',
                    'between:0,6',
                ],
                'lecturas.*.activo' => [
                    'required',
                    'boolean',
                ],
            ],
            $this->mensajesValidacion()
        );

        foreach ($datos['lecturas'] as $lectura) {
            if ((int) $lectura['indice'] >= $cantidadPermitida) {
                return redirect()
                    ->route('admin.clientes.index')
                    ->withErrors(
                        [
                            'lecturas' =>
                                'La lectura no pertenece a la configuración actual del sensor.',
                        ],
                        'lecturas_sensor_'.$sensor->id
                    )
                    ->withInput()
                    ->with('cliente_abierto', $sensor->dispositivo->cliente_id)
                    ->with('admin_tab', 'equipos');
            }
        }

        DB::transaction(function () use ($sensor, $datos): void {
            foreach ($datos['lecturas'] as $lectura) {
                $sensor->lecturas()->updateOrCreate(
                    ['indice' => (int) $lectura['indice']],
                    [
                        'nombre' => trim($lectura['nombre']),
                        'unidad' => $this->textoOpcional(
                            $lectura['unidad'] ?? null
                        ),
                        'factor' => (float) $lectura['factor'],
                        'ajuste' => (float) $lectura['ajuste'],
                        'decimales' => (int) $lectura['decimales'],
                        'activo' => (bool) $lectura['activo'],
                    ]
                );
            }
        });

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Lecturas de {$sensor->nombre} guardadas correctamente."
            )
            ->with('cliente_abierto', $sensor->dispositivo->cliente_id)
            ->with('admin_tab', 'equipos');
    }

    public function cambiarEstadoSensor(
        Sensor $sensor
    ): RedirectResponse {
        $sensor->update([
            'activo' => !$sensor->activo,
        ]);

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Sensor {$sensor->nombre} "
                .($sensor->activo ? 'activado.' : 'desactivado.')
            )
            ->with('cliente_abierto', $sensor->dispositivo->cliente_id)
            ->with('admin_tab', 'equipos');
    }

    public function cambiarEstadoUsuario(
        User $usuario
    ): RedirectResponse {
        $this->comprobarUsuarioCliente($usuario);

        $usuario->update([
            'activo' => !$usuario->activo,
        ]);

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Acceso {$usuario->email} "
                .($usuario->activo ? 'activado.' : 'desactivado.')
            )
            ->with('cliente_abierto', $usuario->cliente_id)
            ->with('admin_tab', 'accesos');
    }

    public function actualizarPasswordUsuario(
        Request $request,
        User $usuario
    ): RedirectResponse {
        $this->comprobarUsuarioCliente($usuario);

        $datos = $this->validateWithBag(
            'password_usuario_'.$usuario->id,
            $request,
            [
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ],
            $this->mensajesValidacion()
        );

        $usuario->update([
            'password' => $datos['password'],
        ]);

        return redirect()
            ->route('admin.clientes.index')
            ->with(
                'success',
                "Contraseña actualizada para {$usuario->email}."
            )
            ->with('cliente_abierto', $usuario->cliente_id)
            ->with('admin_tab', 'accesos');
    }

    private function comprobarUsuarioCliente(User $usuario): void
    {
        abort_unless(
            $usuario->rol === 'cliente' && $usuario->cliente_id !== null,
            404
        );
    }

    private function normalizarCodigos(Request $request): void
    {
        $campos = [
            'cliente_codigo',
            'dispositivo_codigo',
            'codigo',
        ];

        $normalizados = [];

        foreach ($campos as $campo) {
            if ($request->has($campo)) {
                $normalizados[$campo] = strtoupper(
                    trim((string) $request->input($campo))
                );
            }
        }

        $request->merge($normalizados);
    }

    private function normalizarEmail(
        Request $request,
        string $campo
    ): void {
        if ($request->has($campo)) {
            $request->merge([
                $campo => strtolower(
                    trim((string) $request->input($campo))
                ),
            ]);
        }
    }

    private function textoOpcional(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }

    private function datosToken(
        Cliente $cliente,
        Dispositivo $dispositivo,
        string $token,
        string $titulo
    ): array {
        return [
            'titulo' => $titulo,
            'cliente_codigo' => $cliente->codigo,
            'cliente_nombre' => $cliente->nombre,
            'dispositivo_codigo' => $dispositivo->codigo,
            'dispositivo_nombre' => $dispositivo->nombre,
            'token' => $token,
            'pdf_url' => route('admin.documentos.configuracion'),
            'pdf_disponible_hasta' => now()->addMinutes(30)->format('H:i'),
        ];
    }

    /** @param array<string, mixed> $configuracion */
    private function guardarDocumentoPendiente(
        Cliente $cliente,
        Dispositivo $dispositivo,
        string $token,
        array $configuracion
    ): void {
        session()->put('documento_configuracion_pendiente', [
            'cliente_id' => $cliente->id,
            'dispositivo_id' => $dispositivo->id,
            'token_cifrado' => Crypt::encryptString($token),
            'acceso_nombre' => $configuracion['acceso_nombre'] ?? null,
            'acceso_email' => $configuracion['acceso_email'] ?? null,
            'password_cifrado' => !empty($configuracion['acceso_password'])
                ? Crypt::encryptString((string) $configuracion['acceso_password'])
                : null,
            'expira_en' => now()->addMinutes(30)->timestamp,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function documentoPendienteActual(): ?array
    {
        $pendiente = session('documento_configuracion_pendiente');

        if (!is_array($pendiente)) {
            return null;
        }

        if ((int) ($pendiente['expira_en'] ?? 0) < now()->timestamp) {
            session()->forget('documento_configuracion_pendiente');
            return null;
        }

        if (
            empty($pendiente['cliente_id'])
            || empty($pendiente['dispositivo_id'])
            || empty($pendiente['token_cifrado'])
        ) {
            session()->forget('documento_configuracion_pendiente');
            return null;
        }

        return $pendiente + [
            'pdf_url' => route('admin.documentos.configuracion'),
            'pdf_disponible_hasta' => date('H:i', (int) $pendiente['expira_en']),
        ];
    }

    /**
     * @param array<string, mixed> $configuracion
     * @return array<string, mixed>
     */
    private function datosDocumentoConfiguracion(
        Cliente $cliente,
        Dispositivo $dispositivo,
        string $token,
        array $configuracion
    ): array {
        $sensores = $dispositivo->sensores
            ->sortBy('ranura')
            ->map(static function (Sensor $sensor): array {
                return [
                    'ranura' => $sensor->ranura,
                    'nombre' => $sensor->nombre,
                    'tipo' => $sensor->tipo,
                    'slave' => $sensor->slave,
                    'funcion' => $sensor->funcion,
                    'registro_inicial' => $sensor->registro_inicial,
                    'cantidad_registros' => $sensor->cantidad_registros,
                    'activo' => $sensor->activo,
                    'lecturas' => $sensor->lecturas
                        ->sortBy('indice')
                        ->map(static fn ($lectura): array => [
                            'indice' => $lectura->indice,
                            'nombre' => $lectura->nombre,
                            'unidad' => $lectura->unidad,
                            'activo' => $lectura->activo,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'emitido_en' => now()->format('d/m/Y H:i'),
            'cliente' => [
                'codigo' => $cliente->codigo,
                'nombre' => $cliente->nombre,
                'activo' => $cliente->activo,
            ],
            'equipo' => [
                'codigo' => $dispositivo->codigo,
                'nombre' => $dispositivo->nombre,
                'ubicacion' => $dispositivo->ubicacion,
                'activo' => $dispositivo->activo,
                'token' => $token,
            ],
            'acceso' => [
                'nombre' => $configuracion['acceso_nombre'] ?? null,
                'email' => $configuracion['acceso_email'] ?? null,
                'password_temporal' => $configuracion['acceso_password'] ?? null,
            ],
            'sensores' => $sensores,
        ];
    }

    private function mensajesValidacion(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'required_with' => 'Complete todos los datos del acceso.',
            'email' => 'Ingrese un correo electrónico válido.',
            'min' => 'La contraseña debe tener al menos 8 caracteres.',
            'confirmed' => 'La confirmación de contraseña no coincide.',
            'max' => 'El texto supera la longitud permitida.',
            'unique' => 'Este dato ya está registrado.',
            'regex' => 'El formato ingresado no es válido.',
            'ipv4' => 'Ingrese una dirección IPv4 válida.',
            'required_if' => 'Este dato es obligatorio cuando se utiliza IP estática.',
            'between' => 'El valor está fuera del rango permitido.',
            'in' => 'Seleccione una opción válida.',
        ];
    }
}
