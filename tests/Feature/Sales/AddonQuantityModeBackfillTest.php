<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **EL RELLENO del sello del modo** (`specs/hora-extra.md` §12.9 y §12.10, `DECISIONES #448`).
 *
 * La migración escribe sobre datos de un cliente REAL, así que sus dos rellenos tienen caso propio:
 * un `UPDATE` a ciegas sobre `order_items` no puede ir sin red. La migración es idempotente y
 * re-ejecutable a propósito para que estos casos puedan conducirla de verdad.
 *
 * ▶ **Lo que se rellena y lo que NO** es la decisión central de la tanda (`[DECIDIDO owner]`):
 *  - **A · los EXTENSORES**, porque su `fixed` es un hecho COPIADO (el rastro de auditoría demuestra
 *    que a esos enganches no les cambió el modo nadie);
 *  - **B · las líneas que NO PUDIERON NACER ASÍ**, que es la corrección de dato de `R-BOMAZH`;
 *  - **nada más**: rellenar el resto desde el pivote de hoy convertiría un error hoy autocorregible
 *    en un hecho inmutable, y sobre la línea de B escribiría justo lo contrario de lo que se vendió.
 */
class AddonQuantityModeBackfillTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $pack;

    private Order $order;

    // ─── A · los extensores ──────────────────────────────────────────────────────────

    public function test_backfill_seals_stay_extensions_as_fixed(): void
    {
        $extensor = $this->addon('Hora extra', ['duration_min' => 60, 'extends_parent_stay' => true]);
        $this->hookUp($extensor, ProductAddon::MODE_FIXED);

        $parent = $this->parentLine(12);
        $child = $this->childLine($parent, $extensor, quantity: 1);
        $this->unseal($child);

        $this->runMigration();

        $this->assertSame(ProductAddon::MODE_FIXED, $child->fresh()->addon_quantity_mode);
    }

    public function test_control_a_normal_addon_is_left_in_silence(): void
    {
        // CONTROL de A, y es el que impide que el relleno se convierta en «sella todo»: un
        // complemento corriente NO se toca. Sin este caso, un relleno que sellara el corpus entero
        // pasaría en verde — y ése es exactamente el diseño que se descartó.
        //
        // ⚠️⚠️ **Los DOS complementos tienen que convivir en el mismo escenario**, y lo dijo el arnés
        // de mutación: con solo la tarta, `backfillStayExtensions()` sale por su `return` temprano
        // —no hay extensores— y la mutación «sella TODO complemento» pasaba en VERDE. *Un control sin
        // el sujeto de la regla no controla nada*, que es la lección de `#443` §11.12.
        $extensor = $this->addon('Hora extra', ['duration_min' => 60, 'extends_parent_stay' => true]);
        $this->hookUp($extensor, ProductAddon::MODE_FIXED);
        $tarta = $this->addon('Tarta');
        $this->hookUp($tarta, ProductAddon::MODE_FIXED);

        $parent = $this->parentLine(12);
        $extra = $this->childLine($parent, $extensor, quantity: 1);
        $child = $this->childLine($parent, $tarta, quantity: 2);
        $this->unseal($extra);
        $this->unseal($child);

        $this->runMigration();

        $this->assertSame(
            ProductAddon::MODE_FIXED,
            $extra->fresh()->addon_quantity_mode,
            'el extensor SÍ se rellena: es el sujeto de la regla',
        );
        $this->assertNull(
            $child->fresh()->addon_quantity_mode,
            'y el complemento normal se queda en SILENCIO: su modo sigue saliendo del catálogo',
        );
    }

    // ─── B · la línea que no pudo nacer así ──────────────────────────────────────────

    public function test_backfill_seals_a_per_guest_line_whose_quantity_cannot_come_from_that_mode(): void
    {
        // Reproduce `R-BOMAZH`: la línea se vendió como 1 unidad y su enganche pasó a `per_guest`
        // DESPUÉS. `effectiveQuantity()` con `per_guest` devuelve SIEMPRE la cantidad del padre, así
        // que un 1 bajo un padre de 17 no pudo nacer de ese modo.
        $menu = $this->addon('Menú 2');
        $this->hookUp($menu, ProductAddon::MODE_PER_GUEST);

        $parent = $this->parentLine(17);
        $child = $this->childLine($parent, $menu, quantity: 1);
        $this->unseal($child);

        $this->runMigration();

        $this->assertSame(
            ProductAddon::MODE_FIXED,
            $child->fresh()->addon_quantity_mode,
            'se vendió con la otra unidad, y sellarla es lo que evita los +32,00 € de la primera edición',
        );
    }

    public function test_control_a_coherent_per_guest_line_is_left_in_silence(): void
    {
        // CONTROL de B: la misma configuración, pero con la cantidad que ese modo SÍ produce. Sin
        // este caso, un criterio que sellara toda línea `per_guest` pasaría en verde — y eso
        // congelaría seis líneas sanas de producción.
        $menu = $this->addon('Menú 2');
        $this->hookUp($menu, ProductAddon::MODE_PER_GUEST);

        $parent = $this->parentLine(17);
        $child = $this->childLine($parent, $menu, quantity: 17);
        $this->unseal($child);

        $this->runMigration();

        $this->assertNull($child->fresh()->addon_quantity_mode);
    }

    public function test_a_cancelled_line_is_not_backfilled(): void
    {
        // Una línea cancelada ya no la puede re-escalar nadie: tocarla sería escribir un hecho sobre
        // algo que no participa. El criterio filtra `cancelled_at` y este caso lo fija.
        $menu = $this->addon('Menú 2');
        $this->hookUp($menu, ProductAddon::MODE_PER_GUEST);

        $parent = $this->parentLine(17);
        $child = $this->childLine($parent, $menu, quantity: 1);
        $child->forceFill(['cancelled_at' => now()])->save();
        $this->unseal($child);

        $this->runMigration();

        $this->assertNull($child->fresh()->addon_quantity_mode);
    }

    // ─── Idempotencia ────────────────────────────────────────────────────────────────

    public function test_the_backfill_never_overwrites_a_seal_that_already_exists(): void
    {
        // Idempotencia, y no es teórica: la migración se re-ejecuta en cada despliegue que la
        // arrastre. Un relleno que pisara un sello existente reescribiría lo que las puertas
        // escribieron bien.
        $extensor = $this->addon('Hora extra', ['duration_min' => 60, 'extends_parent_stay' => true]);
        $this->hookUp($extensor, ProductAddon::MODE_FIXED);

        $parent = $this->parentLine(12);
        $child = $this->childLine($parent, $extensor, quantity: 1);
        DB::table('order_items')->where('id', $child->id)
            ->update(['addon_quantity_mode' => ProductAddon::MODE_PER_GUEST]);

        $this->runMigration();
        $this->runMigration();

        $this->assertSame(
            ProductAddon::MODE_PER_GUEST,
            $child->fresh()->addon_quantity_mode,
            'el relleno solo escribe donde no hay nada: lo ya sellado es un hecho',
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────────

    private function runMigration(): void
    {
        (require base_path('database/migrations/2026_09_08_120000_add_addon_quantity_mode_to_order_items.php'))->up();
    }

    /** Devuelve la línea al estado ANTERIOR al mecanismo: sin sello. */
    private function unseal(OrderItem $item): void
    {
        DB::table('order_items')->where('id', $item->id)->update(['addon_quantity_mode' => null]);
    }

    /** @param  array<string, mixed>  $extra */
    private function addon(string $name, array $extra = []): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 5,
        ] + $extra);
    }

    private function hookUp(TicketType $addon, string $mode): void
    {
        $this->pack ??= TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);

        // ⚠️ Un extensor SIN `max_qty` es una configuración que el dominio rechaza (§10.5·2: sin
        // techo, el único freno sería que el aforo lo tumbe). El fixture se LEGALIZA en vez de
        // excepcionar el guard — un fixture que necesita un estado prohibido prueba un mundo que no
        // existe, que es la lección de `#299`.
        $this->pack->addons()->attach($addon->id, [
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => $mode, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => $addon->extends_parent_stay ? 2 : null,
        ]);
    }

    private function parentLine(int $quantity): OrderItem
    {
        $this->order ??= Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'R-SEAL'.$quantity,
            'status' => Order::STATUS_PAID,
            'subtotal' => 0, 'tax' => 0, 'total' => 0, 'currency' => 'EUR',
        ]);

        return $this->order->items()->create([
            'ticket_type_id' => $this->pack->id,
            'slot_id' => null,
            'quantity' => $quantity,
            'free_quantity' => 0,
            'unit_price' => 1500,
            'seats' => $quantity,
        ]);
    }

    private function childLine(OrderItem $parent, TicketType $addon, int $quantity): OrderItem
    {
        return $parent->children()->create([
            'order_id' => $parent->order_id,
            'ticket_type_id' => $addon->id,
            'slot_id' => null,
            'quantity' => $quantity,
            'free_quantity' => 0,
            'unit_price' => 200,
            'seats' => 0,
        ]);
    }
}
