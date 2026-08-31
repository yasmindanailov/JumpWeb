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

    /** El portador del DESCUENTO (T4, spec §24.2) — producto propio por las ~6 superficies que imprimen su nombre. */
    public const CREDIT_PRODUCT_KEY = 'mixed_party.credit_product_id';

    /**
     * Prefijo de los TRES textos que lee el cliente cuando una edad no tiene producto
     * (`DECISIONES #284` D6, spec §22.4): `mixed_party.no_product.{below|above|gap}.{es|en|fr}`.
     * Vacío = el texto por defecto de `lang/{es,en,fr}/guestform.php`. Admiten el marcador `:phone`.
     */
    public const NO_PRODUCT_TEXT_PREFIX = 'mixed_party.no_product';

    /** @var list<string> */
    public const NO_PRODUCT_REASONS = [
        AgeFamilySeal::NO_PRODUCT_BELOW,
        AgeFamilySeal::NO_PRODUCT_ABOVE,
        AgeFamilySeal::NO_PRODUCT_GAP,
    ];

    /**
     * El texto que se le enseña al cliente para ese caso, en su idioma: el del PARQUE si lo escribió
     * —en el idioma pedido, si no en el de respaldo de la app, si no en cualquiera de los tres, en
     * ese orden—, y si no escribió ninguno, el texto por defecto en el idioma del cliente. Un parque
     * que solo escribe su texto en español lo enseña también al cliente francés: mejor su frase en
     * otro idioma que una por defecto que no dice lo que él decidió. `:phone` se sustituye por el
     * teléfono de contacto de la instalación — sin él, por «al parque», para que la frase siga
     * teniendo a quién llamar.
     */
    public static function noProductText(string $reason, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $fallback = (string) config('app.fallback_locale');

        $custom = null;
        foreach (array_unique([$locale, $fallback, 'es', 'en', 'fr']) as $candidate) {
            $custom = self::customText($reason, $candidate);
            if ($custom !== null) {
                break;
            }
        }
        $text = $custom ?? (string) __('guestform.no_product_'.$reason, [], $locale);

        $phone = trim((string) Setting::value('contact.phone', ''));
        $phone = $phone !== '' ? $phone : (string) __('guestform.no_product_phone_fallback', [], $locale);

        return str_replace(':phone', $phone, $text);
    }

    private static function customText(string $reason, string $locale): ?string
    {
        $value = Setting::value(self::NO_PRODUCT_TEXT_PREFIX.".{$reason}.{$locale}");
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

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
        return self::carrierProduct(self::SURCHARGE_PRODUCT_KEY);
    }

    /**
     * El portador del DESCUENTO (T4, spec §24.2). Mismas tres condiciones y el mismo aviso: `null`
     * no es un default silencioso — significa que una fiesta con invitados de un tramo más barato
     * no puede descontarse, y la ficha del pedido lo enseña en rojo cuando hay algo que descontar.
     */
    public static function creditProduct(): ?TicketType
    {
        return self::carrierProduct(self::CREDIT_PRODUCT_KEY);
    }

    private static function carrierProduct(string $key): ?TicketType
    {
        $raw = Setting::value($key);

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $product = TicketType::find((int) $raw);

        return $product?->isAddon() === true ? $product : null;
    }
}
