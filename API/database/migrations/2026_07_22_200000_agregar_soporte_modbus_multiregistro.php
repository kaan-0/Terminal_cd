<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mediciones', function (Blueprint $table): void {
            $table->unsignedInteger('baudrate')->nullable()->after('valor');
            $table->char('paridad', 1)->nullable()->after('baudrate');
            $table->unsignedTinyInteger('slave')->nullable()->after('paridad');
            $table->unsignedTinyInteger('funcion')->nullable()->after('slave');

            $table
                ->unsignedSmallInteger('registro_inicial')
                ->nullable()
                ->after('funcion');

            $table
                ->unsignedTinyInteger('cantidad_registros')
                ->default(1)
                ->after('registro_inicial');
        });

        Schema::create(
            'medicion_valores',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('medicion_id')
                    ->constrained('mediciones')
                    ->cascadeOnDelete();

                $table->unsignedTinyInteger('indice');
                $table->unsignedSmallInteger('registro');
                $table->unsignedSmallInteger('valor');

                $table->unique(
                    ['medicion_id', 'indice'],
                    'medicion_valores_medicion_indice_unique'
                );

                $table->index(
                    ['registro', 'valor'],
                    'medicion_valores_registro_valor_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('medicion_valores');

        Schema::table('mediciones', function (Blueprint $table): void {
            $table->dropColumn([
                'baudrate',
                'paridad',
                'slave',
                'funcion',
                'registro_inicial',
                'cantidad_registros',
            ]);
        });
    }
};
