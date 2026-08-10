<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CrearUsuarioAdministrador extends Command
{
    protected $signature = 'usuarios:crear-admin
        {email : Correo electrónico del administrador}
        {--nombre=Administrador : Nombre del administrador}
        {--password= : Contraseña; si se omite se solicitará de forma oculta}';

    protected $description = 'Crea o actualiza una cuenta administradora del dashboard';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $nombre = trim((string) $this->option('nombre'));
        $password = (string) ($this->option('password') ?: $this->secret(
            'Contraseña del administrador (mínimo 8 caracteres)'
        ));

        $validator = Validator::make([
            'email' => $email,
            'nombre' => $nombre,
            'password' => $password,
        ], [
            'email' => ['required', 'email'],
            'nombre' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'cliente_id' => null,
                'name' => $nombre,
                'password' => Hash::make($password),
                'rol' => 'admin',
                'activo' => true,
            ]
        );

        $this->info(
            "Administrador {$usuario->email} creado o actualizado correctamente."
        );

        return self::SUCCESS;
    }
}
