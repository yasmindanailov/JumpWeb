<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\PriceTier;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\CartPricer;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `#324` — PRECIO POR TRAMO DE CANTIDAD (`docs/specs/precio-por-tramo.md`).
 *
 * «Cuantos más vienen, menos cuesta cada uno.» Lo motivan las excursiones de colegio, con el cuadro
 * real del cliente: 30 → 15 €, 70 → 13 €, 100 → 12 € entre semana, y 17/15/14 en finde y festivos.
 *
 * Lo que fija, en orden de importancia:
 *
 *  1. **UNIFORME, no escalonado** (`[DECIDIDO owner]`, preguntado con los dos números delante): 70
 *     personas son **910 €**, no 970 €. Es la aserción que más dinero mueve del fichero.
 *  2. **Presupuesto y checkout dan el MISMO número.** Son dos implementaciones de la misma regla
 *     —`CartPricer` lee la relación precargada y `OrderCreator` pregunta a `RateResolver`— y ésa es
 *     exactamente la forma en que un precio mostrado deja de ser el cobrado.
 *  3. **Un producto SIN tramos no cambia de conducta.** Es la propiedad que hace segura toda la
 *     tanda, igual que `null = hereda` en el horario por zona.
 *  4. **No se pueden tener tramos de cantidad Y familia de edades**, en las DOS direcciones.
 */
class PriceTierTest extends TestCase
{
    use RefreshDatabase;

    private RateType $normal;

    private RateType $special;

    private TicketType $excursion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normal = RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'is_special' => false, 'is_active' => true, 'priority' => 0]);
        $this->special = RateType::create(['key' => RateType::KEY_SPECIAL, 'label' => ['es' => 'Especial'], 'is_special' => true, 'is_active' => true, 'priority' => 10, 'weekdays' => [0, 5, 6]]);

        $zone = Zone::create(['slug' => 'excursiones', 'name' => ['es' => 'Excursiones']]);

        $this->excursion = TicketType::create([
            'name' => ['es' => 'Excursión 2 h'],
            'zone_id' => $zone->id,
            'type' => TicketType::TYPE_PACK,
            'duration_min' => 120,
            'min_qty' => 30,
            'max_qty' => 100,
            'seats_per_unit' => 1,
            'is_active' => true,
            'is_sellable' => true,
        ]);

        // El precio «de siempre», que con tramos declarados no debería ganar nunca.
        Price::create(['priceable_type' => $this->excursion->getMorphClass(), 'priceable_id' => $this->excursion->id, 'rate_type_id' => $this->normal->id, 'amount_cents' => 9900]);

        // El cuadro REAL del cliente.
        foreach ([[30, 1500], [70, 1300], [100, 1200]] as [$min, $cents]) {
            PriceTier::create(['ticket_type_id' => $this->excursion->id, 'rate_type_id' => $this->normal->id, 'min_qty' => $min, 'amount_cents' => $cents]);
        }
        foreach ([[30, 1700], [70, 1500], [100, 1400]] as [$min, $cents]) {
            PriceTier::create(['ticket_type_id' => $this->excursion->id, 'rate_type_id' => $this->special->id, 'min_qty' => $min, 'amount_cents' => $cents]);
        }

        $this->excursion->refresh()->load('prices', 'priceTiers');
    }

    private function weekday(): Carbon
    {
        $day = Carbon::today()->addWeek()->startOfWeek();   // lunes

        return $day;
    }

    private function weekend(): Carbon
    {
        return $this->weekday()->copy()->addDays(5);        // sábado
    }

    // ─── 1 · la aritmética que más dinero mueve ──────────────────────────────────────────────────

    /**
     * ⚠️ **El precio es UNIFORME, no escalonado.** 70 personas a 13 € son 910 €. Escalonado
     * (30×15 + 40×13) darían 970 €: 60 € de diferencia en UN grupo, y la elección fue del owner con
     * los dos números delante.
     */
    public function test_the_tier_price_is_uniform_and_not_stepped(): void
    {
        $rates = app(RateResolver::class);
        $unit = $rates->priceCents($this->excursion, $this->weekday(), 70);

        $this->assertSame(1300, $unit);
        $this->assertSame(910_00, 70 * $unit, '70 personas × 13 € = 910 €, no 970 €');
    }

    /** El cuadro entero del cliente, en sus dos tarifas. */
    public function test_the_clients_full_price_table(): void
    {
        $rates = app(RateResolver::class);

        foreach ([[30, 1500], [45, 1500], [69, 1500], [70, 1300], [99, 1300], [100, 1200]] as [$qty, $cents]) {
            $this->assertSame($cents, $rates->priceCents($this->excursion, $this->weekday(), $qty), "entre semana, {$qty} personas");
        }

        foreach ([[30, 1700], [70, 1500], [100, 1400]] as [$qty, $cents]) {
            $this->assertSame($cents, $rates->priceCents($this->excursion, $this->weekend(), $qty), "en finde, {$qty} personas");
        }
    }

    /**
     * ❗❗ **`#327` CAMBIA LA RESPUESTA DE ESTE CASO, y el caso no era comprobable cuando se escribió.**
     *
     * Hasta hoy decía que por debajo del primer tramo manda el precio de siempre, y su propio
     * comentario reconocía que «el mínimo del producto lo corta antes»: 29 personas en un pack de
     * mínimo 30 **no se podían vender**, así que la aserción describía una aritmética sin sujeto.
     * Desde `#327` el operador SÍ puede vender por debajo del mínimo, y entonces la pregunta es de
     * dinero real: `[DECIDIDO owner, 2026-09-01]` **se cobra el primer tramo**, no el precio base.
     *
     * La propiedad que lo resume, y la razón de que se asevere COMPARANDO en vez de con un literal:
     * **vender por debajo del mínimo nunca sale más barato por cabeza que vender justo en el
     * mínimo.** Escrito así, la guarda sigue valiendo si el cuadro de precios del cliente cambia.
     */
    public function test_below_the_products_minimum_the_first_tier_wins(): void
    {
        $rates = app(RateResolver::class);
        $atMinimum = $rates->priceCents($this->excursion, $this->weekday(), 30);

        $this->assertSame(1500, $atMinimum);

        foreach ([1, 20, 29] as $qty) {
            $this->assertSame($atMinimum, $rates->priceCents($this->excursion, $this->weekday(), $qty),
                "vender {$qty} no puede salir más barato por cabeza que vender el mínimo de 30");
        }

        // Y la misma regla por el otro camino de resolución (`CartPricer` lee la relación precargada).
        $this->assertSame($atMinimum, $this->excursion->priceCentsForRate($this->normal, 20));
    }

    /**
     * ⚠️⚠️ **EL CONTROL, y es el que impide regalar el descuento de volumen.**
     *
     * El suelo de la escala es el mínimo CONTRATABLE del producto, que en una entrada vale 1. Si la
     * regla se hubiera escrito dentro de `PriceTier::resolve()` —«si ningún tramo cubre, coge el más
     * pequeño»— toda entrada comprada por debajo de su primer tramo pasaría a pagar el precio de
     * volumen: aquí, 5 unidades cobrarían 8 € en vez de sus 10 €. **Nada fallaría**; solo se
     * ingresaría menos.
     */
    public function test_an_entry_below_its_first_tier_still_pays_the_plain_price(): void
    {
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada con descuento por volumen'],
            'zone_id' => $this->excursion->zone_id,
            'type' => TicketType::TYPE_ENTRY,
            'seats_per_unit' => 1,
            'is_active' => true,
            'is_sellable' => true,
        ]);
        Price::create(['priceable_type' => $entry->getMorphClass(), 'priceable_id' => $entry->id, 'rate_type_id' => $this->normal->id, 'amount_cents' => 1000]);
        PriceTier::create(['ticket_type_id' => $entry->id, 'rate_type_id' => $this->normal->id, 'min_qty' => 10, 'amount_cents' => 800]);
        $entry->refresh()->load('prices', 'priceTiers');

        $rates = app(RateResolver::class);

        $this->assertSame(1000, $rates->priceCents($entry, $this->weekday(), 5), 'por debajo de su tramo, el precio de siempre');
        $this->assertSame(1000, $rates->priceCents($entry, $this->weekday(), 9));
        $this->assertSame(800, $rates->priceCents($entry, $this->weekday(), 10), 'a partir de 10, el tramo');
    }

    // ─── 2 · presupuesto y checkout NO pueden divergir ───────────────────────────────────────────

    /**
     * ⚠️⚠️ `CartPricer` lee la relación PRECARGADA y `OrderCreator` pregunta a `RateResolver`: son dos
     * caminos distintos hacia la misma regla, a propósito (el primero no consulta por línea). Esta
     * guarda es la que impide que se separen — el defecto sería que lo presupuestado no es lo cobrado.
     */
    public function test_the_quote_and_the_checkout_resolve_the_same_unit_price(): void
    {
        $date = $this->weekday()->toDateString();

        foreach ([30, 70, 100] as $qty) {
            $quote = app(CartPricer::class)->quote([[
                'ticket_type_id' => $this->excursion->id,
                'date' => $date,
                'time' => '09:00:00',
                'qty' => $qty,
                'addons' => [],
                'event_data' => [],
            ]]);

            $this->assertCount(1, $quote->lines, "la línea de {$qty} se tarifica");
            $this->assertSame(
                app(RateResolver::class)->priceCents($this->excursion, Carbon::parse($date), $qty),
                $quote->lines[0]->unitPriceCents,
                "presupuesto y checkout tienen que coincidir con {$qty} personas",
            );
        }
    }

    // ─── 3 · un producto SIN tramos no cambia ────────────────────────────────────────────────────

    public function test_a_product_without_tiers_keeps_the_price_it_always_had(): void
    {
        $plain = TicketType::create([
            'name' => ['es' => 'Entrada 1 h'],
            'zone_id' => $this->excursion->zone_id,
            'type' => TicketType::TYPE_ENTRY,
            'seats_per_unit' => 1,
            'is_active' => true,
            'is_sellable' => true,
        ]);
        Price::create(['priceable_type' => $plain->getMorphClass(), 'priceable_id' => $plain->id, 'rate_type_id' => $this->normal->id, 'amount_cents' => 1200]);

        $rates = app(RateResolver::class);
        foreach ([1, 30, 100, 500] as $qty) {
            $this->assertSame(1200, $rates->priceCents($plain, $this->weekday(), $qty), "sin tramos, {$qty} unidades cuestan lo mismo");
        }
    }

    // ─── 4 · el «desde X €» ──────────────────────────────────────────────────────────────────────

    /**
     * `[DECIDIDO owner]`: el más BARATO — «desde 12 €», el del tramo de 100. Se preguntó con la
     * alternativa delante (el del tramo mínimo vendible, 15 €) y el owner eligió éste. **No es un
     * descuido**: está en la spec §7·2, y esta guarda existe para que nadie lo «corrija».
     */
    public function test_the_landing_shows_the_cheapest_tier(): void
    {
        $this->assertSame(1200, $this->excursion->displayPriceCents());
    }

    /** Y el calendario, que tampoco conoce la cantidad, el más barato DE ESA TARIFA. */
    public function test_the_calendar_shows_the_cheapest_tier_of_each_rate(): void
    {
        $this->assertSame(1200, $this->excursion->displayPriceCentsForRate($this->normal));
        $this->assertSame(1400, $this->excursion->displayPriceCentsForRate($this->special));
    }

    // ─── 5 · la puerta cerrada con el sello, en las DOS direcciones ──────────────────────────────

    /**
     * ⚠️ Hacen falta las dos: una sola deja la puerta entreabierta por el otro lado y no lo ve nadie
     * — la lección de `#301`, donde un arreglo sobrevivió a la guarda que lo protegía.
     */
    public function test_a_product_with_tiers_cannot_declare_an_age_family(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->excursion->update(['guest_age_family' => 'cumple', 'guest_age_min' => 4, 'guest_age_max' => 7]);
    }

    /**
     * ⚠️ `#324` — los tramos se editan DESDE EL PANEL. Esta guarda existe porque la tanda A (`#322`)
     * estuvo a punto de entregarse con las columnas creadas y **sin formulario que las expusiera**:
     * solo se podían tocar por SQL, que es justo lo que el principio data-driven prohíbe.
     *
     * Se asevera por CONDUCTA —el campo existe en el formulario vivo— y no recorriendo el árbol de
     * componentes: la primera versión de este caso lo recorría y reventaba con un `TypeError` de la
     * propia API de Filament, o sea que medía la versión de la librería y no el producto.
     */
    public function test_the_catalog_form_exposes_the_tiers(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin'])->id]);

        Livewire::actingAs($admin)
            ->test(CreateCatalog::class)
            ->assertFormFieldExists('priceTiers');
    }

    public function test_a_product_with_an_age_family_cannot_get_tiers(): void
    {
        $party = TicketType::create([
            'name' => ['es' => 'Cumple Kids'],
            'zone_id' => $this->excursion->zone_id,
            'type' => TicketType::TYPE_PACK,
            'seats_per_unit' => 1,
            'guest_age_family' => 'cumple',
            'guest_age_min' => 4,
            'guest_age_max' => 7,
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);

        PriceTier::create(['ticket_type_id' => $party->id, 'rate_type_id' => $this->normal->id, 'min_qty' => 10, 'amount_cents' => 1000]);
    }
}
