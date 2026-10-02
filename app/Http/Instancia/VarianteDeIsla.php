<?php

namespace App\Http\Instancia;

use App\Domain\Platform\Services\Analytics\Experiments;
use Illuminate\Http\Request;

/**
 * **LA VARIANTE DE LA ISLA EN UNA PÁGINA** (la Z6c de `specs/isla-y-landing-nueva.md` §4.27: el experimento B3 del zip (6)).
 * Lo que `<x-pagina>` pone en su `<html>` ANTES de pintar, para que la cabecera de la instancia y la isla la lean a la vez:
 * si llegara después, la cabecera saltaría.
 *
 *   · `variante`: la CARA de la isla, `b3` u `hoy`. La asigna el SERVIDOR por visitante (`Experiments::forRequest()`, del
 *     SPA: `hash(clave|sujeto)`, sin guardar nada) con la clave `isla`. El experimento lo crea el owner en el panel con las
 *     variantes `hoy` y `b3`; cualquier otra variante enseña la isla de hoy.
 *   · `experimento`: la variante ASIGNADA, SOLO si el servidor asignó algo. Sin experimento vivo la isla es la de hoy y no hay
 *     exposición: lo que el SPA cuenta (`experiment_exposed`, que la isla manda con esta variante: `isla/medir.js`) es a quién
 *     se le ENSEÑÓ una variante, no a quién le tocó.
 *   · `?isla=b3`: la vista previa del diseño, para mirar el B3 sin experimento. No es una exposición, ni aunque al visitante
 *     le hubiera tocado otra cara.
 * Sin la isla en la página, nada.
 */
final class VarianteDeIsla
{
    /** La clave del experimento, la que el owner pone en el panel (y la que lee el SPA para la exposición). */
    public const CLAVE = 'isla';

    public const B3 = 'b3';

    public const HOY = 'hoy';

    /**
     * @param  array<string, string>|null  $asignadas  las variantes de quien mira (`Experiments::forRequest()`; por parámetro, para probarlo)
     * @return array{variante: 'hoy'|'b3', experimento: ?string}|null `experimento`: la variante asignada, o `null`
     */
    public static function paraLaPagina(bool $conIsla, Request $request, ?array $asignadas = null): ?array
    {
        if (! $conIsla) {
            return null;
        }

        if ($request->query('isla') === self::B3) {
            return ['variante' => self::B3, 'experimento' => null];
        }

        $asignada = ($asignadas ?? Experiments::forRequest())[self::CLAVE] ?? null;

        if (! is_string($asignada) || $asignada === '') {
            return ['variante' => self::HOY, 'experimento' => null];
        }

        return ['variante' => $asignada === self::B3 ? self::B3 : self::HOY, 'experimento' => $asignada];
    }
}
