<?php

namespace Tests\Support;

use App\Domain\Booking\Contracts\PostFormAddonView;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\PostFormAddons;

/**
 * Los complementos de venta POSTERIOR de una fiesta de prueba (F5 de `fiesta-sistema-nuevo.md` §4.11, `#749`): cada uno
 * con su tarifa, su tope y su plazo, y lo que la lista necesita para pintarlo (bloque, «para cuántas», familia).
 */
trait AttachesPartyExtras
{
    /**
     * @param  array<string, mixed>  $pivot  del enganche (`postform_block`, `max_qty`, `postform_cutoff_hours`…)
     * @param  array<string, mixed>  $atributos  del complemento (`serves`, `family`, `image`…)
     */
    protected function extra(TicketType $pack, string $nombre, int $centimos, array $pivot = [], array $atributos = []): TicketType
    {
        static $posicion = 40;
        $addon = TicketType::create(array_merge([
            'name' => ['es' => $nombre], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => $posicion++,
        ], $atributos));
        $rate = RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);
        Price::create([
            'priceable_type' => $addon->getMorphClass(), 'priceable_id' => $addon->id,
            'rate_type_id' => $rate->id, 'amount_cents' => $centimos, 'currency' => 'EUR',
        ]);
        $pack->configurableAddons()->attach($addon->id, array_merge([
            'position' => $posicion, 'quantity_mode' => ProductAddon::MODE_FIXED,
            'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 48, 'max_qty' => 10,
        ], $pivot));

        return $addon;
    }

    /** @return array<int, PostFormAddonView> por id de complemento */
    protected function vistas(OrderItem $reservation): array
    {
        $fresca = $reservation->fresh(['ticketType.addons', 'order', 'slot', 'children']);
        $this->assertNotNull($fresca);
        $out = [];
        foreach (app(PostFormAddons::class)->viewFor($fresca) as $vista) {
            $out[$vista->productId] = $vista;
        }

        return $out;
    }

    /** @return array<int, int> lo que la reserva tiene de cada complemento */
    protected function cantidades(OrderItem $reservation): array
    {
        return array_map(static fn (PostFormAddonView $v): int => $v->quantity, $this->vistas($reservation));
    }
}
