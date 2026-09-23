<?php

namespace App\Http\Controllers;

use App\Domain\Content\Models\Faq;
use App\Domain\Content\Services\ContactTopics;
use App\Domain\Content\Services\SiteDestinations;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Honeypot;
use App\Domain\Platform\Services\Turnstile;
use App\Http\Instancia\InstanceViews;
use App\Mail\ContactMessageMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function __construct(private readonly InstanceViews $instancia) {}

    /*
     * 📜 **AQUÍ VIVÍAN LOS TEMAS del desplegable «¿Sobre qué?»** (`DECISIONES #535`) y se mudaron a
     * `Content\Services\ContactTopics` en `#676`: son un vocabulario del DOMINIO y `/site` los
     * publica, así que un recurso de la API tendría que haber leído un controlador de la web para
     * conseguirlos. Lo que valía y sigue valiendo:
     *
     * ⚠️ **Son claves, no textos**: lo que se valida y lo que viaja al correo es la clave, y el
     * rótulo lo ponen los ficheros de idioma. Con los textos como valor, cambiar una traducción
     * habría roto la validación del formulario en ese idioma.
     *
     * ⚠️⚠️ Y no escribas aquí la ruta con comodín de esos ficheros: dentro de un docblock, la
     * barra tras el asterisco CIERRA el comentario y el fichero deja de compilar (la trampa de
     * `#503`, que ya costó un servicio entero sin compilar y un render devolviendo HTML viejo).
     */

    public function show()
    {
        /*
         * **La chapa «Quizá ya está contestado»** (`#535`). La condición de Dudas es la MISMA
         * colección que decide pintar la sección en la portada (`#488`): si el panel no tiene
         * ninguna, el ancla no existe y ofrecerla sería un enlace que no lleva a ninguna parte.
         * ⚠️ `exists()` y no `get()`: aquí solo hace falta saber si hay alguna.
         */
        /*
         * **La primera página que una INSTANCIA viste por su cuenta** (F5 · T2a `#647`; mudada en T2b,
         * `#654`): si su paquete trae `web/contacto.blade.php`, se sirve la suya; si no, el ANFITRIÓN
         * MÍNIMO del producto (`anfitrion/contacto`), que es una página que funciona, no un hueco.
         *
         * ⚠️⚠️ Mudarla exigió antes partir sus pruebas por lo que afirman (`#649`): la conducta —esto
         * que se pasa a la vista, el envío, el correo— se queda en el producto y la suite corre SIN
         * paquete (`phpunit.xml`); el marcado se fue con la vista y lo juzga la huella de maquetación.
         *
         * ⚠️ Lo que se pasa aquí es el CONTRATO DE VISTA (`InstanceViews::CONTRATO_DE_VISTAS`):
         * renombrar una clave rompe la landing de todas las instancias a la vez, cada una en su
         * servidor. Lo vigila `InstanceViewContractTest`.
         */
        return view($this->instancia->pick('contacto', 'anfitrion.contacto'), [
            'answers' => SiteDestinations::answersItself(
                withFaq: Faq::where('is_active', true)->exists(),
            ),
            'topics' => ContactTopics::ALL,
        ]);
    }

    public function store(Request $request)
    {
        // Anti-spam: honeypot. Si el campo oculto viene relleno, es un bot: respondemos como si todo
        // fuera bien, pero no enviamos nada. ⚠️ El nombre del campo vive en `Honeypot` (`#654`): la
        // vista lo pinta por `<x-site.honeypot>` y aquí no se escribe dos veces.
        if (Honeypot::tripped($request)) {
            return redirect()->route('contacto')->with('contact_sent', true);
        }

        // Anti-bot Turnstile (auditoría Fase 1, A4): no-op si no hay claves configuradas (la web
        // funciona igual); con claves, un token ausente/ inválido se descarta como el honeypot. La
        // ruta lleva además `throttle:5,1` como tope de tasa. Espejo de `Register`.
        if (! Turnstile::verify((string) $request->input('cf-turnstile-response'), (string) $request->ip())) {
            return redirect()->route('contacto')->with('contact_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            /*
             * ⚠️ **El tema es OPCIONAL y no tiene opción preseleccionada** (`#535`). El artboard
             * pinta el desplegable con «Un cumpleaños» arriba; dejarlo así haría que quien no lo
             * toca mandara un tema que no ha elegido, y eso es peor que no tener el dato: *una
             * ausencia no es una afirmación*. Sin elegir, el correo no dice nada del tema.
             * ⚠️ `in:` sobre la lista cerrada: el valor llega de un `<select>`, o sea del cliente.
             */
            'topic' => ['nullable', 'string', 'in:'.implode(',', ContactTopics::ALL)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        // Fase 7.5 (decisión #180): el formulario de contacto SOLO envía un email
        // al administrador (al setting `contact.email`); ya NO persiste en BD (la
        // bandeja de contacto del panel se descartó). Si el setting está vacío, el
        // formulario sigue respondiendo OK sin enviar (no rompe la web pública).
        $to = Setting::value('contact.email');
        if ($to) {
            Mail::to($to)->send(new ContactMessageMail([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'topic' => $data['topic'] ?? null,
                'message' => $data['message'],
                'locale' => app()->getLocale(),
            ]));
        }

        return redirect()->route('contacto')->with('contact_sent', true);
    }
}
