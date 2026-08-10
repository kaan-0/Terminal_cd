<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MedicionMultisensorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_hasta_cuatro_sensores_en_un_solo_post(): void
    {
        $cliente = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente multisensor',
            'activo' => true,
        ]);

        $token = str_repeat('A', 64);

        $dispositivo = $cliente->dispositivos()->create([
            'codigo' => 'CDT-HN-000001',
            'nombre' => 'Controlador principal',
            'ubicacion' => 'Tegucigalpa',
            'token_hash' => Hash::make($token),
            'activo' => true,
        ]);

        $sensores = [];

        for ($ranura = 1; $ranura <= 4; $ranura++) {
            $sensores[] = [
                'ranura' => $ranura,
                'slave' => 10 + $ranura,
                'funcion' => 3,
                'registro_inicial' => 0,
                'cantidad' => 2,
                'registros' => [100 * $ranura, 100 * $ranura + 1],
            ];
        }

        $response = $this
            ->withHeaders([
                'X-Device-ID' => $dispositivo->codigo,
                'X-Device-Token' => $token,
                'Accept' => 'application/json',
            ])
            ->postJson('/api/v1/mediciones', [
                'valor' => 100,
                'rs485' => [
                    'baudrate' => 9600,
                    'paridad' => 'N',
                ],
                'sensores' => $sensores,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('sensores_recibidos', 4)
            ->assertJsonCount(4, 'sensores');

        $this->assertDatabaseCount('sensores', 4);
        $this->assertDatabaseCount('mediciones', 4);
        $this->assertDatabaseCount('medicion_valores', 8);
        $this->assertDatabaseCount('sensor_lecturas', 8);

        for ($ranura = 1; $ranura <= 4; $ranura++) {
            $this->assertDatabaseHas('sensores', [
                'dispositivo_id' => $dispositivo->id,
                'ranura' => $ranura,
                'slave' => 10 + $ranura,
                'funcion' => 3,
                'registro_inicial' => 0,
                'cantidad_registros' => 2,
            ]);
        }
    }

    public function test_rechaza_ranuras_repetidas(): void
    {
        $cliente = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente multisensor',
            'activo' => true,
        ]);

        $token = str_repeat('B', 64);

        $dispositivo = $cliente->dispositivos()->create([
            'codigo' => 'CDT-HN-000001',
            'nombre' => 'Controlador principal',
            'token_hash' => Hash::make($token),
            'activo' => true,
        ]);

        $sensor = [
            'ranura' => 1,
            'slave' => 12,
            'funcion' => 3,
            'registro_inicial' => 0,
            'cantidad' => 1,
            'registros' => [250],
        ];

        $this
            ->withHeaders([
                'X-Device-ID' => $dispositivo->codigo,
                'X-Device-Token' => $token,
                'Accept' => 'application/json',
            ])
            ->postJson('/api/v1/mediciones', [
                'rs485' => [
                    'baudrate' => 9600,
                    'paridad' => 'N',
                ],
                'sensores' => [$sensor, $sensor],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sensores.1.ranura');
    }

    public function test_cliente_solo_puede_ver_sus_propios_sensores(): void
    {
        $clienteUno = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente uno',
            'activo' => true,
        ]);

        $clienteDos = Cliente::create([
            'codigo' => 'CLI-HN-0002',
            'nombre' => 'Cliente dos oculto',
            'activo' => true,
        ]);

        $dispositivoUno = $clienteUno->dispositivos()->create([
            'codigo' => 'CTRL-UNO',
            'nombre' => 'Controlador permitido',
            'token_hash' => Hash::make('token-uno'),
            'activo' => true,
        ]);

        $dispositivoDos = $clienteDos->dispositivos()->create([
            'codigo' => 'CTRL-DOS',
            'nombre' => 'Controlador oculto',
            'token_hash' => Hash::make('token-dos'),
            'activo' => true,
        ]);

        $dispositivoUno->sensores()->create([
            'ranura' => 1,
            'nombre' => 'Temperatura permitida',
            'activo' => true,
        ]);

        $dispositivoDos->sensores()->create([
            'ranura' => 1,
            'nombre' => 'Temperatura secreta',
            'activo' => true,
        ]);

        $usuario = User::factory()->create([
            'cliente_id' => $clienteUno->id,
            'rol' => 'cliente',
            'activo' => true,
        ]);

        $this->actingAs($usuario)
            ->get(route('dashboard', [
                'cliente' => $clienteDos->codigo,
                'dispositivo' => $dispositivoDos->codigo,
                'sensor' => 1,
            ]))
            ->assertOk()
            ->assertSee('Temperatura permitida')
            ->assertDontSee('Temperatura secreta')
            ->assertDontSee('Controlador oculto');
    }
}
