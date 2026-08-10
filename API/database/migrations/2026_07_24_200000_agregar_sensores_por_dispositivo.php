<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensores', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('dispositivo_id')
                ->constrained('dispositivos')
                ->cascadeOnDelete();

            // Coincide con las ranuras 1 a 4 del firmware.
            $table->unsignedTinyInteger('ranura');
            $table->string('nombre', 150);
            $table->string('tipo', 100)->nullable();
            $table->string('unidad', 30)->nullable();

            // Última configuración Modbus recibida desde el controlador.
            $table->unsignedTinyInteger('slave')->nullable();
            $table->unsignedTinyInteger('funcion')->nullable();
            $table->unsignedSmallInteger('registro_inicial')->nullable();
            $table->unsignedTinyInteger('cantidad_registros')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_conexion')->nullable();
            $table->timestamps();

            $table->unique(
                ['dispositivo_id', 'ranura'],
                'sensores_dispositivo_ranura_unique'
            );

            $table->index(
                ['dispositivo_id', 'activo'],
                'sensores_dispositivo_activo_index'
            );
        });

        Schema::table('mediciones', function (Blueprint $table): void {
            $table->foreignId('sensor_id')
                ->nullable()
                ->after('dispositivo_id')
                ->constrained('sensores')
                ->nullOnDelete();

            $table->index(
                ['sensor_id', 'fecha_recepcion'],
                'mediciones_sensor_fecha_index'
            );
        });

        // Conserva las mediciones existentes asignándolas a la ranura 1.
        $dispositivosConDatos = DB::table('mediciones')
            ->whereNull('sensor_id')
            ->select('dispositivo_id')
            ->distinct()
            ->pluck('dispositivo_id');

        foreach ($dispositivosConDatos as $dispositivoId) {
            $ultimaConexion = DB::table('mediciones')
                ->where('dispositivo_id', $dispositivoId)
                ->max('fecha_recepcion');

            $sensorId = DB::table('sensores')->insertGetId([
                'dispositivo_id' => $dispositivoId,
                'ranura' => 1,
                'nombre' => 'Sensor 1',
                'tipo' => null,
                'unidad' => null,
                'slave' => null,
                'funcion' => null,
                'registro_inicial' => null,
                'cantidad_registros' => null,
                'activo' => true,
                'ultima_conexion' => $ultimaConexion,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('mediciones')
                ->where('dispositivo_id', $dispositivoId)
                ->whereNull('sensor_id')
                ->update(['sensor_id' => $sensorId]);
        }
    }

    public function down(): void
    {
        Schema::table('mediciones', function (Blueprint $table): void {
            $table->dropForeign(['sensor_id']);
            $table->dropIndex('mediciones_sensor_fecha_index');
            $table->dropColumn('sensor_id');
        });

        Schema::dropIfExists('sensores');
    }
};
