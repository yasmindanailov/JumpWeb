<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Models\TicketType;

/**
 * Presentación (solo lectura) de los COMPLEMENTOS aplicables a un producto para la LANDING.
 *
 * Coherente con lo que realmente se puede añadir: lee la relación `addons()` (el pivote
 * `product_addons` filtrado a complementos vendibles+activos), la MISMA fuente que el flujo
 * de compra → lo que se anuncia es lo que se puede comprar. Usa el precio de REFERENCIA
 * (`displayPriceCents`, tarifa normal/mínima) como el resto de la landing (no la tarifa del
 * día), y reutiliza las claves i18n de complementos (`tickets.addon_*`).
 *
 * No es interactivo (no calcula selección/cantidades): solo describe cada complemento
 * (nombre, etiqueta, nota de precio, features, grupo) para mostrarlo de forma compacta y
 * secundaria bajo la tarjeta del producto.
 */
class LandingAddonPresenter
{
    /**
     * @return list<array{name:string, badge:?string, note:?string, features:list<string>, group:?string}>
     */
    public static function rows(TicketType $product, bool $isPack): array
    {
        // Defensa anti-N+1 si el controlador no los precargó (no-op si ya están cargados).
        $product->loadMissing('addons.prices.rateType');

        $rows = [];
        // El eje de FASE (`specs/complementos-post-reserva.md` §4.4, `#413`): esta pantalla anuncia lo
        // que se compra AL RESERVAR, y su propio docblock declara el invariante que un `postform`
        // rompería —«lo que se anuncia es lo que se puede comprar»—: sin este filtro la landing
        // ofrecería el cubo de refrescos bajo la tarjeta del cumpleaños y el embudo lo rechazaría.
        foreach ($product->addonsSoldAtBooking() as $addon) {
            $pivot = $addon->pivot;
            $included = (bool) $pivot->is_included;

            // "Sin precio" (ninguna tarifa) ≠ "0 € explícito": un complemento DE PAGO sin precio
            // NO se puede comprar (el checkout lo rechaza, igual que AddonResolver::viewModel) →
            // no se anuncia en la landing. Un incluido sí se ofrece (es gratis). `displayPriceCents`
            // devuelve 0 para ambos casos, así que distinguimos por la EXISTENCIA de un precio.
            $hasPrice = $addon->prices->isNotEmpty();
            if (! $included && ! $hasPrice) {
                continue;
            }

            $priceCents = $addon->displayPriceCents();
            $perGuest = $pivot->isPerGuest();
            $priceStr = number_format($priceCents / 100, 2, ',', '.').' €';

            // La ETIQUETA (badge) comunica la categoría (Incluido/Gratis); la NOTA de precio
            // comunica solo el COSTE. Para no DUPLICAR (p. ej. badge "Gratis" + nota "Gratis"),
            // la nota es null cuando el badge ya lo dice todo (gratis / incluido sin extras);
            // los incluidos CON extras de pago muestran solo el coste de los extras.
            if ($included) {
                $badge = $isPack ? 'included' : 'free';
                $note = ($pivot->allow_extra && ! $perGuest && $priceCents > 0)
                    ? __('tickets.addon_extra_each', ['price' => $priceStr])
                    : null;
            } elseif ($priceCents === 0) {
                $badge = 'free';
                $note = null;
            } elseif ($perGuest) {
                // «+2,00 €/invitado»: el `+` da coherencia con el complemento suelto (`'+'.$priceStr`).
                $badge = null;
                $note = __('tickets.addon_per_unit', ['price' => '+'.$priceStr]);
            } else {
                $badge = null;
                $note = '+'.$priceStr;
            }

            // "desde" si el complemento DE PAGO varía de precio por día (festivo/finde): el
            // checkout cobra la tarifa del día, así que lo anunciado no debe quedar por debajo.
            // Mismo matiz que la tarjeta del producto principal (price-card con priceVaries()).
            if (! $included && $priceCents > 0 && $addon->priceVaries()) {
                $note = __('landing.pricing.from').' '.$note;
            }

            $features = $addon->tr('features');
            $features = is_array($features)
                ? array_values(array_filter(array_map(fn ($f) => trim((string) $f), $features), fn ($f): bool => $f !== ''))
                : [];

            $rows[] = [
                'name' => (string) $addon->tr('name'),
                'badge' => $badge,
                'note' => $note,
                'features' => $features,
                'group' => $pivot->choiceGroup(),
            ];
        }

        return $rows;
    }
}
