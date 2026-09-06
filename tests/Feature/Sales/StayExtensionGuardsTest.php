<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * **Las guardas del interruptor que EXTIENDE la estancia** (`specs/hora-extra.md` §10.3.1).
 *
 * Son el espejo exacto de las del OCUPANTE, y la simetría no es estética: las dos prohibiciones son
 * la misma idea vista desde sus dos lados — «quedarse más» significa cosas distintas en una entrada
 * (personas que se quedan) y en una fiesta (la sala sigue ocupada), y por eso un ocupante **jamás**
 * cuelga de un pack y un extensor **solo** cuelga de un pack.
 *
 * ⚠️ **Cada regla se comprueba en las DOS direcciones** (la lección de `#324`): al enganchar y al
 * encender el interruptor sobre un enganche que ya existe. Una sola dirección deja en pie
 * exactamente la configuración que la otra rechaza.
 */
class StayExtensionGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    private TicketType $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
    }

    // ─── El interruptor ──────────────────────────────────────────────────────────────

    public function test_only_an_addon_can_extend(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TicketType::create([
            'name' => ['es' => 'Pack raro'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'extends_parent_stay' => true, 'is_active' => true, 'position' => 3,
        ]);
    }

    public function test_extending_without_saying_how_much_is_rejected(): void
    {
        // «Extiende y no dice cuánto» alargaría CERO: se habría vendido una hora extra que no ocupa
        // nada y la sala se sobrevendería sin que fallara nada.
        $this->expectException(InvalidArgumentException::class);
        $this->makeExtender(duration: null);
    }

    public function test_a_complement_cannot_occupy_and_extend_at_once(): void
    {
        // Su cantidad serían personas y bloques de tiempo a la vez, y su precio no significaría nada.
        $this->expectException(InvalidArgumentException::class);
        TicketType::create([
            'name' => ['es' => 'Las dos cosas'], 'type' => TicketType::TYPE_ADDON, 'duration_min' => 60,
            'seats_per_unit' => 1, 'occupies_after_parent' => true, 'extends_parent_stay' => true,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);
    }

    public function test_the_same_rejection_the_other_way_around(): void
    {
        // Y da igual cuál se declare primero: a un ocupante tampoco se le puede encender «extiende».
        // Sin esta mitad, el orden de los campos decidiría si la fila es legal.
        $occupier = TicketType::create([
            'name' => ['es' => 'Hora extra de entrada'], 'type' => TicketType::TYPE_ADDON,
            'duration_min' => 60, 'seats_per_unit' => 1, 'occupies_after_parent' => true,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $occupier->update(['extends_parent_stay' => true]);
    }

    // ─── El enganche ─────────────────────────────────────────────────────────────────

    public function test_an_extender_can_only_hang_from_a_pack(): void
    {
        $extender = $this->makeExtender();

        $this->expectException(InvalidArgumentException::class);
        $this->entry->addons()->attach($extender->id, $this->pivot());
    }

    public function test_an_extender_hangs_from_a_pack_just_fine(): void
    {
        // CONTROL de los rechazos de arriba: el enganche legítimo pasa, así que lo que bloquean es
        // la configuración y no el mecanismo entero.
        $extender = $this->makeExtender();
        $this->pack->addons()->attach($extender->id, $this->pivot());

        $this->assertDatabaseHas('product_addons', ['product_id' => $this->pack->id, 'addon_id' => $extender->id]);
    }

    public function test_an_extender_cannot_be_included_mandatory_or_per_guest(): void
    {
        // Un INCLUIDO se auto-inyecta con su `included_quantity`: **toda fiesta nacería alargada**
        // sin que nadie lo pida — y una fiesta que dura más de serie es un pack más largo.
        foreach ([['is_included' => true], ['is_mandatory' => true], ['quantity_mode' => ProductAddon::MODE_PER_GUEST]] as $bad) {
            $extender = $this->makeExtender();
            try {
                $this->pack->addons()->attach($extender->id, $this->pivot($bad));
                $this->fail('El enganche '.json_encode($bad).' debería rechazarse.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_an_extender_cannot_be_sold_after_booking(): void
    {
        // Vender aforo DESPUÉS de reservar exige el lock de zona/día y una revalidación que esa fase
        // no tiene (la misma puerta que el ocupante).
        $extender = $this->makeExtender();

        $this->expectException(InvalidArgumentException::class);
        $this->pack->addons()->attach($extender->id, $this->pivot(['stage' => ProductAddon::STAGE_POSTFORM]));
    }

    public function test_an_extender_needs_a_maximum_per_reservation(): void
    {
        // `#423` · A6: el tope del resolutor («no se quedan más de los que entran») no significa nada
        // para bloques de tiempo, así que el tope de un extensor es SUYO y tiene que existir.
        $extender = $this->makeExtender();

        $this->expectException(InvalidArgumentException::class);
        $this->pack->addons()->attach($extender->id, $this->pivot(['max_qty' => null]));
    }

    public function test_turning_the_switch_on_over_a_forbidden_hook_is_rejected(): void
    {
        // La dirección inversa del enganche: el complemento nace neutro, se cuelga de una ENTRADA y
        // después se le enciende «extiende». El guard del pivote no lo ve; el del modelo, sí.
        $neutral = TicketType::create([
            'name' => ['es' => 'Neutro'], 'type' => TicketType::TYPE_ADDON, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);
        $this->entry->addons()->attach($neutral->id, $this->pivot());

        $this->expectException(InvalidArgumentException::class);
        $neutral->update(['extends_parent_stay' => true]);
    }

    // ─── Las dos páginas del catálogo: la puerta por la que el dato entra ────────────

    public function test_the_create_page_keeps_the_duration_of_an_extender(): void
    {
        // ⚠️⚠️ **Es el defecto que `#410` arregló para el ocupante, esperando al hermano**: el saneo
        // del alta borra `duration_min` de todo complemento salvo el que la necesita. Sin esta rama
        // el diseño sería coherente y **no se podría encender desde el panel** — el dato se perdería
        // entre el formulario y el modelo, y el guard rechazaría la fila con un error incomprensible.
        $data = $this->normalizeByType(TicketType::TYPE_ADDON, [
            'extends_parent_stay' => true, 'duration_min' => 60, 'zone_id' => $this->zone->id,
        ]);

        $this->assertSame(60, $data['duration_min'] ?? null);
        $this->assertNull($data['zone_id'], 'un complemento no tiene zona propia');
    }

    public function test_the_create_page_drops_the_duration_of_a_neutral_addon(): void
    {
        // CONTROL del anterior: sin ninguno de los dos interruptores, la duración se va — que es la
        // política del alta y lo que hace que la rama de arriba signifique algo.
        $data = $this->normalizeByType(TicketType::TYPE_ADDON, ['duration_min' => 60]);

        $this->assertArrayNotHasKey('duration_min', $data);
    }

    public function test_a_non_addon_never_extends(): void
    {
        $data = $this->normalizeByType(TicketType::TYPE_PACK, ['extends_parent_stay' => true]);

        $this->assertFalse($data['extends_parent_stay']);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────────────

    /** Conduce el saneo real del ALTA (`CreateCatalog::normalizeByType`). */
    private function normalizeByType(string $type, array $data): array
    {
        $method = new \ReflectionMethod(CreateCatalog::class, 'normalizeByType');
        $method->setAccessible(true);

        return $method->invoke(new CreateCatalog, $type, $data);
    }

    private function makeExtender(?int $duration = 60): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Hora extra de sala'], 'type' => TicketType::TYPE_ADDON,
            'duration_min' => $duration, 'extends_parent_stay' => true,
            'is_sellable' => true, 'is_active' => true, 'position' => TicketType::max('position') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pivot(array $overrides = []): array
    {
        return array_merge([
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => 2,
        ], $overrides);
    }
}
