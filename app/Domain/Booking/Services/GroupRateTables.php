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
 *
 * ▶ **Las FILAS las comparten dos lectores** (`#677`): esta tabla, que la vista recibe escrita, y
 * `/api/v1/prices`, que publica la misma escalera en céntimos. Por eso {@see quantities} es pública: si
 * cada uno derivara sus filas, el día que uno aprendiera una regla (el mínimo de `#329`, el máximo de
 * `#677`) la web y la API anunciarían escaleras distintas del mismo producto.
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
            $filas = $this->quantities($product)->map(fn (int $q): array => [
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
     * **Las cantidades que abren una fila de la escalera**: el mínimo contratable y cada tramo que la
     * cesta puede alcanzar, de menor a mayor.
     *
     * ⚠️ Solo decide CUÁNTOS; el precio de cada fila lo pone quien la lee, preguntando a
     * `priceCentsForRate()`, así que la escalera dice lo mismo que cobra la cesta.
     * ⚠️ **Por debajo del mínimo no se escribe** (`#329`): ahí manda el primer tramo y la fila repetiría la
     * del mínimo.
     * ❗ **Y por encima del MÁXIMO de un pack tampoco** (`#677`): `OrderCreator` rechaza esa cantidad —también
     * al operador—, así que su fila anunciaría un precio que nadie puede comprar, y el «desde» saldría de
     * él. El panel deja guardar ese tramo; la escalera no lo publica.
     * ⚠️ Un COMPLEMENTO no tiene escalera: los tramos no le aplican (`TicketType::tierPriceCents()`), así que
     * aunque tuviera filas en `price_tiers` todas dirían su precio de siempre.
     *
     * @return Collection<int, int>
     */
    public function quantities(TicketType $product): Collection
    {
        $minimo = $product->contractableMinimum();

        if ($product->isAddon()) {
            return collect([$minimo]);
        }

        $maximo = $product->isPack() ? $product->max_qty : null;

        // ⚠️ `toBase()`: sin tramos, `map()` devuelve una colección ELOQUENT vacía, y su `unique()`
        // compara por `getKey()` — con el entero del mínimo dentro revienta con un 500.
        return $product->priceTiers->toBase()
            ->map(fn ($tier): int => (int) $tier->min_qty)
            ->push($minimo)
            ->filter(fn (int $q): bool => $q >= $minimo && ($maximo === null || $q <= $maximo))
            ->unique()->sort()->values();
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
