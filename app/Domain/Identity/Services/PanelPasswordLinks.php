<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Notifications\PanelPasswordLink;
use Illuminate\Support\Facades\Password;

/**
 * **El enlace para crear la contraseña del PANEL** (A5a de `docs/specs/acceso-con-codigo.md` §4.12, `DECISIONES #870`).
 *
 * Los clientes entran con un código al correo y no tienen contraseña (`#848`); el personal sí: la del panel. La recibe
 * por este enlace, que le envía un administrador desde su ficha —nadie lo pide por su cuenta— y que abre una página del
 * PANEL que solo acepta cuentas del panel (`App\Filament\Auth\PanelPassword`).
 *
 * ⚠️⚠️ **A una cuenta de cliente, NUNCA, y lo dice el DOMINIO**, no solo el botón: el enlace le devolvería una contraseña
 * a quien no debe tener ninguna (§4.6 de la spec). El botón lo comprueba también, al pintarse y al ejecutar (`SEC-04`).
 *
 * El token es el del broker de Laravel (`password_reset_tokens`: caduca, y uno por minuto por cuenta); el correo es el
 * nuestro y no el del framework, porque el enlace va a la página del panel y no a la web.
 */
final class PanelPasswordLinks
{
    public const SENT = 'sent';

    /** Ya salió uno hace menos de un minuto (el límite del broker). */
    public const THROTTLED = 'throttled';

    /** No es una cuenta del panel, está anonimizada o no tiene correo. */
    public const NOT_ALLOWED = 'not_allowed';

    public function send(User $member): string
    {
        if (! $member->isTeamMember() || $member->isAnonymized() || blank($member->email)) {
            return self::NOT_ALLOWED;
        }

        $status = Password::broker()->sendResetLink(
            ['email' => $member->email],
            static function (User $user, string $token): void {
                $user->notify(new PanelPasswordLink($token));
            },
        );

        return match ($status) {
            Password::RESET_LINK_SENT => self::SENT,
            Password::RESET_THROTTLED => self::THROTTLED,
            default => self::NOT_ALLOWED,
        };
    }
}
