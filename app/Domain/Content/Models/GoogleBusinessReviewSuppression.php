<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\GoogleReviewSuppressionReason;
use Illuminate\Database\Eloquent\Model;

/**
 * **Una reseña que no se vuelve a publicar** (T2·5,
 * `docs/specs/google-business-profile.md` §4.3·7; `DECISIONES #524`, `#731`).
 *
 * ❗❗ **Es la única tabla de esta feature que NO caduca.** Las reseñas se leen a 29 días y se purgan
 * a 30; esto se queda, porque su trabajo es justo ése: que la pasada de mañana —y la del año que
 * viene— reconozca la reseña y no la traiga. Si caducara, ocultar sería aplazar.
 *
 * ⚠️ **Solo lleva un hash.** Ni nombre, ni texto, ni foto, ni el identificador legible de Google. No
 * es minimalismo por gusto: en la única tabla que no se limpia sola, cada columna de más es un dato
 * de un tercero guardado para siempre.
 */
class GoogleBusinessReviewSuppression extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reason' => GoogleReviewSuppressionReason::class,
    ];
}
