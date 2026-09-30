<?php

namespace App\Http\Controllers\Account;

use App\Domain\Identity\Contracts\EmailChangeOutcome;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Auditoría 2026-05-26 (hallazgo A) — confirmación del cambio de email.
 *
 * Llega aquí el cliente desde el enlace firmado del correo enviado al NUEVO email. Validamos
 * que el `pending_email` sigue siendo el que se pidió cambiar (anti-tampering por hash), que
 * no ha caducado y que sigue libre (otro usuario pudo haberlo registrado en el ínterin) —
 * solo entonces aplicamos el cambio.
 *
 * No requiere sesión: el id+hash firmados prueban la titularidad del NUEVO buzón (anti-CSRF
 * por firma) y permiten confirmar aunque el usuario haya cerrado sesión.
 */
class EmailChangeController extends Controller
{
    /** Cuántos minutos vive una solicitud de cambio de email (ventana de seguridad). */
    /**
     * ⚠️ **La ventana vive en `Identity\Services\AccountProfile` desde la tanda 2** —es dominio, y
     * la usan tres superficies—. Esta constante se conserva como ALIAS para no romper a quien la
     * nombre, pero su valor sale de allí: dos números que hay que mantener a la vez es como divergen.
     */
    public const HOLD_MINUTES = AccountProfile::PENDING_EMAIL_HOLD_MINUTES;

    public function confirm(Request $request, int $id, string $hash): RedirectResponse
    {
        // 403 en lugar de 404 para id inexistente (anti-enumeración).
        $user = User::find($id);
        if (! $user) {
            abort(403);
        }

        // No hay nada pendiente o el hash no corresponde al pending_email firmado → 403.
        if (! $user->pending_email
            || ! $user->pending_email_sent_at
            || ! hash_equals($hash, sha1((string) $user->pending_email))) {
            abort(403);
        }

        // El resto —la caducidad, el correo que otro se quedó, el cambio, la verificación y el aviso al buzón viejo— es de
        // DOMINIO y vive en `AccountProfile::completeEmailChange()` desde la A2b (`#856`): lo comparte con el CÓDIGO.
        $status = match (app(AccountProfile::class)->completeEmailChange($user)->outcome) {
            EmailChangeOutcome::EXPIRED => 'email-change-expired',
            EmailChangeOutcome::TAKEN => 'email-change-taken',
            default => 'email-change-confirmed',
        };

        return redirect()->route('account')->with('status', $status);
    }
}
