<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * **La media y el total de la ficha, tal y como los da Google** (T2·1,
 * `docs/specs/google-business-profile.md` §4.3·2 y §4.3·10; `DECISIONES #524`, `#727`).
 *
 * Fila única. Guarda lo que la portada enseña al lado de las tarjetas —la media, el recuento, y los dos
 * enlaces a la ficha— y **la fecha de la última pasada coherente**, que es lo que permite escribir
 * *«a fecha de …»* en vez de dar la cifra como si fuera de ahora mismo.
 *
 * ❗❗ **La media y el total NUNCA se filtran** (`[DECIDIDO owner]`, §4.3·10). Las tarjetas de la
 * portada enseñan solo las de cuatro estrellas o más; la cifra, no. Enseñar «4,9 sobre 5» calculado
 * sobre las buenas sería el dato engañoso que la Ómnibus 2019/2161 persigue, y además la política de
 * Google prohíbe agregar su contenido. Por eso estas dos columnas **vienen de Google contadas** y no
 * se componen aquí: {@see GoogleBusinessReview} no sabe sumar y no debe aprender.
 *
 * ⚠️ `average_rating` a `null` **no es un cero**: es «todavía no ha habido una pasada coherente». Son
 * dos estados distintos y la portada los pinta distinto —sin chapa contra una chapa con un 0—, así que
 * no se colapsan en un valor por defecto.
 */
class GoogleBusinessReviewSummary extends Model
{
    /**
     * Cuántos días puede envejecer el resumen antes de dejar de sostenerse.
     *
     * ⚠️ Es el mismo número que {@see GoogleBusinessReview::IDENTIFIED_DAYS} y por la misma razón, no
     * por simetría: pasados tres días sin una pasada, la cifra que se enseña puede llevar días sin
     * parecerse a la de la ficha, y una media desfasada es una afirmación falsa sobre un tercero.
     */
    public const FRESH_DAYS = GoogleBusinessReview::IDENTIFIED_DAYS;

    protected $guarded = [];

    protected $casts = [
        'singleton' => 'boolean',
        'average_rating' => 'float',
        'total_review_count' => 'integer',
        'fetched_at' => 'datetime',
    ];

    /** El resumen, o `null` si no ha habido ninguna pasada. */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    /**
     * ¿Se puede sostener la cifra que hay guardada?
     *
     * `false` cuando no hay pasada, cuando la pasada no trajo media, o cuando la última es más vieja
     * que {@see FRESH_DAYS}. Las tres son «no hay cifra que enseñar», y ninguna es un error.
     */
    public function publishable(): bool
    {
        return $this->average_rating !== null
            && $this->fetched_at !== null
            && $this->fetched_at->greaterThanOrEqualTo(now()->subDays(self::FRESH_DAYS));
    }
}
