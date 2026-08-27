<?php

namespace App\Domain\Identity\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fase 6 · subsistema A — el CARNÉ QR de un titular (`docs/specs/identidad-qr-puerta.md` §4.1, §4.4,
 * §4.5, §9.2 A·1/A·2).
 *
 * Es una CREDENCIAL que no autentica (§4.2): identifica a la persona en la puerta y nada más. Por eso
 * puede ser estable, viajar por correo e imprimirse — y por eso mismo entra en `User::revokeAllAccess()`
 * como las sesiones y los tokens (`RGPD-06`): revocar el acceso de alguien tiene UN sitio.
 *
 * Tres reglas que son la fila entera:
 *  - **`token` cifrado y `token_hash` en claro** (§4.5): se busca por el hash y se repinta desde el
 *    cifrado. ⚠️ Con `APP_KEY` rotada el cast `encrypted` LANZA: por eso ninguna superficie lee
 *    `$card->token`; se lee por {@see plainToken()}, que devuelve `null` y deja que el escaneo siga
 *    funcionando (§8.1). `token` y `token_hash` están OCULTOS a la serialización.
 *  - **Uno ACTIVO por titular**, garantizado por `CustomerCards` bajo el lock del titular.
 *  - **Revocar es escribir `revoked_at`, nunca borrar**: la auditoría de «se escaneó el carné X» se
 *    resuelve aunque X esté rotado; y un carné revocado en la puerta dice «carné caducado — busca por
 *    email» (§4.5), que es un camino que ya existe.
 *
 * Único escritor: `Identity\Services\CustomerCards` (emitir, rotar) y `User` (revocar).
 */
#[Fillable(['user_id', 'token', 'token_hash', 'issued_at'])]
class CustomerCard extends Model
{
    public const REASON_ROTATED = 'rotated';

    public const REASON_REVOKED = 'revoked';

    public const REASON_ANONYMIZED = 'anonymized';

    /** El token NUNCA sale en un `toArray()`/JSON por accidente: solo por `plainToken()`, a propósito. */
    protected $hidden = ['token', 'token_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'issued_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<CustomerCard>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * El token en claro para repintar el QR — o `null` si la clave de cifrado ya no es la que lo
     * cifró (§8.1: degradar, no romper; el titular rota el carné y listo).
     */
    public function plainToken(): ?string
    {
        try {
            $token = $this->getAttribute('token');
        } catch (DecryptException) {
            return null;
        }

        return is_string($token) && $token !== '' ? $token : null;
    }
}
