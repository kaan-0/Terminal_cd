<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicion extends Model
{
    protected $table = 'mediciones';

    public $timestamps = false;

    protected $fillable = [
        'dispositivo_id',
        'sensor_id',
        'valor',
        'baudrate',
        'paridad',
        'slave',
        'funcion',
        'registro_inicial',
        'cantidad_registros',
        'fecha_recepcion',
    ];

    protected function casts(): array
    {
        return [
            'sensor_id' => 'integer',
            'valor' => 'integer',
            'baudrate' => 'integer',
            'slave' => 'integer',
            'funcion' => 'integer',
            'registro_inicial' => 'integer',
            'cantidad_registros' => 'integer',
            'fecha_recepcion' => 'datetime',
        ];
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    public function valores(): HasMany
    {
        return $this
            ->hasMany(MedicionValor::class)
            ->orderBy('indice');
    }
}
