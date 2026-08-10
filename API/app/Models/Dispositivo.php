<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    protected $fillable = [
        'cliente_id',
        'codigo',
        'nombre',
        'ubicacion',
        'token_hash',
        'activo',
        'ultima_conexion',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultima_conexion' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sensores(): HasMany
    {
        return $this->hasMany(Sensor::class)->orderBy('ranura');
    }

    public function mediciones(): HasMany
    {
        return $this->hasMany(Medicion::class);
    }
}