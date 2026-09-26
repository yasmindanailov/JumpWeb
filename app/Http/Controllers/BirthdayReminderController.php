<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\BirthdayReminder;
use App\Domain\Identity\Services\BirthdayReminders;
use App\Domain\Platform\Services\SiteLocales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * LA BAJA de «Avísame de fechas» (`specs/avisame-de-fechas.md` §4.4, `[DECIDIDO owner]` `#750`): el enlace de cada correo
 * (y su `List-Unsubscribe`) abre una página con UN botón, y el botón da de baja. Un enlace que escribiera al abrirse lo
 * pulsarían los escáneres de enlaces de los gestores de correo (el molde de la baja de las encuestas).
 *
 * ⚠️ Las dos rutas van FIRMADAS y sin caducidad (LSSI art. 22.1: la baja tiene que funcionar siempre): la firma es la
 * credencial, y un id inventado o una firma torcida no llegan aquí.
 * ⚠️ La página dice el nombre del niño (sin apellidos) y nada más: ni el correo ni la fecha.
 */
class BirthdayReminderController extends Controller
{
    public function show(BirthdayReminder $reminder): Response
    {
        $this->useLocaleOf($reminder);

        return response()
            ->view('fiesta.avisame-baja', [
                'nino' => trim((string) ($reminder->authorization->minor_name ?? '')),
                'accion' => URL::signedRoute('birthday-reminder.unsubscribe.confirm', ['reminder' => $reminder->getKey()]),
                'hecho' => ! $reminder->isLive(),
            ])
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function confirm(BirthdayReminder $reminder, BirthdayReminders $reminders): RedirectResponse
    {
        $reminders->revoke($reminder);

        return redirect()->to(URL::signedRoute('birthday-reminder.unsubscribe', ['reminder' => $reminder->getKey()]));
    }

    /** El idioma en que se marcó la casilla (el del correo); si no es uno del sitio, el de la instalación. */
    private function useLocaleOf(BirthdayReminder $reminder): void
    {
        if (in_array($reminder->locale, SiteLocales::SUPPORTED, true)) {
            app()->setLocale($reminder->locale);
        }
    }
}
