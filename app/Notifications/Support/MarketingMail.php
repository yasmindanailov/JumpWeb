<?php

namespace App\Notifications\Support;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * **EL PIE Y LA BAJA DE UN COMERCIAL a una cuenta** (la C1 de `specs/correos-rediseno.md` §4.4, `[DECIDIDO owner]` `#920`):
 * «porque marcaste la casilla de novedades» y la baja de un toque, firmada y sin caducidad.
 *
 * A QUIÉN se le escribe lo dice la cuenta (`User::canReceiveMarketing()` y `User::scopeMarketable()`: con «novedades», con
 * correo y sin anonimizar). ⚠️ Se mira DOS veces: al elegir el público (el comando, con el scope) y al SALIR el correo (el
 * `shouldSend()` de cada comercial): entre una cosa y la otra hay una cola, y quien se dio de baja en ese rato no recibe nada.
 */
final class MarketingMail
{
    /** La baja de «novedades» de esta cuenta: FIRMADA y sin caducidad (LSSI art. 22.1, `MarketingUnsubscribeController`). */
    public static function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('marketing.unsubscribe', ['user' => $user->getKey()]);
    }

    /** El pie de un comercial a esta cuenta: por qué lo recibe y su baja (`BrandedMailMessage::commercial()`). */
    public static function footer(BrandedMailMessage $mail, User $user): BrandedMailMessage
    {
        return $mail->commercial((string) __('emails.comercial.porque'), self::unsubscribeUrl($user));
    }
}
