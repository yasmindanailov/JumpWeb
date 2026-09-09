<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Support\Collection;

/**
 * **LAS TARJETAS DE LA SECCIÓN «PARA QUIÉN»** (`docs/specs/rediseno-desde-canvas.md` §5.4 · T2b ·
 * `DECISIONES #478`).
 *
 * La primera sección de la portada presenta las zonas del parque: una tarjeta por zona, con su
 * precio de referencia, a quién va dirigida y qué hay dentro. **La tarjeta entera es el enlace**
 * (decisión del marco aprobado del canvas), así que no lleva botón.
 *
 * ▶ **Existe para que la vista no componga nada.** Sin esto, la plantilla tendría que cruzar zonas
 * con entradas, elegir la más barata, sacar la tarifa especial y redactar la regla de altura — y
 * eso es lógica de presentación repartida por Blade, que es como nacen las seis copias de una regla
 * que este repo persigue.
 *
 * ⚠️⚠️ **NO calcula ni un precio.** Pregunta a `TicketType::displayPriceCents()` y a
 * `specialRateSurcharges()`, que son los que el producto ya usa en el resto de la landing. *El
 * precio de presentación se resuelve en un sitio*, y este servicio es un consumidor más — nunca un
 * séptimo camino (`#329`).
 *
 * ⚠️⚠️ **Vive en Booking y no en Content, y lo dijo la guarda de fronteras.** Compone datos de
 * Booking —zonas, entradas y sus precios— y `Content` solo puede mirar a `Booking\Contracts`
 * (`ModuleBoundariesTest`). Meterlo en Content obligaba a inventar un contrato para lo que es
 * presentación de este módulo, o a debilitar el grafo; ninguna de las dos vale por una tarjeta.
 *
 * ⚠️ **Todo lo que pinta sale de la BD**: nombre, descripción y edad son campos traducibles de
 * `zones`, la altura son sus dos columnas nuevas y el precio, del catálogo. Una instalación sin
 * altura no pinta regla; una zona sin entrada vendible no pinta precio. **Vacío es una respuesta.**
 */
final class ZoneCards
{
    /**
     * @param  Collection<int, Zone>  $zones  las zonas de la landing, en su orden
     * @param  Collection<int, TicketType>  $tickets  las entradas activas con `prices.rateType`
     * @return list<array<string, mixed>>
     */
    public function compose(Collection $zones, Collection $tickets): array
    {
        $porZona = $tickets->groupBy('zone_id');

        return $zones->map(function (Zone $zone) use ($porZona): array {
            /** @var Collection<int, TicketType> $entradas */
            $entradas = $porZona->get($zone->id, collect());

            // La entrada de referencia es la MÁS BARATA de la zona, que es lo que el «desde»
            // promete. Con una sola entrada da lo mismo; con varias, prometer otra sería anunciar
            // un precio que no es el más bajo que existe (el criterio de `#324`).
            $referencia = $entradas
                ->sortBy(fn (TicketType $t): int => $t->displayPriceCents())
                ->first();

            $especial = $referencia ? ($referencia->specialRateSurcharges()[0] ?? null) : null;

            return [
                'slug' => $zone->slug,
                'name' => $zone->tr('name'),
                'description' => $zone->tr('description'),
                'age' => $zone->tr('age_range'),
                'height' => $this->heightRule($zone),
                // El precio llega YA ESCRITO, no en céntimos: si la vista formateara, habría una
                // séptima forma de escribir un importe en la web y se separaría de las otras seis
                // en cuanto alguien tocara una.
                'from' => $referencia ? $this->euros($referencia->displayPriceCents()) : null,
                // ⚠️ **`$especial` es `null` cuando la zona no tiene tarifa especial, que es el caso
                // NORMAL fuera de este parque** — y `$especial['rate']` sobre `null` no devuelve
                // `null`: lanza. Lo cazó la suite, no la máquina de quien lo escribió, porque los
                // datos locales sí tienen tarifa de finde. *Un dato presente en tu BD no es un dato
                // que exista.*
                'special' => isset($especial['priceCents']) ? $this->euros((int) $especial['priceCents']) : null,
                // ⚠️ Es `label` y NO `name`: `rate_types` no tiene columna `name`, así que `tr('name')`
                // devolvía cadena vacía y el sello pintaba «14 €» a secas. No fallaba nada — un
                // rótulo ausente se ve igual que un rótulo que no toca. Lo vio el navegador.
                // ⚠️ Y dice lo que el DATO diga: hoy «Viernes, findes y festivos» donde el mockup
                // escribe «finde». Acortarlo aquí sería escribir el texto de este cliente en el
                // producto; si se quiere más corto, se cambia en el panel.
                'specialLabel' => ($especial['rate'] ?? null)?->tr('label'),
                'accent' => $zone->accent,
            ];
        })->values()->all();
    }

    /**
     * La regla de altura de una zona, ya redactada — o `null` si no tiene.
     *
     * ⚠️ **La misma cifra significa lo contrario según la columna**: 130 en `height_max_cm` es
     * «hasta» y en `height_min_cm` es «a partir de». Por eso son dos columnas y no un número con el
     * sentido deducido de qué zona sea, que es el tipo de regla implícita que nadie encuentra luego.
     */
    private function heightRule(Zone $zone): ?string
    {
        $min = $zone->height_min_cm;
        $max = $zone->height_max_cm;

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null) {
            return __('landing.zones.height_between', ['a' => $this->metros($min), 'b' => $this->metros($max)]);
        }

        return $min !== null
            ? __('landing.zones.height_from', ['h' => $this->metros($min)])
            : __('landing.zones.height_up_to', ['h' => $this->metros($max)]);
    }

    /**
     * Centímetros enteros → metros escritos en el idioma que toca.
     *
     * ⚠️ **El separador decimal es del IDIOMA, no del dato**: «1,30 m» en español y en francés,
     * «1.30 m» en inglés. Escribirlo siempre con coma deja el inglés mal, y dejarlo al
     * `number_format` por defecto lo deja mal en los otros dos. Es un mapa de tres entradas porque
     * el sitio tiene tres idiomas; el día que entre un cuarto, se añade aquí y no en seis vistas.
     */
    private function metros(int $cm): string
    {
        return number_format($cm / 100, 2, $this->coma(), '');
    }

    /**
     * Céntimos → importe escrito, **sin decimales cuando son cero**.
     *
     * ⚠️ «10 €» y no «10,00 €»: el sello de la tarjeta es una cifra de escaparate, y dos decimales
     * a cero solo añaden ruido a un número que se lee de un vistazo. Cuando los hay —14,95— se
     * escriben, porque ahí sí dicen algo.
     */
    private function euros(int $cents): string
    {
        $decimales = $cents % 100 === 0 ? 0 : 2;

        return number_format($cents / 100, $decimales, $this->coma(), '.').' €';
    }

    /**
     * El separador decimal del idioma.
     *
     * ⚠️ Es del IDIOMA, no del dato: «1,30 m» en español y en francés, «1.30 m» en inglés. Dejarlo
     * al `number_format` por defecto lo deja mal en dos de los tres idiomas del sitio.
     */
    private function coma(): string
    {
        return app()->getLocale() === 'en' ? '.' : ',';
    }
}
