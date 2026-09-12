<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\WritesLandingValues;
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
    use WritesLandingValues;

    /**
     * **El techo de la escala de altura, en centímetros.**
     *
     * ⚠️ Es una constante de PRESENTACIÓN y no un dato: la barra necesita un tope contra el que
     * colocar el umbral, y 1,90 m es la estatura por encima de la cual la regla deja de decir nada a
     * nadie. Sale del artboard, donde la escala va de 0,00 a 1,90 y el 1,30 cae **al 68 %** — que es
     * exactamente lo que aquí se calcula en vez de escribirse.
     * ▶ Si un día una instalación necesitara otro tope, se convierte en columna; hasta entonces, un
     * ajuste que nadie va a tocar no se pone en el panel.
     */
    public const ESCALA_CM = 190;

    /**
     * **Las zonas que TIENEN regla de altura**, que son las que pueden nombrarse como vecinas al
     * otro lado de la frontera. La tarjeta dice qué hay arriba o abajo de su línea, y eso solo se
     * sabe mirando a las demás — por eso se resuelve fuera del `map`, que solo ve una.
     *
     * @param  Collection<int, Zone>  $zones
     * @return Collection<int, Zone>
     */
    private static function withHeightRule(Collection $zones): Collection
    {
        return $zones->filter(fn (Zone $z): bool => $z->height_min_cm !== null || $z->height_max_cm !== null);
    }

    /**
     * @param  Collection<int, Zone>  $zones  las zonas de la landing, en su orden
     * @param  Collection<int, TicketType>  $tickets  las entradas activas con `prices.rateType`
     * @return list<array<string, mixed>>
     */
    public function compose(Collection $zones, Collection $tickets): array
    {
        $porZona = $tickets->groupBy('zone_id');
        $conAltura = self::withHeightRule($zones);

        return $zones->map(function (Zone $zone) use ($porZona, $conAltura): array {
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
                /*
                 * El COLOR de la zona, para el velo de la tarjeta. Sale de `zones.color` y no de un
                 * token `--zone-N`: esos los reparte el JS por posición y **las dos zonas caían al
                 * mismo fallback**, así que el velo salía idéntico en las dos (medido: cian en Kids
                 * y en Jump). Con el dato, cada una lleva el suyo — y coincide con el artboard, que
                 * pinta Jump en lima y Kids en cian.
                 *
                 * ⚠️⚠️ **Se SANEA porque va a un atributo `style`**: un valor de BD dentro de un
                 * `style` es superficie de inyección, y Blade escapa el texto pero no valida que sea
                 * un color. Solo pasa un hexadecimal; cualquier otra cosa se descarta y la tarjeta
                 * se queda sin velo, que es el modo de fallo correcto — invisible, nunca roto.
                 */
                'tint' => $this->hexOrNull($zone->color),
                // La OPACIDAD del velo, compensada por la luminancia del color. Ver `tintOpacity()`.
                'tintOpacity' => $this->tintOpacity($this->hexOrNull($zone->color)),
                // La FOTO de la zona, a 16:9 en la tarjeta. ⚠️ `zones.image` se quedó **sin
                // consumidor** en `#302` al retirar las tarjetas viejas, y quedó fichado en
                // `DEUDA.md` con tres salidas. Ésta es una de ellas: vuelve a tener pantalla.
                'image' => $zone->image ? asset($zone->image) : null,
                // Las tres piezas del EJE DE ALTURA, o `null` si esta zona no tiene regla.
                'heightAxis' => $this->axisFor($zone, $conAltura),
            ];
        })->values()->all();
    }

    /**
     * El eje de altura de una tarjeta: dónde cae su frontera y qué zona hay al otro lado.
     *
     * ⚠️⚠️ **La frontera la coloca el LAYOUT y la línea CRUZA la regla vertical, que es como el
     * artboard la dibuja** —comprobado renderizando su propio marcado y comparando la misma zona—.
     * Hubo dos intentos de colocarla por porcentaje del umbral: el primero la separaba **124 px** de
     * la marca del eje cuando el texto no cabía en su tramo, y el segundo dejaba el hueco de la
     * vecina tan corto que **se montaba encima del CTA**. La línea es la costura entre los dos
     * bloques, así que no hay dos números que puedan discrepar.
     *
     * ⚠️ **`side` dice si la zona está por ENCIMA o por DEBAJO de su frontera**, y de ahí sale todo
     * lo demás: qué mitad se tiñe y en qué lado se nombra a la vecina. Sale de qué columna lleva el
     * umbral, no de adivinar qué zona es.
     *
     * @param  Collection<int, Zone>  $conAltura  las zonas que tienen regla, para nombrar la vecina
     * @return array{cm: int, label: string, side: string, neighbour: ?string}|null
     */
    private function axisFor(Zone $zone, Collection $conAltura): ?array
    {
        $min = $zone->height_min_cm;
        $max = $zone->height_max_cm;

        if ($min === null && $max === null) {
            return null;
        }

        $cm = $min ?? $max;
        $vecina = $conAltura
            ->first(fn (Zone $otra): bool => $otra->id !== $zone->id
                && ($min !== null ? $otra->height_max_cm !== null : $otra->height_min_cm !== null));

        return [
            'cm' => $cm,
            'label' => $this->metros($cm).' m',
            'side' => $min !== null ? 'above' : 'below',
            'neighbour' => $vecina?->tr('name'),
        ];
    }

    /**
     * **La opacidad del velo, compensada por la luminancia del color.**
     *
     * ⚠️⚠️ **El artboard NO usa la misma opacidad en las dos tarjetas**, y eso no es un descuido
     * suyo: el cian va al **22 %** y el lima al **24 %**. El lima es más claro —luminancia 0,45
     * contra 0,34—, así que al mismo porcentaje pesaría menos; sube dos puntos para que los dos
     * velos se lean con la misma fuerza. Es compensación óptica, y el owner lo vio antes que la
     * sonda: *«el velo es diferente en cada card»*.
     *
     * ▶ **Se calcula en vez de copiarse**, y ésa es la diferencia entre un producto y la web de este
     * parque: una zona con cualquier otro color obtiene su propia compensación sin que nadie ajuste
     * nada a ojo. La recta está calibrada con los dos valores del artboard —los reproduce al
     * dígito— y **se acota a [0,18 · 0,28]** para que un color extremo no se dispare fuera de lo que
     * el sistema considera un velo.
     *
     * ⚠️ `null` cuando no hay color: sin velo no hay opacidad que dar.
     */
    private function tintOpacity(?string $hex): ?float
    {
        if ($hex === null) {
            return null;
        }

        $l = $this->relativeLuminance($hex);

        // ⚠️ DOS decimales, no tres: el sistema escribe la opacidad en centésimas y con tres salía
        // 0,242 donde el artboard pone 0,24 — una diferencia invisible que igualmente hacía que la
        // comparación no cerrara. Un valor que se compara con otro se redondea como el otro.
        return round(min(0.28, max(0.18, 0.1615 + 0.1732 * $l)), 2);
    }

    /**
     * Luminancia relativa (WCAG) de un hexadecimal, de 0 (negro) a 1 (blanco).
     *
     * ⚠️ Es la misma fórmula que el sistema de diseño usa para el contraste, así que la
     * compensación del velo habla el mismo idioma que el resto de decisiones de color.
     */
    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $canal = static function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $canal((int) hexdec(substr($hex, 0, 2)))
            + 0.7152 * $canal((int) hexdec(substr($hex, 2, 2)))
            + 0.0722 * $canal((int) hexdec(substr($hex, 4, 2)));
    }

    /**
     * Un color de BD **solo si es un hexadecimal**; si no, `null`.
     *
     * ⚠️ No es paranoia: el valor viaja a un atributo `style`, y ahí un texto arbitrario deja de ser
     * un dato para ser CSS. Blade escapa comillas, pero `#fff;} body{display:none` seguiría siendo
     * texto válido dentro de una declaración. Se valida la FORMA, que es lo único que hace que el
     * dato sea un color.
     */
    private function hexOrNull(?string $color): ?string
    {
        return is_string($color) && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color) === 1
            ? $color
            : null;
    }

    /** El techo de la escala, escrito, para rotular la barra. */
    public function ceilingLabel(): string
    {
        return $this->metros(self::ESCALA_CM).' m';
    }

    /**
     * La altura en METROS, escrita como la escribe el sitio («1,30»).
     *
     * ⚠️ **Es pública desde `#533` porque `/normas` dibuja la MISMA escala** con las mismas
     * fronteras: si allí se formateara aparte, la portada y la página de normas podrían escribir
     * «1,30» y «1,3» para el mismo umbral. Un formato, un sitio.
     */
    public function metersLabel(int $cm): string
    {
        return $this->metros($cm);
    }

    /** El suelo de la escala, escrito. */
    public function floorLabel(): string
    {
        return $this->metros(0);
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
}
