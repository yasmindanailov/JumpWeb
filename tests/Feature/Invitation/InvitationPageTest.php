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
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Cumple 8 años')
            ->assertSee('Te invita Marta');

        // ❗❗ Lo que NO puede estar, dicho por su nombre.
        $response->assertDontSee('Hugo', escape: false);
        $response->assertDontSee((string) $invitation->token, escape: false);
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
     * **El «Más info» de cada plato** (`[DECIDIDO owner, 2026-09-18]`): la MISMA pieza `<details>` que
     * el post-form usa para sus complementos (`#416`), **sin una línea de JS** — que es la condición
     * de esta página.
     *
     * ⚠️⚠️ **Un plato SIN detalles no lleva desplegable**, y el caso lo comprueba con los dos a la vez:
     * un `<details>` vacío se abre para no enseñar nada, que es peor que no ofrecerlo.
     *
     * ⚠️ Y `features` se lee normalizada: un campo traducible llega **como lista o como texto suelto**
     * según quién lo escribiera (la trampa de `#463`). El fixture usa **texto suelto** a propósito —
     * leerlo a pelo devolvería las LETRAS de la cadena, una por viñeta.
     */
    public function test_a_dish_with_details_gets_the_systems_disclosure_and_one_without_does_not(): void
    {
        [$reservation, $invitation] = $this->party(withMenu: true);

        // Texto SUELTO, no lista: es la forma que rompe una lectura ingenua.
        TicketType::query()->where('name->es', 'Menú Pizza')->update(['features' => json_encode(['es' => 'Pizza, patatas y bebida'])]);

        $html = $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Pizza, patatas y bebida')
            ->assertSee('Ver qué lleva')
            ->getContent();

        $this->assertStringContainsString('<details class="gf-extra__more">', (string) $html, 'no usa la pieza del sistema');
        // Una sola viñeta: el texto suelto NO se recorrió letra a letra.
        $this->assertSame(1, substr_count((string) $html, '<li>Pizza, patatas y bebida</li>'));

        // Y sin detalles, ningún desplegable.
        TicketType::query()->where('name->es', 'Menú Pizza')->update(['features' => null]);

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertDontSee('<details class="gf-extra__more">', escape: false)
            ->assertSee('Menú Pizza');

        $this->assertNotNull($reservation->fresh());
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
