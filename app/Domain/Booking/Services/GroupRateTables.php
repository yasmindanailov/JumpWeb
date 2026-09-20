<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\ReadsRateFacts;
use App\Domain\Booking\Concerns\WritesLandingValues;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * **LA TABLA DE PRECIOS DE GRUPO de un servicio, leída de sus PRODUCTOS** (`DECISIONES #588`).
 *
 * Hasta `#588` `/servicios` pintaba una tabla TECLEADA en el contenido del servicio (`price_table`)
 * mientras el precio que se cobra vive en los tramos del producto: dos fuentes del mismo número que
 * solo cuadraban porque alguien las escribía a la vez. Aquí la tabla sale de lo que se vende.
 *
 * ▶ Una tabla por producto, una fila por TRAMO («desde N») y una columna por tarifa, con el precio
 * ENTERO de cada una — nunca un recargo (`#479`).
 * ⚠️ No calcula ni un precio: pregunta a `priceCentsForRate()`, la resolución única de los tramos
 * (`#329`), así que la tabla dice lo mismo que cobra la cesta.
 * ⚠️ Un tramo por debajo del mínimo contratable no se escribe: por debajo del mínimo manda el primer
 * tramo (`#329`) y esa fila repetiría la del mínimo. Sin tramos, la tabla tiene una sola fila.
 */
final class GroupRateTables
{
    use ReadsRateFacts;
    use WritesLandingValues;

    /**
     * @param  Collection<int, TicketType>  $products  con `priceTiers` y `prices.rateType` cargados
     * @return list<array{id: int, name: string, unit: ?string, badge: ?string, gifts: list<string>, lowest_cents: ?int, rows: list<array{from: int, normal: ?string, special: ?string}>}>
     */
    public function compose(Collection $products): array
    {
        $normal = $this->normalRate();
        $special = $this->specialRate();

        return $products->map(function (TicketType $product) use ($normal, $special): array {
            $minimo = $product->contractableMinimum();

            // ⚠️ `toBase()`: sin tramos, `map()` devuelve una colección ELOQUENT vacía, y su `unique()`
            // compara por `getKey()` — con el entero del mínimo dentro revienta con un 500.
            $desde = $product->priceTiers->toBase()
                ->map(fn ($tier): int => (int) $tier->min_qty)
                ->push($minimo)
                ->filter(fn (int $q): bool => $q >= $minimo)
                ->unique()->sort()->values();

            $filas = $desde->map(fn (int $q): array => [
                'from' => $q,
                'normal' => $normal ? $product->priceCentsForRate($normal, $q) : null,
                'special' => $special ? $product->priceCentsForRate($special, $q) : null,
            ]);

            return [
                'id' => (int) $product->id,
                'name' => (string) $product->tr('name'),
                'unit' => $product->tr('period_label') ?: null,
                'badge' => $product->tr('badge') ?: null,
                // Los REGALOS del servicio (`#589`): «un profesor gratis por cada 15 alumnos».
                'gifts' => $product->giftLines(),
                // El «desde» del servicio es el precio MÁS BAJO de sus tablas (`#329`: el más barato).
                'lowest_cents' => $filas->flatMap(fn (array $f): array => [$f['normal'], $f['special']])->filter()->min(),
                'rows' => $filas->map(fn (array $f): array => [
                    'from' => $f['from'],
                    // `null` no es «gratis»: es que con esa tarifa no se vende, y la vista pinta la raya.
                    'normal' => $this->written($f['normal']),
                    'special' => $this->written($f['special']),
                ])->all(),
            ];
        })->values()->all();
    }

    /**
     * **El «desde» de un servicio: el precio MÁS BAJO de todas sus tablas, ya escrito** (`#329`, `#660`).
     *
     * ⚠️⚠️ **Las dos mitades estaban en la VISTA, y las dos son del producto.** Cuál es el precio que se
     * anuncia —el más bajo de todas las tablas, saltándose los `null`, que no son «gratis» sino «ese día no
     * se vende»— es una regla de catálogo; y cómo se escribe, la del escaparate ({@see euros}). Escritas en
     * una landing, cada instancia las re-deriva: una que tomara el máximo, u olvidara el filtro, anunciaría
     * un «desde» que no existe.
     *
     * ❗ **Y ahí vivía un defecto medido** (`#660`): la vista escribía este importe con `Money::format()` —el
     * registro de TRANSACCIÓN, dos decimales fijos y coma siempre—, así que en la misma pantalla y en
     * inglés convivían «from 14.95 €» (la tarjeta de cumpleaños, escrita por el producto) y «12,00 €»
     * (esta línea). Es el mismo defecto que `#651` cazó en la portada.
     *
     * ⚠️ El `filter()` es EXPLÍCITO, no imprescindible: medido, `Collection::min()` ya ignora los `null`.
     * Se deja porque la intención —«un `null` no es un precio»— se lee en la línea y no en la documentación
     * del framework; su mutante se retiró del arnés por equivalente, con el motivo escrito allí.
     *
     * @param  list<array{lowest_cents: ?int, ...}>  $tables  lo que devuelve {@see compose}
     */
    public function lowestWritten(array $tables): ?string
    {
        $cents = collect($tables)->pluck('lowest_cents')->filter()->min();

        return $cents === null ? null : $this->euros((int) $cents);
    }

    private function written(?int $cents): ?string
    {
        return $cents === null ? null : $this->euros($cents);
    }
}
