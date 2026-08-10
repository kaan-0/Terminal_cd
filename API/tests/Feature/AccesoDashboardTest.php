<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccesoDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requiere_autenticacion(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_cliente_solo_ve_sus_dispositivos_aunque_altere_la_url(): void
    {
        $clienteUno = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente uno',
            'activo' => true,
        ]);

        $clienteDos = Cliente::create([
            'codigo' => 'CLI-HN-0002',
            'nombre' => 'Cliente dos secreto',
            'activo' => true,
        ]);

        $dispositivoUno = $clienteUno->dispositivos()->create([
            'codigo' => 'LC0001C',
            'nombre' => 'Sensor permitido',
            'token_hash' => Hash::make('token-uno'),
            'activo' => true,
        ]);

        $dispositivoDos = $clienteDos->dispositivos()->create([
            'codigo' => 'LC0002C',
            'nombre' => 'Sensor ajeno secreto',
            'token_hash' => Hash::make('token-dos'),
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
            ]))
            ->assertOk()
            ->assertSee($clienteUno->nombre)
            ->assertSee($dispositivoUno->nombre)
            ->assertDontSee($clienteDos->nombre)
            ->assertDontSee($dispositivoDos->nombre)
            ->assertDontSee('Administrar clientes');
    }

    public function test_administrador_puede_cambiar_de_cliente(): void
    {
        Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente uno',
            'activo' => true,
        ]);

        Cliente::create([
            'codigo' => 'CLI-HN-0002',
            'nombre' => 'Cliente dos',
            'activo' => true,
        ]);

        $admin = User::factory()->create([
            'cliente_id' => null,
            'rol' => 'admin',
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', [
                'cliente' => 'CLI-HN-0002',
            ]))
            ->assertOk()
            ->assertSee('Cliente dos')
            ->assertSee('CLI-HN-0002')
            ->assertSee('Administrar clientes');
    }

    public function test_usuario_activo_puede_iniciar_sesion(): void
    {
        $cliente = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente uno',
            'activo' => true,
        ]);

        $usuario = User::factory()->create([
            'cliente_id' => $cliente->id,
            'email' => 'cliente@example.com',
            'password' => 'ClaveSegura123',
            'rol' => 'cliente',
            'activo' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => 'ClaveSegura123',
        ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $cliente = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente uno',
            'activo' => true,
        ]);

        User::factory()->create([
            'cliente_id' => $cliente->id,
            'email' => 'inactivo@example.com',
            'password' => 'ClaveSegura123',
            'rol' => 'cliente',
            'activo' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => 'inactivo@example.com',
            'password' => 'ClaveSegura123',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
