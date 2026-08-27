<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 · subsistema A — EMITIR, ROTAR y BUSCAR el carné QR (`docs/specs/identidad-qr-puerta.md`
 * §4.1, §4.5, §9.2 A·1/A·3/A·8).
 *
 *  - {@see ensureFor()}: el carné nace cuando hace falta (al componer el correo de confirmación, al
 *    pedirlo el titular). Uno ACTIVO por titular, bajo el `lockForUpdate()` de su fila: dos correos a
 *    la vez no emiten dos carnés.
 *  - {@see rotate()}: mata el viejo EN EL ACTO (§4.5, `[DECIDIDO owner]`: sin ventana de gracia — una
 *    credencial revocada que sigue valiendo no es amable, es un hueco) y emite otro.
 *  - {@see findByToken()}: normaliza, comprueba la forma (longitud, alfabeto, control) ANTES de mirar
 *    la base de datos, y devuelve también un carné REVOCADO, para que la puerta pueda decir «carné
 *    caducado — busca por email» en vez de «no registrado».
 *
 * La REVOCACIÓN sin emisión (anonimizar, bloquear) no vive aquí: vive en `User::revokeAllAccess()`,
 * que es el punto único de `RGPD-06`, y ahí entra el carné con las sesiones y los tokens.
 *
 * `RGPD-02`: la auditoría lleva el id del carné y el motivo, nunca el token.
 */
final class CustomerCards
{
    public function activeFor(User $user): ?CustomerCard
    {
        return CustomerCard::query()->where('user_id', $user->getKey())->active()->orderByDesc('id')->first();
    }

    public function ensureFor(User $user): CustomerCard
    {
        return DB::transaction(function () use ($user): CustomerCard {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            return $this->activeFor($locked) ?? $this->issue($locked);
        });
    }

    /** Revoca el activo (si lo hay) con `$reason` y emite uno nuevo. Nunca deja al titular sin carné. */
    public function rotate(User $user, string $reason = CustomerCard::REASON_ROTATED): CustomerCard
    {
        return DB::transaction(function () use ($user, $reason): CustomerCard {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $revoked = CustomerCard::query()
                ->where('user_id', $locked->getKey())
                ->active()
                ->get();
            foreach ($revoked as $card) {
                $card->forceFill(['revoked_at' => now(), 'revoked_reason' => $reason])->save();
                AuditLogger::log('cards.rotated', $locked, ['card_id' => (int) $card->getKey(), 'reason' => $reason]);
            }

            return $this->issue($locked);
        });
    }

    /**
     * El carné cuyo token es este —activo o revocado—, o `null` si no tiene forma de carné o no existe.
     * Un token que no pasa el control no llega a la base de datos (§4.3).
     */
    public function findByToken(string $raw): ?CustomerCard
    {
        $normalized = CardToken::normalize($raw);
        if (! CardToken::isWellFormed($normalized)) {
            return null;
        }

        return CustomerCard::query()->where('token_hash', CardToken::hash($normalized))->with('user')->first();
    }

    private function issue(User $locked): CustomerCard
    {
        // Colisión de sha256 sobre 2⁸⁵: no va a pasar; el bucle existe para que si pasa no sea un 500.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = CardToken::generate();
            $hash = CardToken::hash($token);
            if (CustomerCard::query()->where('token_hash', $hash)->exists()) {
                continue;
            }

            $card = CustomerCard::create([
                'user_id' => $locked->getKey(),
                'token' => $token,
                'token_hash' => $hash,
                'issued_at' => now(),
            ]);
            AuditLogger::log('cards.issued', $locked, ['card_id' => (int) $card->getKey()]);

            return $card;
        }

        throw new \RuntimeException('No se pudo emitir un carné único tras cinco intentos.');
    }
}
