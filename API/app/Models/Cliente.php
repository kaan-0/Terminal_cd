<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function dispositivos(): HasMany
    {
        return $this->hasMany(Dispositivo::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}