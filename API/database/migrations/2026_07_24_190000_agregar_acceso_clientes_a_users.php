<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table
                ->foreignId('cliente_id')
                ->nullable()
                ->after('id')
                ->constrained('clientes')
                ->nullOnDelete();

            $table
                ->string('rol', 20)
                ->default('cliente')
                ->after('password');

            $table
                ->boolean('activo')
                ->default(true)
                ->after('rol');

            $table->index(
                ['cliente_id', 'rol', 'activo'],
                'users_cliente_rol_activo_index'
            );
        });

        // Los usuarios que ya existían antes de esta actualización se
        // conservan como administradores para no bloquear el sistema.
        DB::table('users')
            ->whereNull('cliente_id')
            ->update([
                'rol' => 'admin',
                'activo' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['cliente_id']);
            $table->dropIndex('users_cliente_rol_activo_index');
            $table->dropColumn([
                'cliente_id',
                'rol',
                'activo',
            ]);
        });
    }
};
