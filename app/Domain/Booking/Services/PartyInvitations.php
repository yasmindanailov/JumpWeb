<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\InvitationReplyOutcome;
use App\Domain\Booking\Contracts\SignedInvitationReplies;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\PersonNameKey;
use App\Domain\Platform\Services\PublicFreeText;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * **La INVITACIÓN DIGITAL de una reserva y lo que contestan los padres**
 * (`docs/specs/celebracion-e-invitacion.md` §4.5, T4·2; `DECISIONES #574`).
 *
 * ❗❗ **Un padre no escribe `order_items`.** Lo que contesta vive en su propia tabla y se le PROPONE
 * al anfitrión, que la adopta al guardar por la puerta de siempre. Por eso este servicio **no toca
 * dinero ni aforo**, no bloquea `slots` y no roza ningún fichero del `CRITICAL_RE`.
 *
 * ## El lock, y por qué es UNA sola fila
 *
 * La exclusión que hace falta es entre **respuestas de la misma invitación** compitiendo por la última
 * plaza, y todas pasan por su fila de `party_invitations`. Bloquear ahí —y **solo ahí**— da esa
 * exclusión sin añadir ninguna arista al grafo de bloqueos del post-form, que ya tiene un ciclo
 * conocido entre `orders` e `items` (ficha en `DEUDA.md`). La reserva se lee fresca dentro de la misma
 * transacción, sin `FOR UPDATE`: no se escribe, y pedirle lock la metería en ese grafo para nada.
 *
 * ⚠️ `SEC-04` aplicado al tiempo: entre que el padre abre la página y contesta puede pasar la fiesta,
 * cancelarse el pedido, vencer el plazo **o llenarse la lista con otros «sí»**. Todo se re-comprueba
 * DENTRO, nunca solo al pintar.
 */
final class PartyInvitations
{
    /**
     * Tope de respuestas por invitación: `3 × invitados` (§4.5·12). Frena el spam de «no» —que no
     * ocupa plaza y por tanto no lo para la regla de lista completa— sin castigar el caso real, donde
     * una familia contesta, se equivoca y vuelve a contestar.
     */
    public const REPLY_CAP_PER_GUEST = 3;

    /**
     * El NOMBRE de la ruta de la página pública, que nace en la T5 (§4.6). Se pregunta por su nombre
     * y nunca se compone el path a mano: es lo que hace que {@see shareUrlFor()} se cierre solo.
     */
    public const PUBLIC_ROUTE = 'invitation.show';

    /** La ruta del RECIBO, firmada y temporal (§4.5·6). */
    public const RECEIPT_ROUTE = 'invitation.receipt';

    /**
     * Las DOS HORAS del recibo (D9). No es un plazo elegido al peso: es el tiempo en que un padre que
     * acaba de contestar sigue con el móvil en la mano. Más allá, el enlace sería una credencial viva
     * sobre los datos de un menor viajando por un chat de padres.
     */
    public const RECEIPT_HOURS = 2;

    public function __construct(
        private GuestCountPolicy $policy,
        private SignedInvitationReplies $signed,
    ) {}

    /**
     * La invitación de esta reserva, creándola si no existe.
     *
     * ⚠️ **Nace en el GET a propósito** (§4.5·1): Web Share necesita el enlace **en el mismo gesto**
     * del usuario, y un `fetch` previo pierde la activación en Safari. Un escáner de correos que abra
     * el enlace solo crea una fila vacía, sin nada que filtrar.
     *
     * ⚠️ El `firstOrCreate` puede chocar contra el único de `order_item_id` si dos peticiones entran a
     * la vez: **se relee, no se revienta**. Es el mismo trato que el resto del proyecto le da a una
     * carrera contra un índice que ya expresa la regla.
     *
     * `null` si el producto no ofrece invitación, o si su esquema por invitado no tiene columna de
     * nombre — sin ella no hay con qué emparejar y el interruptor no debería poder encenderse (T4·3).
     */
    public function forReservation(OrderItem $reservation): ?PartyInvitation
    {
        $type = $reservation->ticketType;

        if ($type === null || ! $type->guest_invitation || $type->guestNameFieldKey() === null) {
            return null;
        }

        $defaults = [
            'token' => PartyInvitation::freshToken(),
            'theme' => PartyInvitation::THEME_DEFAULT,
            'honoree_name' => $this->prefilledHonoreeName($reservation, $type),
            'honoree_age' => $this->prefilledHonoreeAge($reservation, $type),
            'host_line' => $this->prefilledHostLine($reservation),
            'show_host_phone' => false,
        ];

        try {
            return PartyInvitation::query()->firstOrCreate(
                ['order_item_id' => $reservation->getKey()],
                $defaults,
            );
        } catch (QueryException) {
            return PartyInvitation::query()->where('order_item_id', $reservation->getKey())->first();
        }
    }

    /**
     * ¿Se puede COMPARTIR?
     *
     * ⚠️⚠️ **Pasado el plazo se sigue compartiendo** (§7.2·R8), y es lo contrario de lo que la primera
     * versión de la spec decía: la información de la fiesta —dónde, a qué hora, qué menú— hace falta
     * justo **el día de la fiesta**, y cerrar el enlace con el plazo dejaría a los padres sin ella
     * precisamente cuando la necesitan. Lo único que el plazo cierra son las RESPUESTAS.
     */
    public function isShareable(OrderItem $reservation, ?PartyInvitation $invitation): bool
    {
        return $invitation !== null
            && trim((string) $invitation->honoree_name) !== ''
            && $this->policy->isOpenFor($reservation);
    }

    /**
     * Lo que contesta un padre, con la invitación bloqueada.
     *
     * @param  array<string, mixed>  $data  las columnas del pack que quiera dejar (G2), todas opcionales
     */
    public function reply(
        PartyInvitation $invitation,
        string $childName,
        bool $attending,
        ?string $companion = null,
        array $data = [],
    ): InvitationReplyOutcome {
        $childKey = PersonNameKey::for($childName);

        if ($childKey === '') {
            return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_NO_NAME);
        }

        $outcome = DB::transaction(function () use ($invitation, $childName, $childKey, $attending, $companion, $data): InvitationReplyOutcome {
            /** @var PartyInvitation|null $locked */
            $locked = PartyInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_CLOSED);
            }

            /** @var OrderItem|null $reservation */
            $reservation = OrderItem::query()
                ->with(['ticketType', 'slot', 'order'])
                ->whereKey($locked->order_item_id)
                ->first();

            if ($reservation === null || ! $this->policy->isOpenFor($reservation)) {
                return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_CLOSED);
            }
            // El MISMO plazo y la MISMA fuente que el número de invitados (D14): si el anfitrión ya no
            // puede mover su lista, un padre tampoco puede meterle a nadie.
            if (! $this->policy->isWithinWindow($reservation)) {
                return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_CUTOFF);
            }

            $quantity = max(0, (int) $reservation->quantity);
            $existing = $locked->replies()->get();

            if ($existing->count() >= self::REPLY_CAP_PER_GUEST * max(1, $quantity)) {
                return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_TOO_MANY);
            }

            // Un «no» NUNCA ocupa (D3). Y desde `#700` un «sí» TAMPOCO se rechaza por lista llena: lo
            // único que dice `placeFor()` es si toma plaza nueva o se une a una que ya tenía dueño.
            $joined = $attending && $this->placeFor($reservation, $existing, $childKey);

            $type = $reservation->ticketType;

            $reply = InvitationReply::query()->create([
                'party_invitation_id' => $locked->getKey(),
                'order_item_id' => $reservation->getKey(),
                'attending' => $attending,
                'child_name' => mb_substr(trim($childName), 0, InvitationReply::CHILD_NAME_MAX),
                'child_key' => $childKey,
                // Se sanea con el MISMO saneo del pack, tratando la respuesta como una fila: así una
                // clave inventada no entra y una edad fuera de rango no llega a la aritmética de un
                // suplemento el día que el anfitrión la adopte.
                'data' => $data === [] || $type === null ? null : ($type->sanitizeGuestData([$data], 1)[0] ?: null),
                'companion' => in_array($companion, InvitationReply::COMPANIONS, true) ? $companion : null,
            ]);

            // `RGPD-02`: el rastro NO lleva el nombre del niño ni sus datos. Solo qué reserva, si viene
            // o no, y si ocupó plaza nueva — que es lo que el operador necesita para entender la lista.
            AuditLogger::log('orders.invitation_reply_received', $reservation->order, [
                'order_code' => $reservation->order?->code,
                'order_item_id' => $reservation->getKey(),
                'attending' => $attending,
                'joined_existing_place' => $joined,
            ]);

            return InvitationReplyOutcome::accepted($reply, $joined);
        });

        return $outcome;
    }

    /**
     * ¿Este «sí» se une a una plaza que YA tenía dueño (`true`) o toma una nueva (`false`)?
     *
     * ⚠️ **Ya no contesta «no cabes»** (`#700`): la lista completa dejó de rechazar, y con ella
     * desapareció el único desenlace que dependía de **qué nombre** traía la respuesta. Lo que queda
     * es una distinción que el padre no puede observar —los dos caminos le dan el mismo 200— y que
     * solo usa el suelo de `#444` para saber cuántas plazas tienen dueño.
     *
     * La cuenta de §4.5·3: **fichas con nombre** (acotadas a la cantidad) **+ «sí» pendientes,
     * distintos por `child_key`, que no emparejan con ninguna de esas fichas**.
     *
     * @param  Collection<int, InvitationReply>  $existing
     */
    private function placeFor(OrderItem $reservation, $existing, string $childKey): bool
    {
        $namedKeys = $this->namedGuestKeys($reservation);

        // Empareja con una ficha ya escrita (regla 4) → esa plaza ya tiene dueño y es el mismo niño.
        if ($this->matches($namedKeys, $childKey)) {
            return true;
        }

        // Repite un «sí» pendiente (regla 5, V6) → se une a su propuesta y no ocupa plaza nueva.
        $pending = $existing
            ->filter(fn (InvitationReply $r): bool => (bool) $r->attending && $r->isPending())
            ->pluck('child_key')
            ->unique();

        if ($pending->contains($childKey)) {
            return true;
        }

        // Los pendientes que NO emparejan con una ficha escrita son los que ya ocupan plaza propia.
        $pendingOwnPlaces = $pending
            ->reject(fn (string $key): bool => $this->matches($namedKeys, $key))
            ->count();

        // ❗❗ **La lista completa ya NO rechaza** (`[DECIDIDO owner, 2026-09-18]`, `DECISIONES #700`;
        // sustituye a D2 de `#569`). Antes se devolvía `null` → `full`, y eso abría un ORÁCULO DE
        // PERTENENCIA que se cargaba la propiedad central de la feature: con la lista llena, un nombre
        // que EMPAREJA con una ficha escrita se aceptaba y uno nuevo recibía «full», así que cualquiera
        // con el enlace —un grupo de clase entero— podía **reconstruir la lista de invitados probando
        // nombres**. Lo encontró la revisión adversarial de `#579`.
        //
        // ⚠️⚠️ No era un descuido, sino una CONTRADICCIÓN de la propia spec: §4.5·3 mandaba rechazar y
        // §7.2·R1 manda que la hoja sea en blanco **también en sus errores**. Las dos no pueden
        // cumplirse a la vez cuando el nombre empareja, y la que se queda es la hoja en blanco.
        //
        // ▶ Lo que ocupa su sitio ya estaba escrito en §4.7: el «sí» que no cabe **se acepta igual** y
        // sale en el aviso «hay N respuestas que ya no caben: sube el número o avisa a esas familias».
        // La decisión vuelve al ANFITRIÓN, que es el único que sabe quién va. Lo que frena el spam
        // sigue siendo el tope `3 × invitados`, que no depende del nombre y por eso no delata nada.
        //
        // ⚠️ El valor devuelto sigue distinguiendo si se une a una plaza con dueño (`true`) o toma una
        // nueva (`false`), porque de eso vive el suelo de `#444`. Lo que desaparece es el «no».
        return false;
    }

    /**
     * Las claves normalizadas de las fichas que YA tienen nombre, acotadas a la cantidad de la reserva.
     *
     * ⚠️ La columna de nombre es **la primera `text`** del esquema, no la clave `name`
     * ({@see TicketType::guestNameFieldKey()}): el esquema por invitado no tiene un tipo «nombre» y
     * una instalación puede renombrarla desde su panel.
     *
     * @return list<string>
     */
    private function namedGuestKeys(OrderItem $reservation): array
    {
        $type = $reservation->ticketType;
        $nameKey = $type?->guestNameFieldKey();
        if ($type === null || $nameKey === null) {
            return [];
        }

        $keys = [];
        foreach (array_slice($reservation->guestData(), 0, max(0, (int) $reservation->quantity)) as $row) {
            // Sin `is_array($row)`: `guestData()` declara `array<int, array<string,string>>` y
            // `sanitizeGuestData()` lo impone al escribir. Comprobarlo afirmaba una duda que el
            // contrato ya cierra — y Larastan lo dice.
            $name = trim((string) ($row[$nameKey] ?? ''));
            if ($name !== '') {
                $keys[] = PersonNameKey::for($name);
            }
        }

        return $keys;
    }

    /**
     * ¿La clave de la respuesta es la de alguna ficha, **o la de su primera palabra**?
     *
     * El segundo caso es el real y no un adorno: el anfitrión pega «Mateo» con el pegado de la T2 y el
     * padre escribe «Mateo Ruiz» — con apellidos, porque es lo que se le pide para distinguir a dos
     * niños que se llamen igual.
     *
     * @param  list<string>  $namedKeys
     */
    private function matches(array $namedKeys, string $childKey): bool
    {
        // `explode()` nunca devuelve lista vacía: sin `?? ''`, que afirmaría lo contrario.
        $firstWord = PersonNameKey::for(explode(' ', $childKey)[0]);

        foreach ($namedKeys as $key) {
            if ($key === $childKey || ($key !== '' && $key === $firstWord)) {
                return true;
            }
        }

        return false;
    }

    // ══ Lo que ve el ANFITRIÓN (T4·6, §4.10; `DECISIONES #578`) ════════════════════════════════
    //
    // Hasta aquí este servicio solo sabía de lo que hace un PADRE. Lo de abajo es la otra mitad: el
    // resumen, la personalización y la ADOPCIÓN — el gesto por el que una respuesta deja de ser una
    // propuesta y se convierte en una ficha del formulario, que es la puerta de siempre.

    /**
     * La invitación que abre un desconocido con su token, **o `null`**.
     *
     * ⚠️⚠️ **`null` en TODOS los casos, y ése es el diseño** (§4.5·12, §7.2·R10): token inexistente,
     * enlace anulado, pedido cancelado, producto con la invitación apagada o titular anonimizado
     * devuelven **lo mismo**. Distinguirlos sería un oráculo — un «410 cancelada» frente a un «404 no
     * existe» le diría a cualquiera que ese token existió, y el enlace circula por un chat de padres.
     *
     * ⚠️ El plazo **no** entra aquí: pasado, la tarjeta se sigue viendo y lo que se cierra son las
     * respuestas (§7.2·R8). Eso lo dice `replies_open`, no un 404.
     */
    public function resolvePublic(string $token): ?PartyInvitation
    {
        if (mb_strlen($token) !== PartyInvitation::TOKEN_LENGTH) {
            return null;
        }

        /** @var PartyInvitation|null $invitation */
        $invitation = PartyInvitation::query()
            ->with(['reservation.ticketType', 'reservation.slot', 'reservation.order.user'])
            ->where('token', $token)
            ->first();

        $reservation = $invitation?->reservation;

        if ($invitation === null || $reservation === null) {
            return null;
        }

        // La supresión del titular cierra el canal, igual que `RGPD-03` hace con el post-form: lo que
        // la tarjeta publica es el nombre y la edad de un menor.
        if ($reservation->order?->user?->isAnonymized() ?? false) {
            return null;
        }

        // El producto pudo apagar la invitación después de repartirse el enlace. Y `isOpenFor` cubre
        // el pedido cancelado y la fiesta ya celebrada — un enlace no sobrevive a su fiesta.
        if (! (bool) $reservation->ticketType->guest_invitation || ! $this->policy->isOpenFor($reservation)) {
            return null;
        }

        return $invitation;
    }

    /**
     * **El MENÚ de la fiesta** (§4.6): los complementos **de esta reserva** que el catálogo marcó como
     * «se enseña en la invitación» (D12).
     *
     * ⚠️⚠️ **Los COMPRADOS, no los ofrecidos.** Lo que un padre quiere saber es qué van a comer en
     * ESTA fiesta, no qué se podría haber pedido: listar el catálogo pondría en la invitación cosas
     * que nadie ha pagado.
     *
     * ⚠️ El nombre se lee con `tr()` y nunca del array crudo —la trampa de `#463`—: es un campo
     * traducible, y leerlo a pelo devuelve el mapa de idiomas entero. Lo mismo vale para lo que
     * INCLUYE cada plato, que además puede venir como lista **o como texto suelto**
     * ({@see TicketType::featureLines()}).
     *
     * ▶ Cada plato viaja con sus `features` para que la página pueda ofrecer el «Más info» —la pieza
     * `<details>` que el post-form ya usa, y que funciona **sin una línea de JS**, que es la condición
     * de esta página—. Un plato sin detalles se pinta como una fila y ya: un desplegable vacío es
     * peor que ninguno.
     *
     * @return list<array{name: string, features: list<string>}>
     */
    public function menuFor(OrderItem $reservation): array
    {
        $type = $reservation->ticketType;

        if ($type === null) {
            return [];
        }

        // ❗❗ **El pivote se lee DIRECTO y no por `addons()`** (`DECISIONES #521`). Esa relación filtra
        // `is_sellable` y `is_active` —es la del CATÁLOGO, que describe qué se vende hoy—, así que
        // usarla aquí hacía que retirar un complemento de la venta lo borrara del menú de **una fiesta
        // que ya lo había pagado**: cambio de carta en septiembre, y las invitaciones de octubre dejan
        // de decir qué se come. Lo encontró la sonda en el navegador, no la suite.
        //
        // ⚠️ Lo que manda es lo COMPRADO más la marca del enganche. El catálogo dice qué se vende;
        // esta reserva dice qué se pagó, y son preguntas distintas.
        $marked = DB::table('product_addons')
            ->where('product_id', $type->getKey())
            ->where('show_in_invitation', true)
            ->pluck('addon_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($marked === []) {
            return [];
        }

        return $reservation->children
            ->filter(static fn (OrderItem $line): bool => in_array((int) $line->ticket_type_id, $marked, true))
            ->map(static fn (OrderItem $line): array => [
                'name' => trim((string) $line->ticketType?->tr('name')),
                'features' => $line->ticketType?->featureLines() ?? [],
            ])
            ->filter(static fn (array $dish): bool => $dish['name'] !== '')
            ->unique('name')
            ->values()
            ->all();
    }

    /** ¿Admite respuestas ahora mismo? La tarjeta se ve igual; esto solo gobierna los botones. */
    public function repliesOpenFor(OrderItem $reservation): bool
    {
        return $this->policy->isOpenFor($reservation) && $this->policy->isWithinWindow($reservation);
    }

    /**
     * ¿Esa respuesta ya tiene justificante? (`#704`, §10.6·C)
     *
     * ⚠️⚠️ **Se pregunta al CONTRATO y no a la base**: las firmas son de Identity y Booking no puede
     * mirar allí (`ModuleBoundariesTest`). Es la misma frontera que el suelo de plazas de `#444`, y el
     * implementador es el mismo — el único sitio donde las dos mitades coexisten.
     *
     * ⚠️ Una respuesta descartada no se pregunta: ya no tiene recibo (su página responde 404).
     */
    public function waiverSignedFor(InvitationReply $reply): bool
    {
        return $this->signed->isReplySigned((int) $reply->getKey());
    }

    /**
     * El enlace que el anfitrión reparte.
     *
     * ⚠️⚠️ **`null` mientras la PÁGINA pública no exista, y eso se cierra solo.** Esa página es la T5
     * (§4.6) y no puede nacer a medias: recoge alergias de un menor que va a leer un tercero, así que
     * `§7.2·R7` le exige su aviso de privacidad — publicarla sin él sería un defecto de RGPD, y
     * publicar una ruta que no lleva a ninguna página sería peor.
     *
     * ▶ Lo que **no** se hace aquí es clavar el path a mano «para que ya tenga algo»: el día que la T5
     * declarara su ruta en otro sitio, el anfitrión estaría repartiendo un enlace muerto y nadie se
     * enteraría. Preguntando por el NOMBRE de la ruta, el campo se rellena **solo** en cuanto exista,
     * sin que nadie tenga que acordarse de volver aquí.
     */
    public function shareUrlFor(PartyInvitation $invitation): ?string
    {
        return Route::has(self::PUBLIC_ROUTE)
            ? route(self::PUBLIC_ROUTE, ['token' => (string) $invitation->token])
            : null;
    }

    /**
     * «N vienen · M no pueden · K por repasar» (§4.7).
     *
     * ⚠️ «Por repasar» son las pendientes de las DOS clases: un «no» también hay que verlo —lleva a
     * bajar el número de invitados— y contarlo solo entre los «sí» dejaría avisos invisibles.
     *
     * @return array{yes: int, no: int, pending: int}
     */
    public function summaryFor(OrderItem $reservation): array
    {
        $replies = InvitationReply::query()
            ->where('order_item_id', $reservation->getKey())
            ->whereNull('dismissed_at')
            ->get(['id', 'attending', 'adopted_at', 'dismissed_at', 'child_key']);

        return [
            // Distintos por `child_key`: dos respuestas del mismo niño son un niño (regla 5).
            'yes' => $replies->filter(fn (InvitationReply $r): bool => (bool) $r->attending)
                ->pluck('child_key')->unique()->count(),
            'no' => $replies->reject(fn (InvitationReply $r): bool => (bool) $r->attending)
                ->pluck('child_key')->unique()->count(),
            'pending' => $replies->filter(fn (InvitationReply $r): bool => $r->isPending())->count(),
        ];
    }

    /**
     * Cómo el anfitrión personaliza su invitación (§4.7).
     *
     * ⚠️⚠️ **Escribe SOLO `party_invitations`, y eso es la propiedad, no un detalle de implementación**:
     * `order_items.updated_at` es el testigo del post-form (§1.3·2) y tocarlo aquí dejaría obsoleta la
     * página que el anfitrión tiene abierta **por cambiar el color de una banda**.
     *
     * ⚠️ `honoree_name` y `host_line` son TEXTO LIBRE que se publica bajo el dominio del parque
     * (§7.2·R9): pasan por `PublicFreeText`, que **rechaza** enlaces y correos en vez de limpiarlos —
     * una invitación no puede decir «paga el regalo en este enlace». Un valor con enlace se queda como
     * estaba: no se escribe a medias.
     *
     * @param  array<string, mixed>  $data  solo las claves presentes se tocan (es un PATCH)
     */
    public function personalize(PartyInvitation $invitation, array $data): PartyInvitation
    {
        $changes = [];

        if (array_key_exists('theme', $data)) {
            $theme = is_scalar($data['theme']) ? (string) $data['theme'] : '';
            // Lista CERRADA (§3.4). Hoy tiene un solo tema: los tres los elige el owner viéndolos
            // renderizados en la T5, y hasta entonces cualquier otro valor cae al de por defecto.
            $changes['theme'] = in_array($theme, PartyInvitation::THEMES, true)
                ? $theme
                : PartyInvitation::THEME_DEFAULT;
        }

        foreach ([
            'honoree_name' => PartyInvitation::HONOREE_NAME_MAX,
            'host_line' => PartyInvitation::HOST_LINE_MAX,
        ] as $field => $max) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = is_scalar($data[$field]) ? (string) $data[$field] : '';
            $clean = PublicFreeText::clean($value, $max);
            // `null` = llevaba un enlace o un correo: se RECHAZA, no se limpia a medias.
            if ($clean !== null) {
                $changes[$field] = $clean;
            }
        }

        if (array_key_exists('honoree_age', $data)) {
            $age = $data['honoree_age'];
            $changes['honoree_age'] = is_numeric($age) ? max(0, min(255, (int) $age)) : null;
        }

        if (array_key_exists('show_host_phone', $data)) {
            $changes['show_host_phone'] = (bool) $data['show_host_phone'];
        }

        if ($changes !== []) {
            $invitation->fill($changes)->save();
        }

        return $invitation;
    }

    /**
     * Las respuestas **por repasar**, cada «sí» con la ficha sobre la que se propone (regla 4).
     *
     * ⚠️⚠️ **La propuesta se calcula AQUÍ y no en cada cliente**, y es la razón de que este método
     * exista: la web y la app son clientes iguales (`API-first`), y una regla de emparejado repetida en
     * dos sitios diverge en el primer arreglo. Lo que sale es la POSICIÓN de la ficha; el prerrelleno
     * campo a campo —solo los vacíos— lo pinta quien la dibuja.
     *
     * El reparto es **conjunto y determinista**: dos respuestas no pueden proponerse sobre la misma
     * ficha, así que se recorren por orden de llegada y cada una consume la suya. Las fichas que ya
     * adoptó otra respuesta salen ocupadas de entrada.
     *
     * ⚠️ `slot_index` **es `null` cuando no cabe**, y eso no es un error: es la carrera de §7.1·3
     * dicha en voz alta —entre que el anfitrión pintó y guardó entraron más «sí»— y lo que la T6 pinta
     * como «hay N respuestas que ya no caben». Un «no» tampoco lleva ficha: no se pinta sobre ninguna.
     *
     * @return list<array{id: int, child_name: string, attending: bool, companion: string|null, guest_data: array<string, string>, slot_index: int|null, repeated: bool}>
     */
    public function proposalsFor(OrderItem $reservation): array
    {
        $slots = $this->slotsOf($reservation);

        $pending = InvitationReply::query()
            ->where('order_item_id', $reservation->getKey())
            ->pending()
            ->orderBy('id')
            ->get();

        // Regla 5: con el mismo `child_key` manda **la más reciente**, y las demás no se proponen
        // aparte — son el mismo niño. `keyBy` sobre una lista ordenada por id se queda la última.
        $latestByChild = $pending
            ->filter(fn (InvitationReply $r): bool => (bool) $r->attending)
            ->keyBy('child_key');

        $proposals = [];

        foreach ($pending as $reply) {
            $childKey = (string) $reply->child_key;
            $isYes = (bool) $reply->attending;

            // De un niño repetido solo se propone su respuesta más reciente.
            if ($isYes && (int) ($latestByChild[$childKey]->id ?? 0) !== (int) $reply->id) {
                continue;
            }

            $proposals[] = [
                'id' => (int) $reply->getKey(),
                'child_name' => (string) $reply->child_name,
                'attending' => $isYes,
                'companion' => $reply->companion === null ? null : (string) $reply->companion,
                'guest_data' => array_map(strval(...), (array) ($reply->data ?? [])),
                'slot_index' => $isYes ? $this->takeSlotFor($slots, $childKey) : null,
                'repeated' => $isYes && $pending
                    ->filter(fn (InvitationReply $r): bool => (bool) $r->attending && $r->child_key === $childKey)
                    ->count() > 1,
            ];
        }

        return $proposals;
    }

    /**
     * El anfitrión ADOPTA respuestas: dejan de proponerse y pasan a ser fichas suyas.
     *
     * ⚠️⚠️ **Se re-comprueba todo aquí dentro** (`SEC-04` aplicado al tiempo, como `reply()`): que la
     * respuesta siga PENDIENTE y que sea **de esta reserva**. Un id de otra fiesta no adopta nada, y
     * uno que ya se adoptó o se descartó tampoco — el `where` es la guarda, no una optimización.
     *
     * ▶ Las que llegaron DESPUÉS de pintar no se adoptan y siguen pendientes (§4.7): salen solas en el
     * siguiente render, porque el cliente manda ids concretos y no «todas».
     *
     * @param  list<int>  $replyIds
     * @return int cuántas se adoptaron de verdad
     */
    public function adopt(OrderItem $reservation, array $replyIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map(intval(...), $replyIds), fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return 0;
        }

        $replies = InvitationReply::query()
            ->where('order_item_id', $reservation->getKey())
            ->whereIn('id', $ids)
            ->pending()
            // Un «no» no se pinta sobre ninguna ficha, así que no hay nada que adoptar: se descarta.
            ->where('attending', true)
            ->orderBy('id')
            ->get();

        // ❗❗ **La clave con la que se adopta es la de la FICHA, no la del nombre que escribió el
        // padre** (`DECISIONES #579`). Hasta esa corrección se guardaba `child_key`, y entonces el
        // camino de bandera de la feature **se rompía solo**: el anfitrión pega la lista de clase con
        // nombres de pila («Hugo»), al padre se le pide nombre y apellidos («Hugo Ruiz»), el
        // emparejado los une por la primera palabra… y `reconcileAdopted()` —que compara contra las
        // fichas con igualdad exacta— la daba por huérfana y la descartaba **en el mismo `PUT`**. El
        // niño que había confirmado dejaba de ocupar plaza y desaparecía del resumen sin que nadie lo
        // quitara. Todo lo que lee `adopted_name_key` después compara contra fichas: `slotsOf()` para
        // saber cuáles están ocupadas y `reconcileAdopted()` para ver cuáles siguen escritas.
        //
        // ▶ Se reparten con la MISMA función que calcula la propuesta, así que adoptar no puede
        // decidir una ficha distinta de la que se le enseñó al anfitrión. Y las fichas ya están
        // guardadas cuando esto corre: si la suya quedó **vacía**, es que él no aceptó ese nombre y
        // no hay nada que adoptar.
        $slots = $this->slotsOf($reservation);
        $adopted = 0;

        foreach ($replies as $reply) {
            $index = $this->takeSlotFor($slots, (string) $reply->child_key);
            $key = $index === null ? null : $slots[$index]['key'];

            if ($key === null) {
                continue;
            }

            $reply->forceFill(['adopted_at' => now(), 'adopted_name_key' => $key])->save();
            $adopted++;
        }

        return $adopted;
    }

    /**
     * «No lo apuntes» (§7.2·R11): el anfitrión quita una respuesta de su lista.
     *
     * ❗❗ **Sin esto, V4 deja al anfitrión ATRAPADO.** Desde `#576` un «sí» pendiente es una plaza con
     * dueño y sube el suelo de `#444`, así que una respuesta que el anfitrión no quiere le impediría
     * bajar el número de invitados **y no tendría forma de retirarla**. Es el par de la regla que la
     * T4·4 añadió, no una comodidad.
     *
     * ⚠️ Descartar **no borra**: la respuesta sigue en su tabla hasta que la poda de los 14 días se la
     * lleve (V3). Lo que cambia es que deja de contar, de proponerse y de ocupar plaza.
     */
    public function dismiss(OrderItem $reservation, int $replyId): bool
    {
        $reply = InvitationReply::query()
            ->where('order_item_id', $reservation->getKey())
            ->whereKey($replyId)
            ->whereNull('dismissed_at')
            ->first();

        if ($reply === null) {
            return false;
        }

        $reply->forceFill(['dismissed_at' => now()])->save();

        return true;
    }

    /**
     * Tras guardar el formulario: una respuesta ADOPTADA cuya ficha ya no existe **se descarta**,
     * porque el anfitrión la quitó (§4.7).
     *
     * ⚠️ Se compara por `adopted_name_key` —la clave con la que se adoptó— contra las claves que hay
     * escritas AHORA. Si el anfitrión borró ese nombre del formulario, su respuesta vuelve a la nada:
     * dejarla adoptada la escondería para siempre (ni se propone ni se ve) **y seguiría ocupando su
     * plaza en el suelo de `#444`**, que es la peor de las dos mitades.
     *
     * @return int cuántas se descartaron
     */
    public function reconcileAdopted(OrderItem $reservation): int
    {
        $namedKeys = $this->namedGuestKeys($reservation);

        $orphans = InvitationReply::query()
            ->where('order_item_id', $reservation->getKey())
            ->whereNotNull('adopted_at')
            ->whereNull('dismissed_at')
            ->get()
            ->reject(fn (InvitationReply $r): bool => in_array((string) $r->adopted_name_key, $namedKeys, true));

        foreach ($orphans as $orphan) {
            $orphan->forceFill(['dismissed_at' => now()])->save();
        }

        return $orphans->count();
    }

    /**
     * Las fichas de la reserva por POSICIÓN, con su clave y si ya las tiene alguien.
     *
     * @return list<array{key: string|null, taken: bool}>
     */
    private function slotsOf(OrderItem $reservation): array
    {
        $nameKey = $reservation->ticketType?->guestNameFieldKey();
        $quantity = max(0, (int) $reservation->quantity);
        $rows = $nameKey === null ? [] : array_slice($reservation->guestData(), 0, $quantity);

        $slots = [];
        for ($i = 0; $i < $quantity; $i++) {
            $name = $nameKey === null ? '' : trim((string) ($rows[$i][$nameKey] ?? ''));
            $slots[] = ['key' => $name === '' ? null : PersonNameKey::for($name), 'taken' => false];
        }

        // Regla 4: una ficha que ya adoptó otra respuesta NO es candidata de nadie más.
        $adopted = InvitationReply::query()
            ->where('order_item_id', $reservation->getKey())
            ->whereNotNull('adopted_at')
            ->whereNull('dismissed_at')
            ->pluck('adopted_name_key');

        foreach ($adopted as $key) {
            foreach ($slots as $i => $slot) {
                if (! $slot['taken'] && $slot['key'] !== null && $slot['key'] === (string) $key) {
                    // Se reconstruye el elemento entero en vez de tocarle una clave: mutar
                    // `$slots[$i]['taken']` le hace perder la forma al análisis estático.
                    $slots[$i] = ['key' => $slot['key'], 'taken' => true];
                    break;
                }
            }
        }

        return $slots;
    }

    /**
     * La ficha que le toca a esta respuesta, marcándola ocupada (regla 4).
     *
     * **Exactamente una** candidata por nombre → ésa. **Cero o varias** → la primera ficha VACÍA. Y si
     * no queda ninguna, `null`: no cabe.
     *
     * ⚠️ «Varias candidatas» no es un caso raro de laboratorio: el anfitrión puede haber escrito dos
     * hermanos como «Ruiz» y «Ruiz», y entonces **adivinar cuál es sería peor que no adivinar**.
     *
     * @param  list<array{key: string|null, taken: bool}>  $slots
     */
    private function takeSlotFor(array &$slots, string $childKey): ?int
    {
        $firstWord = PersonNameKey::for(explode(' ', $childKey)[0]);

        $candidates = [];
        foreach ($slots as $i => $slot) {
            if ($slot['taken'] || $slot['key'] === null) {
                continue;
            }
            if ($slot['key'] === $childKey || ($firstWord !== '' && $slot['key'] === $firstWord)) {
                $candidates[] = $i;
            }
        }

        if (count($candidates) === 1) {
            $only = $candidates[0];
            $slots[$only] = ['key' => $slots[$only]['key'], 'taken' => true];

            return $only;
        }

        foreach ($slots as $i => $slot) {
            if (! $slot['taken'] && $slot['key'] === null) {
                $slots[$i] = ['key' => null, 'taken' => true];

                return $i;
            }
        }

        return null;
    }

    /**
     * **El RECIBO de una respuesta** (§4.5·6, T5·3; `DECISIONES #703`): una URL firmada de **2 horas**
     * atada a esa fila, que abre las dos ofertas — dejar los datos del niño (G2) y decir si va un
     * adulto con él (G3).
     *
     * ⚠️⚠️ **DOS HORAS y no es un enlace de edición** (D9). Un enlace permanente convertiría cada
     * respuesta en una credencial viva sobre los datos de un menor, repartida por un grupo de clase
     * entero: quien reenviara el mensaje del padre entraría a sus alergias meses después. Pasado el
     * plazo las ofertas desaparecen y lo que se dejó se queda como está.
     *
     * ⚠️ **Se firma con la fila, no con el token de la invitación.** El token abre la fiesta entera;
     * esto abre UNA respuesta, la de quien acaba de contestar. Son dos alcances distintos y mezclarlos
     * le daría a cualquiera con el enlace de la fiesta los datos de todos los niños.
     *
     * ▶ Estuvo aplazado desde la T4·2 con su razón escrita: nombraba una ruta que no existía, y un
     * método así es uno que lanza en cuanto alguien lo llama y que ninguna prueba puede ejercer.
     */
    public function receiptUrl(InvitationReply $reply): string
    {
        return URL::temporarySignedRoute(
            self::RECEIPT_ROUTE,
            now()->addHours(self::RECEIPT_HOURS),
            ['reply' => $reply->getKey()],
        );
    }

    /**
     * El recibo de una respuesta **de esta reserva**, por su id (`#704`, §10.6·D): es como vuelve al
     * suyo el padre que acaba de firmar.
     *
     * ⚠️⚠️ **La reserva se exige y no se deduce.** Quien llama llega desde el justificante de UNA
     * reserva, y sin esta comprobación un id de otra fiesta le abriría el recibo del hijo de otro. Que
     * el id venga dentro de una firma HMAC no basta como garantía: quien firma esa URL es esta casa,
     * y esta casa tiene que seguir comprobando de qué fiesta es la respuesta.
     *
     * ⚠️ Una respuesta descartada no tiene recibo —su página responde 404 (D9)—, así que tampoco se
     * emite el enlace: se para aquí y no se le manda a un 404.
     */
    public function receiptUrlForReplyIn(int $replyId, int $reservationId): ?string
    {
        $reply = InvitationReply::query()
            ->whereKey($replyId)
            ->where('order_item_id', $reservationId)
            ->whereNull('dismissed_at')
            ->first();

        return $reply === null ? null : $this->receiptUrl($reply);
    }

    /**
     * **Las dos ofertas del recibo** (G2 y G3): los datos del niño y si va un adulto con él.
     *
     * ⚠️⚠️ **Se re-comprueba el plazo AQUÍ dentro** (`SEC-04` aplicado al tiempo): entre que el padre
     * recibe el recibo y lo rellena pueden pasar dos horas de fiesta, y una firma válida no puede
     * escribir sobre una reserva que ya cerró. El plazo de estas ofertas es el MISMO que el de
     * contestar — si el anfitrión ya no puede mover su lista, nadie le añade datos.
     *
     * ⚠️ `data` se sanea con el esquema del pack, igual que en `reply()`: una clave inventada no entra
     * y una edad fuera de rango no llega a la aritmética de un suplemento el día que se adopte.
     *
     * ⚠️ **Una respuesta DESCARTADA no se toca.** Si el anfitrión ya dijo «no lo apuntes», el recibo
     * que el padre tenga abierto no puede resucitarla por la puerta de atrás.
     *
     * @param  array<string, mixed>  $data
     */
    public function completeReply(InvitationReply $reply, ?string $companion, array $data): bool
    {
        $reservation = $reply->reservation;

        if ($reservation === null
            || $reply->dismissed_at !== null
            || ! $this->policy->isOpenFor($reservation)
            || ! $this->policy->isWithinWindow($reservation)) {
            return false;
        }

        $type = $reservation->ticketType;
        $clean = $data === [] || $type === null ? null : ($type->sanitizeGuestData([$data], 1)[0] ?: null);

        $reply->forceFill([
            'companion' => in_array($companion, InvitationReply::COMPANIONS, true) ? $companion : $reply->companion,
            // Lo que ya había se conserva si esta vez no viene nada: el recibo se puede rellenar en
            // dos pasadas —primero los datos, luego la compañía— y la segunda no borra la primera.
            'data' => $clean ?? $reply->data,
        ])->save();

        return true;
    }

    /**
     * ▶ **La nota de por qué el recibo estuvo aplazado**, que conviene no perder: era una URL firmada de
     * 2 horas atada a la fila, que abre las dos ofertas —dejar los datos del niño y decir si va un
     * adulto con él—; nombra la ruta de la página pública, **que nace en la T5**. Escribirlo hoy sería
     * un método que lanza en cuanto alguien lo llame y que ninguna prueba puede ejercer: el mismo
     * criterio con el que la T4·1 aplazó el contrato `PartyGuests`.
     * ⚠️ Lo que sí queda decidido y escrito en la spec: **2 horas y no es un enlace de edición** (D9).
     * Un enlace permanente convertiría cada respuesta en una credencial viva sobre los datos de un
     * menor, repartida por un grupo de clase entero.
     */

    /** El nombre del homenajeado que el anfitrión ya escribió al comprar, si se puede publicar. */
    private function prefilledHonoreeName(OrderItem $reservation, TicketType $type): string
    {
        $key = $type->celebrantNameFieldKey();
        $value = $key === null ? null : ($reservation->event_data[$key] ?? null);

        return PublicFreeText::clean(is_scalar($value) ? (string) $value : null, PartyInvitation::HONOREE_NAME_MAX) ?? '';
    }

    /**
     * La edad, leída por TIPO y SANEADA.
     *
     * ⚠️ `celebrantAgeFieldKey()` busca el tipo `celebrant_age`, nunca una clave llamada «age»: es la
     * regla que `#588` fijó, y leerla por nombre se rompería en la primera instalación que la
     * renombrara desde su panel.
     */
    private function prefilledHonoreeAge(OrderItem $reservation, TicketType $type): ?int
    {
        $key = $type->celebrantAgeFieldKey();
        if ($key === null) {
            return null;
        }

        $answers = $reservation->event_data ?? [];
        $value = $type->sanitizeEventData($answers, TicketType::EVENT_STAGE_BOOKING)[$key] ?? null;

        return is_numeric($value) ? max(0, min(255, (int) $value)) : null;
    }

    /** «Te invita …», rellenado con el nombre de la cuenta. El teléfono NO se copia: sale de la cuenta. */
    private function prefilledHostLine(OrderItem $reservation): string
    {
        return PublicFreeText::clean($reservation->order?->user?->name, PartyInvitation::HOST_LINE_MAX) ?? '';
    }
}
