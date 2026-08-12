<?php

namespace Tests\Feature\Support;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Services\LandingAddonPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #194 — Presentación de complementos por producto en la landing: coherente con el pivote
 * `product_addons` (solo los enganchados, vendibles+activos) y con la nota/etiqueta correcta.
 */
class LandingAddonPresenterTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'priority' => 0, 'is_active' => true]);
    }

    private function product(string $type, string $name): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => $type,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => ++$this->counter,
        ]);
    }

    private function addon(string $name, ?int $priceCents): TicketType
    {
        $a = $this->product(TicketType::TYPE_ADDON, $name);
        if ($priceCents !== null) {
            $a->prices()->create(['rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'), 'amount_cents' => $priceCents]);
        }

        return $a;
    }

    public function test_only_pivot_addons_are_returned(): void
    {
        $entry = $this->product(TicketType::TYPE_ENTRY, 'Jump 1h');
        $sock = $this->addon('Calcetines', 200);
        $this->addon('Taquilla', 200); // NO enganchado a esta entrada

        $entry->configurableAddons()->attach($sock->id, ['quantity_mode' => 'fixed', 'position' => 1]);

        $rows = LandingAddonPresenter::rows($entry->fresh(), isPack: false);

        $this->assertCount(1, $rows);                 // solo el enganchado (coherencia con el pivote)
        $this->assertSame('Calcetines', $rows[0]['name']);
    }

    public function test_paid_addon_shows_plus_price_no_badge(): void
    {
        $entry = $this->product(TicketType::TYPE_ENTRY, 'Jump 1h');
        $sock = $this->addon('Calcetines', 200);
        $entry->configurableAddons()->attach($sock->id, ['quantity_mode' => 'fixed', 'position' => 1]);

        $rows = LandingAddonPresenter::rows($entry->fresh(), isPack: false);

        $this->assertNull($rows[0]['badge']);
        $this->assertSame('+2,00 €', $rows[0]['note']);
    }

    public function test_included_addon_in_pack_shows_included_badge(): void
    {
        $pack = $this->product(TicketType::TYPE_PACK, 'Cumpleaños Jump');
        $cake = $this->addon('Tarta', 2500);
        $cake->update(['features' => ['es' => ['Bizcocho de chocolate', 'Velas incluidas']]]);
        $pack->configurableAddons()->attach($cake->id, [
            'is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'fixed', 'allow_extra' => true, 'position' => 1,
        ]);

        $rows = LandingAddonPresenter::rows($pack->fresh(), isPack: true);

        $this->assertSame('included', $rows[0]['badge']);
        // El badge ya dice "Incluido"; la nota muestra SOLO el coste de los extras (sin repetir).
        $this->assertStringNotContainsString('Incluido', (string) $rows[0]['note']);
        $this->assertStringContainsString('extras', $rows[0]['note']);
        $this->assertStringContainsString('25,00', $rows[0]['note']);
        $this->assertSame(['Bizcocho de chocolate', 'Velas incluidas'], $rows[0]['features']);
    }

    public function test_free_and_included_addons_have_no_redundant_price_note(): void
    {
        // El bug reportado: "Gratis" salía 2 veces (badge + nota). El badge comunica la
        // categoría; la nota de precio se omite cuando no añade coste.
        $entry = $this->product(TicketType::TYPE_ENTRY, 'Jump 1h');
        $free = $this->addon('Calcetines', 0);            // 0 € explícito → badge "Gratis"
        $entry->configurableAddons()->attach($free->id, ['quantity_mode' => 'fixed', 'position' => 1]);

        $rows = LandingAddonPresenter::rows($entry->fresh(), isPack: false);
        $this->assertSame('free', $rows[0]['badge']);
        $this->assertNull($rows[0]['note']);              // sin "Gratis" duplicado

        // Incluido sin extras → badge "Incluido" y nota null (no se repite).
        $pack = $this->product(TicketType::TYPE_PACK, 'Cumpleaños Jump');
        $gift = $this->addon('Regalo', null);
        $pack->configurableAddons()->attach($gift->id, ['is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'fixed', 'allow_extra' => false, 'position' => 1]);

        $packRows = LandingAddonPresenter::rows($pack->fresh(), isPack: true);
        $this->assertSame('included', $packRows[0]['badge']);
        $this->assertNull($packRows[0]['note']);
    }

    public function test_varying_price_addon_shows_desde_qualifier(): void
    {
        // Si el complemento de pago varía de precio por día, se antepone "desde" (igual que la
        // tarjeta de producto), para no anunciar por debajo de lo que cobra el checkout.
        $entry = $this->product(TicketType::TYPE_ENTRY, 'Jump 1h');
        $sock = $this->addon('Calcetines', 200);
        $special = RateType::create(['key' => RateType::KEY_SPECIAL, 'label' => ['es' => 'Especial'], 'is_special' => true, 'weekdays' => [0, 6], 'priority' => 10, 'is_active' => true]);
        $sock->prices()->create(['rate_type_id' => $special->id, 'amount_cents' => 400]); // varía: 200 normal / 400 especial
        $entry->configurableAddons()->attach($sock->id, ['quantity_mode' => 'fixed', 'position' => 1]);

        $rows = LandingAddonPresenter::rows($entry->fresh(), isPack: false);

        $this->assertStringContainsString('desde', $rows[0]['note']);
        $this->assertStringContainsString('+2,00 €', $rows[0]['note']); // referencia = tarifa normal
    }

    public function test_choice_group_is_captured_in_rows(): void
    {
        // Dos complementos en el mismo grupo de elección → el presenter expone su `group`
        // (lo consume el componente para mostrarlos como «Elige una opción», no aditivos).
        $pack = $this->product(TicketType::TYPE_PACK, 'Cumpleaños Jump');
        $m1 = $this->addon('Menú 1', 0);
        $m2 = $this->addon('Menú 2', 800);
        $pack->configurableAddons()->attach($m1->id, ['is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 1]);
        $pack->configurableAddons()->attach($m2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 2]);

        $rows = LandingAddonPresenter::rows($pack->fresh(), isPack: true);

        $this->assertCount(2, $rows);
        $this->assertSame('menu', $rows[0]['group']);
        $this->assertSame('menu', $rows[1]['group']);
    }

    public function test_per_guest_addon_shows_per_guest_note(): void
    {
        $pack = $this->product(TicketType::TYPE_PACK, 'Cumpleaños Jump');
        $menu = $this->addon('Menú', 800);
        $pack->configurableAddons()->attach($menu->id, ['quantity_mode' => 'per_guest', 'position' => 1]);

        $rows = LandingAddonPresenter::rows($pack->fresh(), isPack: true);

        // Formato compacto «precio/invitado» (sin «por invitado», para ahorrar espacio).
        $this->assertStringContainsString('8,00 €/invitado', $rows[0]['note']);
    }

    public function test_paid_addon_without_price_is_excluded(): void
    {
        // De pago + sin precio = no comprable (el checkout lo rechaza) → no se anuncia.
        $entry = $this->product(TicketType::TYPE_ENTRY, 'Jump 1h');
        $noPrice = $this->addon('Sin precio', null);
        $entry->configurableAddons()->attach($noPrice->id, ['quantity_mode' => 'fixed', 'position' => 1]);

        $this->assertCount(0, LandingAddonPresenter::rows($entry->fresh(), isPack: false));
    }

    public function test_included_addon_without_price_is_shown_as_included(): void
    {
        // Incluido + sin precio = gratis incluido → SÍ se anuncia ("Incluido").
        $pack = $this->product(TicketType::TYPE_PACK, 'Cumpleaños Jump');
        $gift = $this->addon('Regalo', null);
        $pack->configurableAddons()->attach($gift->id, ['is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'fixed', 'position' => 1]);

        $rows = LandingAddonPresenter::rows($pack->fresh(), isPack: true);
        $this->assertCount(1, $rows);
        $this->assertSame('included', $rows[0]['badge']);
    }

    public function test_inactive_or_unsellable_addons_are_excluded(): void
    {
        $entry = $this->product(TicketType::TYPE_ENTRY, 'Jump 1h');
        $hidden = $this->addon('Oculto', 500);
        $hidden->update(['is_sellable' => false]); // fuera de venta → no se anuncia
        $entry->configurableAddons()->attach($hidden->id, ['quantity_mode' => 'fixed', 'position' => 1]);

        $this->assertCount(0, LandingAddonPresenter::rows($entry->fresh(), isPack: false));
    }
}
