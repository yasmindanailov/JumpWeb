<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

/**
 * Mesa/sala para packs (cumpleaños). El aforo de packs por franja = mesas libres (recurso
 * físico distinto a las plazas de entradas). Estructura de la transversal §9.3; la lógica
 * de aforo por mesas + buffers de preparación entra en la Capa 2. Nombre traducible y todo
 * editable desde el panel (Fase 7); valores reales [PENDIENTE].
 */
class Room extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'note' => 'array',
        'capacity' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];
}
