<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\VenueRule;
use Closure;
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
     * ⚠️⚠️ **DESVIACIÓN DECLARADA DEL ARTBOARD**: su franja de en medio es «de 1 a 1,30 m, con
     * tutor». **Ese 1,00 no existe como dato en ninguna parte**: vive en el TEXTO de acceso del
     * panel, que es donde debe estar —es una excepción con condiciones, no un umbral—, y la vista lo
     * pinta DEBAJO de la escala, que es donde el artboard pone los matices. Inventarlo aquí sería
     * poner un número en un dibujo que ningún panel puede cambiar.
     *
     * ❗❗ **LAS FRANJAS NO SE PISAN, Y LO GARANTIZA EL CORTE** (`#589`, `[DECIDIDO owner]`). El
     * artboard las apila porque supone que una zona acaba donde empieza la otra, y los datos reales
     * se SOLAPAN (Kids hasta 1,50 · Jump desde 1,30: manda la edad). Una banda por zona las dibujaba
     * una encima de otra. ▶ La escala se corta en CADA frontera declarada y cada tramo es una franja:
     * la de una zona lleva su nombre y su color, y la que comparten dos o más, las dos.
     * ▶ La REGLA lleva una marca por cota —techo, fronteras y suelo—, con las fronteras en fuerte.
     *
     * ⚠️ **Sin ninguna zona con altura no hay tarjeta**: vacío es una respuesta (`#485`), y un eje
     * con una sola banda a lo largo de toda la escala no dice nada.
     *
     * ❗❗❗ **NO NOMBRA A `Booking`, Y ESO LO IMPONE EL GRAFO DE MÓDULOS**: `Content` solo puede mirar
     * a `Platform` y a los **contratos** de Booking y Payments, nunca a sus modelos ni a sus
     * servicios (`ModuleBoundariesTest`). La primera versión importaba `Zone` y `ZoneCards` y la
     * guarda la paró con las dos flechas escritas. ▶ Es el mismo trato que `RideMosaic`, que recibe
     * las zonas **sin nombrar el tipo**: aquí llegan el techo y el suelo ya escritos por quien sí
     * puede componerlos, y este servicio solo reparte porcentajes.
     *
     * @param  Collection<int, object>  $zones  zonas con `height_min_cm`/`height_max_cm`, `color` y `tr('name')`
     * @param  int  $techo  el tope de la escala en cm — el MISMO que usa el eje de la portada
     * @param  string  $ceiling  el techo, escrito con su unidad («1,90 m»)
     * @param  string  $floor  el suelo, escrito («0 m»)
     * @param  Closure(int): string  $metres  cómo escribe el sitio una altura («1,30»): un formato, un sitio
     * @return array{ticks: list<array{top: float, label: string, strong: bool}>, bands: list<array{top: float, height: float, label: string, overlap: bool, color: ?string}>}|null
     */
    public function heightScale(Collection $zones, int $techo, string $ceiling, string $floor, Closure $metres): ?array
    {
        $tramos = [];
        foreach ($zones as $zone) {
            $min = $zone->height_min_cm;
            $max = $zone->height_max_cm;

            if ($min === null && $max === null) {
                continue;
            }

            // `height_min_cm` es «a partir de» —la zona va de su cifra al techo— y `height_max_cm`
            // es «hasta» —del suelo a su cifra—. La misma cifra significa lo contrario según la
            // columna, que es justo por lo que son dos y no un número con el sentido deducido.
            $desde = min((int) ($min ?? 0), $techo);
            $hasta = $min !== null ? $techo : min((int) $max, $techo);

            if ($hasta <= $desde) {
                continue;
            }

            $tramos[] = [
                'desde' => $desde,
                'hasta' => $hasta,
                'frontera' => (int) ($min ?? $max),
                'label' => (string) $zone->tr('name'),
                'color' => $this->hexOrNull($zone->color ?? null),
            ];
        }

        if ($tramos === []) {
            return null;
        }

        // En porcentaje y desde ARRIBA, que es como se apila el dibujo.
        $y = fn (int $cm): float => round((1 - $cm / $techo) * 100, 2);

        // Las cotas donde se corta la escala: el techo, cada frontera declarada y el suelo.
        $cotas = collect($tramos)
            ->flatMap(fn (array $t): array => [$t['desde'], $t['hasta']])
            ->push(0)->push($techo)
            ->unique()->sortDesc()->values()->all();

        // Las franjas, de arriba abajo. Un tramo que no cubre ninguna zona no se pinta: la escala no
        // afirma nada donde el dato calla.
        $bands = [];
        for ($i = 0; $i < count($cotas) - 1; $i++) {
            [$alto, $bajo] = [$cotas[$i], $cotas[$i + 1]];
            $cubren = array_values(array_filter($tramos, fn (array $t): bool => $t['desde'] <= $bajo && $t['hasta'] >= $alto));

            if ($cubren === []) {
                continue;
            }

            $compartida = count($cubren) > 1;
            $bands[] = [
                'top' => $y($alto),
                'height' => round((($alto - $bajo) / $techo) * 100, 2),
                'label' => $compartida
                    ? __('site.rules_axis_overlap', ['zones' => implode(' '.__('site.rules_axis_or').' ', array_column($cubren, 'label'))])
                    : $cubren[0]['label'],
                'overlap' => $compartida,
                // El tinte de la zona sale del panel; el de la compartida es el del marcador y lo pone el CSS.
                'color' => $compartida ? null : $cubren[0]['color'],
            ];
        }

        // LA REGLA: el techo, cada frontera de zona —en fuerte— y el suelo, de arriba abajo.
        $ticks = [['top' => 0.0, 'label' => $ceiling, 'strong' => false]];
        $fronteras = collect($tramos)->pluck('frontera')
            ->filter(fn (int $cm): bool => $cm > 0 && $cm < $techo)
            ->unique()->sortDesc()->values();
        foreach ($fronteras as $cm) {
            $ticks[] = ['top' => $y($cm), 'label' => $metres($cm), 'strong' => true];
        }
        $ticks[] = ['top' => 100.0, 'label' => $floor, 'strong' => false];

        return ['ticks' => $ticks, 'bands' => $bands];
    }

    /** Un color del panel solo entra en un `style` si es un hex: cualquier otra cosa se ignora. */
    private function hexOrNull(mixed $color): ?string
    {
        return is_string($color) && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color) === 1 ? $color : null;
    }
}
