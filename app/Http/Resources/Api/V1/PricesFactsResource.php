<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Concerns\ReadsRateFacts;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * **`GET /api/v1/prices` — LOS PRECIOS POR TARIFA** (F5 · T5 del menú,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * ⚠️⚠️ **Esto NO duplica `/catalog/products`, y merece decirse porque lo parece.** Aquel recurso —el del
 * cajón— publica el **«desde»** de cada producto (`from_price_cents`) y si su precio varía, que es lo que el
 * embudo necesita para abrir una ficha. Lo que una landing pinta en su carril de tarifas es otra cosa: **el
 * precio de CADA TARIFA**, en columnas —«Lunes a jueves» y «Viernes, fines de semana, vísperas y festivos»—,
 * y ese desglose no estaba en ningún sitio público. Un hecho, un sitio: el «desde» sigue viviendo en el
 * catálogo y aquí no se repite.
 *
 * ⚠️⚠️ **El «precio de antes» tachado de la promo NO viaja, y es una decisión, no un olvido.** `#628` es una
 * chapuza DECLARADA —dos filas de `settings` que pintan un tachado sobre precios ya rebajados— con fecha de
 * caducidad: la retira el owner al subir los precios. Meterla en el contrato público obligaría a sacarla
 * después, y un contrato no se rompe para desmontar un apaño temporal. El mecanismo de ofertas que `#631`
 * decidió —«oferta» como HECHO de precio— es quien traerá esto, con su forma pensada.
 *
 * ⚠️ Los importes viajan en **céntimos enteros**, nunca en euros con coma: un `12.40` en coma flotante es
 * dinero que se redondea solo en algún cliente. El símbolo y el formato los pone quien pinta, que es quien
 * sabe en qué idioma y con qué separador escribe.
 */
class PricesFactsResource extends JsonResource
{
    // ⚠️ Se trae la regla de los días normales en vez de re-derivarla (`#676`): el trait ya sabe
    // que una tarifa especial ACTIVA sin días declarados hace la respuesta INDECIDIBLE, y eso es
    // lo que una copia se dejaría. La misma que lee la página `/precios`.
    use ReadsRateFacts;

    public static $wrap = null;

    /**
     * @param  Collection<int, TicketType>  $productos
     * @param  Collection<int, RateType>  $tarifas
     */
    public function __construct(
        private readonly Collection $productos,
        private readonly Collection $tarifas,
    ) {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        // ⚠️ La MISMA regla que usa la página `/precios`, no una copia: `plainWeekdays()` vive en
        // `ReadsRateFacts` y ya sabe que un «no se puede saber» no es una lista vacía (`#676`).
        $normales = $this->plainWeekdays();

        return [
            'lang' => app()->getLocale(),
            // ⚠️ **La moneda sale de los PRECIOS, que la llevan por fila.** Es lo que se descubre al mirar
            // la tabla en vez de suponerla: `prices` tiene columna `currency` —hoy `EUR` en las 57 filas—.
            // El primer borrador la puso constante «porque el producto vende en euros»; con una fuente
            // real al lado, escribirla a mano es inventarse un hecho que la BD ya sabe.
            // ⚠️ Y NO sale del ajuste `redsys_currency`: los `redsys_*` no son hechos públicos —los niega
            // `PublicFacts`— y colar la configuración de la pasarela en un recurso de landing es abrir la
            // puerta que ese lector existe para cerrar.
            'currency' => $this->moneda(),
            // El catálogo de tarifas CON SU RÓTULO: sin él, una landing tendría que escribir «Viernes,
            // fines de semana, vísperas y festivos» a mano y quedarse desfasada el día que el negocio lo
            // cambie en el panel — que es justo el defecto que este menú existe para no repetir.
            'rates' => $this->tarifas->map(fn (RateType $tarifa): array => array_filter([
                'key' => (string) $tarifa->key,
                'label' => (string) $tarifa->tr('label'),
                'special' => (bool) $tarifa->is_special,
                // Los días que ESTA tarifa reclama, `0 = domingo` como en `/schedule`. Falta en la
                // tarifa que no reclama ninguno —la normal—, porque es la que se aplica a lo que
                // sobra, no a una lista.
                'weekdays' => is_array($tarifa->weekdays) && $tarifa->weekdays !== []
                    ? array_values(array_map('intval', $tarifa->weekdays))
                    : null,
            ], fn ($valor): bool => $valor !== null))->values()->all(),
            // ⚠️⚠️ **La derivación viaja hecha, y `null` NO significa «ninguno»: significa «no se
            // puede saber»** — el caso es una tarifa especial ACTIVA que no declara sus días, y ahí
            // el producto no puede afirmar cuáles son normales. Una landing que restara «7 menos los
            // especiales» publicaría una semana inventada sin enterarse. Es el mismo motivo por el
            // que `/site` publica `address.written` y `/rules` su `summary`: la regla es del
            // producto, y tiene un modo de fallo que el cliente no puede ver.
            ...($normales === null ? [] : ['plain_weekdays' => $normales]),
            'products' => $this->productos->map(fn (TicketType $producto): array => array_filter([
                'id' => $producto->id,
                'zone' => $producto->zone?->slug,
                'name' => $producto->tr('name'),
                // La unidad de venta —«por persona», «por hora»— la escribe el panel y sin ella un número
                // suelto no dice qué se compra.
                'unit' => $producto->tr('period_label') ?: null,
                'prices' => $this->porTarifa($producto),
            ], fn ($valor): bool => $valor !== null && $valor !== []))->values()->all(),
        ];
    }

    /**
     * La moneda de los precios que se están sirviendo.
     *
     * ⚠️ Si un día hubiera dos, esto se quedaría con una y mentiría — por eso devuelve la del primer precio
     * y no un `EUR` escrito a mano: el día que aparezca una segunda, el hecho estará en la tabla y esta
     * línea será el sitio donde arreglarlo. Sin precios, `EUR`, que es lo que formatea `Money`.
     */
    private function moneda(): string
    {
        foreach ($this->productos as $producto) {
            foreach ($producto->prices as $precio) {
                if ($precio->currency !== '') {
                    return $precio->currency;
                }
            }
        }

        return 'EUR';
    }

    /**
     * El precio de ese producto en cada tarifa, omitiendo las que no tiene.
     *
     * ⚠️ **Una tarifa sin precio NO viaja como `0`.** Un cero es un precio —y uno muy llamativo—; lo que
     * pasa de verdad es que ese producto no se vende en esa tarifa, y eso se dice callando.
     *
     * @return list<array{rate: string, cents: int}>
     */
    private function porTarifa(TicketType $producto): array
    {
        $salida = [];

        foreach ($this->tarifas as $tarifa) {
            $cents = $producto->priceCentsForRate($tarifa);

            if ($cents !== null) {
                $salida[] = ['rate' => (string) $tarifa->key, 'cents' => (int) $cents];
            }
        }

        return $salida;
    }
}
