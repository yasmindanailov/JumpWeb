<?php

namespace App\Http\Cuenta;

use Illuminate\Support\Facades\Lang;

/**
 * **El aviso que deja el servidor al volver a una página nueva** (T5e·2 de `specs/isla-y-landing-nueva.md` §4.13,
 * `DECISIONES #779`): el `status` de la sesión —la vuelta de Google al entrar o al vincular, un cambio de correo, un
 * pago que no se pudo reintentar— con su texto (`account.status.*`, el MISMO del aviso de la web de siempre) y su TONO.
 *
 * ⚠️ **Existe porque las páginas nuevas lo perdían en silencio.** El layout de siempre lo pinta (`components/layout`), pero
 * el de las páginas de la instancia (`components/pagina`) no, y el motor pide su arranque a la API DESPUÉS de cargar la
 * página: el `status` dura una petición y ya se habría gastado. Así que viaja en la configuración de la isla de la
 * página, que se pinta en la misma petición, y lo toma una sola vez quien se abra primero (Mi cuenta, la compra que
 * vuelve de Google o, si no, el «Aviso» de la isla).
 *
 * El tono lo decide una lista, no el texto: lo que salió (`success`), lo que no (`danger`) y lo que solo informa
 * (`info`). Un `status` nuevo tiene que clasificarse aquí: `AvisoDeSesionTest` cruza las listas con `account.status`.
 */
final readonly class AvisoDeSesion
{
    /** Lo que dice que algo SALIÓ. */
    public const BIEN = [
        'email-verified', 'email-already-verified', 'verification-link-sent', 'password-reset', 'profile-updated',
        'password-updated', 'email-change-requested', 'email-change-confirmed', 'email-change-cancelled',
        'email-change-resent', 'logged-out-others', 'account-deleted', 'guest-form-saved', 'google-linked',
        'google-already-linked',
    ];

    /** Lo que dice que algo NO salió, y qué hacer. */
    public const MAL = [
        'email-change-expired', 'email-change-taken', 'order-retry-unavailable', 'order-retry-failed',
        'order-retry-throttled', 'order-retry-paused', 'google-failed', 'google-email-unverified', 'google-anonymized',
        'google-provider-conflict', 'google-provider-taken', 'google-link-session-changed',
    ];

    /** Lo que solo informa: no es un error de nadie (cancelar en Google, esperar un minuto antes de pedir otro correo). */
    public const INFO = ['google-cancelled', 'verification-resend-throttled'];

    /**
     * El aviso para la isla de la página, o `null` sin `status` o con uno que no tiene texto de cuenta (los de la fiesta,
     * p. ej., los pintan sus páginas).
     *
     * @return array{texto: string, tono: 'success'|'danger'|'info'}|null
     */
    public function paraLaIsla(mixed $status): ?array
    {
        if (! is_string($status) || $status === '' || ! Lang::has('account.status.'.$status)) {
            return null;
        }

        return [
            'texto' => (string) __('account.status.'.$status),
            'tono' => in_array($status, self::BIEN, true) ? 'success' : (in_array($status, self::MAL, true) ? 'danger' : 'info'),
        ];
    }
}
