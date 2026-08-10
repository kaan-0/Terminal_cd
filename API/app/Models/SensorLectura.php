<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorLectura extends Model
{
    protected $table = 'sensor_lecturas';

    protected $fillable = [
        'sensor_id',
        'indice',
        'nombre',
        'unidad',
        'factor',
        'ajuste',
        'decimales',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'indice' => 'integer',
            'factor' => 'decimal:6',
            'ajuste' => 'decimal:6',
            'decimales' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    public function convertir(int|float $valorCrudo): float
    {
        return ((float) $valorCrudo * (float) $this->factor)
            + (float) $this->ajuste;
    }
}
