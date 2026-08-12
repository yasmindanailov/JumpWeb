<?php

namespace App\Models;

use App\Support\CookieConsent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro-prueba de un consentimiento de cookies (#219). Distinto de `consents` (legales, de
 * usuario): aquí el sujeto puede ser anónimo (`user_id` nullable). Nombre distinto del helper
 * `App\Support\CookieConsent` (autoridad/gate) para no colisionar.
 *
 * **Prunable** (RGPD: minimización + limitación del plazo): se borran las filas más antiguas que la
 * vida del consentimiento (24 meses). Lo ejecuta `model:prune` programado en `routes/console.php`.
 */
class CookieConsentLog extends Model
{
    use Prunable;

    protected $guarded = [];

    protected $casts = [
        'categories' => 'array',
        'accepted_at' => 'datetime',
    ];

    /**
     * Filas a podar: más antiguas que la vida del consentimiento (24 meses). Conserva la prueba
     * mientras el consentimiento es válido; pasado ese plazo el consentimiento se re-pide igualmente.
     *
     * @return Builder<CookieConsentLog>
     */
    public function prunable(): Builder
    {
        return static::query()->where('accepted_at', '<', now()->subMinutes(CookieConsent::LIFETIME_MINUTES));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
