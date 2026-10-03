<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Domain\Platform\Services\SiteLocales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * LA BAJA de «novedades» (`specs/correos-rediseno.md` §4.4, la C1a, `[DECIDIDO owner]` `#920`): el enlace del pie de cada
 * correo comercial (y su `List-Unsubscribe`) abre una página con UN botón, y el botón retira «Quiero recibir novedades» con su
 * prueba (`AccountPrivacy::setMarketing()`: la casilla y la fila de `consents`, como desde Mi cuenta). Un enlace que escribiera
 * al abrirse lo pulsarían los escáneres de enlaces de los gestores de correo (el molde de la baja de «Avísame de fechas»).
 *
 * ⚠️ Las dos rutas van FIRMADAS y sin caducidad (LSSI art. 22.1: la baja tiene que funcionar siempre): la firma es la
 * credencial, sin sesión. ⚠️ La página no dice el correo ni el nombre: quien la abre puede no ser el titular.
 */
class MarketingUnsubscribeController extends Controller
{
    public function show(User $user): Response
    {
        $this->useLocaleOf($user);

        return response()
            ->view('novedades.baja', [
                'accion' => URL::signedRoute('marketing.unsubscribe.confirm', ['user' => $user->getKey()]),
                'hecho' => ! $user->marketing_opt_in,
            ])
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function confirm(User $user, AccountPrivacy $privacy, Request $request): RedirectResponse
    {
        $privacy->setMarketing($user, false, (string) $request->ip());

        return redirect()->to(URL::signedRoute('marketing.unsubscribe', ['user' => $user->getKey()]));
    }

    /** El idioma de la cuenta (el de sus correos); si no es uno del sitio, el de la instalación. */
    private function useLocaleOf(User $user): void
    {
        if (in_array($user->locale, SiteLocales::SUPPORTED, true)) {
            app()->setLocale((string) $user->locale);
        }
    }
}
