<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\TicketType;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5 (Capa 3a) — Cimientos de los complementos (add-ons, #87): productos `type=addon`
 * sin zona/aforo, con precio plano, y aplicabilidad POR PRODUCTO vía el pivote `product_addons`
 * (configurable en el panel). Ver docs/PLAN-COMPRA-PRODUCTOS.md (§3,§5E) y DECISIONES (#87).
 */
class AddonFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_addons_are_seeded_as_sellable_products_with_a_price(): void
    {
        $this->seed(LandingContentSeeder::class);

        $addons = TicketType::sellable()->ofType(TicketType::TYPE_ADDON)->get();

        $this->assertCount(4, $addons);
        $this->assertTrue($addons->every(fn (TicketType $a) => $a->isAddon()));
        // Precio en normal y especial → vendibles cualquier día.
        $this->assertTrue($addons->every(fn (TicketType $a) => $a->prices()->count() === 2));
    }

    public function test_addons_apply_per_product(): void
    {
        $this->seed(LandingContentSeeder::class);

        $entry = TicketType::ofType(TicketType::TYPE_ENTRY)->first();
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();

        // Calcetines/taquilla → todas; tarta/monitor → solo cumpleaños.
        $this->assertCount(2, $entry->addons);
        $this->assertCount(4, $pack->addons);

        $entryAddons = $entry->addons->map(fn (TicketType $a) => $a->tr('name', 'es'))->all();
        $this->assertContains('Calcetines antideslizantes', $entryAddons);
        $this->assertNotContains('Tarta', $entryAddons);

        $packAddons = $pack->addons->map(fn (TicketType $a) => $a->tr('name', 'es'))->all();
        $this->assertContains('Tarta', $packAddons);
        $this->assertContains('Monitor extra', $packAddons);
    }
}
