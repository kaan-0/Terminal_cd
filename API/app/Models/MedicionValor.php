<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicionValor extends Model
{
    protected $table = 'medicion_valores';

    public $timestamps = false;

    protected $fillable = [
        'medicion_id',
        'indice',
        'registro',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'medicion_id' => 'integer',
            'indice' => 'integer',
            'registro' => 'integer',
            'valor' => 'integer',
        ];
    }

    public function medicion(): BelongsTo
    {
        return $this->belongsTo(Medicion::class);
    }
}
