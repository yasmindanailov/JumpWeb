<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\AddonOffer;
use App\Domain\Booking\Contracts\CartPricing;
use App\Http\Api\CartPayload;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ResolvedAddonsResource;
use Illuminate\Http\Request;

/**
 * Fase 4 · paso 4.0b·5 — **los complementos resueltos, con el dinero de la línea**
 * (`docs/specs/sidebar-spa.md` §4.4.1 hueco 5 y §4.4.3, `DECISIONES #41`).
 *
 * El catálogo publica la CONFIGURACIÓN de cada enganche; esto publica el **resultado de aplicarla**
 * a lo que el cliente lleva elegido: la partición en grupos excluyentes, las notas de precio, la
 * etiqueta, las unidades gratis, los topes, la poda **en cadena** de las dependencias «requiere» y
 * la regla de que un complemento de pago sin tarifa ese día ni se ofrece. Reimplementar eso en el
 * cliente es lo que `CE-4` prohíbe.
 *
 * ### Por qué devuelve también el dinero de la línea
 *
 * Es la pantalla con más clics del embudo —una configuración típica son 8–12— y cada clic cambia el
 * importe. Con dos endpoints serían **2 peticiones por clic**, es decir 16–24 contra un
 * `throttle:api` de 60/min **compartido** con disponibilidad, catálogo y presupuesto; detrás de un
 * NAT el cubo es por IP. Medido antes de decidirlo: componer las dos cosas en una petición cuesta
 * **las mismas consultas** que pedirlas por separado, con la mitad de viajes y de fichas de límite.
 *
 * ⚠️ **Y el dinero no se calcula aquí**: se pide a `CartPricing`, la misma implementación que sirve
 * `orders/quote` y que alimenta la creación del pedido. `PAY-12` exige una sola fuente de CÁLCULO,
 * no una sola URL — un total compuesto aquí a mano sería la segunda aritmética.
 *
 * De paso cierra un fallo silencioso: `CartPricer::resolveAddons()` captura cualquier error del
 * resolutor y **tarifica la línea sin complementos**, así que una selección inconsistente daría un
 * pie sin ellos mientras las filas muestran sus importes. Aquí la selección que se tarifica es la
 * que el propio dominio acaba de resolver —obligatorios inyectados, huérfanos podados—, así que ese
 * `catch` no puede dispararse por lo que mande el cliente.
 *
 * **Público**, como el catálogo y el presupuesto: la web deja llegar hasta el pago sin cuenta.
 */
class CatalogAddonsController extends Controller
{
    public function __invoke(Request $request, int $product, AddonOffer $addons, CartPricing $pricing): ResolvedAddonsResource
    {
        $validated = $request->validate([
            // La cantidad de la línea: los invitados de un pack. Decide la cantidad de los
            // complementos por-invitado, así que no es opcional ni sustituible por un defecto.
            'quantity' => ['required', 'integer', 'min:1'],
            // Fecha y hora de la línea. Para TARIFICARLA —el precio del producto base depende del
            // día— y, desde la hora extra (`specs/hora-extra.md` §4.5), también para RESOLVER los
            // complementos que OCUPAN: sin la hora no se sabe si su franja siguiente existe y cabe.
            // El PRECIO de los complementos sigue siendo el de «hoy» (§4.11, `[DECIDIDO owner]`).
            // Sin ellas se devuelven los complementos sin el pie y sin decorar la ocupación.
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'time' => ['sometimes', 'date_format:H:i:s,H:i'],
            // Cantidades de los complementos de cantidad libre.
            'addons' => ['sometimes', 'array'],
            'addons.*.product_id' => ['required', 'integer', 'min:1'],
            'addons.*.quantity' => ['required', 'integer', 'min:0'],
            // El elegido de cada grupo excluyente. Va aparte de las cantidades porque dentro de un
            // grupo lo que selecciona es SER el elegido, no tener cantidad: con una lista plana, dos
            // miembros marcados serían ambiguos y el servidor tendría que desempatar por su cuenta.
            'choices' => ['sometimes', 'array'],
            'choices.*.group' => ['required', 'string', 'max:100'],
            'choices.*.product_id' => ['required', 'integer', 'min:1'],
        ]);

        $quantity = (int) $validated['quantity'];

        // La fecha y la hora entran también en la RESOLUCIÓN desde la hora extra
        // (`specs/hora-extra.md` §4.5): un complemento que OCUPA la franja siguiente no se ofrece
        // a una hora donde no aterriza, y el que aterriza sale capado por sus plazas reales. El
        // comentario histórico de arriba («nunca para resolver») murió con esa feature.
        $resolved = $addons->resolve(
            $product,
            $quantity,
            self::quantities($validated['addons'] ?? []),
            self::choices($validated['choices'] ?? []),
            $validated['date'] ?? null,
            $validated['time'] ?? null,
        );

        // Un producto que no está en el catálogo responde 404, igual que en `catalog/products/{id}`.
        abort_if($resolved === null, 404);

        $resource = new ResolvedAddonsResource($resolved);

        // El pie solo se puede componer si hay día y hora: el precio del producto base es del día.
        // Sin ellos la respuesta sigue siendo útil —los complementos y su total— y `line` viaja
        // nula, que es más honesto que devolver un importe calculado sobre una fecha inventada.
        if (isset($validated['date'], $validated['time'])) {
            $quote = $pricing->quote([CartPayload::toLine([
                'product_id' => $product,
                'date' => $validated['date'],
                'time' => $validated['time'],
                'quantity' => $quantity,
                'addons' => array_map(static fn (array $line): array => [
                    'product_id' => $line['ticket_type_id'],
                    'quantity' => $line['qty'],
                ], $resolved->selection),
            ])]);

            $resource->withQuote($quote);
        }

        return $resource;
    }

    /**
     * `[{product_id, quantity}]` → `[id => qty]`, descartando los ceros.
     *
     * Un 0 se acepta en la petición y NO llega al dominio: es como un cliente dice «este ya no lo
     * quiero» sin tener que reconstruir la lista entera, y para el resolutor «ausente» y «cero» son
     * lo mismo. Rechazarlo con un 422 obligaría a cada cliente a filtrar antes de preguntar.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $addons
     * @return array<int, int>
     */
    private static function quantities(array $addons): array
    {
        $out = [];
        foreach ($addons as $addon) {
            if ((int) $addon['quantity'] > 0) {
                $out[(int) $addon['product_id']] = (int) $addon['quantity'];
            }
        }

        return $out;
    }

    /**
     * `[{group, product_id}]` → `[grupo => id]`.
     *
     * @param  array<int, array{group:string, product_id:int}>  $choices
     * @return array<string, int>
     */
    private static function choices(array $choices): array
    {
        $out = [];
        foreach ($choices as $choice) {
            $out[(string) $choice['group']] = (int) $choice['product_id'];
        }

        return $out;
    }
}
