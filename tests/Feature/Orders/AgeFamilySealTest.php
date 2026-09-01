<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySeal;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Booking\Services\GuestAgeMix;
use App\Domain\Booking\Services\GuestAgeMixReader;
use App\Domain\Booking\Services\MixedPartySurcharge;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\SealedRegime;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use App\Notifications\MixedPartySurchargeChanged;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * El SELLO de condiciones de una reserva (`docs/specs/cumple-mixto.md` §21, `DECISIONES #284`
 * D1/D2 y `#288`).
 *
 * `[DECIDIDO owner, 2026-08-31]` «el cliente compra con unas condiciones y las mantenemos; ya las
 * siguientes reservas empiezan con las nuevas». Cada fiesta guarda al nacer la familia por edad, los
 * tramos y los precios con los que se vendió; el veredicto de fiesta MIXTA deriva de eso y no del
 * catálogo, y el sello solo se reescribe cuando la fiesta cambia de pack (sello nuevo) o de día (el
 * mismo sello, re-preciado).
 *
 * ⚠️ Lo que estos casos protegen son los TRES huecos medidos que el sello cierra (§18.4): el primer
 * cargo se calculaba con el catálogo del día del formulario y no del día de la compra (5,00 €
 * pactados → 10,00 € cobrados); un cambio de tramo movía lo vendido en las dos direcciones (crear
 * 32,00 € de la nada, destruir 40,00 € comunicados); y la etiqueta podía contradecir al cargo. Y la
 * mitad del mecanismo que no se ve: cuándo se RE-sella y qué pasa con un sello que no casa con su
 * fila. Cada caso lleva la mutación que lo pone en rojo (§21.10).
 */
class AgeFamilySealTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private Slot $slot;

    private RateType $rate;

    private TicketType $kids;

    private TicketType $jump;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        // Zona OPERATIVA (vende) para que `OrderCreator` acepte la compra del caso de nacimiento.
        $this->zone = Zone::create([
            'slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'],
            'is_active' => true, 'show_in_landing' => false,
        ]);
        $this->slot = $this->slotOn(now()->addDays(20)->toDateString());

        $this->kids = $this->pack('Cumpleaños Kids', 1, 6, 1800, 'cumple');
        $this->jump = $this->pack('Cumpleaños Jump', 7, 99, 2500, 'cumple');

        // Cupo de packs para la compra real (placeholders, como en `PackAvailabilityTest`).
        foreach ([
            PackAvailability::SETTING_MAX_PER_SLOT => '5',
            PackAvailability::SETTING_MAX_GUESTS_PER_SLOT => '60',
            PackAvailability::SETTING_PREP_BLOCKS_CUPO => '0',
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['key' => $key, 'value' => $value, 'group' => 'packs']);
        }
        Setting::flushMemo();
    }

    private function slotOn(string $date): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);
    }

    private function pack(string $name, ?int $min, ?int $max, ?int $cents, ?string $family): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name, 'en' => $name.' (en)'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1, 'duration_min' => 120,
            'min_qty' => 1, 'max_qty' => 30, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'guest_fields' => [
                ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']],
            ],
            'guest_age_family' => $family, 'guest_age_min' => $min, 'guest_age_max' => $max,
        ]);
        if ($cents !== null) {
            Price::create([
                'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
                'rate_type_id' => $this->rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
            ]);
        }

        return $pack;
    }

    /** Una reserva PAGADA de N invitados del pack dado, sellada como lo hace `OrderCreator`. */
    private function reservation(int $guests = 3, ?TicketType $pack = null, bool $sealed = true): OrderItem
    {
        $pack ??= $this->kids;
        $unit = (int) $pack->prices()->value('amount_cents');
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-SL'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => $unit * $guests, 'tax' => 0, 'total' => $unit * $guests, 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (400000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $this->slot->id,
            'quantity' => $guests, 'unit_price' => $unit, 'seats' => $guests,
        ]);
        if ($sealed) {
            app(AgeFamilySealer::class)->seal($item, $pack, $this->slot->date);
        }

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    /**
     * Guarda el post-form por la MISMA puerta que usan la web y la API (es la que reconcilia).
     *
     * @param  list<int|null>  $ages
     */
    private function declareAges(OrderItem $item, array $ages, string $namePrefix = 'Invitado '): OrderItem
    {
        $rows = [];
        foreach ($ages as $i => $age) {
            $row = ['name' => $namePrefix.($i + 1)];
            if ($age !== null) {
                $row['edad'] = (string) $age;
            }
            $rows[] = $row;
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    /** @return Collection<int, OrderItem> */
    private function surchargeLines(OrderItem $item): Collection
    {
        $marked = OrderAdjustment::query()
            ->where('order_id', $item->order_id)
            ->get()
            ->filter(fn (OrderAdjustment $a): bool => is_array($a->context) && isset($a->context['mixed_party']))
            ->pluck('order_item_id')
            ->all();

        return $item->children()->whereNull('cancelled_at')->whereIn('id', $marked)->get();
    }

    private function written(OrderItem $item): int
    {
        return app(MixedPartySurcharge::class)->written($item->fresh(['ticketType', 'slot', 'order', 'children']))['cents'];
    }

    /** Lo que la reserva debe EN EL PARQUE según su LIBRO (T3·4): su saldo cuando es positivo. */
    private function gateDue(OrderItem $item): int
    {
        $order = $item->order()->with(['items.ticketType', 'items.slot', 'items.children', 'adjustments', 'payments.refunds'])->first();
        $book = OrderBook::forReservation($order, $order->items->firstWhere('id', $item->id));

        return max(0, $book->totalCents - $book->paidCents);
    }

    private function read(OrderItem $item): GuestAgeMix
    {
        return app(GuestAgeMixReader::class)->for($item->fresh(['ticketType', 'slot']));
    }

    /** Un empleado con lo justo para editar una reserva desde el panel. */
    private function staff(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $staff->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['orders.view', 'orders.edit_item'])->pluck('id'),
        );

        return $staff;
    }

    private function editProduct(OrderItem $item, TicketType $newType, ?int $qty = null): void
    {
        $staff = $this->staff();
        $item = $item->fresh(['ticketType', 'slot', 'order']);
        $outcome = app(OrderItemEditor::class)->edit(
            $item->order, $item, '', '', false,
            (int) $newType->id, $qty ?? (int) $item->quantity, null,
            ['edits' => [], 'adds' => []],
            (string) $item->updated_at->getTimestamp(),
            $staff,
        );
        $this->assertFalse($outcome->isBlocked(), 'bloqueado: '.json_encode($outcome));
    }

    private function moveToDay(OrderItem $item, Slot $target): void
    {
        $staff = $this->staff();
        $item = $item->fresh(['ticketType', 'slot', 'order']);
        $outcome = app(OrderItemEditor::class)->changeSlot(
            $item->order, $item, $target->date->toDateString(), '11:00:00',
            (string) $item->updated_at->getTimestamp(), null, $staff,
        );
        $this->assertFalse($outcome->isBlocked(), 'bloqueado: '.json_encode($outcome));
    }

    // ─── A · El sello NACE con la reserva, por la puerta REAL de la compra ────────

    public function test_a_party_is_born_with_the_conditions_of_its_family_sealed(): void
    {
        // Por `OrderCreator` y no escribiendo la fila a mano: es la puerta de la web, la API y el
        // alta manual del panel, y si el sello dejara de ponerse ahí, todo lo demás derivaría del
        // silencio. Mutación: quitar el sello del `create()` de `OrderCreator` → rojo.
        $order = app(OrderCreator::class)->createPendingOrder(User::factory()->create(), [[
            'ticket_type_id' => $this->kids->id, 'date' => $this->slot->date->toDateString(),
            'time' => '11:00:00', 'qty' => 3,
        ]]);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $seal = $item->ageFamilySeal();
        $this->assertNotNull($seal, 'la fiesta nace sellada');
        $this->assertSame('cumple', $seal->family);
        $this->assertSame((int) $this->kids->id, $seal->bookedTypeId);
        $this->assertSame($this->slot->date->toDateString(), $seal->pricedOn);
        $this->assertNotSame('', $seal->sealedAt);

        // Los DOS packs de la familia, ORDENADOS por el inicio del tramo, con tramo y precio del día.
        $this->assertSame([(int) $this->kids->id, (int) $this->jump->id], array_map(fn (SealedRegime $m): int => $m->typeId, $seal->members));
        $this->assertSame([1, 6, 1800], [$seal->member($this->kids->id)->ageMin, $seal->member($this->kids->id)->ageMax, $seal->member($this->kids->id)->priceCents]);
        $this->assertSame([7, 99, 2500], [$seal->member($this->jump->id)->ageMin, $seal->member($this->jump->id)->ageMax, $seal->member($this->jump->id)->priceCents]);
        // El nombre viaja ENTERO (traducible): es lo que se le dijo, en su idioma.
        $this->assertSame('Cumpleaños Jump (en)', $seal->member($this->jump->id)->displayName('en'));

        // ⚠️ La invariante interna: el precio sellado del pack reservado ES el `unit_price` de la
        // fila. Los dos salen de `RateResolver` para el mismo día; si divergen, alguien tarificó
        // por otra fuente.
        $this->assertSame((int) $item->unit_price, $seal->booked()->priceCents);
    }

    public function test_an_entry_has_no_seal_and_a_pack_without_a_family_is_sealed_as_such(): void
    {
        // Dos «no participa» distintos, y el sello los distingue: una ENTRADA no lleva sello (el
        // veredicto solo existe en un pack); un pack SIN familia lleva un sello que dice «sin
        // condiciones» — una afirmación, no un silencio (§21.5).
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'position' => (int) TicketType::max('position') + 1,
        ]);
        Price::create([
            'priceable_type' => $entry->getMorphClass(), 'priceable_id' => $entry->id,
            'rate_type_id' => $this->rate->id, 'amount_cents' => 1000, 'currency' => 'EUR',
        ]);
        $solo = $this->pack('Excursión de colegio', null, null, 1500, null);

        $order = app(OrderCreator::class)->createPendingOrder(User::factory()->create(), [
            ['ticket_type_id' => $entry->id, 'date' => $this->slot->date->toDateString(), 'time' => '11:00:00', 'qty' => 2],
            ['ticket_type_id' => $solo->id, 'date' => $this->slot->date->toDateString(), 'time' => '11:00:00', 'qty' => 4],
        ]);

        $entryLine = $order->items()->where('ticket_type_id', $entry->id)->firstOrFail();
        $soloLine = $order->items()->where('ticket_type_id', $solo->id)->firstOrFail();

        $this->assertNull($entryLine->age_family_seal);
        $this->assertNull($entryLine->ageFamilySeal());

        $soloSeal = $soloLine->ageFamilySeal();
        $this->assertNotNull($soloSeal);
        $this->assertNull($soloSeal->family);
        $this->assertFalse($soloSeal->participates());
        $this->assertSame((int) $solo->id, $soloSeal->bookedTypeId);
    }

    // ─── B · El PRIMER cargo usa el catálogo del día de la COMPRA (§18.4·A) ──────

    public function test_the_first_charge_uses_the_catalogue_of_the_purchase_day(): void
    {
        // Medido antes del sello: se reservaba con 7,00 € de diferencia por cabeza, el parque subía
        // Jump, y al rellenar el formulario semanas después se cobraban 12,00 €. No hacía falta
        // tocar tramos —bastaba subir un precio— y el formulario SIEMPRE se rellena más tarde.
        // Mutación: el lector deriva del catálogo vivo → 1200, rojo.
        $item = $this->reservation(3);

        $this->jump->prices()->update(['amount_cents' => 3000]); // configuración, después de vender

        $item = $this->declareAges($item, [4, 5, 8]);

        $this->assertSame(700, $this->gateDue($item), 'la diferencia del día de la compra');
        $this->assertSame(700, (int) $this->surchargeLines($item)->first()->unit_price);
    }

    // ─── C y D · Un cambio de TRAMO no mueve lo vendido en NINGUNA dirección ───────

    public function test_reordering_the_bands_creates_no_charge_from_nothing(): void
    {
        // El caso ESPEJO (§17.5): una fiesta Jump de 8 invitados de 12 años, cero cargo. El parque
        // reordena los tramos (Kids pasa a 1–12) y el cliente corrige un NOMBRE. Antes del sello:
        // 40,00 € de la nada. Mutación: el lector deriva del catálogo vivo → rojo.
        $item = $this->reservation(8, $this->jump);
        $item = $this->declareAges($item, [12, 12, 12, 12, 12, 12, 12, 12]);
        $this->assertCount(0, $this->surchargeLines($item));

        $this->jump->forceFill(['guest_age_min' => 13])->save();
        $this->kids->forceFill(['guest_age_max' => 12])->save();

        $item = $this->declareAges($item, [12, 12, 12, 12, 12, 12, 12, 12], 'Otro nombre ');

        $this->assertCount(0, $this->surchargeLines($item), 'nada se crea de la nada');
        $this->assertSame(0, $this->gateDue($item));
        $this->assertFalse($this->read($item)->mixed, 'y la etiqueta tampoco aparece');
        Notification::assertNothingSent();
    }

    public function test_widening_a_band_destroys_no_written_charge(): void
    {
        // La otra dirección (§17.5, medida el 2026-08-30): 35,00 € comunicados, el parque decide que
        // Kids llega hasta los 8, el cliente edita un nombre → antes del sello, 0,00 €. Y esa
        // dirección era la que la abstención de `#268` NO veía, porque el veredicto seguía completo.
        $item = $this->reservation(5);
        $item = $this->declareAges($item, [8, 8, 8, 8, 8]);
        $this->assertSame(3500, $this->gateDue($item));

        $this->jump->forceFill(['guest_age_min' => 9])->save();
        $this->kids->forceFill(['guest_age_max' => 8])->save();
        Notification::fake();

        $item = $this->declareAges($item, [8, 8, 8, 8, 8], 'Otro nombre ');

        $this->assertSame(3500, $this->gateDue($item), 'lo comunicado se conserva');
        $this->assertSame(5, $this->read($item)->upgradedGuests(), 'y el veredicto sigue diciendo lo mismo');
        Notification::assertNothingSent();
    }

    // ─── E · La ETIQUETA sigue al sello, no al catálogo (el «gemelo») ─────────────

    public function test_the_label_follows_the_seal_not_the_catalogue(): void
    {
        // Medido antes del sello: con los tramos devueltos a su sitio, la hoja de sala imprimía
        // «Cumpleaños Jump» SIN la etiqueta mientras el mostrador cobraba 40,00 € «por 8 invitados
        // que corresponden a Kids». Tres superficies del mismo pedido diciendo cosas distintas.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertTrue($item->isMixedParty());

        $this->jump->forceFill(['guest_age_min' => 9])->save();
        $this->kids->forceFill(['guest_age_max' => 8])->save();

        $fresh = $item->fresh(['ticketType', 'slot']);
        $this->assertTrue($fresh->isMixedParty(), 'la etiqueta sale del sello, como el cargo');
        $regimes = app(GuestAgeMixReader::class)->guestRegimes($fresh);
        $this->assertSame('Cumpleaños Jump', $regimes[2]['name']);
        $this->assertFalse($regimes[2]['own']);
    }

    // ─── F y O · Cambiar de DÍA re-precia el sello y CONSERVA sus tramos (Q2) ─────

    public function test_moving_the_party_to_another_day_reprices_the_seal_but_keeps_its_bands(): void
    {
        // `[DECIDIDO owner, 2026-08-31]` (§21.8 Q2): «mantenemos sus condiciones». Mover la fiesta de
        // día NO la somete a los tramos nuevos del catálogo; solo el precio sigue al día (`PAY-18`).
        // Aquí el parque bajó el corte de Kids a 5 DESPUÉS de la venta: los niños de 6 seguirían
        // siendo Kids… y Jump vale 30,00 € el día nuevo, así que el de 8 pasa de 7,00 a 12,00 €.
        // Mutación: re-sellar desde el catálogo al mover de día → los de 6 se vuelven Jump, rojo.
        $item = $this->declareAges($this->reservation(3), [6, 6, 8]);
        $this->assertSame(700, $this->gateDue($item), 'solo el de 8');

        $this->kids->forceFill(['guest_age_max' => 5])->save();
        $this->jump->forceFill(['guest_age_min' => 6])->save();
        $this->jump->prices()->update(['amount_cents' => 3000]);
        $otherDay = $this->slotOn(now()->addDays(27)->toDateString());

        $this->moveToDay($item, $otherDay);

        $seal = $item->fresh()->ageFamilySeal();
        $this->assertSame($otherDay->date->toDateString(), $seal->pricedOn, 'el sello es del día nuevo');
        $this->assertSame(6, $seal->member($this->kids->id)->ageMax, 'con los TRAMOS de la compra');
        $this->assertSame(7, $seal->member($this->jump->id)->ageMin);
        $this->assertSame(3000, $seal->member($this->jump->id)->priceCents, 'y los PRECIOS del día nuevo');

        $this->assertSame(1200, $this->written($item), '30,00 − 18,00 por el de 8, y solo por él');
        $this->assertSame(1, $this->read($item)->upgradedGuests(), 'los de 6 siguen siendo Kids');
    }

    // ─── G · Cambiar de PACK sella de nuevo ───────────────────────────────────────

    public function test_changing_the_pack_within_the_family_seals_anew(): void
    {
        // «Solo cambia de condiciones lo que cambia de producto»: Kids → Jump es producto nuevo, así
        // que el sello es nuevo (el reservado pasa a ser Jump). Los de 4 y 5 corresponden ahora a un
        // pack MÁS BARATO: la línea de suplemento se retira y — desde la T4 (§20/§24) — el
        // DESCUENTO se escribe de verdad, absorbido por el cargo de puerta que la propia edición
        // creó (+21,00 € del cambio a Jump). Neto de puerta: 21,00 − 14,00 = 7,00 €.
        // Mutación: `edit()` sin re-sellar → el sello sigue diciendo Kids y no casa con la fila.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertSame(700, $this->gateDue($item));

        $this->editProduct($item, $this->jump);

        $seal = $item->fresh()->ageFamilySeal();
        $this->assertSame((int) $this->jump->id, $seal->bookedTypeId);
        $this->assertFalse($this->read($item)->staleSeal, 'el sello casa con la fila nueva');
        $written = app(MixedPartySurcharge::class)->written($item->fresh(['ticketType', 'slot', 'order', 'children']));
        $this->assertSame(0, $written['charge_cents'], 'nadie está por encima de Jump');
        $this->assertSame(1400, $written['credit_cents'], 'los dos Kids se descuentan: 2 × 7,00');
        $this->assertSame(700, $this->gateDue($item), '21,00 del cambio − 14,00 del descuento');
        $this->assertTrue($this->read($item)->hasSavings(), 'dos invitados corresponden a Kids');
    }

    public function test_changing_the_pack_to_one_without_a_family_retires_the_surcharge(): void
    {
        // El sello nuevo dice «sin condiciones» — una AFIRMACIÓN— y por eso gobierna: la línea del
        // suplemento se cancela. Antes del sello ese «no aplica» se leía como silencio y la línea
        // sobrevivía HUÉRFANA cobrando una diferencia con un pack que la fiesta ya no tiene.
        // Mutación: `derivationGoverns` ignorando `sealed` → la línea sobrevive, rojo.
        $solo = $this->pack('Excursión de colegio', null, null, 1800, null);
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertCount(1, $this->surchargeLines($item));

        $this->editProduct($item, $solo);

        $this->assertNull($item->fresh()->ageFamilySeal()->family);
        $this->assertCount(0, $this->surchargeLines($item->fresh()), 'sin familia no hay suplemento');
        $this->assertSame(0, $this->written($item));
    }

    // ─── H · Cambiar la CANTIDAD no toca el sello ─────────────────────────────────

    public function test_changing_only_the_quantity_leaves_the_seal_untouched(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $before = $item->fresh()->age_family_seal;

        $this->editProduct($item, $this->kids, qty: 4);

        $this->assertSame($before, $item->fresh()->age_family_seal, 'ni un byte: no cambió de producto');
    }

    // ─── I · Un sello que NO casa con la fila no gobierna nada ────────────────────

    public function test_a_seal_that_no_longer_matches_the_row_governs_nothing(): void
    {
        // Ningún camino del producto lo produce: es un `UPDATE` a mano, o un camino nuevo que nadie
        // enganchó al sellador. Derivar de él sería poner precio a condiciones que no son las de la
        // fila, así que el veredicto CALLA, lo escrito se conserva y la ficha lo enseña en rojo.
        // Mutación: quitar la comprobación de `booked_type_id`/`priced_on` → deriva igual, rojo.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertSame(700, $this->gateDue($item));

        OrderItem::whereKey($item->id)->update(['ticket_type_id' => $this->jump->id]);
        Notification::fake();

        $mix = $this->read($item);
        $this->assertTrue($mix->staleSeal);
        $this->assertFalse($mix->applies);

        $item = $this->declareAges($item, [4, 5, 6]);
        $this->assertSame(700, $this->gateDue($item), 'ni se retira ni se recalcula');
        $this->assertCount(1, $this->surchargeLines($item));
        Notification::assertNothingSent();
    }

    // ─── J · El olvido borra los datos del cliente, no las condiciones del parque ──

    public function test_anonymising_the_customer_keeps_the_seal(): void
    {
        // `RGPD-01` vacía `guest_data` y `event_data`. El sello no lleva PII —ids, nombres de
        // producto, tramos y precios— y es lo que sostiene un cargo que el parque sigue teniendo
        // derecho a cobrar. Mutación: `anonymize()` vaciando `age_family_seal` → rojo.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $user = $item->order->user;

        $this->assertTrue($user->anonymize());

        $fresh = $item->fresh();
        $this->assertNull($fresh->guest_data, 'los datos del cliente sí se borran');
        $this->assertNotNull($fresh->ageFamilySeal(), 'las condiciones del parque se conservan');
        $this->assertSame('cumple', $fresh->ageFamilySeal()->family);
    }

    // ─── N · Un día SIN TARIFA no retira el suplemento (el hueco de §21.8) ────────

    public function test_a_day_without_a_tariff_does_not_retire_the_surcharge(): void
    {
        // El editor no bloquea mover la fiesta a un día en que un pack no tiene precio
        // (`validateNewSlot` no mira precios). Ahí la diferencia no se puede calcular, y la lógica
        // anterior lo leía como «la diferencia es cero» y CANCELABA lo escrito. No poder tarificar
        // es una AUSENCIA, no una corrección (`#268`): ni retira ni crea, y el veredicto lo dice.
        // Mutación: `derivationGoverns` sin la condición «tarificable» → la línea se cancela, rojo.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertSame(700, $this->gateDue($item));

        Price::where('priceable_type', $this->jump->getMorphClass())->where('priceable_id', $this->jump->id)->delete();
        $otherDay = $this->slotOn(now()->addDays(27)->toDateString());
        Notification::fake();

        $this->moveToDay($item, $otherDay);

        $this->assertNull($item->fresh()->ageFamilySeal()->member($this->jump->id)->priceCents, 'sellado como «sin precio»');
        $mix = $this->read($item);
        $this->assertTrue($mix->mixed);
        $this->assertNull($mix->surchargeCents, 'no se puede tarificar');
        $this->assertSame(700, $this->written($item), 'y lo escrito se conserva');
        $this->assertCount(1, $this->surchargeLines($item->fresh()));
        Notification::assertNotSentTo($item->order->user, MixedPartySurchargeChanged::class);
    }

    // ─── El VALOR: lo que se puede y no se puede leer como sello ──────────────────

    public function test_a_document_without_the_two_facts_of_the_receipt_is_not_a_seal(): void
    {
        // Sin `booked_type_id` y `priced_on` no hay contra qué validarlo: no se «repara», se ignora.
        $this->assertNull(AgeFamilySeal::fromArray(null));
        $this->assertNull(AgeFamilySeal::fromArray('garbage'));
        $this->assertNull(AgeFamilySeal::fromArray(['family' => 'cumple', 'members' => []]));
        $this->assertNull(AgeFamilySeal::fromArray(['booked_type_id' => 3, 'priced_on' => '']));

        $seal = AgeFamilySeal::fromArray([
            'v' => 1, 'family' => 'cumple', 'booked_type_id' => '3', 'priced_on' => '2026-09-15',
            'members' => [
                ['type_id' => 9, 'name' => ['es' => 'Jump'], 'age_min' => 7, 'age_max' => null, 'price_cents' => 2500],
                ['type_id' => 3, 'name' => ['es' => 'Kids'], 'age_min' => 1, 'age_max' => 6, 'price_cents' => 1800],
                ['name' => ['es' => 'sin producto']], // un miembro sin `type_id` no describe nada
            ],
        ]);
        $this->assertNotNull($seal);
        $this->assertSame(3, $seal->bookedTypeId);
        $this->assertSame([3, 9], array_map(fn (SealedRegime $m): int => $m->typeId, $seal->members), 'ordenados por el inicio del tramo');
        $this->assertSame(9, $seal->regimeFor(7)->typeId, 'el 7 es Jump: extremos incluidos');
        $this->assertSame(3, $seal->regimeFor(6)->typeId);
        $this->assertNull($seal->regimeFor(0), 'nadie cubre el 0');
        $this->assertSame(1, $seal->toArray()['v']);
    }
}
