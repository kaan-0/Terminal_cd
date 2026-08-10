<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_lecturas', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('sensor_id')
                ->constrained('sensores')
                ->cascadeOnDelete();

            // Posición dentro del arreglo registros[] enviado por el firmware.
            $table->unsignedTinyInteger('indice');
            $table->string('nombre', 100);
            $table->string('unidad', 30)->nullable();

            // valor_visible = valor_crudo * factor + ajuste
            $table->decimal('factor', 14, 6)->default(1);
            $table->decimal('ajuste', 14, 6)->default(0);
            $table->unsignedTinyInteger('decimales')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(
                ['sensor_id', 'indice'],
                'sensor_lecturas_sensor_indice_unique'
            );
        });

        // Crea una definición inicial para los sensores existentes.
        DB::table('sensores')
            ->orderBy('id')
            ->chunkById(100, function ($sensores): void {
                foreach ($sensores as $sensor) {
                    $cantidad = max(
                        1,
                        min(16, (int) ($sensor->cantidad_registros ?: 1))
                    );

                    for ($indice = 0; $indice < $cantidad; $indice++) {
                        DB::table('sensor_lecturas')->insert([
                            'sensor_id' => $sensor->id,
                            'indice' => $indice,
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
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_lecturas');
    }
};
