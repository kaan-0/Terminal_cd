<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mediciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispositivo_id')
                ->constrained('dispositivos')
                ->restrictOnDelete();

            $table->unsignedInteger('valor');

            $table->timestamp('fecha_recepcion')
                ->useCurrent();

            $table->index([
                'dispositivo_id',
                'fecha_recepcion',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediciones');
    }
};