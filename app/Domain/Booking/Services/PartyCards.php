<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\WritesLandingValues;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * **LAS DOS TARJETAS DE LA SECCIÓN «CUMPLEAÑOS»** (`docs/specs/rediseno-desde-canvas.md` §5.4 · T2e ·
 * `DECISIONES #483`). Artboard `Cumpleanos PJP` **7b** (móvil) + `Escritorio PJP` **5a**.
 *
 * ▶ **Existe por lo mismo que `ZoneCards` y `RateCards`**: cruzar el pack con su tarifa especial,
 * elegir qué edad se publica y escribir los importes es lógica, y en Blade se convierte en dos
 * copias de la misma regla —una por tarjeta— que el día que cambien divergen.
 *
 * ⚠️⚠️ **NO calcula ni un precio ni un formato propios.** El importe sale de
 * `TicketType::displayPriceCents()` y la especial de `specialRateSurcharges()`, que son los que el
 * resto de la landing ya usa, y se escriben con `WritesLandingValues`, que es el mismo trait que
 * escriben las otras dos secciones. *El precio se resuelve en un sitio* (`#329`).
 *
 * ❗❗ **LA EDAD QUE SE PUBLICA ES LA DEL PACK, NO LA DE SU ZONA**, y eso cierra una divergencia que
 * el propio canvas dejó abierta —*«la página dice 4–7 y desde 8; la portada dice la de la zona, 2–6
 * y desde 7»*—. Medido: `ticket_types.guest_age_min/max` dice **4–7** y **8+**, y es el campo que
 * **cobra el suplemento de fiesta mixta**. Publicar la de la zona haría que la portada prometiera
 * un tramo distinto del que se cobra. `#478` ya lo había decidido para las zonas: **manda la BD**.
 */
final class PartyCards
{
    use WritesLandingValues;

    /**
     * @param  Collection<int, TicketType>  $packs  packs de la superficie de cumpleaños, ya ordenados
     * @return list<array<string, mixed>>
     */
    public function compose(Collection $packs): array
    {
        return $packs->map(fn (TicketType $pack): array => $this->card($pack))->values()->all();
    }

    /**
     * El precio más barato de los packs, para la entradilla que vende con una cifra.
     *
     * ⚠️ `null` cuando no hay ninguno con precio: una entradilla que promete un «desde» que no
     * existe miente, y la vista cae a su variante sin cifra. Es la misma regla que `#479` escribió
     * para la sección de tarifas.
     *
     * @param  Collection<int, TicketType>  $packs
     */
    public function cheapest(Collection $packs): ?string
    {
        $cents = $packs->map(fn (TicketType $pack): int => $pack->displayPriceCents())->filter()->min();

        return $cents ? $this->euros((int) $cents) : null;
    }

    /** @return array<string, mixed> */
    private function card(TicketType $pack): array
    {
        /*
         * ⚠️⚠️ **`$especial['rate']` sobre `null` LANZA**, y es la trampa que `#478` pagó con 31
         * casos en rojo: aquí siempre hay tarifa de finde y en una instalación sin ella no. Se
         * comprueba la existencia antes de tocarlo.
         */
        $especial = $pack->specialRateSurcharges()[0] ?? null;

        return [
            'id' => $pack->id,
            // El nombre del CATÁLOGO, que es lo que va en la chapa. El titular de la tarjeta lo
            // ocupa la EDAD, que es lo que de verdad elige quien reserva.
            'name' => $pack->tr('name'),
            // La etiqueta destacada del panel (`#585`): chip amarillo junto a la chapa del nombre.
            'badge' => $pack->tr('badge') ?: null,
            'age' => $this->ageLabel($pack),
            /*
             * Lo que incluye, tal cual lo escribe el panel. ⚠️ **El tope de TRES lo declara la
             * VISTA, no este servicio**: el panel decide QUÉ incluye y el diseño CUÁNTAS caben — es
             * la regla que `#293` dejó escrita para las normas de la portada.
             */
            'features' => array_values(array_filter((array) ($pack->tr('features') ?: []))),
            // LOS REGALOS (`#589`): aparte de lo que incluye y SIN el tope de tres — cada uno va en su
            // etiqueta, y es lo que la tarjeta tiene que destacar.
            'gifts' => $pack->giftLines(),
            'price' => $this->numero($pack->displayPriceCents()),
            /*
             * ⚠️ **La especial se publica ENTERA, nunca como recargo** (regla dura del canvas,
             * aplicada en `#479` a toda la web): «16,95 € en tarifa especial», no «+2 €». Y no es
             * plana entre los dos packs —+2 en Kids y +4 en Jump—, así que un recargo obligaría al
             * cliente a recordar cuál le toca.
             */
            'special' => $especial ? $this->euros((int) $especial['priceCents']) : null,
            /*
             * ⚠️⚠️ **La señal viaja SIN el símbolo, y esto fue un defecto REAL** que el owner vio en
             * la tarjeta: la cadena `reserve_terms` ya escribe el «€» —la comparte `/cumpleanos`,
             * que le pasa un número pelado— y mandarla formateada publicaba «Señal de 50 € € para
             * reservar». *Una cadena con la unidad dentro pide el número, no el importe escrito.*
             */
            'deposit' => $this->numero((int) $pack->deposit_value),
            'min' => (int) $pack->min_qty,
            'max' => (int) $pack->max_qty,
            'duration' => $pack->duration_min ? (int) $pack->duration_min : null,
            // La misma duración, ESCRITA, para la primera línea de lo que incluye (`#583`).
            'durationLabel' => $pack->duration_min ? $this->duracion((int) $pack->duration_min) : null,
            /*
             * ⚠️ **Aquí NO viaja la zona, y se probó.** La tarjeta enlazaba a
             * `/cumpleanos#<slug de zona>` y medido salía el MISMO destino para las dos: los dos
             * packs viven en la zona `cumpleanos`. El enlace va a la página sin ancla, y desde
             * `#528` no hace falta más: la página compara los packs lado a lado.
             */
        ];
    }

    /**
     * La edad del pack, escrita.
     *
     * ⚠️ **Son TRES formas y no una con un valor opcional** —«de 4 a 7 años», «desde 8 años» y
     * «hasta 7 años» dicen cosas distintas—, por lo mismo que la regla de altura de `ZoneCards`: una
     * sola frase con los números dentro obligaría a que el idioma adivinara el sentido.
     * ⚠️ Y sin tramo declarado devuelve `null`: la tarjeta se queda sin titular de edad en vez de
     * publicar un rango inventado. **Vacío es una respuesta.**
     *
     * ▶ Pública desde `#528`: la comparativa de `/cumpleanos` publica la MISMA edad en su fila, y
     * una segunda redacción de estas tres frases es cómo la portada y la página acabarían diciendo
     * dos tramos distintos del mismo pack.
     */
    public function ageLabel(TicketType $pack): ?string
    {
        $min = $pack->guest_age_min;
        $max = $pack->guest_age_max;

        return match (true) {
            $min !== null && $max !== null => __('landing.events.age_between', ['a' => $min, 'b' => $max]),
            $min !== null => __('landing.events.age_from', ['a' => $min]),
            $max !== null => __('landing.events.age_up_to', ['b' => $max]),
            default => null,
        };
    }
}
