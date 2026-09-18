<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * **La PÁGINA de la invitación digital** (`docs/specs/celebracion-e-invitacion.md` §4.6, T5·1;
 * `DECISIONES #521`). Es la que abre un padre con el enlace que le pasaron por el chat de la clase.
 *
 * ❗❗ **Con esta ruta, `Invitation.url` deja de ser `null` sin tocar una línea más**: el enlace se
 * compone preguntando por el NOMBRE de esta ruta (`PartyInvitations::PUBLIC_ROUTE`), así que el
 * mecanismo que la T4·6 dejó preparado se cierra solo en el momento en que este fichero existe.
 *
 * ## Es una HOJA EN BLANCO, igual que su gemela de la API
 *
 * No pinta ni una respuesta, ni un contador, **ni si un nombre concreto ya contestó**. Ése es el
 * motivo por el que su enlace se puede repartir a un grupo de clase entero. Toda la decisión de a
 * quién se le abre vive en `PartyInvitations::resolvePublic()` —un solo sitio para la web y para la
 * API—, y los cuatro «no» son **el mismo 404**: distinguirlos diría que ese token existió (§7.2·R10).
 *
 * ## Las tres cabeceras, y por qué cada una
 *
 *  · `noindex` — lo pone el layout enfocado: una fiesta de un niño no se indexa.
 *  · `Cache-Control: no-store` (`RGPD-04`) — se entra **sin sesión** y lo que se sirve es el nombre y
 *    la edad de un menor, así que ninguna caché intermedia debe guardarlo.
 *  · ⚠️⚠️ `Referrer-Policy: no-referrer` — **es la que se olvida y la que más cuesta**: sin ella, el
 *    día que la página tenga el enlace «Cómo llegar», pulsarlo le manda a Google **el token en el
 *    `Referer`**. Una credencial que abre los datos de una fiesta no puede viajar en la cabecera de
 *    una petición a un tercero.
 */
class InvitationPageController extends Controller
{
    public function __construct(private readonly PartyInvitations $invitations) {}

    /**
     * Lo que contesta un padre, desde la propia página.
     *
     * ⚠️⚠️ **El desenlace va por FLASH y la respuesta es un redirect** (patrón POST-redirect-GET, el
     * mismo del justificante): sin él, recargar reenvía el formulario y el padre contesta dos veces
     * sin querer — que aquí no rompe nada (el repetido se acepta en silencio) pero le enseñaría un
     * diálogo del navegador que no entiende.
     *
     * ⚠️ **El anti-robot va ANTES que el dominio**, y su fallo se DICE: Turnstile le falla también a
     * personas, y aquí un fallo es un niño que se queda sin confirmar. Mentirle con un «hecho» sería
     * peor que el fallo.
     */
    public function reply(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->resolvePublic($token);

        abort_if($invitation === null, 404);

        // Se valida DESPUÉS de resolver, igual que en la API: al revés, un cuerpo bien formado
        // distinguiría un token real de uno inventado.
        $data = $request->validate([
            'child_name' => ['required', 'string', 'min:1', 'max:'.InvitationReply::CHILD_NAME_MAX],
            'attending' => ['required', 'in:1,0'],
        ]);

        // ⚠️ Se vuelve a la página POR SU NOMBRE y no con `back()`: aquél depende del `Referer`, que lo
        // manda el cliente y puede no venir —un enlace abierto desde una app de mensajería suele
        // quitarlo—. Con `back()` el padre acababa en la portada sin saber si se había apuntado.
        $volver = redirect()->route(PartyInvitations::PUBLIC_ROUTE, ['token' => $token]);

        if (! Turnstile::verify((string) $request->input('cf-turnstile-response'), (string) $request->ip())) {
            return $volver->with('invitation_status', 'antibot');
        }

        $outcome = $this->invitations->reply(
            $invitation,
            (string) $data['child_name'],
            $data['attending'] === '1',
        );

        return $volver
            ->with('invitation_status', $outcome->accepted
                ? ($data['attending'] === '1' ? 'yes' : 'no')
                : (string) $outcome->reason)
            // ⚠️ Solo el nombre que ACABA de escribir quien contesta, y solo en SU sesión: es para
            // decirle «contamos con Hugo» y nada más. La página no lista ni una respuesta.
            ->with('invitation_child', trim((string) $data['child_name']));
    }

    public function show(string $token, Response $response): Response
    {
        $invitation = $this->invitations->resolvePublic($token);

        abort_if($invitation === null, 404);

        $reservation = $invitation->reservation;

        abort_if($reservation === null, 404);

        $date = $reservation->slot?->date;

        return response()
            ->view('invitation.show', [
                'invitation' => $invitation,
                'honoreeName' => (string) $invitation->honoree_name,
                'honoreeAge' => $invitation->honoree_age === null ? null : (int) $invitation->honoree_age,
                'hostLine' => (string) $invitation->host_line,
                // De la CUENTA y solo si el anfitrión lo marcó (§4.5·12): en la invitación no hay
                // ningún campo donde teclear un teléfono, y eso es deliberado.
                'hostPhone' => $invitation->show_host_phone
                    ? ($reservation->order?->user?->phone ?: null)
                    : null,
                'dayLabel' => $date === null ? null : DisplayTime::dayLabel(Carbon::parse($date->toDateString())),
                // Compuesta por el dominio: base + hora extra. El fin de la FRANJA diría una hora de
                // menos en una fiesta de dos horas (la trampa de `#426`).
                'timeWindow' => $reservation->displayTimeWindow(),
                'productName' => $reservation->displayProductName(),
                'menu' => $this->invitations->menuFor($reservation),
                // La tarjeta se ve igual pasado el plazo (§7.2·R8): lo único que cierra son las
                // respuestas, y la información de la fiesta hace falta **el día de la fiesta**.
                'repliesOpen' => $this->invitations->repliesOpenFor($reservation),
            ])
            ->header('Referrer-Policy', 'no-referrer');
    }
}
