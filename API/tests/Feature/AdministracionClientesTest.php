<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministracionClientesTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarAdministrador(): User
    {
        $admin = User::factory()->create([
            'rol' => 'admin',
            'cliente_id' => null,
            'activo' => true,
        ]);

        $this->actingAs($admin);

        return $admin;
    }

    public function test_la_pantalla_de_administracion_requiere_login(): void
    {
        $this->get(route('admin.clientes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_la_pantalla_de_administracion_carga_para_admin(): void
    {
        $this->autenticarAdministrador();

        $this->get(route('admin.clientes.index'))
            ->assertOk()
            ->assertSee('Crear cliente y primer dispositivo');
    }

    public function test_crea_cliente_dispositivo_usuario_y_token_seguro(): void
    {
        $this->autenticarAdministrador();

        $response = $this->post(route('admin.clientes.store'), [
            'cliente_codigo' => 'cli-hn-0002',
            'cliente_nombre' => 'Cliente de prueba',
            'dispositivo_codigo' => 'lc0002c',
            'dispositivo_nombre' => 'Estación principal',
            'dispositivo_ubicacion' => 'Tegucigalpa',
            'acceso_nombre' => 'Usuario cliente',
            'acceso_email' => 'cliente@example.com',
            'acceso_password' => 'ClaveSegura123',
            'acceso_password_confirmation' => 'ClaveSegura123',
        ]);

        $response
            ->assertRedirect(route('admin.clientes.index'))
            ->assertSessionHas('token_generado');

        $cliente = Cliente::query()
            ->where('codigo', 'CLI-HN-0002')
            ->firstOrFail();

        $dispositivo = Dispositivo::query()
            ->where('codigo', 'LC0002C')
            ->firstOrFail();

        $usuario = User::query()
            ->where('email', 'cliente@example.com')
            ->firstOrFail();

        $tokenGenerado = $response
            ->getSession()
            ->get('token_generado');

        $this->assertSame($cliente->id, $dispositivo->cliente_id);
        $this->assertSame($cliente->id, $usuario->cliente_id);
        $this->assertSame('cliente', $usuario->rol);
        $this->assertSame(64, strlen($tokenGenerado['token']));
        $this->assertTrue(Hash::check(
            $tokenGenerado['token'],
            $dispositivo->token_hash
        ));
        $this->assertNotSame(
            $tokenGenerado['token'],
            $dispositivo->token_hash
        );
    }

    public function test_agrega_otro_dispositivo_a_un_cliente(): void
    {
        $this->autenticarAdministrador();

        $cliente = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente existente',
            'activo' => true,
        ]);

        $response = $this->post(
            route('admin.dispositivos.store', $cliente),
            [
                'codigo' => 'LC0003C',
                'nombre' => 'Estación secundaria',
                'ubicacion' => 'San Pedro Sula',
            ]
        );

        $response
            ->assertRedirect(route('admin.clientes.index'))
            ->assertSessionHas('token_generado');

        $this->assertDatabaseHas('dispositivos', [
            'cliente_id' => $cliente->id,
            'codigo' => 'LC0003C',
            'nombre' => 'Estación secundaria',
        ]);
    }

    public function test_regenerar_token_invalida_el_token_anterior(): void
    {
        $this->autenticarAdministrador();

        $cliente = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente existente',
            'activo' => true,
        ]);

        $tokenAnterior = 'token-anterior-de-prueba';

        $dispositivo = $cliente->dispositivos()->create([
            'codigo' => 'LC0001C',
            'nombre' => 'Estación principal',
            'ubicacion' => null,
            'token_hash' => Hash::make($tokenAnterior),
            'activo' => true,
        ]);

        $response = $this->post(
            route('admin.dispositivos.regenerar-token', $dispositivo)
        );

        $response
            ->assertRedirect(route('admin.clientes.index'))
            ->assertSessionHas('token_generado');

        $dispositivo->refresh();
        $tokenNuevo = $response
            ->getSession()
            ->get('token_generado')['token'];

        $this->assertFalse(Hash::check(
            $tokenAnterior,
            $dispositivo->token_hash
        ));
        $this->assertTrue(Hash::check(
            $tokenNuevo,
            $dispositivo->token_hash
        ));
    }

    public function test_cliente_no_puede_entrar_a_administracion(): void
    {
        $cliente = Cliente::create([
            'codigo' => 'CLI-HN-0001',
            'nombre' => 'Cliente uno',
            'activo' => true,
        ]);

        $usuario = User::factory()->create([
            'cliente_id' => $cliente->id,
            'rol' => 'cliente',
            'activo' => true,
        ]);

        $this->actingAs($usuario)
            ->get(route('admin.clientes.index'))
            ->assertForbidden();
    }
}
