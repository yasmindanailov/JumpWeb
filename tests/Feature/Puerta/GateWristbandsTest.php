<?php

namespace Tests\Feature\Puerta;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\WristbandColor;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\WristbandWheel;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Admin\Puerta\FichaPuerta;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Las pulseras y lo que se entrega, de la base de datos a la ficha** (`docs/specs/puerta-nueva.md` §4.4, la P2): Booking
 * resuelve la pulsera de cada reserva —la FIJA del producto gana a la de la RUEDA por su hora—, la zona de SALTO de un pack
 * y los complementos que se ENTREGAN en la puerta; la ficha agrupa y pinta. Con colores neutros: el producto no sabe de
 * qué color es ningún parque.
 */
class GateWristbandsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-02';

    private Zone $kids;

    private Zone $sala;

    private TicketType $hour;

    private TicketType $free;

    private TicketType $pack;

    private TicketType $socks;

    private TicketType $cake;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::TODAY.' 16:00:00', 'Europe/Madrid'));
        app()->setLocale('es');

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->kids = Zone::create(['slug' => 'kids', 'name' => ['es' => 'Kids'], 'position' => 1, 'is_active' => true]);
        $this->sala = Zone::create(['slug' => 'sala', 'name' => ['es' => 'Sala'], 'position' => 3, 'is_active' => true]);

        // La rueda: dos colores desde las 17:00 cada 30 min (el segundo, en mayúsculas: Booking lo da en minúsculas).
        WristbandColor::create(['name_one' => 'pulsera primera', 'name_other' => 'pulseras primeras', 'hex' => '#111111', 'in_wheel' => true, 'position' => 1]);
        WristbandColor::create(['name_one' => 'pulsera segunda', 'name_other' => 'pulseras segundas', 'hex' => '#EEEEEE', 'in_wheel' => true, 'position' => 2]);
        $fija = WristbandColor::create(['name_one' => 'pulsera fija', 'name_other' => 'pulseras fijas', 'hex' => '#777777', 'in_wheel' => false, 'position' => 3]);
        $fiesta = WristbandColor::create(['name_one' => 'pulsera de fiesta', 'name_other' => 'pulseras de fiesta', 'hex' => '#aa0000', 'in_wheel' => false, 'position' => 4]);
        Setting::updateOrCreate(['key' => WristbandWheel::KEY_START], ['value' => '17:00', 'group' => 'puerta']);

        $base = ['seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true];
        $this->hour = TicketType::create($base + ['name' => ['es' => 'Kids · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->kids->id, 'duration_min' => 60, 'guest_age_max' => 7, 'position' => 1]);
        $this->free = TicketType::create($base + ['name' => ['es' => 'Kids · Ilimitada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->kids->id, 'duration_min' => null, 'wristband_color_id' => $fija->id, 'position' => 2]);
        $this->pack = TicketType::create($base + ['name' => ['es' => 'Pack Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->sala->id, 'gate_zone_id' => $this->kids->id, 'wristband_color_id' => $fiesta->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'position' => 3]);
        $this->socks = TicketType::create($base + ['name' => ['es' => 'Calcetines antideslizantes'], 'type' => TicketType::TYPE_ADDON, 'handed_at_gate' => true, 'gate_label_one' => 'par de calcetines', 'gate_label_other' => 'pares de calcetines', 'icon' => 'socks', 'position' => 4]);
        $this->cake = TicketType::create($base + ['name' => ['es' => 'Tarta'], 'type' => TicketType::TYPE_ADDON, 'position' => 5]);
    }

    private function slot(Zone $zone, string $start): Slot
    {
        return Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => self::TODAY, 'start_time' => $start.':00'],
            ['end_time' => Carbon::parse($start)->addHour()->format('H:i:s'), 'capacity' => 50, 'online_capacity' => 50],
        );
    }

    /** Un pedido PAGADO con una línea y sus complementos `[producto, cantidad]`. */
    private function booked(User $holder, TicketType $type, int $quantity, string $start, array $addons = []): OrderItem
    {
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-PULS'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => now()->subDay(),
        ]);
        Payment::create(['payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'cash', 'amount' => 1000, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'paid_at' => now()->subDay()]);
        $zone = $type->type === TicketType::TYPE_PACK ? $this->sala : $this->kids;
        $item = $order->items()->create(['ticket_type_id' => $type->id, 'slot_id' => $this->slot($zone, $start)->id, 'quantity' => $quantity, 'unit_price' => 1000, 'seats' => $quantity]);
        foreach ($addons as [$addon, $n]) {
            OrderItem::create(['order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $addon->id, 'quantity' => $n, 'unit_price' => 0, 'seats' => 0]);
        }

        return $item;
    }

    /** @return array<int, array<string, mixed>> las filas de hoy por su línea */
    private function rows(User $holder): array
    {
        return collect(app(GateProfile::class)->for($holder, CarbonImmutable::parse(self::TODAY), 0)->toArray()['today_reservations'])->keyBy('order_item_id')->all();
    }

    public function test_booking_gives_each_reservation_its_wristband_its_gate_zone_and_what_is_handed_over(): void
    {
        $holder = User::factory()->create();
        $hour = $this->booked($holder, $this->hour, 2, '17:30', [[$this->socks, 2], [$this->cake, 1]]);
        $free = $this->booked($holder, $this->free, 1, '17:30');
        $party = $this->booked($holder, $this->pack, 10, '17:30');

        $rows = $this->rows($holder);

        $this->assertSame(['one' => 'pulsera segunda', 'other' => 'pulseras segundas', 'hex' => '#eeeeee'], $rows[$hour->id]['wristband'], 'la rueda: a las 17:30, la segunda; el hex, en minúsculas');
        $this->assertSame('pulsera fija', $rows[$free->id]['wristband']['one'], 'el color FIJO gana a la rueda, aunque empiece a la misma hora');
        $this->assertSame('pulseras de fiesta', $rows[$party->id]['wristband']['other']);
        $this->assertSame(['Kids', 'kids'], [$rows[$party->id]['zone_name'], $rows[$party->id]['zone_slug']], 'un pack, en la fila de la zona donde SALTAN, no en la de su sala');

        $this->assertSame(['1 × Tarta'], $rows[$hour->id]['addons'], 'lo que se entrega en la puerta no va en la línea de complementos');
        $this->assertSame([['key' => $this->socks->id, 'quantity' => 2, 'one' => 'par de calcetines', 'other' => 'pares de calcetines', 'icon' => 'socks']], $rows[$hour->id]['handed_at_gate']);

        $ficha = FichaPuerta::de(app(GateProfile::class)->for($holder, CarbonImmutable::parse(self::TODAY), 0)->toArray(), now());
        $this->assertSame([['Kids', 2, 'pulseras segundas'], ['Kids', 1, 'pulsera fija'], ['Kids', 10, 'pulseras de fiesta']], array_map(fn (array $g): array => [$g['zona'], $g['cifra'], $g['pulsera']['frase']], $ficha['filas']));
        $this->assertSame([['clave' => (string) $this->socks->id, 'cifra' => 2, 'frase' => 'pares de calcetines', 'icono' => 'socks']], $ficha['entregas']);
    }

    public function test_without_a_gate_zone_a_pack_keeps_its_own_and_without_colours_there_is_no_wristband(): void
    {
        $this->pack->update(['gate_zone_id' => null, 'wristband_color_id' => null]);
        Setting::updateOrCreate(['key' => WristbandWheel::KEY_START], ['value' => '', 'group' => 'puerta']);
        $holder = User::factory()->create();
        $party = $this->booked($holder, $this->pack, 8, '17:30');
        $hour = $this->booked($holder, $this->hour, 1, '17:30');

        $rows = $this->rows($holder);

        $this->assertSame('Sala', $rows[$party->id]['zone_name'], 'sin zona de salto, la del pack');
        $this->assertNull($rows[$party->id]['wristband']);
        $this->assertNull($rows[$hour->id]['wristband'], 'sin hora del primero no hay rueda');

        // La zona de salto es de los PACKS: una entrada que la tuviera (un dato escrito a mano) sigue en la suya.
        $this->hour->update(['gate_zone_id' => $this->sala->id]);
        $this->assertSame('Kids', $this->rows($holder)[$hour->id]['zone_name']);
    }

    /**
     * La PANTALLA, abierta como en el mostrador (por carné): la cifra sobre su color —en un `style` que solo lleva un hex
     * comprobado— con su frase tras la zona, y lo que se entrega con su dibujo y su rótulo, tras las pulseras.
     */
    public function test_the_gate_screen_paints_the_wristband_and_what_is_handed_over(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $holder = User::factory()->create(['email_verified_at' => now(), 'waiver_accepted_at' => now()]);
        $holder->roles()->sync([Role::where('name', 'customer')->value('id')]);
        $card = (string) app(CustomerCards::class)->ensureFor($holder)->plainToken();
        $this->booked($holder, $this->hour, 2, '17:30', [[$this->socks, 2]]);
        $this->booked($holder, $this->free, 1, '17:00');

        $page = Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $card)->call('search');
        // Sobre un gris medio, la cifra en blanco (`clara`); entre los dos atributos, Blade deja dos espacios.
        $this->assertMatchesRegularExpression('/class="ppu-cant clara"\s+style="background: #777777"/', $page->html());
        $page
            ->assertSeeHtml('data-gate-wristband="pulseras segundas"')
            ->assertSeeHtml('style="background: #eeeeee"')
            ->assertSeeHtml('<span class="ppu-pulsera">pulsera fija</span>')
            ->assertSeeHtml('data-gate-handed="'.$this->socks->id.'"')
            ->assertSeeHtml('ic-s1')
            ->assertSee('pares de calcetines')
            ->assertDontSee('2 × Calcetines antideslizantes');
    }

    /** Un hex corrupto (escrito saltándose el panel) no llega al `style`: la frase se queda, el color no. */
    public function test_a_corrupt_hex_never_leaves_booking(): void
    {
        DB::table('wristband_colors')->where('name_one', 'pulsera fija')->update(['hex' => 'red;background:url(x)']);
        $holder = User::factory()->create();
        $free = $this->booked($holder, $this->free, 1, '17:00');

        $this->assertSame(['one' => 'pulsera fija', 'other' => 'pulseras fijas', 'hex' => null], $this->rows($holder)[$free->id]['wristband']);
    }

    /** El rótulo en la puerta: sin él, el NOMBRE del complemento; con uno solo, ese para los dos. */
    public function test_the_gate_label_falls_back_to_the_other_form_and_then_to_the_name(): void
    {
        $holder = User::factory()->create();
        $this->socks->update(['gate_label_one' => null, 'gate_label_other' => 'pares de calcetines']);
        $a = $this->booked($holder, $this->hour, 1, '17:00', [[$this->socks, 1]]);
        $h = $this->rows($holder)[$a->id]['handed_at_gate'][0];
        $this->assertSame(['pares de calcetines', 'pares de calcetines'], [$h['one'], $h['other']]);

        $this->socks->update(['gate_label_one' => '  ', 'gate_label_other' => null]);
        $h = $this->rows($holder)[$a->id]['handed_at_gate'][0];
        $this->assertSame(['Calcetines antideslizantes', 'Calcetines antideslizantes'], [$h['one'], $h['other']]);
    }

    /**
     * El presupuesto de la ficha (`GateProfileTest`, 29) con TODO puesto —rueda, fijos, zona de salto y complementos que se
     * entregan—: crece en un número FIJO (los colores de la rueda, un lote para el fijo y otro para la zona de salto), nunca
     * con las reservas.
     */
    public function test_the_wristbands_cost_a_fixed_number_of_queries(): void
    {
        $small = User::factory()->create();
        $this->booked($small, $this->hour, 1, '17:00', [[$this->socks, 1]]);
        $this->booked($small, $this->pack, 6, '17:30');

        $big = User::factory()->create();
        foreach (['17:00', '17:30', '18:00', '18:30'] as $start) {
            $this->booked($big, $this->hour, 2, $start, [[$this->socks, 2], [$this->cake, 1]]);
            $this->booked($big, $this->free, 1, $start);
        }
        $this->booked($big, $this->pack, 10, '17:30');

        $count = function (User $holder): int {
            Setting::flushMemo();
            DB::enableQueryLog();
            DB::flushQueryLog();
            app(GateProfile::class)->for($holder, CarbonImmutable::parse(self::TODAY), 0);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $forSmall = $count($small);
        $forBig = $count($big);
        $this->assertSame($forSmall, $forBig, "las pulseras no cuestan una consulta por reserva ({$forSmall} frente a {$forBig})");
        // Medido el 2026-10-02: 25 con esta ficha (sin menores, firmas ni carné, que `GateProfileTest` sí cuenta).
        $this->assertLessThanOrEqual(25, $forSmall, 'una ficha compuesta, no un escaneo que dispara decenas de consultas');

        // Y lo que cuestan las pulseras, exacto: los colores de la rueda y un lote para el fijo y otro para la zona de salto.
        Setting::updateOrCreate(['key' => WristbandWheel::KEY_START], ['value' => '', 'group' => 'puerta']);
        TicketType::query()->update(['wristband_color_id' => null, 'gate_zone_id' => null]);
        $this->assertSame(3, $forSmall - $count($small), 'la P2 suma TRES consultas fijas a la ficha');
    }
}
