<?php

namespace App\Domain\Platform\Services;

use Carbon\CarbonInterface;

/**
 * **Cómo se escribe una fecha en el idioma de quien la lee** (F5 · T2b, `DECISIONES #656`). Es el
 * hermano de {@see LocalNumber}: allí los separadores, aquí la forma de nombrar un mes y un año.
 *
 * ❗❗ **Medido el 20-09 en la web**: el pie de `/normas` decía «Updated in **September de 2026**» y «Mis
 * à jour en **septembre de 2026**», porque la vista escribía `translatedFormat('F \d\e Y')` a mano — el
 * mes se traducía y el «de» no. No fallaba nada: una fecha mal escrita se pinta igual de bien, y el
 * defecto solo se ve en los idiomas en los que nadie mira.
 *
 * ⚠️⚠️ **Y por eso es T2b y no una errata**: la regla vivía DENTRO de una vista que se va a la instancia.
 * El día de la mudanza se iba con ella y cada landing la re-derivaría, mal en inglés y en francés.
 */
final class LocalDate
{
    /**
     * Un mes con su año: «septiembre de 2026» · «September 2026» · «septembre 2026».
     *
     * ⚠️ El «de» es del español; inglés y francés nombran el mes y el año seguidos. La pregunta es por
     * el español y no por una lista de idiomas que habría que mantener: es el único que lleva partícula.
     */
    public static function monthYear(CarbonInterface $fecha): string
    {
        return app()->getLocale() === 'es'
            ? $fecha->translatedFormat('F \d\e Y')
            : $fecha->translatedFormat('F Y');
    }
}
