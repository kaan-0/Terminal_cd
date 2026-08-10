<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sensor extends Model
{
    protected $table = 'sensores';

    protected $fillable = [
        'dispositivo_id',
        'ranura',
        'nombre',
        'tipo',
        'unidad',
        'slave',
        'funcion',
        'registro_inicial',
        'cantidad_registros',
        'activo',
        'ultima_conexion',
    ];

    protected function casts(): array
    {
        return [
            'ranura' => 'integer',
            'slave' => 'integer',
            'funcion' => 'integer',
            'registro_inicial' => 'integer',
            'cantidad_registros' => 'integer',
            'activo' => 'boolean',
            'ultima_conexion' => 'datetime',
        ];
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function mediciones(): HasMany
    {
        return $this->hasMany(Medicion::class);
    }

    public function ultimaMedicion(): HasOne
    {
        return $this->hasOne(Medicion::class)->latestOfMany('fecha_recepcion');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(SensorLectura::class)->orderBy('indice');
    }
}
