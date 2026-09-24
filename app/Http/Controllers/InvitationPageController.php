<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\CalendarFile;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Turnstile;
use App\Http\Concerns\RecordsPartyFacts;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
    use RecordsPartyFacts;

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

        // El hecho de la RESERVA (`specs/analitica-fiesta.md` §4.2): solo lo que el dominio ACEPTÓ, y de ello
        // solo el sí o el no y si viene acompañado. El nombre del niño no viaja.
        if ($outcome->accepted && $invitation->reservation !== null) {
            $this->partyFact($request, $invitation->reservation, 'invitation_replied', [
                'attending' => $data['attending'] === '1' ? 'yes' : 'no',
                'companion' => (bool) ($outcome->reply->companion ?? false),
            ]);
        }

        return $volver
            ->with('invitation_status', $outcome->accepted
                ? ($data['attending'] === '1' ? 'yes' : 'no')
                : (string) $outcome->reason)
            // ⚠️ Solo el nombre que ACABA de escribir quien contesta, y solo en SU sesión: es para
            // decirle «contamos con Hugo» y nada más. La página no lista ni una respuesta.
            ->with('invitation_child', trim((string) $data['child_name']))
            // El RECIBO, solo tras un «sí» y solo si de verdad se guardó una fila. Va por flash y no
            // en la página: es una credencial de dos horas sobre los datos de un menor, y publicarla
            // en el HTML de una página que ve cualquiera con el enlace sería regalarla.
            ->with('invitation_receipt', $outcome->accepted && $data['attending'] === '1' && $outcome->reply !== null
                ? $this->invitations->receiptUrl($outcome->reply)
                : null);
    }

    /**
     * **El RECIBO** (§4.5·6): las dos ofertas que se le hacen a quien acaba de decir que sí.
     *
     * ⚠️⚠️ **Lo autoriza la FIRMA de la URL, no el token de la invitación.** Son dos alcances
     * distintos: el token abre la fiesta entera y esto abre UNA respuesta. Mezclarlos le daría a
     * cualquiera con el enlace de la fiesta los datos de todos los niños.
     *
     * ⚠️ Pasadas las dos horas, la firma caduca y Laravel responde 403 antes de llegar aquí. No es un
     * enlace de edición (D9): lo que se dejó se queda como está.
     */
    public function receipt(InvitationReply $reply, Response $response): Response
    {
        $reservation = $reply->reservation;

        abort_if($reservation === null || $reply->dismissed_at !== null, 404);

        $type = $reservation->ticketType;
        $nameKey = $type?->guestNameFieldKey();

        return response()
            ->view('invitation.receipt', [
                'reply' => $reply,
                'childName' => (string) $reply->child_name,
                // Las columnas del pack **menos la del nombre**, que ya se contestó: volver a pedirlo
                // aquí sería preguntar dos veces lo mismo y abrir la puerta a que no coincidan.
                'fields' => collect($type?->guestFields() ?? [])
                    // El esquema normalizado garantiza la clave: un `?? null` afirmaría una duda que el
                    // contrato ya cierra, y Larastan lo dice.
                    ->reject(fn (array $f): bool => $f['key'] === $nameKey)
                    ->values()->all(),
                'data' => (array) ($reply->data ?? []),
                'companion' => $reply->companion,
                // ❗ **Si ya firmó, no se le vuelve a ofrecer** (`#704`, §10.6·C). La pregunta cruza la
                // frontera con Identity y va por contrato; el implementador responde por la ATADURA de
                // `#576`, no por el nombre.
                'signed' => app(PartyInvitations::class)->waiverSignedFor($reply),
                // «Lo dejo y me voy» lleva al justificante de ESTA reserva con la respuesta atada, y
                // el nombre del menor entero — sin partirlo en nombre y apellidos (`#236`).
                // ⚠️⚠️ Los dos extras viajan **DENTRO** de la firma: medido, pegar un `&x=y` a una URL
                // ya firmada la invalida, y el padre que acaba de decir que sí habría recibido un 403.
                'waiverUrl' => $reservation->guardianAuthorizationSignedUrl([
                    'invitation_reply_id' => (int) $reply->getKey(),
                    'minor' => (string) $reply->child_name,
                ]),
                'open' => app(PartyInvitations::class)->repliesOpenFor($reservation),
            ])
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** Guarda las dos ofertas. La firma de la URL es lo que autoriza; el plazo lo re-mira el dominio. */
    public function saveReceipt(Request $request, InvitationReply $reply): RedirectResponse
    {
        abort_if($reply->reservation === null, 404);

        $data = $request->validate([
            'companion' => ['sometimes', 'nullable', 'string', Rule::in(InvitationReply::COMPANIONS)],
            'guest_data' => ['sometimes', 'nullable', 'array'],
            'guest_data.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $saved = app(PartyInvitations::class)->completeReply(
            $reply,
            is_string($data['companion'] ?? null) ? $data['companion'] : null,
            is_array($data['guest_data'] ?? null) ? $data['guest_data'] : [],
        );

        // ⚠️ Se vuelve a la MISMA URL firmada: `back()` perdería la firma y el padre acabaría en un 403
        // justo después de que le hayamos guardado los datos.
        return redirect()
            ->to($request->fullUrl())
            ->with('receipt_status', $saved ? 'saved' : 'closed');
    }

    public function show(Request $request, string $token, Response $response): Response
    {
        $invitation = $this->invitations->resolvePublic($token);

        abort_if($invitation === null, 404);

        $reservation = $invitation->reservation;

        abort_if($reservation === null, 404);

        // La analítica de la fiesta (`specs/analitica-fiesta.md` §4.2): una apertura, como hecho de la RESERVA
        // y sin visitante. Las vistas previas de los chats (robots) no cuentan.
        $this->partyFact($request, $reservation, 'invitation_viewed');

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
                // ── Lo que se ve al PEGAR el enlace en un chat (§4.6, T5·4) ──
                //
                // ⚠️⚠️ **Solo nombre, edad, día, hora y negocio.** La vista previa la pinta el chat de
                // la clase entera —y a veces la caja de un buscador que nadie controla—, así que aquí
                // no entran ni la dirección, ni el menú, ni una sola respuesta. La página lleva
                // `noindex`; esto es lo único que sale de ella sin que nadie la abra.
                'preview' => $this->previewOf($invitation, $reservation, $date),
                // «Añadir al calendario»: `null` cuando falta la hora o la duración —sin dato, sin
                // bloque—, y así el botón no existe en vez de ofrecer un fichero vacío.
                'calendarUrl' => $this->calendarEventOf($reservation) === null
                    ? null
                    : route('invitation.calendar', ['token' => $invitation->token]),
            ])
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * **El `.ics` de la fiesta** (§4.6, `DECISIONES #705`).
     *
     * ⚠️ Mismo portero que la página —`resolvePublic()`— y el mismo 404 para los cuatro «no»: si esta
     * ruta distinguiera un token caducado de uno inventado, sería la rendija que §4.5·12 cerró en la
     * página. Y no lleva el token dentro del fichero: el `UID` se compone con el id de la invitación.
     */
    public function calendar(Request $request, string $token): Response
    {
        $invitation = $this->invitations->resolvePublic($token);

        abort_if($invitation === null, 404);

        $reservation = $invitation->reservation;
        $evento = $reservation === null ? null : $this->calendarEventOf($reservation);

        // Sin hora o sin duración no hay evento que dar. Es el mismo criterio que el bloque de la
        // página: «sin dato, sin bloque».
        abort_if($reservation === null || $evento === null, 404);

        $this->partyFact($request, $reservation, 'invitation_calendar_downloaded');

        [$inicio, $fin] = $evento;
        $negocio = trim((string) Setting::businessName());
        $sitio = array_filter([
            $negocio,
            trim((string) Setting::value('address.line1')),
            trim((string) Setting::value('address.line2')),
        ], static fn (string $linea): bool => $linea !== '');

        $ics = CalendarFile::event(
            // ESTABLE y sin el token dentro: volver a descargarlo actualiza el evento del padre en vez
            // de duplicárselo, y el fichero puede acabar en un calendario compartido.
            uid: 'invitacion-'.$invitation->getKey().'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'jumpweb'),
            summary: __('invitation.calendar.summary', ['name' => (string) $invitation->honoree_name]),
            startsAt: $inicio,
            endsAt: $fin,
            // ⚠️ La zona del PARQUE, no la del servidor ni la del móvil del padre: las franjas son hora
            // de pared (§7.2·R13).
            timezone: DisplayTime::timezone(),
            location: implode(', ', $sitio),
        );

        return response($ics, 200, [
            'Content-Type' => CalendarFile::MIME,
            'Content-Disposition' => 'attachment; filename="'.$this->calendarFileName($invitation).'"',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /**
     * El PRINCIPIO y el FIN de la fiesta como instantes, o `null` si falta alguno.
     *
     * ⚠️⚠️ La duración es la EFECTIVA (`occupiedMinutes()`: base + hora extra), no la del producto —la
     * trampa de `#426`—, y el fin de la FRANJA no vale: en una fiesta de dos horas diría una hora menos.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function calendarEventOf(OrderItem $reservation): ?array
    {
        $date = $reservation->slot?->date;
        // ⚠️ Sin `?->` a la izquierda de un `??`: el propio `??` ya tapa la relación ausente, y
        // encadenarlos hace que Larastan cuente una rama que no existe.
        $hora = (string) ($reservation->slot->start_time ?? '');
        $minutos = $reservation->occupiedMinutes();

        if ($date === null || $hora === '' || $minutos === null || $minutos <= 0) {
            return null;
        }

        $inicio = CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date->toDateString().' '.substr($hora, 0, 8),
            DisplayTime::timezone(),
        );

        // Una fecha que no case con el formato devuelve `null`, y entonces no hay evento: es el mismo
        // criterio que arriba, «sin dato, sin bloque».
        return $inicio === null ? null : [$inicio, $inicio->addMinutes($minutos)];
    }

    /** Un nombre de fichero que una persona reconozca en su carpeta de descargas, sin acentos ni token. */
    private function calendarFileName(PartyInvitation $invitation): string
    {
        $slug = Str::slug((string) $invitation->honoree_name);

        return ($slug === '' ? 'invitacion' : 'cumple-'.$slug).'.ics';
    }

    /**
     * Lo que se ve al pegar el enlace (§4.6): título, descripción e imagen.
     *
     * ⚠️ La imagen es la del TEMA de la instalación si la hay —el mismo PNG que usan los correos,
     * porque un SVG no vale para Open Graph— y si no, la del sitio. Las medidas se declaran **solo
     * cuando el fichero es nuestro y se puede medir**: inventarlas para una URL externa sería afirmar
     * algo que no sabemos.
     *
     * @return array{title: string, description: string, image: ?string, width: ?int, height: ?int}
     */
    private function previewOf(PartyInvitation $invitation, OrderItem $reservation, mixed $date): array
    {
        $nombre = trim((string) $invitation->honoree_name);
        $edad = $invitation->honoree_age === null ? null : (int) $invitation->honoree_age;
        $negocio = trim((string) Setting::businessName());

        $titulo = $edad === null
            ? __('invitation.og.title_no_age', ['name' => $nombre])
            : __('invitation.og.title', ['name' => $nombre, 'age' => $edad]);

        if ($date !== null) {
            $titulo .= ' · '.DisplayTime::dayLabel(Carbon::parse($date->toDateString()));
        }

        $hora = substr((string) ($reservation->slot->start_time ?? ''), 0, 5);

        return [
            'title' => $titulo,
            'description' => $hora === ''
                ? __('invitation.og.description_no_time', ['business' => $negocio])
                : __('invitation.og.description', ['time' => $hora, 'business' => $negocio]),
            ...$this->previewImage(),
        ];
    }

    /** @return array{image: ?string, width: ?int, height: ?int} */
    private function previewImage(): array
    {
        foreach (['img/client-logo@4x.png', 'og-image.jpg'] as $candidato) {
            $ruta = public_path($candidato);
            if (! is_file($ruta)) {
                continue;
            }
            $medidas = @getimagesize($ruta);

            return [
                'image' => asset($candidato).'?v='.@filemtime($ruta),
                'width' => $medidas === false ? null : (int) $medidas[0],
                'height' => $medidas === false ? null : (int) $medidas[1],
            ];
        }

        // La del panel, que es una URL externa ya saneada: se publica sin medidas porque no se pueden
        // medir sin salir a buscarla.
        $site = (array) (view()->shared('site') ?? []);
        $externa = trim((string) ($site['og_image'] ?? ''));

        return ['image' => $externa === '' ? null : $externa, 'width' => null, 'height' => null];
    }
}
