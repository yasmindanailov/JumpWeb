<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PersonNameKey;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **Compartir y calendario** (T5·4 de `docs/specs/celebracion-e-invitacion.md` §4.6;
 * `DECISIONES #705`): lo que se ve al pegar el enlace en un chat, y el `.ics` de la fiesta.
 *
 * Lo que vigila, en orden de importancia:
 *
 *  1. ❗❗ **La hora del `.ics` es de PARED** (`§7.2·R13`): la zona del PARQUE, no la del servidor. Se
 *     mide con el parque en una zona distinta de la del contenedor, porque con las dos iguales un
 *     `.ics` en UTC pasaría el caso sin ser correcto.
 *  2. ⚠️ **La vista previa solo lleva nombre, edad, día, hora y negocio.** La pinta el chat de la clase
 *     entera: ni la dirección, ni el menú, ni una sola respuesta.
 *  3. Los cuatro «no» son el MISMO 404 también aquí: una ruta que distinguiera un token caducado de
 *     uno inventado abriría la rendija que §4.5·12 cerró en la página.
 */
class InvitationSharingTest extends TestCase
{
    use RefreshDatabase;

    /** La zona del PARQUE en los casos: a propósito distinta de la del contenedor (UTC). */
    private const PARQUE = 'Europe/Madrid';

    /**
     * ❗❗ **La fiesta cae en el instante correcto**, y el fichero escribe la hora del reloj del parque.
     *
     * No se aserta solo la cifra: se reconstruye el instante como lo hace un calendario. Con una
     * aserción de cadena, `20261004T170000Z` —la trampa de `§7.2·R13`— pasaría igual y le movería la
     * fiesta al padre una o dos horas.
     */
    public function test_the_calendar_file_keeps_the_wall_clock_of_the_park(): void
    {
        [, $invitation] = $this->party();

        $ics = (string) $this->get(route('invitation.calendar', ['token' => $invitation->token]))
            ->assertOk()->getContent();

        $this->assertTrue(
            (bool) preg_match('/^DTSTART;TZID=([^:\r\n]+):(\d{8}T\d{6})\r?$/m', $ics, $m),
            'el fichero no declara la hora con la zona del parque: '.substr($ics, 0, 400)
        );
        $this->assertSame(self::PARQUE, $m[1], 'la zona del fichero no es la del parque');

        // La fiesta empieza a las 17:00 de pared, dura 120 minutos y el `.ics` tiene que decir las dos.
        $inicio = new DateTimeImmutable('2026-10-04 17:00:00', new DateTimeZone(self::PARQUE));
        $this->assertSame($inicio->format('Ymd\THis'), $m[2]);
        $this->assertSame(
            $inicio->getTimestamp(),
            (new DateTimeImmutable($m[2], new DateTimeZone($m[1])))->getTimestamp(),
            'leído como lo lee un calendario, el evento no cae en el instante correcto'
        );
        $this->assertStringContainsString('DTEND;TZID='.self::PARQUE.':20261004T190000', $ics);

        // Y la zona viene definida dentro, que es lo que pide la norma.
        $this->assertStringContainsString('BEGIN:VTIMEZONE', $ics);
    }

    /** Se sirve como calendario y se descarga: en un móvil, lo abre la aplicación de calendario. */
    public function test_the_file_is_served_as_a_calendar_and_is_never_stored(): void
    {
        [, $invitation] = $this->party();

        $respuesta = $this->get(route('invitation.calendar', ['token' => $invitation->token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        // `RGPD-04`: lleva el nombre de un menor y la dirección del parque. Se comprueba la DIRECTIVA y
        // no la cadena entera: el orden de las demás lo compone el framework y cambiaría con él.
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));

        $this->assertStringContainsString(
            'attachment; filename="cumple-lucia.ics"',
            (string) $this->get(route('invitation.calendar', ['token' => $invitation->token]))
                ->headers->get('Content-Disposition'),
        );
    }

    /**
     * ⚠️ **Sin duración no hay fichero NI botón**: un `.ics` sin cuándo no es un recordatorio, es
     * basura en la carpeta de descargas de un padre.
     */
    public function test_without_a_duration_there_is_neither_a_button_nor_a_file(): void
    {
        [$reservation, $invitation] = $this->party();

        $reservation->ticketType?->forceFill(['duration_min' => null])->save();

        $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()
            ->assertDontSee('data-invitation-calendar', escape: false);

        $this->get(route('invitation.calendar', ['token' => $invitation->token]))->assertNotFound();
    }

    /** Los cuatro «no» son el MISMO 404, también en esta ruta. */
    public function test_a_token_that_does_not_open_gets_the_same_404(): void
    {
        [, $invitation] = $this->party();

        $viejo = (string) $invitation->token;

        $this->get(route('invitation.calendar', ['token' => 'AAAAAAAAAAAA']))->assertNotFound();

        // Y anular el enlace (que es ROTAR el token, `#576`) cierra también su calendario: el enlace
        // repartido por el chat deja de abrir el fichero, igual que deja de abrir la página.
        $invitation->forceFill(['token' => Str::upper(Str::random(12))])->save();

        $this->get(route('invitation.calendar', ['token' => $viejo]))->assertNotFound();
        $this->get(route('invitation.calendar', ['token' => $invitation->fresh()?->token]))->assertOk();
    }

    /**
     * ❗❗ **La vista previa solo lleva lo que §4.6 permite.** Lo que se pega en un chat lo lee gente que
     * no ha abierto la página —y a veces una caja de terceros—, así que la dirección, el menú y las
     * respuestas se quedan fuera.
     */
    public function test_the_preview_carries_the_party_and_nothing_else(): void
    {
        [$reservation, $invitation] = $this->party();

        // Una respuesta y un dato de dirección, para que el caso pueda FALLAR si algo se cuela.
        InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $reservation->getKey(),
            'attending' => true,
            'child_name' => 'Hugo Ruiz',
            'child_key' => PersonNameKey::for('Hugo Ruiz'),
        ]);

        $html = (string) $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()->getContent();

        $this->assertTrue(
            (bool) preg_match_all('/<meta property="og:[^"]+" content="([^"]*)"/', $html, $m),
            'la página no publica ninguna vista previa'
        );
        $previa = implode(' | ', $m[1]);

        // Lo que SÍ: quién cumple, cuántos hace, cuándo y dónde se celebra (el negocio).
        $this->assertStringContainsString('Lucía', $previa);
        $this->assertStringContainsString('8', $previa);
        $this->assertStringContainsString('17:00', $previa);
        $this->assertStringContainsString('SaltoPark', $previa);

        // Lo que NO: la calle, el menú, y desde luego ningún niño invitado.
        $this->assertStringNotContainsString('Calle Mayor', $previa, 'la dirección se coló en la vista previa');
        $this->assertStringNotContainsString('Hugo', $previa, 'un invitado se coló en la vista previa');
        // Ni el token: la página ya está en el enlace que se pega.
        $this->assertStringNotContainsString((string) $invitation->token, $previa, 'el token viaja en una meta');
    }

    /** La imagen de la vista previa declara sus medidas **solo** cuando el fichero es nuestro. */
    public function test_the_preview_image_only_claims_a_size_it_could_measure(): void
    {
        [, $invitation] = $this->party();

        $html = (string) $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertOk()->getContent();

        if (! preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $m)) {
            $this->markTestSkipped('esta instalación no tiene imagen de vista previa');
        }

        $local = str_contains($m[1], '/img/client-logo@4x.png') || str_contains($m[1], '/og-image.jpg');
        $this->assertSame(
            $local,
            str_contains($html, 'og:image:width'),
            'las medidas se declaran si y solo si la imagen es un fichero nuestro'
        );
    }

    // ── Fixture ───────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: PartyInvitation} */
    private function party(): array
    {
        // ⚠️ El parque, en una zona DISTINTA de la del contenedor (UTC): con las dos iguales, un `.ics`
        // escrito en UTC pasaría el caso de la hora de pared sin ser correcto.
        Setting::query()->updateOrCreate(['key' => 'display_timezone'], ['value' => self::PARQUE]);
        Setting::query()->updateOrCreate(['key' => 'business.name'], ['value' => 'SaltoPark']);
        Setting::query()->updateOrCreate(['key' => 'address.line1'], ['value' => 'Calle Mayor, 3']);

        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(
            ['zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK],
            [
                'name' => ['es' => 'Cumpleaños'], 'duration_min' => 120, 'seats_per_unit' => 1,
                'min_qty' => 1, 'max_qty' => 20, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
                'guest_invitation' => true,
                // La columna de NOMBRE la exige el guard de `#575`: sin ella no habría con qué
                // emparejar lo que conteste un padre, y el producto no puede ofrecer invitación.
                'guest_fields' => [
                    ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ],
            ]
        );
        // La franja es de una hora; la fiesta dura DOS. El `.ics` tiene que decir dos (la trampa
        // de `#426`: el fin de la franja diría una hora de menos).
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => '2026-10-04', 'start_time' => '17:00:00'],
            ['end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );
        $user = User::factory()->create(['name' => 'Marta Anfitriona']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $reservation = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 6, 'unit_price' => 500, 'seats' => 6,
        ]);

        $fresh = $reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation;
        $invitation = app(PartyInvitations::class)->forReservation($fresh);
        $invitation->forceFill(['honoree_name' => 'Lucía', 'honoree_age' => 8])->save();

        return [$fresh, $invitation->fresh() ?? $invitation];
    }
}
