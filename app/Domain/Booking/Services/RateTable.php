<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\ReadsRateFacts;
use App\Domain\Booking\Concerns\WritesLandingValues;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Support\Collection;

/**
 * **LA TABLA DE TARIFAS DE `/precios`** (`DECISIONES #531`, carril de diseño Fase 3 · T3b).
 * Artboard `Precios Pagina PJP` **1a** (móvil) + **1b** (escritorio).
 *
 * La página contesta «cuánto cuesta, qué día» y por eso su pieza central es una TABLA por zona con
 * **dos columnas de precio ENTERO** —la tarifa normal y la especial—, nunca un recargo: *«un recargo
 * no se publica como recargo, y menos si no es plano»* (regla dura del canvas).
 *
 * ▶ **Hermano de `RateCards`, y comparten sus reglas por el trait** (`ReadsRateFacts`): el nombre
 * sin el prefijo de la zona, los días de la normal, quién lidera y si un producto se vende en la
 * especial. Lo que cambia es la FORMA —allí tarjetas en un carril, aquí filas— y lo que la tabla
 * necesita de más: el precio de CADA tarifa por separado.
 *
 * ❗❗❗ **LA HORA EXTRA ES UNA FILA DE LA TABLA, y lo decidió el owner** (`#531`) con los datos
 * delante: el artboard dibuja una sola a 3 € «en las entradas de 2 horas» y este catálogo tiene DOS
 * productos distintos —uno por zona— que además **solo tienen precio en la tarifa especial**. En su
 * fila, la columna normal dice «—», que es exactamente lo que pasa: ese día no se vende.
 * ⚠️ **Se reconoce por el MECANISMO** (`occupiesAfterParent()`), nunca por el nombre: es el mismo
 * criterio con el que `/cumpleanos` distingue la suya (`extends_parent_stay`, `#528`).
 *
 * ⚠️ **No calcula ni un precio**: pregunta a `displayPriceCentsForRate()`, que es la resolución de
 * precio de presentación que ya usa el resto de la landing. *El precio se resuelve en un sitio.*
 */
final class RateTable
{
    use ReadsRateFacts;
    use WritesLandingValues;

    /**
     * **LA SEMANA DIBUJADA**: los siete días con su inicial y si son de tarifa especial.
     *
     * ⚠️⚠️ **El especial NO se marca con color: se marca con SUPERFICIE** —tinta contra blanco—, que
     * es lo que el artboard escribe y la misma regla del velo del carril de la portada. Aquí solo
     * viaja el HECHO; la superficie la pone la hoja.
     *
     * ⚠️ **`null` cuando no se puede afirmar** (sin tarifa especial activa, o con una que no declara
     * sus días): entonces la tira no se pinta. Dibujar siete días sin saber cuáles son especiales
     * sería un diagrama que afirma lo que nadie ha medido.
     *
     * @return list<array{initial: string, name: string, special: bool}>|null
     */
    public function week(): ?array
    {
        $normales = $this->plainWeekdays();

        if ($normales === null || $normales === []) {
            return null;
        }

        return array_map(fn (int $weekday): array => [
            // ⚠️ La INICIAL sale del diccionario y no de Carbon: en español el miércoles es «X» por
            // convenio de calendario y `dayName` daría «M», que es la del martes. El nombre COMPLETO
            // sí lo pone Carbon, y es el que lee un lector de pantalla.
            'initial' => __('landing.pricing.week_initials.'.$weekday),
            'name' => $this->dayName($weekday),
            'special' => ! in_array($weekday, $normales, true),
        ], self::WEEK_ORDER);
    }

    /**
     * El rótulo de la columna de la tarifa NORMAL: «L a J».
     *
     * ⚠️ Se DERIVA de los días que ninguna especial reclama, igual que la frase larga. Y cuando no
     * se pueden saber cae al rótulo de la propia tarifa en el panel («Día normal»), que es un dato
     * y no una suposición.
     */
    public function normalColumnLabel(): string
    {
        $normales = $this->plainWeekdays();

        if ($normales === null || $normales === []) {
            return (string) ($this->normalRate()?->tr('label') ?? '');
        }

        $inicial = fn (int $d): string => (string) __('landing.pricing.week_initials.'.$d);

        if (count($normales) === 1) {
            return $inicial($normales[0]);
        }

        // Solo como RANGO si son contiguos: con un conjunto suelto —martes y viernes— «L a V»
        // incluiría días que no son. La misma regla que la frase larga del trait.
        if ($this->areContiguous($normales)) {
            return (string) __('landing.pricing.col_range', [
                'from' => $inicial($normales[0]),
                'to' => $inicial($normales[count($normales) - 1]),
            ]);
        }

        return implode(' · ', array_map($inicial, $normales));
    }

    /** El rótulo de la columna especial, o `null` si la instalación no tiene tarifa especial. */
    public function specialColumnLabel(): ?string
    {
        return $this->specialRate() === null ? null : (string) __('landing.pricing.col_special');
    }

    /**
     * **El rótulo de la tarifa especial tal y como lo escribe el PANEL** — hoy «Viernes, findes y
     * festivos» — o `null` si no hay ninguna.
     *
     * ⚠️ Es `label` y NO `name`: `rate_types` no tiene columna `name` y `tr('name')` devolvería
     * cadena vacía **sin fallar** (el defecto que `#478` pagó en el sello de la tarjeta de zona).
     * ⚠️ Dice lo que el DATO diga: acortarlo aquí sería meter el texto de este cliente en el
     * producto. ▶ Y si ese rótulo se queda corto —las vísperas pagan especial y no las nombra,
     * `#498`— se arregla **en el panel**, que es donde vive.
     */
    public function specialLabel(): ?string
    {
        return $this->specialRate()?->tr('label') ?: null;
    }

    /** Los días de la tarifa normal, escritos («de lunes a jueves»), o `null` si no se pueden saber. */
    public function plainDays(): ?string
    {
        return $this->plainDaysPhrase();
    }

    /**
     * Una tabla por zona, con sus filas ya escritas.
     *
     * ⚠️ **Una zona sin entradas no sale**: una tabla con cabecera y sin filas es la caja vacía que
     * el sistema prohíbe («una sección cuyo contenido pone el panel desaparece con cero filas»).
     *
     * @param  Collection<int, Zone>  $zones  las zonas de la landing, en su orden
     * @param  Collection<int, TicketType>  $tickets  las entradas activas con precios y complementos
     * @return list<array<string, mixed>>
     */
    public function compose(Collection $zones, Collection $tickets): array
    {
        $porZona = $tickets->groupBy('zone_id');

        return $zones->map(function (Zone $zone) use ($porZona): ?array {
            /** @var Collection<int, TicketType> $entradas */
            $entradas = $porZona->get($zone->id, collect())->values();

            if ($entradas->isEmpty()) {
                return null;
            }

            $lidera = $this->leadingIndex($entradas);

            return [
                'slug' => $zone->slug,
                'name' => (string) $zone->tr('name'),
                // El cuadrado de color es la IDENTIDAD de la zona, que llega del panel. No es un
                // color de acción ni de estado: es de quién es esta tabla (`#436`).
                'color' => $zone->color,
                // La edad la manda la BD, como en la sección 01 (`#478`): aquí se dice una vez por
                // zona y no una vez por entrada.
                'age' => $zone->tr('age_range') ?: null,
                'rows' => array_merge(
                    $entradas->map(fn (TicketType $t, int $i): array => $this->entryRow($t, $zone, $i === $lidera))->all(),
                    $this->extraRows($entradas, $zone),
                ),
            ];
        })->filter()->values()->all();
    }

    /**
     * La fila de una entrada.
     *
     * @return array<string, mixed>
     */
    private function entryRow(TicketType $ticket, Zone $zone, bool $lidera): array
    {
        [$nombre, $matiz] = $this->nameAndNuance((string) $ticket->tr('name'), (string) $zone->tr('name'));
        $badge = $ticket->tr('badge') ?: null;
        $especial = $this->specialPriceCents($ticket);
        $dias = $this->plainDaysPhrase();

        return [
            'name' => $nombre,
            /*
             * **El `badge` de una entrada que NO lidera baja al matiz** y no se pierde (`#480`): el
             * panel lo ha escrito y tiene que salir en alguna parte. Si el nombre ya trae matiz
             * propio, manda el del nombre — es parte de cómo se llama el producto.
             */
            'nuance' => $matiz ?? ($lidera ? null : $badge),
            /*
             * **El chip es el MARCADOR de la que lidera** y su texto lo escribe el panel: la señal
             * es del diseño y la palabra del dueño. Una destacada sin `badge` no pinta chip.
             */
            'badge' => $lidera ? $badge : null,
            /*
             * ⚠️ La NOTA solo existe para decir la excepción: esta entrada **no se vende** los días
             * de la tarifa especial. Cuando sí se vende, las dos columnas ya lo cuentan y una frase
             * más sería repetir la tabla en prosa.
             */
            'note' => ($especial === null && $dias !== null)
                ? __('landing.rates.days_only', ['days' => $dias])
                : null,
            'normal' => $this->writeCents($this->normalPriceCents($ticket)),
            'special' => $this->writeCents($especial),
        ];
    }

    /**
     * **LAS FILAS DE TIEMPO EXTRA**: los complementos que ocupan la franja siguiente («Hora extra»).
     *
     * ▶ Van en la tabla y no en el bloque de complementos `[DECIDIDO owner, 2026-09-11]`: son dos
     * productos con dos precios y **solo se venden en tarifa especial**, así que en una ficha de
     * escaparate habría que escribir esa excepción a mano, mientras que en la tabla la dice la
     * columna con un «—».
     *
     * ⚠️ **Se deduplican por ID**, nunca por nombre: en otra instalación dos complementos distintos
     * pueden llamarse igual y fundirlos publicaría el precio de uno bajo el nombre del otro.
     * ⚠️ **Solo los que se venden AL RESERVAR** (`addonsSoldAtBooking()`): uno de fase `postform` no
     * se puede comprar aquí, y anunciarlo en la tabla de precios sería ofrecer lo que el embudo
     * rechaza.
     *
     * @param  Collection<int, TicketType>  $entradas
     * @return list<array<string, mixed>>
     */
    private function extraRows(Collection $entradas, Zone $zone): array
    {
        /** @var array<int, array<string, mixed>> $filas */
        $filas = [];
        /** @var array<int, list<string>> $padres */
        $padres = [];

        foreach ($entradas as $entrada) {
            [$nombrePadre] = $this->nameAndNuance((string) $entrada->tr('name'), (string) $zone->tr('name'));

            foreach ($entrada->addonsSoldAtBooking() as $addon) {
                if (! $addon->occupiesAfterParent()) {
                    continue;
                }

                [$nombre, $matiz] = $this->nameAndNuance((string) $addon->tr('name'), (string) $zone->tr('name'));

                // ⚠️ Aquí el nombre del catálogo lleva la zona como SUFIJO —«Hora extra · KIDS»—, así
                // que el matiz sale siendo la zona: dentro de su propia tabla, decirlo sería repetir
                // la cabecera en cada fila.
                if ($matiz !== null && mb_strtolower($matiz) === mb_strtolower((string) $zone->tr('name'))) {
                    $matiz = null;
                }

                $filas[$addon->id] ??= [
                    'name' => $nombre,
                    'nuance' => $matiz,
                    'badge' => null,
                    'note' => null,
                    'normal' => $this->writeCents($this->normalPriceCents($addon)),
                    'special' => $this->writeCents($this->specialPriceCents($addon)),
                ];

                $padres[$addon->id][] = $nombrePadre;
            }
        }

        foreach ($filas as $id => $fila) {
            // **Con qué entradas se puede comprar**, escrito desde el PIVOTE: es la diferencia entre
            // «se añade a lo que quieras» y «solo con la de 2 horas», y el catálogo ya lo sabe.
            $filas[$id]['note'] = __('landing.pricing.with_entry', [
                'entries' => implode(', ', array_unique($padres[$id])),
            ]);
        }

        return array_values($filas);
    }

    /**
     * El importe escrito, o `null` cuando ese día no se vende.
     *
     * ⚠️ `null` NO es «gratis» ni «0 €»: es que ese día el producto no está a la venta, y la tabla
     * lo pinta con una raya. Distinguirlo aquí evita que la vista tenga que inventar la diferencia.
     */
    private function writeCents(?int $cents): ?string
    {
        return $cents === null ? null : $this->euros($cents);
    }
}
