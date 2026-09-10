<?php

namespace App\Domain\Content\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

/**
 * Una opinión PROPIA del parque, escrita en el panel (`DECISIONES #490`).
 *
 * ⚠️ **No se confunde con una reseña de Google**: aquéllas no se persisten nunca —la política de
 * Places prohíbe almacenarlas (R2)— y viajan por el mismo contrato de dominio (`SocialProof`) desde
 * un servicio distinto. Lo único que este modelo tiene en común con ellas es la FORMA.
 */
class Testimonial extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'text' => 'array',
        'rating' => 'integer',
        'published_at' => 'date',
        'is_active' => 'boolean',
    ];
}
