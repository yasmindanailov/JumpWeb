<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Services\BirthdayReminders;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «AVÍSAME DE FECHAS» (`specs/avisame-de-fechas.md` §4.1, `[DECIDIDO owner]` `#750`): el adulto que firmó la autorización
 * de un invitado con su correo quiere UN correo antes del cumpleaños de ese niño.
 *
 * ⚠️ No lleva datos propios: el correo, los nombres de pila y la fecha de nacimiento los da la autorización FIRMADA, que
 * es a la vez la prueba del consentimiento. La escribe y la lee {@see BirthdayReminders}.
 *
 * @property int $guardian_authorization_id
 * @property string $locale
 */
class BirthdayReminder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'sent_for' => 'date',
        'sent_at' => 'datetime',
    ];

    /** ¿Sigue en pie? Con la baja, la fila solo prueba que un día se pidió. */
    public function isLive(): bool
    {
        return $this->revoked_at === null;
    }

    /** @return BelongsTo<GuardianAuthorization, $this> */
    public function authorization(): BelongsTo
    {
        return $this->belongsTo(GuardianAuthorization::class, 'guardian_authorization_id');
    }
}
