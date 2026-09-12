<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ZoneCards;
use App\Domain\Content\Models\VenueRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * **LAS NORMAS, AGRUPADAS POR MOMENTO** (`docs/specs/rediseno-desde-canvas.md` §5.5 · T3b ·
 * `DECISIONES #533`). Artboard `Normas PJP` 1a/1b.
 *
 * ❗❗❗ **EL MOMENTO ES EL ÚNICO ORDEN QUE EL VISITANTE PUEDE USAR**, y ésa es toda la pieza: *antes
 * de venir* y *en la puerta* son las que deciden **si entras** —y las únicas que la portada
 * adelanta—; las de *dentro* no cambian ninguna decisión y por eso viven solo aquí. La página que
 * había las listaba por orden de panel, que mezclaba las dos cosas.
 *
 * ❗❗ **SE COMPONE EN EL DOMINIO Y NO EN LA VISTA**, como el resto del carril: agrupar, respetar el
 * orden de los momentos, mandar al final lo que no tiene y decidir la fecha son **reglas**, y en
 * Blade serían tres bucles anidados con la regla repartida entre ellos.
 *
 * ⚠️⚠️ **NINGUNA NORMA SE PIERDE, Y ESO ES LO QUE VIGILA LA GUARDA.** Una norma sin momento —o con
 * uno que el producto ya no declara— **se publica igual, al final y sin rótulo de grupo**. El modo
 * de fallo que esto evita no es un error: es una norma que el parque escribe en el panel, da por
 * publicada, y que no aparece en ninguna parte sin que nada falle.
 *
 * ⚠️ **El orden DENTRO de un grupo lo sigue mandando el panel** (`position`): este servicio reparte,
 * no reordena.
 */
final class RuleBoard
{
    /**
     * @return array{
     *     groups: list<array{moment: string, rules: Collection<int, VenueRule>}>,
     *     ungrouped: Collection<int, VenueRule>,
     *     updatedAt: ?Carbon,
     *     total: int,
     * }
     */
    public function compose(Collection $rules): array
    {
        $porMomento = $rules->groupBy(fn (VenueRule $rule): string => $rule->momentOrNull() ?? '');

        // El orden de los grupos es el de la constante, NO el de los datos: con `groupBy` a secas
        // mandaría qué norma aparece antes en la tabla, y entonces reordenar en el panel cambiaría
        // el sentido de la página.
        $groups = [];
        foreach (VenueRule::MOMENTS as $moment) {
            $delMomento = $porMomento->get($moment);
            if ($delMomento !== null && $delMomento->isNotEmpty()) {
                $groups[] = ['moment' => $moment, 'rules' => $delMomento->values()];
            }
        }

        return [
            'groups' => $groups,
            'ungrouped' => ($porMomento->get('') ?? collect())->values(),
            /*
             * **«Actualizado en …»**: las normas cambian, y quien las leyó hace un año necesita
             * saber si siguen siendo éstas.
             * ⚠️ Sale de la norma tocada MÁS RECIENTEMENTE, no de la fecha de hoy: escribir el mes
             * actual afirmaría una revisión que nadie ha hecho. Con la tabla vacía es `null`, y
             * entonces la página no escribe la línea.
             */
            'updatedAt' => $rules->max('updated_at'),
            'total' => $rules->count(),
        ];
    }

    /**
     * **LA ESCALA DE ALTURA, «de un vistazo»** — la tarjeta que abre la página en el artboard.
     *
     * ❗❗❗ **SE DIBUJA CON LO QUE EL DATO DICE, Y NADA MÁS.** Las fronteras salen de
     * `zones.height_min_cm` / `height_max_cm` (`#478`), que es el mismo par de columnas que ya pinta
     * el eje de las tarjetas de zona en la portada, y el techo es **la misma constante**
     * (`ZoneCards::ESCALA_CM`): dos escalas con topes distintos harían que el mismo 1,30 cayera a
     * distinta altura en dos pantallas del mismo sitio.
     *
     * ⚠️⚠️ **DESVIACIÓN DECLARADA DEL ARTBOARD**: él dibuja **tres** bandas y la de en medio es «de
     * 1 a 1,30 m, con tutor». **Ese 1,00 no existe como dato en ninguna parte**: vive dentro del
     * TEXTO de la norma de la zona Jump, que es donde debe estar —es una excepción con condiciones,
     * no un umbral—. Inventarlo aquí sería poner un número en un dibujo que ningún panel puede
     * cambiar y que ninguna otra instalación tendría. Se dibujan las bandas que el dato sostiene.
     *
     * ⚠️ **Sin ninguna zona con altura no hay tarjeta**: vacío es una respuesta (`#485`), y un eje
     * con una sola banda a lo largo de toda la escala no dice nada.
     *
     * @param  Collection<int, Zone>  $zones
     * @return array{ceiling: string, floor: string, bands: list<array{top: float, height: float, label: string, cm: int}>}|null
     */
    public function heightScale(Collection $zones, ZoneCards $cards): ?array
    {
        $techo = ZoneCards::ESCALA_CM;

        $bands = [];
        foreach ($zones as $zone) {
            $min = $zone->height_min_cm;
            $max = $zone->height_max_cm;

            if ($min === null && $max === null) {
                continue;
            }

            // `height_min_cm` es «a partir de» —la banda va de su cifra al techo— y `height_max_cm`
            // es «hasta» —del suelo a su cifra—. La misma cifra significa lo contrario según la
            // columna, que es justo por lo que son dos y no un número con el sentido deducido.
            $desde = $min ?? 0;
            $hasta = $min !== null ? $techo : $max;

            $bands[] = [
                // En porcentaje y desde ARRIBA, que es como se apila el dibujo.
                'top' => round((1 - $hasta / $techo) * 100, 2),
                'height' => round((($hasta - $desde) / $techo) * 100, 2),
                'label' => (string) $zone->tr('name'),
                'cm' => $min ?? $max,
            ];
        }

        if ($bands === []) {
            return null;
        }

        return [
            'ceiling' => $cards->ceilingLabel(),
            'floor' => $cards->floorLabel(),
            'bands' => $bands,
        ];
    }
}
