<?php

namespace Tests\Feature\Support;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\LandingComplementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #228 — Comprabilidad de una atracción de pago (complemento) en su zona. Un complemento solo
 * es «comprable» (precio + CTA en la landing) si es vendible Y está enganchado a ≥1 entrada
 * vendible de una zona operativa. El resolver lo calcula en BATCH (una query). El modelo
 * (`Attraction::complementIsPurchasable`) lo comprueba para UNA atracción (aviso del panel).
 */
class LandingComplementResolverTest extends TestCase
{
    use RefreshDatabase;

    private function rateNormal(): RateType
    {
        return RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'priority' => 0]);
    }

    private function zone(string $slug, bool $active = true, bool $landing = true): Zone
    {
        return Zone::create([
            'slug' => $slug, 'name' => ['es' => ucfirst($slug)], 'position' => 1,
            'is_active' => $active, 'show_in_landing' => $landing,
        ]);
    }

    private function entry(Zone $zone, bool $sellable = true): TicketType
    {
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada '.$zone->slug], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id, 'is_active' => $sellable, 'is_sellable' => $sellable,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
        $entry->prices()->create(['rate_type_id' => $this->rateNormal()->id, 'amount_cents' => 1190]);

        return $entry;
    }

    private function addon(int $priceCents = 500, bool $sellable = true, bool $withPrice = true): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Tirolina'], 'type' => TicketType::TYPE_ADDON,
            'is_active' => $sellable, 'is_sellable' => $sellable, 'seats_per_unit' => 1, 'position' => 20,
        ]);
        if ($withPrice) {
            $addon->prices()->create(['rate_type_id' => $this->rateNormal()->id, 'amount_cents' => $priceCents]);
        }

        return $addon;
    }

    private function attraction(Zone $zone, ?TicketType $addon = null, int $position = 1): Attraction
    {
        return Attraction::create([
            'zone_id' => $zone->id, 'name' => ['es' => 'Atracción'], 'position' => $position,
            'is_active' => true, 'ticket_type_id' => $addon?->id,
        ]);
    }

    private function purchasable(Attraction $attraction): bool
    {
        return (new LandingComplementResolver(collect([$attraction])))->isPurchasable($attraction);
    }

    public function test_purchasable_when_addon_attached_to_sellable_entry_in_zone(): void
    {
        $zone = $this->zone('jump');
        $entry = $this->entry($zone);
        $addon = $this->addon(priceCents: 700);
        $entry->configurableAddons()->attach($addon->id, ['position' => 1]);
        $attraction = $this->attraction($zone, $addon);

        $resolver = new LandingComplementResolver(collect([$attraction]));
        $this->assertTrue($resolver->isPurchasable($attraction));
        $this->assertSame(700, $resolver->priceCents($attraction));
        // El modelo (aviso del panel) coincide con el resolver.
        $this->assertTrue($attraction->complementIsPurchasable());
    }

    public function test_not_purchasable_when_addon_not_attached_to_any_entry(): void
    {
        $zone = $this->zone('jump');
        $this->entry($zone); // entrada vendible, pero el addon NO está enganchado
        $addon = $this->addon();
        $attraction = $this->attraction($zone, $addon);

        $this->assertFalse($this->purchasable($attraction));
        $this->assertFalse($attraction->complementIsPurchasable());
    }

    public function test_not_purchasable_when_addon_is_not_sellable(): void
    {
        $zone = $this->zone('jump');
        $entry = $this->entry($zone);
        $addon = $this->addon(sellable: false);
        $entry->configurableAddons()->attach($addon->id, ['position' => 1]);

        $this->assertFalse($this->purchasable($this->attraction($zone, $addon)));
    }

    public function test_not_purchasable_when_zone_entry_is_not_sellable(): void
    {
        $zone = $this->zone('jump');
        $entry = $this->entry($zone, sellable: false);
        $addon = $this->addon();
        $entry->configurableAddons()->attach($addon->id, ['position' => 1]);

        $this->assertFalse($this->purchasable($this->attraction($zone, $addon)));
    }

    public function test_not_purchasable_when_only_attached_in_another_zone(): void
    {
        $jump = $this->zone('jump');
        $kids = $this->zone('kids');
        $kidsEntry = $this->entry($kids);
        $addon = $this->addon();
        $kidsEntry->configurableAddons()->attach($addon->id, ['position' => 1]);

        // La atracción está en JUMP, pero el addon solo se vende vía una entrada de KIDS.
        $this->assertFalse($this->purchasable($this->attraction($jump, $addon)));
    }

    public function test_not_purchasable_when_addon_has_no_price(): void
    {
        // Un addon vendible pero SIN precio mostraría «0,00 €» → no es comprable (coherencia #226).
        $zone = $this->zone('jump');
        $entry = $this->entry($zone);
        $addon = $this->addon(withPrice: false);
        $entry->configurableAddons()->attach($addon->id, ['position' => 1]);

        $attraction = $this->attraction($zone, $addon);
        $this->assertFalse($this->purchasable($attraction));
        $this->assertFalse($attraction->complementIsPurchasable());
    }

    public function test_not_purchasable_when_attached_only_as_included(): void
    {
        // Enganchado SOLO como incluido (gratis) → no se vende aparte.
        $zone = $this->zone('jump');
        $entry = $this->entry($zone);
        $addon = $this->addon();
        $entry->configurableAddons()->attach($addon->id, ['position' => 1, 'is_included' => true]);

        $attraction = $this->attraction($zone, $addon);
        $this->assertFalse($this->purchasable($attraction));
        $this->assertFalse($attraction->complementIsPurchasable());
    }

    public function test_purchasable_when_paid_on_one_entry_even_if_included_on_another(): void
    {
        // Incluido en una entrada de la zona PERO de pago en otra → sí se puede comprar aparte.
        $zone = $this->zone('jump');
        $entryA = $this->entry($zone);
        $entryB = $this->entry($zone);
        $addon = $this->addon();
        $entryA->configurableAddons()->attach($addon->id, ['position' => 1, 'is_included' => true]);
        $entryB->configurableAddons()->attach($addon->id, ['position' => 1, 'is_included' => false]);

        $this->assertTrue($this->purchasable($this->attraction($zone, $addon)));
    }

    public function test_not_purchasable_when_zone_is_inactive(): void
    {
        $zone = $this->zone('jump', active: false);
        $entry = $this->entry($zone);
        $addon = $this->addon();
        $entry->configurableAddons()->attach($addon->id, ['position' => 1]);

        $this->assertFalse($this->purchasable($this->attraction($zone, $addon)));
    }

    public function test_not_purchasable_without_a_linked_complement(): void
    {
        $zone = $this->zone('jump');
        $this->assertFalse($this->purchasable($this->attraction($zone, null)));
    }

    public function test_resolver_computes_in_a_single_query(): void
    {
        $zone = $this->zone('jump');
        $entry = $this->entry($zone);
        $addon = $this->addon();
        $entry->configurableAddons()->attach($addon->id, ['position' => 1]);

        $attractions = collect([
            $this->attraction($zone, $addon, position: 1),
            $this->attraction($zone, $addon, position: 2),
            $this->attraction($zone, null, position: 3),
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $resolver = new LandingComplementResolver($attractions);
        $this->assertCount(1, DB::getQueryLog(), 'la comprabilidad se resuelve en UNA query (sin N+1)');
        DB::disableQueryLog();

        $this->assertTrue($resolver->isPurchasable($attractions[0]));
        $this->assertTrue($resolver->isPurchasable($attractions[1]));
        $this->assertFalse($resolver->isPurchasable($attractions[2]));
    }
}
