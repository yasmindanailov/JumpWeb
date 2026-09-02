<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    /**
     * Versión actual de los documentos legales aceptados. Se sube cuando
     * cambian los textos (entonces se vuelve a pedir aceptación). `[DECIDIDO]` 2026-05-23.
     */
    public const CURRENT_VERSION = '2026-05-23';

    /**
     * Los cuatro tipos que el sistema escribe. **Solo `marketing` se puede RETIRAR** (art. 7.3): los
     * otros tres no son consentimientos revocables en el sentido del RGPD —privacidad y condiciones
     * son la base contractual de la reserva, y el descargo es una prueba que el parque conserva—.
     */
    public const TYPE_PRIVACY = 'privacy';

    public const TYPE_TERMS = 'terms';

    public const TYPE_WAIVER = 'waiver';

    public const TYPE_MARKETING = 'marketing';

    protected $guarded = [];

    protected $casts = [
        'accepted_at' => 'datetime',
        // La RETIRADA (art. 7.3, `#344`): la fila no se borra —sigue probando que en su día se
        // aceptó— y esta columna dice cuándo dejó de valer.
        'revoked_at' => 'datetime',
    ];

    /** ¿Sigue vivo? Lo que el titular ve como «aceptado» es esto, no la mera existencia de la fila. */
    public function isLive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
