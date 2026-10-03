<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PendingBeforeVisit;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\VisitEveNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **EL AVISO DE LA VÍSPERA** (T7·2b, `docs/specs/celebracion-e-invitacion.md` §4.9 y §10.17;
 * `DECISIONES #717`).
 *
 * Lo que estos casos garantizan:
 *
 *  · sale **la tarde de antes**, a la hora del PARQUE —no la del contenedor, que va en UTC— y **solo
 *    a las reservas de MAÑANA**;
 *  · **una sola vez**: la marca vive en la fila y el comando corre cada hora;
 *  · **solo si queda algo por hacer**, y el correo **nombra solo lo que falta**;
 *  · **no mueve el testigo** de los extras, con su CONTROL — mandar un correo no puede tumbarle al
 *    cliente la página que tiene abierta;
 *  · y **sin titular no se avisa ni se marca**: si mañana recupera correo, que lo reciba.
 */
class VisitEveNoticeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true,
            'max_per_slot' => 3, 'max_guests_per_slot' => 40, 'prep_blocks_cupo' => false, 'position' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Cuándo sale ─────────────────────────────────────────────────────────────────

    public function test_it_warns_the_owner_of_a_party_that_is_tomorrow(): void
    {
        $this->atParkHour(19);
        Notification::fake();

        $item = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow());

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertSentTo($item->order->user, VisitEveNotice::class);
        $this->assertNotNull($item->fresh()->eve_notice_at, 'la marca vive en la fila de la reserva');
    }

    public function test_the_hour_is_the_park_s_and_not_the_container_s(): void
    {
        // ⏰⏰ **El contenedor va en UTC y el parque en Madrid.** A las 17:00 UTC en el parque son las
        // 19:00, así que el aviso SÍ tiene que salir; leyendo la hora del contenedor no saldría.
        $this->atParkHour(19);
        $this->assertNotSame(now()->hour, DisplayTime::now()->hour, 'el instrumento: si el contenedor no fuera UTC, este caso no mediría nada');

        Notification::fake();
        $item = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow());

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertSentTo($item->order->user, VisitEveNotice::class);
    }

    public function test_before_six_it_says_nothing_unless_forced(): void
    {
        $this->atParkHour(10);
        Notification::fake();

        $item = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow());

        $this->artisan('reservations:eve-notice')->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertNull($item->fresh()->eve_notice_at);

        // `--force` es la puerta de staging, donde el scheduler no corre (`#115`).
        $this->artisan('reservations:eve-notice', ['--force' => true])->assertSuccessful();
        Notification::assertSentTo($item->order->user, VisitEveNotice::class);
    }

    public function test_only_tomorrow_is_the_eve(): void
    {
        $this->atParkHour(19);
        Notification::fake();

        // ⚠️⚠️ La fiesta de HOY es a las 21:00 y son las 19:30: **todavía no ha pasado** y le faltan
        // las fichas. Con una fiesta de hoy por la MAÑANA este caso no mediría nada —el lector ya la
        // da por celebrada— y la mutación de «mañana» sobrevivía. Lo cazó el arnés.
        $hoy = $this->reservation(quantity: 4, guests: [], day: DisplayTime::now()->copy(), hour: 21);
        $this->assertFalse($hoy->isFinishedInPractice(), 'el instrumento: la fiesta de hoy aún no ha empezado');
        $this->assertTrue(app(PendingBeforeVisit::class)->forReservation($hoy)->any(), 'y le falta algo, así que solo la fecha la salva');

        $pasado = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow()->addDay(), hour: 12);

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($hoy->fresh()->eve_notice_at, 'hoy ya no es la víspera: el aviso llegaría tarde');
        $this->assertNull($pasado->fresh()->eve_notice_at, 'y pasado mañana llegaría pronto');
    }

    // ─── Una sola vez ────────────────────────────────────────────────────────────────

    public function test_it_never_warns_twice_although_the_command_runs_every_hour(): void
    {
        $this->atParkHour(18);
        Notification::fake();

        $item = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow());

        $this->artisan('reservations:eve-notice')->assertSuccessful();
        $this->atParkHour(19);
        $this->artisan('reservations:eve-notice')->assertSuccessful();
        $this->atParkHour(20);
        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertSentToTimes($item->order->user, VisitEveNotice::class, 1);
    }

    // ─── Solo si queda algo ──────────────────────────────────────────────────────────

    public function test_with_nothing_pending_nobody_gets_written_to(): void
    {
        $this->atParkHour(19);
        Notification::fake();

        $item = $this->reservation(quantity: 2, guests: [
            ['name' => 'Ana', 'age' => '7'],
            ['name' => 'Pablo', 'age' => '8'],
        ], day: $this->parkTomorrow(), paid: true);

        $this->assertFalse(app(PendingBeforeVisit::class)->forReservation($item)->any(), 'el instrumento: a esta reserva no le falta nada');

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertNothingSent();
        // ⚠️ Y **no se marca**: si mañana le surge algo —una respuesta, una edad sin rellenar—, el
        // aviso de las 20:00 tiene que poder salir.
        $this->assertNull($item->fresh()->eve_notice_at);
    }

    /**
     * La R2d (`correos-rediseno.md` §4.3): solo pedidos PAGADOS. Hasta entonces el comando no miraba el pedido, y un carrito
     * abandonado con su franja mañana también recibía la víspera. CONTROL: la misma reserva, pagada, sí.
     */
    public function test_an_unpaid_order_is_not_warned(): void
    {
        $this->atParkHour(19);
        Notification::fake();

        $sinPagar = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow(), paid: false);
        $pagada = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow());

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertNotSentTo($sinPagar->order->user, VisitEveNotice::class);
        $this->assertNull($sinPagar->fresh()->eve_notice_at);
        Notification::assertSentTo($pagada->order->user, VisitEveNotice::class);
    }

    public function test_a_cancelled_reservation_is_not_warned(): void
    {
        $this->atParkHour(19);
        Notification::fake();

        $item = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow());
        $item->forceFill(['cancelled_at' => now()])->save();

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_without_an_email_there_is_nobody_to_warn_and_nothing_to_mark(): void
    {
        $this->atParkHour(19);
        Notification::fake();

        // El caso REAL: un cliente dado de alta por el mostrador **por teléfono** (`#263`, por eso
        // `users.email` es nullable). No es un error y no hay a quién escribir.
        $item = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow());
        User::query()->whereKey($item->order?->user_id)->update(['email' => null]);

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($item->fresh()->eve_notice_at, 'si mañana nos deja un correo, que lo reciba');
    }

    // ─── El testigo ──────────────────────────────────────────────────────────────────

    public function test_warning_the_customer_does_not_move_the_witness_of_the_extras(): void
    {
        $this->atParkHour(19);
        Notification::fake();

        $item = $this->reservation(quantity: 4, guests: [], day: $this->parkTomorrow());
        $before = $item->fresh()?->updated_at;
        $this->assertNotNull($before);

        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->artisan('reservations:eve-notice')->assertSuccessful();

        $this->assertNotNull($item->fresh()->eve_notice_at, 'el instrumento: la marca SÍ se escribió');
        $this->assertEquals($before, $item->fresh()?->updated_at, 'mandar un correo no puede tumbarle la página abierta');

        // ⚠️ EL CONTROL, sin el cual la aserción de arriba pasaría aunque el testigo no se moviera
        // nunca: el guardado del post-form SÍ tiene que moverlo.
        $item->submitGuestForm([['name' => 'Ana', 'age' => '7']], null, 'account');
        $this->assertNotEquals($before, $item->fresh()?->updated_at, 'el control: guardar sí mueve el testigo');
    }

    // ─── Lo que dice el correo ───────────────────────────────────────────────────────

    public function test_the_email_names_only_what_is_missing(): void
    {
        $this->atParkHour(19);

        // Solo le falta dinero: fichas completas, sin invitación y sin justificante.
        $item = $this->reservation(quantity: 2, guests: [
            ['name' => 'Ana', 'age' => '7'],
            ['name' => 'Pablo', 'age' => '8'],
        ], day: $this->parkTomorrow(), paid: true, atParkCents: 2000);

        $work = app(PendingBeforeVisit::class)->forReservation($item);
        $mail = (new VisitEveNotice($item, $work))->toMail($item->order->user);
        $html = $mail->render();

        // «Lo que queda» (la R2d): SOLO lo del parque, con su cifra en euros.
        $this->assertSame([__('emails.visit_eve.balance', ['amount' => "20\u{00A0}€"])], $this->loQueQueda($mail));
        // ⚠️ No nombra lo que NO falta: un correo que dice «2 de 2» es ruido que enseña a no leerlo.
        $this->assertStringNotContainsString('2 de 2', $html);
        // ❗ Y la frase que quita el susto va siempre.
        $this->assertStringContainsString(e(__('emails.visit_eve.not_serious')), $html);
        $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}]/u', $html), 'un correo no lleva emojis');
    }

    public function test_the_email_carries_the_figure_of_the_cards_when_they_are_what_is_missing(): void
    {
        $this->atParkHour(19);

        $item = $this->reservation(quantity: 4, guests: [['name' => 'Ana', 'age' => '7']], day: $this->parkTomorrow(), paid: true);

        $work = app(PendingBeforeVisit::class)->forReservation($item);
        $mail = (new VisitEveNotice($item, $work))->toMail($item->order->user);

        $this->assertSame([__('emails.visit_eve.guests', ['done' => 1, 'total' => 4])], $this->loQueQueda($mail), 'las fichas, y nada del parque');
    }

    /**
     * EL 4 DEL DISEÑO (la R2d): el asunto con la hora y quien cumple, sin chapa, «Lo que queda», el botón a la lista FIRMADA
     * y, después, el QR en claro —el trabajo del correo es el botón—.
     */
    public function test_the_eve_of_a_party_is_its_design(): void
    {
        $this->atParkHour(19);
        $item = $this->reservation(quantity: 4, guests: [['name' => 'Ana', 'age' => '7']], day: $this->parkTomorrow(), hour: 17);
        $mail = (new VisitEveNotice($item, app(PendingBeforeVisit::class)->forReservation($item)))->toMail($item->order->user);

        $this->assertSame('Mañana a las 17:00 · el cumple de Lucía', $mail->subject);
        $this->assertSame('', $mail->viewData['hero']['chapa']);
        $this->assertSame(['lista', 'linea', 'boton', 'qr'], array_column($mail->viewData['cuerpo'], 'tipo'));
        $this->assertTrue(collect($mail->viewData['cuerpo'])->firstWhere('tipo', 'qr')['secundario']);
        $this->get(collect($mail->viewData['cuerpo'])->firstWhere('tipo', 'boton')['url'])->assertOk();
    }

    /** @return list<string> las líneas de «Lo que queda» */
    private function loQueQueda(MailMessage $mail): array
    {
        return array_column(collect($mail->viewData['cuerpo'])->firstWhere('tipo', 'lista')['lineas'] ?? [], 'texto');
    }

    // ─── Quien cumple (F7, `specs/fiesta-sistema-nuevo.md` §4.13, `#752`) ─────────────

    public function test_the_honoree_without_its_waiver_is_named_and_alone_is_enough_to_warn(): void
    {
        $this->atParkHour(19);
        $this->internalWaiver();

        // Todo lo demás, hecho: fichas completas, pagada, sin invitación y sin justificante de invitados. Su plaza cuenta como
        // ocupada, así que «plazas sin resolver» nunca lo nombraba: sin esta línea, a esta reserva no le faltaba nada.
        $item = $this->sealed($this->reservation(quantity: 2, guests: [
            ['name' => 'Noa', 'age' => '7'],
            ['name' => 'Pablo', 'age' => '8'],
        ], day: $this->parkTomorrow(), paid: true));

        $work = app(PendingBeforeVisit::class)->forReservation($item);
        $this->assertTrue($work->honoreeWaiverMissing);
        $this->assertSame(0, $work->minorsUnresolved, 'el instrumento: la otra cifra no lo ve');
        $this->assertTrue($work->any(), 'basta para avisar');

        $html = (new VisitEveNotice($item, $work))->toMail($item->order->user)->render();
        $this->assertStringContainsString(e(__('emails.visit_eve.honoree', ['name' => 'Noa'])), $html);
        $this->assertStringNotContainsString(__('emails.visit_eve.guests', ['done' => 2, 'total' => 2]), $html);

        // CONTROL: con su justificante atado ya no falta nada, y no se escribe a nadie.
        app(GuardianAuthorizationSigner::class)->sign($item->order->user, (int) $item->getKey(), $this->waiverDocument(), [
            'minor_name' => 'Noa', 'minor_surname' => 'Ruiz', 'minor_born_on' => now()->subYears(7)->toDateString(),
            'guardian_name' => 'Marta', 'guardian_surname' => 'Ruiz', 'guardian_relationship' => 'mother',
            'guardian_email' => null, 'guardian_phone' => '600111222',
        ], WaiverSignatureRequest::web('127.0.0.1', 'test'), null, true);
        $this->assertFalse(app(PendingBeforeVisit::class)->forReservation($item->fresh(['ticketType', 'slot', 'order']) ?? $item)->any());
    }

    public function test_without_a_card_the_celebrant_names_it_and_without_either_it_says_who_it_is(): void
    {
        $this->atParkHour(19);
        $this->internalWaiver();

        // Sin ficha escrita, lo nombra el homenajeado de la reserva (lo que se pidió al comprar).
        $item = $this->sealed($this->reservation(quantity: 2, guests: [], day: $this->parkTomorrow(), paid: true));
        $html = (new VisitEveNotice($item, app(PendingBeforeVisit::class)->forReservation($item)))->toMail($item->order->user)->render();
        $this->assertStringContainsString(e(__('emails.visit_eve.honoree', ['name' => 'Lucía'])), $html);

        // Sin nombre en ningún sitio, se dice quién es.
        $item->forceFill(['event_data' => []])->save();
        $item = $item->fresh(['ticketType', 'slot', 'order']) ?? $item;
        $html = (new VisitEveNotice($item, app(PendingBeforeVisit::class)->forReservation($item)))->toMail($item->order->user)->render();
        $this->assertStringContainsString(e(__('emails.visit_eve.honoree_unnamed')), $html);
    }

    public function test_outside_the_internal_mode_or_without_the_seal_the_honoree_is_not_asked_for(): void
    {
        $this->atParkHour(19);
        $guests = [['name' => 'Noa', 'age' => '7'], ['name' => 'Pablo', 'age' => '8']];

        // Sin el sello (una reserva de antes de F3): su ficha 0 es la de un invitado.
        $this->internalWaiver();
        $unsealed = $this->reservation(quantity: 2, guests: $guests, day: $this->parkTomorrow(), paid: true);
        $this->assertFalse(app(PendingBeforeVisit::class)->forReservation($unsealed)->honoreeWaiverMissing);

        // Sin el descargo gestionado dentro no hay firma que pedir.
        $sealed = $this->sealed($this->reservation(quantity: 2, guests: $guests, day: $this->parkTomorrow(), paid: true));
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => 'externo']);
        Setting::flushMemo();
        $this->assertFalse(app(PendingBeforeVisit::class)->forReservation($sealed)->honoreeWaiverMissing);
    }

    // ─── Fixture ─────────────────────────────────────────────────────────────────────

    private function internalWaiver(): void
    {
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
        $this->waiverDocument();
    }

    private function waiverDocument(): LegalDocumentVersion
    {
        return LegalDocumentVersion::query()->where('slug', WaiverSettings::SLUG)->orderByDesc('id')->first()
            ?? app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
                'es' => ['title' => 'Descargo', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
            ])->first();
    }

    /** La reserva SELLA a quien cumple (F3a): su ficha 0 es la suya. */
    private function sealed(OrderItem $item): OrderItem
    {
        $item->forceFill(['honoree_row' => true])->save();

        return $item->fresh(['ticketType', 'slot', 'order']) ?? $item;
    }

    /** Congela el reloj en esa hora **del parque** (el contenedor va en UTC). */
    private function atParkHour(int $hour): void
    {
        Carbon::setTestNow(Carbon::parse(
            Carbon::today()->toDateString().' '.sprintf('%02d:30', $hour),
            DisplayTime::timezone(),
        ));
    }

    private function parkTomorrow(): Carbon
    {
        return DisplayTime::now()->copy()->addDay();
    }

    /**
     * @param  list<array<string, string>>  $guests
     */
    private function reservation(
        int $quantity,
        array $guests,
        Carbon $day,
        // PAGADA por defecto desde la R2d: la víspera solo avisa a pedidos pagados (un carrito abandonado no es una reserva).
        bool $paid = true,
        int $atParkCents = 0,
        ?int $hour = null,
    ): OrderItem {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Quién cumple']],
            ],
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'age', 'type' => 'age', 'required' => true, 'label' => ['es' => 'Edad']],
            ],
        ]);

        // La hora de la franja importa: es lo que decide si una fiesta de HOY ya pasó. Sin pasarla,
        // las franjas van corriéndose para no chocar con el único de `(zona, día, hora)`.
        $start = $hour ?? (9 + Slot::query()->count());
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $day->toDateString(),
            'start_time' => sprintf('%02d:00:00', $start),
            'end_time' => sprintf('%02d:00:00', $start + 2),
            'capacity' => 100, 'online_capacity' => 100,
        ]);

        $user = User::factory()->create(['locale' => 'es']);
        $total = $quantity * 1495;
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $paid ? Order::STATUS_PAID : Order::STATUS_PENDING,
            'paid_at' => $paid ? now() : null,
            'total' => $total, 'currency' => 'EUR',
        ]);

        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => $quantity,
            'unit_price' => 1495, 'seats' => $quantity, 'event_data' => ['celebrant' => 'Lucía'],
            'guest_data' => $guests,
        ]);

        if ($paid) {
            if ($atParkCents > 0) {
                OrderAdjustment::create([
                    'order_id' => $order->id, 'order_item_id' => $line->id,
                    'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT, 'amount_cents' => $atParkCents,
                    'currency' => 'EUR', 'reason' => 'deposit_split', 'applied_by' => $order->user_id,
                ]);
            }
            Payment::create([
                'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
                'amount' => $total - $atParkCents, 'currency' => 'EUR', 'provider' => 'redsys',
                'status' => Payment::STATUS_PAID, 'paid_at' => now(),
                'gateway_order' => str_pad((string) (500000 + $order->id), 10, '0', STR_PAD_LEFT),
            ]);
        }

        return $order->items()->whereNull('parent_item_id')->with(['ticketType', 'slot', 'order'])->firstOrFail();
    }
}
