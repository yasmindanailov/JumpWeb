<?php

namespace App\Http\Controllers\Account;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountProfile;
use App\Http\Controllers\Controller;
use App\Notifications\EmailChangeCompleted;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Caducidad de la solicitud (defensa adicional a la firma de la ruta).
        if ($user->pending_email_sent_at->lt(now()->subMinutes(self::HOLD_MINUTES))) {
            $user->forceFill(['pending_email' => null, 'pending_email_sent_at' => null])->save();

            return redirect()->route('account')->with('status', 'email-change-expired');
        }

        // Otro usuario pudo haber registrado ese email entre la solicitud y la confirmación.
        // UNIQUE de BD ya lo blindaría, pero damos un mensaje claro y limpiamos el pending.
        if (User::where('email', $user->pending_email)->where('id', '!=', $user->id)->exists()) {
            $user->forceFill(['pending_email' => null, 'pending_email_sent_at' => null])->save();

            return redirect()->route('account')->with('status', 'email-change-taken');
        }

        $previousEmail = $user->email;
        $newEmail = $user->pending_email;

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => now(),       // implícitamente verificado (el cliente abrió el enlace del nuevo)
            'pending_email' => null,
            'pending_email_sent_at' => null,
        ])->save();
        // S-5 (`#181`): confirmar el correo NUEVO es verificarlo — y lo que espera a la verificación (la
        // aceptación pendiente del waiver) tiene que enterarse, como por el enlace del alta o por el cobro.
        event(new Verified($user));

        // Aviso al EMAIL VIEJO de que el cambio se consumó (cierre del loop anti-takeover, C-07).
        // Si la víctima ve este correo en su buzón original y no fue ella, sabe que la cuenta
        // fue tomada antes de que el atacante haga más daño. `previous_email` se inyecta como
        // atributo temporal para que la notificación rute al buzón correcto sin tocar `email`.
        $user->previous_email = $previousEmail;
        $user->notify(new EmailChangeCompleted(AccountProfile::maskEmail($newEmail)));

        Log::info('account.email_change_confirmed', ['user_id' => $user->id]);

        return redirect()->route('account')->with('status', 'email-change-confirmed');
    }
}
