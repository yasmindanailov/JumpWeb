<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\ReadsRateFacts;
use App\Domain\Booking\Models\RateType;

/**
 * **QUÉ ES LA TARIFA ESPECIAL, EN UNA FRASE Y SACADA DEL CÁLCULO** (`DECISIONES #542`).
 *
 * ❗❗❗ Nace de retirar los días de la tarifa NORMAL de las superficies de precio
 * (`[DECIDIDO owner, 2026-09-12]`: «quitamos lo de lunes a jueves, eso es implícito»). El término
 * «tarifa especial» se queda entonces sin definir en ninguna parte, y esta clase es su definición:
 * la sirve **una vez, donde se usa**, detrás de una «i».
 *
 * ⚠️⚠️ **LOS DÍAS SE DERIVAN DE `weekdays`, NO DEL RÓTULO DEL PANEL**, y la diferencia no es
 * teórica: `rate_types` guarda las dos cosas y en esta instalación **no dicen lo mismo** —
 * `weekdays` es `[5, 6, 0]` (viernes, sábado y domingo) mientras el rótulo, tecleado a mano, dice
 * «Viernes, findes y festivos». El rótulo puede desmentir al cálculo sin que nada falle, que es
 * exactamente el defecto que `#531` encontró con la frase «solo de lunes a jueves».
 *
 * ⚠️ **No afirma nada de festivos ni de vísperas.** No viven en `rate_types` sino en
 * `special_dates`, que es otra tabla: publicar aquí que las vísperas cobran especial sería
 * prometer una regla que el cálculo no aplica. Lo que sí se dice es que hay días sueltos que el
 * parque marca, que es cierto y comprobable.
 *
 * ⚠️ **`null` es una respuesta**: sin tarifas especiales activas, o sin `weekdays` declarados, no
 * hay nada que explicar y la «i» no se pinta. Vacío es una respuesta, no una falta.
 */
class SpecialRateExplainer
{
    use ReadsRateFacts;

    /** La frase de los días, derivada. `null` si la instalación no puede afirmarlos. */
    public function days(): ?string
    {
        return $this->specialDaysPhrase();
    }

    /** ¿Hay algo que explicar? */
    public function exists(): bool
    {
        return $this->days() !== null;
    }

    /**
     * El cuerpo del tooltip.
     *
     * ⚠️ Dos frases y no una: la primera es el HECHO (qué días) y la segunda la tranquilidad
     * —«no hay que calcular nada»—, que es la que ya existía en `/precios` y que el owner no pidió
     * retirar. Juntarlas en un párrafo haría que el dato se leyera como una disculpa.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $dias = $this->days();

        if ($dias === null) {
            return [];
        }

        return [
            __('landing.rates.tip_days', ['days' => $dias]),
            __('landing.rates.tip_calm'),
        ];
    }

    /** El rótulo accesible de la «i»: qué se va a explicar, no «más información». */
    public function label(): string
    {
        return __('landing.rates.tip_label');
    }

    /** ¿Existe alguna tarifa especial activa? (lo que hoy comprueban las vistas antes de pintar) */
    public function rate(): ?RateType
    {
        return RateType::firstSpecial();
    }
}
