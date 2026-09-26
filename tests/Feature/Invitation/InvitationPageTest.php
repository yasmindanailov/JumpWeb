<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **La PÁGINA pública de la invitación** (T5·1 de `docs/specs/celebracion-e-invitacion.md` §4.6;
 * `DECISIONES #521`).
 *
 * Lo que vigila, en orden de importancia:
 *
 *  1. ❗❗ **Que siga siendo una HOJA EN BLANCO.** Es la pantalla donde más importa: su enlace se
 *     reparte a un grupo de clase entero. Ni una respuesta, ni un contador, ni si un nombre contestó.
 *  2. **Que los cuatro «no» sean el mismo 404**, igual que en la API y por la misma fuente.
 *  3. ⚠️ **Que el NOMBRE de la ruta sea el que el dominio pregunta.** `PartyInvitations::PUBLIC_ROUTE`
 *     compone con él el enlace que reparte el anfitrión: renombrarla devolvería `Invitation.url` a
 *     `null` **sin romper nada más**, y el anfitrión se quedaría sin enlace que compartir.
 *  4. **Las tres cabeceras** — `noindex`, `no-store` y `Referrer-Policy: no-referrer`. La tercera es
 *     la que se olvida, y es la que impide que «Cómo llegar» le mande el token a Google.
 *  5. **«Sin dato, sin bloque»**: una invitación con celdas vacías parece rota.
 */
class InvitationPageTest extends TestCase
{
    use RefreshDatabase;

    // ── 1 · La hoja en blanco ─────────────────────────────────────────────────

    public function test_the_page_shows_the_party_and_not_a_single_reply(): void
    {
        [$reservation, $invitation] = $this->party();
        InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $reservation->getKey(),
            'attending' => true,
            'child_name' => 'Hugo Ruiz',
            'child_key' => PersonNameKey::for('Hugo Ruiz'),
        ]);

        $response = $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]));

        $response->assertOk()
            ->assertSee('Lucía')
            // La frase del brief, entera: «Lucía cumple 8 años y te invita a saltar» (`fiesta-sistema-nuevo.md` T2).
            ->assertSee('cumple 8 años y te invita a saltar')
            ->assertSee('Te invita Marta');

        // ❗❗ Lo que NO puede estar: ni una respuesta de otro padre.
        $response->assertDontSee('Hugo', escape: false);

        // ⚠️ **El token SÍ está, y tiene que estar**: el formulario de contestar postea a su propia
        // URL. Quien ve esta página ya lo tiene en la barra de direcciones, así que esconderlo del
        // marcado no protegería de nada. Lo que impide que SALGA de aquí es el
        // `Referrer-Policy: no-referrer`, que vigila su propio caso.
        //
        // ▶ Hasta la T5·2 esta línea aseveraba lo contrario, y pasaba solo porque la página no tenía
        // formulario. *Una aserción que deja de poder cumplirse se revisa: puede estar describiendo
        // una propiedad que nunca existió.*
        $response->assertSee((string) $invitation->token, escape: false);
    }

    /** «Sin dato, sin bloque»: sin edad y sin línea de anfitrión, esos bloques no se pintan. */
    public function test_a_block_without_data_is_not_painted_at_all(): void
    {
        [, $invitation] = $this->party();
        $invitation->forceFill(['honoree_age' => null, 'host_line' => ''])->save();

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertDontSee('Cumple')
            ->assertDontSee('Te invita');
    }

    /**
     * El teléfono del anfitrión sale de la CUENTA y solo si lo marcó (§4.5·12). En esta página no hay
     * ningún campo donde teclear uno, y eso es deliberado.
     */
    public function test_the_host_phone_only_appears_when_the_host_asked_for_it(): void
    {
        [, $invitation] = $this->party();

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()->assertDontSee('600111222');

        $invitation->forceFill(['show_host_phone' => true])->save();

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()->assertSee('600111222');
    }

    // ── 2 · Un solo «no» ──────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ Los cuatro «no» son el MISMO 404, **con control**: el mismo fixture responde 200 antes.
     * Distinguirlos le diría a un desconocido que ese token existió (§7.2·R10).
     */
    public function test_every_way_of_not_being_available_answers_the_same_404(): void
    {
        $this->get('/invitacion/'.Str::random(12))->assertNotFound();

        [, $rotated] = $this->party();
        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $rotated->token]))->assertOk();
        $old = (string) $rotated->token;
        $rotated->rotateToken();
        $this->get('/invitacion/'.$old)->assertNotFound();

        [$cancelled, $invCancelled] = $this->party();
        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invCancelled->token]))->assertOk();
        $cancelled->order?->forceFill(['status' => Order::STATUS_CANCELLED])->save();
        $this->get('/invitacion/'.$invCancelled->token)->assertNotFound();

        [$anon, $invAnon] = $this->party();
        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invAnon->token]))->assertOk();
        $anon->order?->user?->anonymize();
        $this->get('/invitacion/'.$invAnon->token)->assertNotFound();
    }

    /** Un token con otra forma no llega ni a mirarse contra la base de datos: lo para la ruta. */
    public function test_a_token_of_the_wrong_shape_never_reaches_the_database(): void
    {
        $this->get('/invitacion/corto')->assertNotFound();
        $this->get('/invitacion/con-guiones-12')->assertNotFound();
    }

    // ── 3 · El nombre de la ruta es contrato ──────────────────────────────────

    /**
     * ❗❗ **El enlace que reparte el anfitrión se compone preguntando por el NOMBRE de esta ruta.**
     * `PartyInvitations::shareUrlFor()` usa `Route::has(PUBLIC_ROUTE)`, así que renombrarla devolvería
     * `Invitation.url` a `null` —el anfitrión se quedaría sin nada que compartir— **sin que fallara
     * ningún otro caso**. Éste existe para que ese renombrado no pueda pasar en silencio.
     */
    public function test_the_route_name_is_the_one_the_domain_composes_the_link_with(): void
    {
        [$reservation, $invitation] = $this->party();

        $this->assertTrue(Route::has(PartyInvitations::PUBLIC_ROUTE), 'el dominio pregunta por un nombre que ya no existe');

        $url = app(PartyInvitations::class)->shareUrlFor($invitation);

        $this->assertNotNull($url, 'el enlace del anfitrión volvió a ser null');
        $this->assertSame(url('/invitacion/'.$invitation->token), $url);

        // Y ese enlace abre de verdad: componerlo bien y que diera 404 sería lo mismo que no tenerlo.
        $this->get($url)->assertOk()->assertSee('Lucía');
        $this->assertNotNull($reservation->fresh());
    }

    // ── 4 · Las tres cabeceras ────────────────────────────────────────────────

    /**
     * ⚠️⚠️ `Referrer-Policy: no-referrer` es la que se olvida y la que más cuesta: sin ella, pulsar
     * «Cómo llegar» le manda a Google **el token** en el `Referer`. Una credencial que abre los datos
     * de la fiesta de un niño no puede viajar en la cabecera de una petición a un tercero.
     */
    public function test_the_page_is_not_indexed_not_stored_and_leaks_no_referrer(): void
    {
        [, $invitation] = $this->party();

        $response = $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]));

        $response->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('noindex', escape: false);

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
            'la página lleva el nombre y la edad de un menor (RGPD-04)'
        );
    }

    // ── 5 · El menú ───────────────────────────────────────────────────────────

    /**
     * El menú son los complementos **COMPRADOS** de esta reserva que el catálogo marcó (D12) — no los
     * ofrecidos, que pondrían en la invitación cosas que nadie ha pagado.
     *
     * ⚠️ El fixture ofrece DOS y compra UNO, y solo el comprado está marcado: sin ese contraste, una
     * implementación que listara el catálogo entero pasaría el caso.
     */
    public function test_the_menu_lists_what_was_bought_and_marked_not_what_is_offered(): void
    {
        [$reservation, $invitation] = $this->party(withMenu: true);

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Menú Pizza')
            ->assertDontSee('Menú Nuggets');

        $this->assertSame(
            ['Menú Pizza'],
            array_column(app(PartyInvitations::class)->menuFor($reservation->fresh(['ticketType.addons', 'children.ticketType']) ?? $reservation), 'name')
        );
    }

    /**
     * ❗❗ **Un complemento RETIRADO de la venta sigue en el menú de una fiesta ya comprada.**
     *
     * Lo encontró la sonda en el navegador, no la suite: el menú no salía, y el motivo era que
     * `menuFor()` leía la relación `addons()` del catálogo, **que filtra `is_sellable`**. Con eso, el
     * día que el parque deja de vender el «Menú Pizza» —temporada, cambio de carta— la invitación de
     * una fiesta que YA lo pagó deja de enseñarlo, y nadie se entera.
     *
     * ⚠️ Lo que manda es lo COMPRADO y la marca del enganche, no si hoy se puede comprar. El catálogo
     * dice qué se vende; esta reserva dice qué se pagó.
     */
    public function test_a_dish_pulled_from_sale_stays_in_an_already_bought_party(): void
    {
        [$reservation, $invitation] = $this->party(withMenu: true);

        TicketType::query()
            ->where('type', TicketType::TYPE_ADDON)
            ->update(['is_sellable' => false, 'is_active' => false]);

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Menú Pizza');

        $this->assertSame(
            ['Menú Pizza'],
            array_column(app(PartyInvitations::class)->menuFor($reservation->fresh(['ticketType.addons', 'children.ticketType']) ?? $reservation), 'name'),
            'retirar un complemento de la VENTA lo borró del menú de una fiesta ya pagada'
        );
    }

    /**
     * **La merienda, por lo que es** (el diseño del 24-09; `fiesta-sistema-nuevo.md` T2, antes el `<details>` de
     * `#416`): cada plato MARCADO y comprado es un grupo de `inv-merienda`, con su nombre de rótulo y sus detalles
     * en línea, **sin una línea de JS** — que es la condición de esta página.
     *
     * ⚠️⚠️ **Un plato SIN detalles pinta solo su rótulo**, y el caso lo comprueba con los dos a la vez: una lista de
     * cosas vacía sería un hueco con separadores huérfanos.
     *
     * ⚠️ Y `features` se lee normalizada: un campo traducible llega **como lista o como texto suelto**
     * según quién lo escribiera (la trampa de `#463`). El fixture usa **texto suelto** a propósito —
     * leerlo a pelo devolvería las LETRAS de la cadena, una por cosa.
     */
    public function test_a_dish_with_details_gets_the_systems_disclosure_and_one_without_does_not(): void
    {
        [$reservation, $invitation] = $this->party(withMenu: true);

        // Texto SUELTO, no lista: es la forma que rompe una lectura ingenua.
        TicketType::query()->where('name->es', 'Menú Pizza')->update(['features' => json_encode(['es' => 'Pizza, patatas y bebida'])]);

        $html = $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Pizza, patatas y bebida')
            ->getContent();

        $this->assertStringContainsString('data-invitation-menu', (string) $html, 'no está el bloque de la merienda');
        $this->assertStringContainsString('<span class="rot">Menú Pizza</span>', (string) $html, 'el plato no es el rótulo de su grupo');
        // Una sola cosa: el texto suelto NO se recorrió letra a letra.
        $this->assertSame(1, substr_count((string) $html, '<span>Pizza, patatas y bebida</span>'));

        // Y sin detalles, solo el rótulo: ninguna lista de cosas.
        TicketType::query()->where('name->es', 'Menú Pizza')->update(['features' => null]);

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertDontSee('class="cosas"', escape: false)
            ->assertSee('Menú Pizza');

        $this->assertNotNull($reservation->fresh());
    }

    /**
     * **La merienda POR GRUPOS** (F1b de `fiesta-sistema-nuevo.md`): lo que el complemento reparte en «para beber», «para
     * comer» y «y para terminar» (dato del panel, tres listas i18n) sale en esos tres grupos, con su rótulo fijo y en
     * el orden del diseño, y el plato deja de ser su propio grupo. Sin reparto, el caso de arriba: su nombre y sus
     * ventajas.
     */
    public function test_a_dish_split_into_groups_is_painted_as_the_three_groups_of_the_design(): void
    {
        [$reservation, $invitation] = $this->party(withMenu: true);
        TicketType::query()->where('name->es', 'Menú Pizza')->update([
            'menu_drink' => json_encode(['es' => ['Refresco o zumo', 'Agua']]),
            'menu_food' => json_encode(['es' => ['Pizza', 'Patatas']]),
            'menu_sweet' => json_encode(['es' => ['Cono de chuches']]),
        ]);

        $html = (string) $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))->assertOk()->getContent();

        $this->assertStringContainsString('data-invitation-menu', $html);
        $rotulos = __('fiesta.invitacion_pagina.merienda_grupos');
        foreach ([[$rotulos['drink'], 'Refresco o zumo'], [$rotulos['food'], 'Pizza'], [$rotulos['sweet'], 'Cono de chuches']] as [$rotulo, $cosa]) {
            $this->assertStringContainsString('<span class="rot">'.$rotulo.'</span>', $html, $rotulo);
            // Una cosa que no es la última lleva el separador DENTRO de su `span`: se afirma el texto, no el cierre.
            $this->assertStringContainsString('>'.$cosa.'<', $html, $cosa);
        }
        $this->assertStringContainsString('<span>Refresco o zumo<span aria-hidden="true" class="sep">·</span></span><span>Agua</span>', $html, 'las cosas del grupo, con su separador');
        $this->assertMatchesRegularExpression('#'.preg_quote($rotulos['drink'], '#').'.*'.preg_quote($rotulos['food'], '#').'.*'.preg_quote($rotulos['sweet'], '#').'#s', $html, 'los tres grupos, en el orden del diseño');
        $this->assertStringNotContainsString('<span class="rot">Menú Pizza</span>', $html, 'repartido, el plato ya no es su propio grupo');
        $this->assertNotNull($reservation->fresh());
    }

    // ── 6 · Contestar (T5·2) ──────────────────────────────────────────────────

    public function test_a_parent_answers_from_the_page_and_is_told_so(): void
    {
        [$reservation, $invitation] = $this->party();

        // El desenlace es el RECIBO, directo (`#744`): una credencial firmada sobre SU respuesta, en la redirección.
        $this->post(route('invitation.reply', ['token' => $invitation->token]), [
            'child_name' => 'Hugo Ruiz', 'attending' => '1',
        ])->assertRedirectContains('/invitacion/recibo/');

        $this->assertSame(1, InvitationReply::query()->where('order_item_id', $reservation->id)->count());
        $this->assertTrue((bool) InvitationReply::query()->value('attending'));

        // Tras «No podemos», la misma tarjeta con «Gracias por avisar.» y quien lo verá, sin ficha (POST-redirect-GET).
        $this->followingRedirects()
            ->post(route('invitation.reply', ['token' => $invitation->token]), [
                'child_name' => 'Lía Fernández', 'attending' => '0',
            ])
            ->assertOk()
            ->assertSee('Gracias por avisar.')
            ->assertSee('Marta lo verá en su lista.')
            ->assertSee('data-receipt="no"', escape: false)
            ->assertDontSee('data-receipt-fields', escape: false);
    }

    /**
     * ❗❗ **LA PROPIEDAD QUE ORDENA ESTA PANTALLA.** Con la lista completa, un nombre que YA está y uno
     * nuevo reciben **exactamente el mismo desenlace** (`#700`). Si no fuera así, quien tiene el enlace
     * —repartido a un grupo de clase entero— podría reconstruir la lista de invitados probando nombres.
     *
     * ⚠️ Se compara el HTML del aviso **entero**: bastaría un título distinto, o un tono distinto, para
     * abrir la misma rendija que el `reason` abría en la API.
     */
    public function test_with_a_full_list_a_known_name_and_a_new_one_see_the_same_thing(): void
    {
        [$reservation, $invitation] = $this->party();
        $reservation->forceFill(['quantity' => 1, 'guest_data' => [['name' => 'Ana Gil']]])->save();

        $conocido = $this->followingRedirects()
            ->post(route('invitation.reply', ['token' => $invitation->token]),
                ['child_name' => 'Ana Gil', 'attending' => '1'])
            ->assertOk()->getContent();

        $nuevo = $this->followingRedirects()
            ->post(route('invitation.reply', ['token' => $invitation->token]),
                ['child_name' => 'Hugo Ruiz', 'attending' => '1'])
            ->assertOk()->getContent();

        // El desenlace es el RECIBO entero (`#744`). Se neutraliza lo que es PROPIO de cada padre —su nombre, el id
        // y la firma de SU recibo (la URL de la ficha y la de la autorización atada)— y se compara TODO lo demás.
        //
        // ⚠️⚠️ **Normalizar las credenciales no debilita la aserción, y conviene ver por qué**: si uno llevara
        // ficha y el otro no, o una tarjeta distinta, la diferencia seguiría ahí. Lo único que se tapa es lo que es
        // de quien contesta y no dice nada sobre quién está en la lista. Sin esta normalización el caso salía ROJO
        // por dos credenciales distintas, que es justo lo que TIENEN que ser.
        // ⚠️ Se compara lo que el padre VE (`<main>`), no el documento entero: Livewire inyecta sus estilos en la cabecera
        // de UNA de las dos respuestas según qué corrió antes en la suite, y eso no es un desenlace.
        $recibo = static fn (string $html): string => (string) preg_replace(
            ['/Ana Gil|Hugo Ruiz|Ana(?:%20|\+)Gil|Hugo(?:%20|\+)Ruiz/', '#/invitacion/recibo/\d+\?[^"]*#', '/invitation_reply_id=\d+/', '/(signature|expires)=[^&"]+/', '/name="_token" value="[^"]+"/'],
            ['NOMBRE', '/invitacion/recibo/RECIBO', 'invitation_reply_id=N', '$1=X', 'name="_token" value="T"'],
            preg_match('#<main class="inv">.*?</main>#s', $html, $m) ? $m[0] : 'SIN PÁGINA'
        );

        $this->assertStringContainsString('data-receipt="si"', (string) $nuevo, 'el desenlace no es el recibo');
        $this->assertSame(
            $recibo((string) $conocido),
            $recibo((string) $nuevo),
            'el desenlace distingue quién está en la lista: es un oráculo de pertenencia'
        );
        $this->assertStringContainsString('Contamos con vosotros', (string) $nuevo);
    }

    /**
     * Pasado el plazo la fiesta **se sigue viendo** y lo que se cierra son los botones (§7.2·R8): la
     * información hace falta justo el día de la fiesta.
     *
     * ⚠️⚠️ **Pasado el PLAZO, no pasada la FIESTA, y la diferencia importa.** La primera versión de
     * este caso ponía la fiesta ayer y recibía un 404 — correcto: un enlace no sobrevive a su fiesta,
     * porque `resolvePublic()` exige que la reserva siga abierta. Lo que §7.2·R8 protege es la ventana
     * de en medio: la fiesta es mañana y el corte de respuestas ya venció.
     */
    public function test_past_the_deadline_the_party_is_still_visible_but_the_buttons_are_gone(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00', 'UTC'));
        Setting::query()->updateOrCreate(
            ['key' => GuestCountPolicy::SETTING_CUTOFF_HOURS], ['value' => '48']
        );

        [$reservation, $invitation] = $this->party();
        // Mañana: la fiesta NO ha terminado, pero el corte de 48 h ya pasó.
        $reservation->slot?->forceFill(['date' => now()->addDay()->toDateString()])->save();

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Lucía')
            // La barra se queda con su línea, que nombra a quien organiza por su nombre de pila (`#744`).
            ->assertSee('El plazo pasó: habla con Marta.')
            ->assertDontSee('name="child_name"', escape: false);
    }

    /**
     * ⚠️ El AVISO DE PRIVACIDAD está **desde el primer momento y sin casilla** (§7.2·R7, `#350`): dice
     * para qué son los datos, **quién los va a ver** —el anfitrión, que es un tercero— y cuándo se
     * borran. Quien escribe aquí el nombre de un niño no tiene cuenta ni ha aceptado nada.
     */
    public function test_the_privacy_notice_is_there_without_a_checkbox(): void
    {
        [, $invitation] = $this->party();

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            // Quién lo ve, por su nombre (`#744`): un tercero, el anfitrión; y cuándo se borra.
            ->assertSee('Marta verá el nombre de tu hijo')
            ->assertSee('14 días')
            ->assertSee(route('legal.privacidad'), escape: false)
            // Sin casilla: el consentimiento no se pide con un checkbox aquí.
            ->assertDontSee('type="checkbox"', escape: false);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: PartyInvitation} */
    private function party(bool $withMenu = false): array
    {
        Setting::query()->updateOrCreate(['key' => 'business.name'], ['value' => 'Parque Verify']);
        Setting::query()->updateOrCreate(['key' => 'address.line1'], ['value' => 'Calle Falsa 1']);

        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños Jump'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => true,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true,
                    'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
            ],
        ]);
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => now()->addMonth()->toDateString(), 'start_time' => '17:00:00'],
            ['end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );

        $user = User::factory()->create(['name' => 'Marta Anfitriona', 'phone' => '600111222']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $reservation = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 6, 'unit_price' => 500, 'seats' => 6,
            'event_data' => ['celebrant' => 'Lucía'],
        ]);

        if ($withMenu) {
            $this->attachMenu($type, $order, $reservation, $slot);
        }

        $invitation = app(PartyInvitations::class)
            ->forReservation($reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation);
        $invitation->forceFill(['honoree_age' => 8, 'host_line' => 'Te invita Marta'])->save();

        return [$reservation->fresh(['ticketType.addons', 'slot', 'order.user', 'children.ticketType']) ?? $reservation, $invitation];
    }

    /** Dos complementos ofrecidos, UNO marcado y comprado: el contraste que hace útil el caso. */
    private function attachMenu(TicketType $pack, Order $order, OrderItem $reservation, Slot $slot): void
    {
        foreach ([['Menú Pizza', true], ['Menú Nuggets', false]] as [$name, $marked]) {
            $addon = TicketType::create([
                'zone_id' => $pack->zone_id, 'type' => TicketType::TYPE_ADDON, 'name' => ['es' => $name],
                'duration_min' => 0, 'seats_per_unit' => 0, 'is_sellable' => true, 'is_active' => true, 'position' => 5,
            ]);
            $pack->addons()->attach($addon->id, [
                // ⚠️ La columna se llama `stage`, no `sale_stage`: la lista blanca del pivote es
                // `TicketType::ADDON_PIVOT_COLUMNS` y lo que no esté ahí se cae en silencio.
                'stage' => ProductAddon::STAGE_BOOKING,
                'show_in_invitation' => $marked,
            ]);

            // Solo el marcado se COMPRA: si el menú listara el catálogo, saldrían los dos.
            if ($marked) {
                $order->items()->create([
                    'ticket_type_id' => $addon->id, 'slot_id' => $slot->id, 'parent_item_id' => $reservation->id,
                    'quantity' => 6, 'unit_price' => 300, 'seats' => 0,
                ]);
            }
        }
    }
}
