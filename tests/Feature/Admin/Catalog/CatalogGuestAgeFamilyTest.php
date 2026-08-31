<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\MixedPartyBandImpact;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * La PUERTA de la familia y el tramo de edad (`docs/specs/cumple-mixto.md` §9·1).
 *
 * Es la configuración de la que sale el veredicto de fiesta MIXTA y, tras él, un cobro. Por eso se
 * valida al ESCRIBIR y no al leer: `GuestAgeMixReader` resuelve un solape de forma determinista
 * para no romperse, pero la respuesta correcta a «dos packs cubren la edad 7» no es elegir uno —es
 * no dejar entrar el dato. Misma doctrina que `AFORO-07`.
 *
 * ⚠️ Crear y editar tienen que decir LO MISMO: la validación vive una sola vez en el trait
 * (`InteractsWithCatalogForm::normalizeGuestAgeFields`) y estos casos la conducen por las dos
 * páginas, porque una puerta que solo cierra en el alta no es una puerta.
 */
class CatalogGuestAgeFamilyTest extends TestCase
{
    use RefreshDatabase;

    private int $sold = 0;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'], 'accent' => 'cumpleanos',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function pack(string $name, ?string $family, ?int $min, ?int $max): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'tax_rate' => 21,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'guest_age_family' => $family, 'guest_age_min' => $min, 'guest_age_max' => $max,
        ]);
    }

    /** Ejecuta la puerta como lo hace el guardado, con o sin producto en edición. */
    private function normalize(array $data, bool $isPack = true, ?TicketType $record = null): array
    {
        $page = $record !== null ? new EditCatalog : new CreateCatalog;
        if ($record !== null) {
            $page->record = $record;
        }

        $method = new ReflectionMethod($page::class, 'normalizeGuestAgeFields');
        $method->setAccessible(true);

        return $method->invoke($page, $data, $isPack);
    }

    /** Ejecuta el saneo de esquemas del panel, que es la puerta por la que entra un `type`. */
    private function sanitizeSchema(array $rows, bool $perGuest): array
    {
        $page = new CreateCatalog;
        $method = new ReflectionMethod($page::class, $perGuest ? 'sanitizeGuestFields' : 'sanitizeEventFields');
        $method->setAccessible(true);

        return $method->invoke($page, $rows);
    }

    // ─── La EDAD solo existe en los datos POR INVITADO (§17.2·4) ────────────

    public function test_the_age_type_is_refused_in_the_event_schema(): void
    {
        // ⚠️⚠️ El `Select` del panel nunca ofrece `age` aquí, pero eso NO es una defensa: es la
        // regla 12 de este proyecto —no se confía en que el formulario oculte lo que no debe
        // llegar—. Medido antes del arreglo: forzado, persistía, y salía por
        // `GET /catalog/products/{id}` contra el `enum` cerrado de su propio contrato.
        $out = $this->sanitizeSchema([[
            'key' => 'edad_fiesta', 'type' => TicketType::FIELD_TYPE_AGE,
            'label' => ['es' => 'Edad'], 'required' => false, 'stage' => 'booking',
        ]], perGuest: false);

        $this->assertSame(TicketType::FIELD_TYPE_TEXT, $out[0]['type'], 'la edad no puede colarse en los datos del evento');
    }

    public function test_the_age_type_is_kept_in_the_guest_schema(): void
    {
        // El CONTROL: sin él, lo de arriba se cumpliría degradando el tipo en los DOS esquemas, y el
        // campo del que sale el suplemento dejaría de existir sin que nada se pusiera rojo.
        $out = $this->sanitizeSchema([[
            'key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE,
            'label' => ['es' => 'Edad'], 'required' => true,
        ]], perGuest: true);

        $this->assertSame(TicketType::FIELD_TYPE_AGE, $out[0]['type']);
    }

    public function test_the_model_also_refuses_it_when_reading_a_row_written_by_hand(): void
    {
        // La segunda puerta: una fila escrita directamente en la base —una semilla, una migración,
        // un `update()` de consola— no pasa por el panel. El modelo la sanea al leerla.
        $pack = $this->pack('Con edad en el evento', null, null, null);
        $pack->forceFill(['event_fields' => [
            ['key' => 'edad_fiesta', 'type' => TicketType::FIELD_TYPE_AGE, 'label' => ['es' => 'Edad'], 'stage' => 'booking'],
        ]])->save();

        $this->assertSame(TicketType::FIELD_TYPE_TEXT, $pack->fresh()->eventFields()[0]['type']);
    }

    // ─── Normalización ───────────────────────────────────────────────────────

    public function test_the_family_is_normalised_on_write(): void
    {
        // Se compara por IGUALDAD contra la de sus hermanos: si el valor guardado dependiera de
        // cómo lo escribió el operador, la conducta dependería además del motor de BD (MySQL
        // cotejaría «Cumple» y «cumple» como iguales; SQLite, donde corre la suite, no).
        $out = $this->normalize(['guest_age_family' => '  Cumple  ', 'guest_age_min' => 1, 'guest_age_max' => 6]);

        $this->assertSame('cumple', $out['guest_age_family']);
    }

    public function test_an_empty_family_is_null_not_an_empty_string(): void
    {
        $out = $this->normalize(['guest_age_family' => '', 'guest_age_min' => null, 'guest_age_max' => null]);

        $this->assertNull($out['guest_age_family'], 'vacío = este producto no distingue edades');
    }

    public function test_an_empty_bound_is_null_not_zero(): void
    {
        // «Sin tope por abajo» y «desde los 0 años» son cosas distintas, y el formulario manda ''.
        $out = $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => '', 'guest_age_max' => '6']);

        $this->assertNull($out['guest_age_min']);
        $this->assertSame(6, $out['guest_age_max']);
    }

    public function test_outside_a_pack_the_three_columns_are_wiped(): void
    {
        // Defensa en profundidad (regla 12): la sección está oculta para no-packs, pero un payload
        // manipulado no debe dejar configuración viva donde el veredicto nunca la mirará.
        $out = $this->normalize(
            ['guest_age_family' => 'cumple', 'guest_age_min' => 1, 'guest_age_max' => 6],
            isPack: false,
        );

        $this->assertNull($out['guest_age_family']);
        $this->assertNull($out['guest_age_min']);
        $this->assertNull($out['guest_age_max']);
    }

    // ─── Bloqueos ────────────────────────────────────────────────────────────

    public function test_a_family_without_a_range_is_blocked(): void
    {
        $this->expectException(Halt::class);
        $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => null, 'guest_age_max' => null]);
    }

    public function test_an_inverted_range_is_blocked(): void
    {
        $this->expectException(Halt::class);
        $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 10, 'guest_age_max' => 4]);
    }

    public function test_a_range_that_overlaps_a_sibling_is_blocked(): void
    {
        $this->pack('Cumpleaños Kids', 'cumple', 1, 6);

        $this->expectException(Halt::class);
        // 5–12 pisa al 1–6: un niño de 5 tendría dos packs posibles.
        $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 5, 'guest_age_max' => 12]);
    }

    public function test_an_open_bound_overlaps_by_numbers_not_by_special_cases(): void
    {
        $this->pack('Cumpleaños Kids', 'cumple', null, 6); // «hasta 6»

        $this->expectException(Halt::class);
        // «de 6 en adelante» pisa al «hasta 6» en el 6 exacto — los extremos son INCLUSIVOS.
        $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 6, 'guest_age_max' => null]);
    }

    // ─── Lo que SÍ tiene que pasar ───────────────────────────────────────────

    public function test_contiguous_ranges_are_allowed(): void
    {
        $this->pack('Cumpleaños Kids', 'cumple', 1, 6);

        $out = $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 7, 'guest_age_max' => 99]);

        $this->assertSame('cumple', $out['guest_age_family'], '1–6 y 7–99 no se tocan: es el caso normal');
    }

    public function test_another_family_is_not_a_conflict(): void
    {
        $this->pack('Campamento', 'campamento', 1, 12);

        $out = $this->normalize(['guest_age_family' => 'cumple', 'guest_age_min' => 1, 'guest_age_max' => 6]);

        $this->assertSame(1, $out['guest_age_min']);
    }

    public function test_editing_a_pack_does_not_collide_with_itself(): void
    {
        $kids = $this->pack('Cumpleaños Kids', 'cumple', 1, 6);

        // Sin excluirse a sí mismo, guardar el pack sin tocar su tramo sería imposible.
        $out = $this->normalize(
            ['guest_age_family' => 'cumple', 'guest_age_min' => 1, 'guest_age_max' => 6],
            record: $kids,
        );

        $this->assertSame(6, $out['guest_age_max']);
    }

    // ─── El AVISO antes de mover un tramo con fiestas vendidas (§17.8) ──────────

    /** Una fiesta PAGADA y por celebrar del pack dado, con las edades ya declaradas. */
    private function soldParty(TicketType $pack, array $ages): OrderItem
    {
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(20)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-BI'.str_pad((string) ++$this->sold, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => 0, 'tax' => 0, 'total' => 0, 'currency' => 'EUR',
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => count($ages), 'unit_price' => 1100, 'seats' => count($ages),
        ]);

        $rows = [];
        foreach ($ages as $i => $age) {
            $rows[] = ['name' => 'Invitado '.($i + 1), 'edad' => (string) $age];
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    /** Kids 1-6 a 11,00 € y Jump 7-99 a 15,00 €, que es el catálogo real de la instalación. */
    private function twoPacksWithPrices(): array
    {
        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $mk = function (string $name, int $min, int $max, int $cents) use ($rate): TicketType {
            $pack = $this->pack($name, 'cumple', $min, $max);
            $pack->forceFill(['guest_fields' => [
                ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']],
            ]])->save();
            Price::create([
                'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
                'rate_type_id' => $rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
            ]);

            return $pack->fresh();
        };

        return [$mk('Cumpleaños Kids', 1, 6, 1100), $mk('Cumpleaños Jump', 7, 99, 1500)];
    }

    public function test_moving_the_cut_warns_with_the_money_it_would_create(): void
    {
        // ⚠️ **El camino REAL son DOS guardados, y el test lo aprendió por las malas.** Bajar el
        // corte de Kids a 1–5 no mueve un euro: los de 6 caen en un HUECO —no los cubre ningún
        // pack— y la abstención de `#268` protege lo escrito precisamente ahí. El cargo nace en el
        // segundo paso, al ENSANCHAR Jump hasta el 6, que es cuando esos invitados tienen a dónde ir.
        // Medido sobre el catálogo real: 8 × (15,00 − 11,00) = 32,00 € que nadie comunicó.
        [$kids, $jump] = $this->twoPacksWithPrices();
        $kids->forceFill(['guest_age_max' => 5])->save();
        $this->soldParty($kids->fresh(), [6, 6, 6, 6, 6, 6, 6, 6]);

        $impacto = app(MixedPartyBandImpact::class)->of($jump->fresh(), 'cumple', 6, 99);

        $this->assertSame(1, $impacto['reservations']);
        $this->assertSame(3200, $impacto['created_cents']);
        $this->assertSame(0, $impacto['removed_cents']);
    }

    public function test_narrowing_alone_moves_nobody_because_the_guests_fall_into_a_gap(): void
    {
        // El control del de arriba, y la razón por la que el aviso no interrumpe en el primer paso:
        // sin pack que los cubra, el veredicto queda INCOMPLETO y lo escrito no se toca.
        [$kids] = $this->twoPacksWithPrices();
        $this->soldParty($kids, [6, 6, 6]);

        $this->assertSame(0, app(MixedPartyBandImpact::class)->of($kids->fresh(), 'cumple', 1, 5)['reservations']);
    }

    public function test_raising_the_cut_warns_with_the_money_it_would_remove(): void
    {
        // La dirección contraria, que también hay que enseñar: el cargo escrito DESAPARECERÍA.
        [$kids] = $this->twoPacksWithPrices();
        $this->soldParty($kids, [8, 8, 4]);          // dos de 8 → Jump: 2 × 4,00 € escritos

        $impacto = app(MixedPartyBandImpact::class)->of($kids->fresh(), 'cumple', 1, 8);

        $this->assertSame(1, $impacto['reservations']);
        $this->assertSame(0, $impacto['created_cents']);
        $this->assertSame(800, $impacto['removed_cents']);
    }

    public function test_a_finished_party_is_not_counted(): void
    {
        // Su cargo ya se da por resuelto y ninguna reconciliación la va a tocar: contarla asustaría
        // al operador con un número que no puede pasar.
        //
        // ⚠️⚠️ **Este caso nació CIEGO y lo dijo la mutación.** Estaba escrito sobre un escenario que
        // no movía dinero de todas formas, así que quitar el filtro de finalizadas lo dejaba en
        // verde: no probaba el filtro, probaba que no había nada que filtrar. Ahora usa EL MISMO
        // escenario que el caso de arriba —el que sí crea 32,00 €— con la franja en el pasado.
        [$kids, $jump] = $this->twoPacksWithPrices();
        $kids->forceFill(['guest_age_max' => 5])->save();
        $item = $this->soldParty($kids->fresh(), [6, 6, 6, 6, 6, 6, 6, 6]);
        $item->slot->forceFill(['date' => now()->subDays(3)->toDateString()])->save();

        $impacto = app(MixedPartyBandImpact::class)->of($jump->fresh(), 'cumple', 6, 99);

        $this->assertSame(0, $impacto['reservations'], 'una fiesta ya celebrada no la mueve nadie');
        $this->assertSame(0, $impacto['created_cents']);
    }

    public function test_a_change_that_moves_nobody_does_not_warn(): void
    {
        // El control: sin él, el aviso se cumpliría interrumpiendo SIEMPRE, que es la forma más
        // rápida de que un operador aprenda a darle a guardar dos veces sin leer.
        [$kids] = $this->twoPacksWithPrices();
        $this->soldParty($kids, [3, 4, 5]);          // ninguno se mueve al estrechar hasta 5

        $impacto = app(MixedPartyBandImpact::class)->of($kids->fresh(), 'cumple', 1, 5);

        $this->assertSame(0, $impacto['reservations']);
        $this->assertFalse(app(MixedPartyBandImpact::class)->isWorthWarning($impacto));
    }

    public function test_retiring_the_family_also_counts_as_impact(): void
    {
        // Dejar al pack sin familia mueve el dinero de sus fiestas igual que estrechar su tramo, y
        // ese camino salía por la puerta de atrás: la validación devolvía antes de llegar al aviso.
        [$kids] = $this->twoPacksWithPrices();
        $this->soldParty($kids, [8, 4]);             // 1 × 4,00 € escrito

        $impacto = app(MixedPartyBandImpact::class)->of($kids->fresh(), null, null, null);

        $this->assertSame(1, $impacto['reservations']);
        $this->assertSame(400, $impacto['removed_cents']);
    }

    public function test_the_first_save_is_stopped_and_the_second_goes_through(): void
    {
        // No bloquea: interrumpe UNA vez. Y la firma es lo que impide que «volver a guardar» sea un
        // cheque en blanco — si el operador cambia los números, se le vuelve a avisar.
        [$kids, $jump] = $this->twoPacksWithPrices();
        $kids->forceFill(['guest_age_max' => 5])->save();
        $this->soldParty($kids->fresh(), [6, 6, 6, 6, 6, 6, 6, 6]);

        $page = new EditCatalog;
        $page->record = $jump->fresh();
        $method = new ReflectionMethod(EditCatalog::class, 'normalizeGuestAgeFields');
        $method->setAccessible(true);
        $data = ['guest_age_family' => 'cumple', 'guest_age_min' => 6, 'guest_age_max' => 99];

        try {
            $method->invoke($page, $data, true);
            $this->fail('el primer guardado tenía que pararse para enseñar el impacto');
        } catch (Halt) {
            // esperado
        }

        $this->assertNotSame('', $page->ackBandImpact, 'no se ha recordado el aviso enseñado');

        // El segundo, con lo MISMO, pasa.
        $out = $method->invoke($page, $data, true);
        $this->assertSame(6, $out['guest_age_min']);

        // Pero cambiar los números vuelve a avisar: la confirmación era de ESE cambio.
        $this->expectException(Halt::class);
        // ⚠️ Un tramo DISTINTO del confirmado y distinto del que ya tiene guardado: si se pasara el
        // que ya tiene, no habría cambio que avisar y este caso pasaría en verde sin probar la firma.
        $method->invoke($page, ['guest_age_family' => 'cumple', 'guest_age_min' => 6, 'guest_age_max' => 98], true);
    }
}
