<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\Money;

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
     * @return list<array{name:string, badge:?string, note:?string, features:list<string>, gifts:list<string>, group:?string}>
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
            // Mismo matiz que el precio del producto principal, que también anuncia con «desde»
            // cuando varía por día (`TicketType::priceVaries()`).
            if (! $included && $priceCents > 0 && $addon->priceVaries()) {
                $note = __('landing.pricing.from').' '.$note;
            }

            $features = $addon->tr('features');
            $features = is_array($features)
                ? array_values(array_filter(array_map(fn ($f) => trim((string) $f), $features), fn ($f): bool => $f !== ''))
                : [];

            // **EL TEXTO DESCRIPTIVO DEL COMPLEMENTO** — lo que abre el «Más info» del carril (`#549`).
            //
            // ⚠️⚠️ **Son DOS campos del catálogo y no uno, y publicar solo uno dejaría a la mitad de
            // los complementos mudos.** Medido sobre el catálogo real (16 complementos): **once
            // llevan `features`** —los menús, los combos, los cubos, la tarta, los calcetines— **y
            // ninguno `description`**; los **dos** extensores de sala llevan `description` («La fiesta
            // se queda una hora más en la sala») **y ninguna `features`**. Los tres que no tienen ni
            // una ni otra son los portadores internos del suplemento mixto y las horas extra viejas.
            // ▶ Así que la vista enseña **lo que haya**, y el botón solo existe si hay algo. Es DATO:
            // el texto se escribe en el panel, no aquí.
            $description = $addon->tr('description');
            $description = is_string($description) ? trim($description) : '';

            $rows[] = [
                // El ID del complemento, para poder unir las listas de varios productos SIN repetir
                // (`unique()`). Sin él la deduplicación tendría que hacerse por NOMBRE, y dos
                // complementos distintos con el mismo rótulo se fundirían en uno.
                'id' => $addon->id,
                'icon' => $addon->iconKey(),
                // ¿Su precio cambia según el día? Entonces la cifra se anuncia con un «desde», o el
                // bloque prometería el más barato como si fuera el único.
                'varies' => ! $included && $priceCents > 0 && $addon->priceVaries(),
                'name' => (string) $addon->tr('name'),
                'badge' => $badge,
                'note' => $note,
                /*
                 * ¿Este complemento es TIEMPO y no una cosa? (`#531`). Lo dicen los dos interruptores
                 * del catálogo —ocupar la franja siguiente o alargar la estancia del padre—, **nunca
                 * el nombre**: es el mismo criterio con el que `/cumpleanos` distingue su hora extra
                 * (`#528`) y con el que el aforo la cobra (`specs/hora-extra.md`).
                 * ▶ Lo usa `/precios`, que lo lleva a una FILA de su tabla porque su precio depende
                 * del día; en el carril de escaparate no cabría esa excepción.
                 */
                'time_extra' => $addon->occupiesAfterParent() || $addon->extendsParentStay(),
                /*
                 * **El precio SUELTO, en registro de escaparate** (`#479`). `note` lleva el importe
                 * con su signo y su matiz ya pegados —«+2,00 €», «desde +2,00 €/invitado»— porque
                 * así lo pinta la lista de complementos de la tarjeta antigua. La píldora de la
                 * sección «Cuánto» escribe otra frase, «+ Calcetines · 2 €», y necesita el número
                 * a secas.
                 * ⚠️ **No es un segundo cálculo: es el MISMO `$priceCents`.** Lo que cambia es cómo
                 * se escribe — `Money::showcase()` para el escaparate, dos decimales fijos para
                 * `note`, que es el registro que ese consumidor ya tenía.
                 * ⚠️ `null` cuando no hay coste (incluido o gratis): ahí lo que se dice es el badge,
                 * no una cifra.
                 */
                'price' => ($included || $priceCents === 0) ? null : Money::showcase($priceCents),
                'perGuest' => $perGuest,
                'features' => $features,
                // Los REGALOS (`#589`): se pintan aparte, cada uno en su etiqueta.
                'gifts' => $addon->giftLines(),
                // `null` y no `''`: la vista pregunta por la EXISTENCIA del texto para decidir si
                // pinta el «Más info», y una cadena vacía es verdadera en cuanto alguien escribe
                // `isset()` en vez de un truthy.
                'description' => $description === '' ? null : $description,
                'group' => $pivot->choiceGroup(),
            ];
        }

        return $rows;
    }

    /**
     * **LOS COMPLEMENTOS DE VARIOS PRODUCTOS, UNIDOS Y SIN REPETIR** (`DECISIONES #480`).
     *
     * Lo pide la sección «Cuánto» de la portada: desde `#480` los complementos salen de las tarjetas
     * y viven en un carril debajo, con **los de los productos que se ven** —o sea los de la zona
     * activa— y **cada uno una sola vez**.
     *
     * ▶ **Existe aquí y no en la vista, y tampoco en `Booking`.** En la vista sería lógica repartida
     * por Blade; en `Booking` sería una flecha prohibida hacia `Content` (`ModuleBoundariesTest`).
     * Aquí es lo que ya es: la capa que presenta complementos.
     *
     * ⚠️⚠️ **Se deduplica por ID, nunca por nombre.** «Hora extra · KIDS» y «Hora extra · JUMP» son
     * dos productos distintos con precios distintos, y en otra instalación podrían llamarse igual:
     * fundirlos por rótulo publicaría **el precio de uno bajo el nombre del otro**.
     *
     * ⚠️ **El ORDEN es el de aparición**, que es el de los productos del carril: el primero que
     * ofrece un complemento decide dónde sale. Ordenarlo por precio o por nombre inventaría una
     * jerarquía que el catálogo no declara.
     *
     * ▶ `$withoutChoices` (`DECISIONES #528`): fuera los que pertenecen a un GRUPO de elección
     * excluyente —el menú—. `/cumpleanos` los enseña en su propio bloque con lo que lleva cada uno,
     * y en el carril serían dos fichas que parecen sumarse cuando se elige una.
     *
     * ▶ `$withoutTimeExtras` (`DECISIONES #531`): fuera los que son TIEMPO —la hora extra—. `/precios`
     * los lleva a una fila de su tabla, donde la columna dice en qué tarifa se venden; como ficha de
     * escaparate habría que escribir esa excepción a mano y encima una vez por zona.
     *
     * @param  iterable<TicketType>  $products
     * @return list<array<string, mixed>>
     */
    public static function unique(iterable $products, bool $withoutChoices = false, bool $withoutTimeExtras = false): array
    {
        $vistos = [];

        foreach ($products as $product) {
            foreach (self::rows($product, false) as $row) {
                if ($withoutChoices && $row['group'] !== null) {
                    continue;
                }
                if ($withoutTimeExtras && $row['time_extra']) {
                    continue;
                }
                $vistos[$row['id']] ??= $row;
            }
        }

        return array_values($vistos);
    }

    /**
     * **LOS GRUPOS DE ELECCIÓN EXCLUYENTE DE VARIOS PRODUCTOS** —el menú de un cumpleaños—, cada
     * complemento una sola vez (`DECISIONES #528`).
     *
     * ▶ Lo pide el bloque «Qué comen» de `/cumpleanos`: el artboard escribe que *«el menú no es un
     * complemento: es una elección dentro del pack»*, así que no vive en el carril sino en su propio
     * bloque, con lo que lleva cada opción (sus `features`).
     *
     * ⚠️ Se leen como PACK (`rows($product, true)`): el incluido se rotula «Incluido», no «Gratis».
     * ⚠️ La deduplicación es por ID, como en `unique()`, y el grupo por su CLAVE: dos packs que
     * comparten los mismos dos menús dan un grupo de dos, no uno de cuatro.
     *
     * @param  iterable<TicketType>  $products
     * @return list<array{key: string, rows: list<array<string, mixed>>}>
     */
    public static function choiceGroups(iterable $products): array
    {
        $grupos = [];

        foreach ($products as $product) {
            foreach (self::rows($product, true) as $row) {
                if ($row['group'] === null) {
                    continue;
                }
                $grupos[$row['group']][$row['id']] ??= $row;
            }
        }

        $salida = [];
        foreach ($grupos as $clave => $filas) {
            $salida[] = ['key' => (string) $clave, 'rows' => array_values($filas)];
        }

        return $salida;
    }
}
