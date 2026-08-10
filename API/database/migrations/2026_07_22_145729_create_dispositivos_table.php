<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();

            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('ubicacion', 200)->nullable();

            // Aquí se guardará el hash, nunca el token original.
            $table->string('token_hash', 255);

            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_conexion')->nullable();

            $table->timestamps();

            $table->index([
                'cliente_id',
                'activo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos');
    }
};