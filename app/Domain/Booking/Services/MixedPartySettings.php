<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Models\Setting;

/**
 * El producto que LLEVA la línea del suplemento de una fiesta mixta
 * (`docs/specs/cumple-mixto.md` §12).
 *
 * Helper defensivo, mismo patrón que `CatalogSettings`/`PuertaSettings`: si la fila falta, apunta a
 * un producto borrado o a uno que ya no es un complemento, devuelve `null` sin lanzar.
 *
 * ⚠️⚠️ **Pero `null` aquí NO es un default silencioso**: significa que una fiesta mixta no puede
 * cobrarse. Por eso la ficha del pedido enseña un aviso ROJO cuando esto devuelve `null` en vez de
 * limitarse a no ofrecer nada. Un cobro que deja de aplicarse sin que nadie se entere es el modo de
 * fallo que esta función tenía que evitar, no uno que pueda permitirse.
 */
class MixedPartySettings
{
    public const SURCHARGE_PRODUCT_KEY = 'mixed_party.surcharge_product_id';

    /**
     * El producto portador, o `null` si no se puede usar. Exige las TRES condiciones, porque cada
     * una rompe algo distinto:
     *  - que el ajuste exista y sea numérico (si no, no hay a qué apuntar);
     *  - que el producto exista (alguien pudo borrarlo antes de que tuviera ventas);
     *  - **que siga siendo un COMPLEMENTO** — si alguien lo convirtiera en pack, sus líneas
     *    empezarían a consumir aforo de la zona en silencio (§12, `AFORO-01`).
     */
    public static function surchargeProduct(): ?TicketType
    {
        $raw = Setting::value(self::SURCHARGE_PRODUCT_KEY);

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $product = TicketType::find((int) $raw);

        return $product?->isAddon() === true ? $product : null;
    }
}
