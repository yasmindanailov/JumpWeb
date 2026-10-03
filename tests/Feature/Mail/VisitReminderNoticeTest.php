<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\VisitEveNotice;
use App\Notifications\VisitReminderNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * **EL 3, «MAÑANA OS ESPERAMOS»** (la R2d de `specs/correos-rediseno.md` §4.3, `#915`, a): a toda reserva PAGADA que no sea
 * una fiesta, SIEMPRE, la víspera desde las 18:00 del parque; la del MISMO día, con «Hoy», dos horas antes. Y lo que dice: el
 * QR, la hora, cómo llegar, lo que queda y el aviso de los menores a cargo. Cada promesa, con su control.
 *
 * ⚠️ El reloj, en la hora del PARQUE (`atParkHour`): el contenedor va en UTC.
 */
class VisitReminderNoticeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private int $orders = 0;

    protected function setUp(): void
    {
        parent::setUp();
        App::setLocale('es');
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'is_active' => true, 'position' => 1]);
        foreach (['address.line1' => 'Calle del Salto, 1', 'address.maps_url' => 'https://maps.example.test/parque', WaiverSettings::KEY_MODE => WaiverSettings::MODE_INTERNAL] as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Setting::flushMemo();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Cuándo sale ─────────────────────────────────────────────────────────────────

    public function test_tickets_for_tomorrow_get_the_3_always_and_a_party_only_with_something_left(): void
    {
        $this->atParkHour(19);
        Notification::fake();

        // Unas entradas pagadas a las que NO les falta nada: hasta la R2d no recibían nada; ahora, su QR y su hora.
        $entradas = $this->reservation($this->entry(), DisplayTime::now()->copy()->addDay(), 17);
        // CONTROL: una fiesta a la que no le falta nada sigue sin correo (`#714`).
        $fiesta = $this->reservation($this->party(), DisplayTime::now()->copy()->addDay(), 18, quantity: 1, guests: [['name' => 'Ana', 'age' => '7']]);

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertSentTo($entradas->order->user, VisitReminderNotice::class, static fn (VisitReminderNotice $n): bool => $n->hoy === false);
        $this->assertNotNull($entradas->fresh()->eve_notice_at);
        Notification::assertNotSentTo($fiesta->order->user, VisitReminderNotice::class);
        Notification::assertNotSentTo($fiesta->order->user, VisitEveNotice::class);
    }

    public function test_a_reservation_of_today_gets_it_two_hours_before_and_only_once(): void
    {
        $today = DisplayTime::now()->copy();
        Notification::fake();
        $entradas = $this->reservation($this->entry(), $today, 17);
        // CONTROL: una fiesta de hoy no recibe el «Hoy» —su repaso es de la víspera—, aunque le falte algo. A la MISMA hora que
        // las entradas: a otra fuera de la ventana, el caso no mediría nada (lo cazó `mutar-correo-r2d.sh`).
        $fiesta = $this->reservation($this->party(), $today, 17, quantity: 4);

        $this->atParkHour(14); // las 14:30: faltan dos horas y media
        $this->artisan('reservations:eve-notice')->assertSuccessful();
        Notification::assertNothingSent();

        $this->atParkHour(15); // las 15:30: falta hora y media
        $this->artisan('reservations:eve-notice')->assertSuccessful();
        Notification::assertSentTo($entradas->order->user, VisitReminderNotice::class, static fn (VisitReminderNotice $n): bool => $n->hoy);
        Notification::assertNotSentTo($fiesta->order->user, VisitReminderNotice::class);
        Notification::assertNotSentTo($fiesta->order->user, VisitEveNotice::class);

        $this->atParkHour(16);
        $this->artisan('reservations:eve-notice')->assertSuccessful();
        Notification::assertSentToTimes($entradas->order->user, VisitReminderNotice::class, 1);
    }

    public function test_what_already_started_or_was_warned_the_eve_gets_nothing_today(): void
    {
        Notification::fake();
        $today = DisplayTime::now()->copy();
        $empezada = $this->reservation($this->entry(), $today, 15);
        $avisada = $this->reservation($this->entry(), $today, 17);
        OrderItem::query()->whereKey($avisada->getKey())->toBase()->update(['eve_notice_at' => now()->subDay()]);

        $this->atParkHour(15); // las 15:30: la de las 15:00 ya empezó
        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_an_unpaid_order_is_never_warned(): void
    {
        $this->atParkHour(19);
        Notification::fake();
        $sinPagar = $this->reservation($this->entry(), DisplayTime::now()->copy()->addDay(), 17, paid: false);

        $this->artisan('reservations:eve-notice')->assertSuccessful();

        Notification::assertNotSentTo($sinPagar->order->user, VisitReminderNotice::class);
        $this->assertNull($sinPagar->fresh()->eve_notice_at);
    }

    // ─── Lo que dice ─────────────────────────────────────────────────────────────────

    public function test_the_3_says_the_time_how_to_get_there_and_what_is_left_with_its_qr_first(): void
    {
        $this->atParkHour(19);
        $item = $this->reservation($this->entry(), DisplayTime::now()->copy()->addDay(), 17);

        $mail = $this->mail($item, new PendingWork(0, 0, 0, 2, 1500));

        $this->assertSame('Mañana a las 17:00 · Entrada 1 hora', $mail->subject);
        $this->assertSame(['Mañana os esperamos', ''], [$mail->viewData['hero']['titulo'], $mail->viewData['hero']['chapa']]);
        $this->assertSame(['qr', 'lista', 'aviso'], array_column($mail->viewData['cuerpo'], 'tipo'));
        $this->assertFalse($this->block($mail, 'qr')['secundario'], 'el trabajo del 3 es el QR');
        $this->assertSame([
            ['Llegad unos minutos antes de las 17:00.', 'clock'],
            ['Calle del Salto, 1: [Cómo llegar](mapa).', 'map-pin'],
            ['En el parque se pagan **15'."\u{00A0}".'€**.', 'banknote'],
            ['**Autorizaciones:** 0 de 2 firmadas; [pasa el enlace a las familias](autorizacion) o se firman en la puerta.', 'pen-line'],
        ], array_map(static fn (array $l): array => [$l['texto'], $l['icono']], $this->block($mail, 'lista')['lineas']));
        $this->assertSame('https://maps.example.test/parque', $mail->viewData['enlaces']['mapa']);
        $this->assertArrayNotHasKey('autorizacion', $mail->viewData['enlaces'], 'un producto que no pide autorizaciones no ofrece su enlace');

        // «Hoy», la del mismo día.
        $hoy = $this->mail($item, new PendingWork(0, 0, 0, 0, 0), hoy: true);
        $this->assertSame('Hoy a las 17:00 · Entrada 1 hora', $hoy->subject);
        $this->assertSame('Hoy os esperamos', $hoy->viewData['hero']['titulo']);
        // CONTROL: sin nada que quede, ni el parque ni las autorizaciones.
        $this->assertCount(2, $this->block($hoy, 'lista')['lineas']);
    }

    public function test_the_minors_notice_follows_the_holder_and_is_not_for_a_group(): void
    {
        $this->atParkHour(19);
        $item = $this->reservation($this->entry(), DisplayTime::now()->copy()->addDay(), 17);
        $aviso = fn (OrderItem $r): ?array => $this->block($this->mail($r->fresh(['ticketType', 'slot', 'order.user']), new PendingWork(0, 0, 0, 0, 0)), 'aviso');

        $this->assertSame((string) __('emails.manana.minors'), $aviso($item)['texto']);
        Dependent::create(['user_id' => $item->order->user_id, 'name' => 'Vera', 'relationship' => 'mother', 'born_on' => DisplayTime::today()->subYears(7)->toDateString()]);
        $this->assertNull($aviso($item), 'con un menor a su cargo, sin aviso');

        // Un grupo (un pack sin lista) no lo lleva, aunque el titular no tenga menores.
        $grupo = $this->reservation($this->group(), DisplayTime::now()->copy()->addDay(), 10, quantity: 20);
        $this->assertNull($aviso($grupo));
        // CONTROL: el mismo grupo dice su hora.
        $this->assertSame('Llegad unos minutos antes de las 10:00.', $this->block($this->mail($grupo, new PendingWork(0, 0, 0, 0, 0)), 'lista')['lineas'][0]['texto']);
    }

    // ─── El caso ─────────────────────────────────────────────────────────────────────

    private function mail(OrderItem $item, PendingWork $work, bool $hoy = false): MailMessage
    {
        return (new VisitReminderNotice($item, $work, $hoy))->toMail($item->order->user);
    }

    /** @return array<string, mixed>|null */
    private function block(MailMessage $mail, string $type): ?array
    {
        return collect($mail->viewData['cuerpo'])->firstWhere('tipo', $type);
    }

    /** Congela el reloj en esa hora y media **del parque** (el contenedor va en UTC). */
    private function atParkHour(int $hour): void
    {
        Carbon::setTestNow(Carbon::parse(Carbon::today()->toDateString().' '.sprintf('%02d:30', $hour), DisplayTime::timezone()));
    }

    private function entry(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    private function group(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Excursión'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 10, 'max_qty' => 80, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
    }

    private function party(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 1, 'max_qty' => 20, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 3,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'age', 'type' => 'age', 'required' => true, 'label' => ['es' => 'Edad']],
            ],
        ]);
    }

    /** @param  list<array<string, string>>  $guests */
    private function reservation(TicketType $type, Carbon $day, int $hour, int $quantity = 2, bool $paid = true, array $guests = []): OrderItem
    {
        $slot = Slot::query()->firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => $day->toDateString(), 'start_time' => sprintf('%02d:00:00', $hour)],
            ['end_time' => sprintf('%02d:00:00', $hour + 1), 'capacity' => 100, 'online_capacity' => 100],
        );
        $user = User::factory()->create(['locale' => 'es']);
        $total = $quantity * 1000;
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.(++$this->orders), 'status' => $paid ? Order::STATUS_PAID : Order::STATUS_PENDING,
            'paid_at' => $paid ? now() : null, 'subtotal' => $total, 'total' => $total, 'currency' => 'EUR',
        ]);
        $order->items()->create(['ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => $quantity, 'unit_price' => 1000, 'seats' => $quantity, 'guest_data' => $guests]);
        if ($paid) {
            Payment::create([
                'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id, 'amount' => $total, 'currency' => 'EUR',
                'provider' => 'redsys', 'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'gateway_order' => '17'.$order->id.'0001',
            ]);
        }

        return $order->items()->with(['ticketType', 'slot', 'order.user'])->firstOrFail();
    }
}
