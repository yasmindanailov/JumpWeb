<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\ReadsRateFacts;
use App\Domain\Booking\Concerns\WritesLandingValues;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Support\Collection;

/**
 * **LAS TARIFAS DE LA SECCIÓN «CUÁNTO»** (`docs/specs/rediseno-desde-canvas.md` §5.4 · T2c ·
 * `DECISIONES #479`).
 *
 * La segunda sección de la portada contesta «cuánto cuesta»: se elige zona con una pestaña y sus
 * entradas salen en un carril con foco. Este servicio compone **una entrada por zona** con sus
 * tarjetas ya redactadas.
 *
 * ▶ **Hermano de `ZoneCards` y con la misma razón de existir**: sin él, la plantilla tendría que
 * agrupar entradas por zona, elegir cuál abre el carril, partir el nombre de su matiz y redactar
 * los días de cada tarifa — lógica de presentación repartida por Blade, que es como nacen las seis
 * copias de una regla.
 *
 * ⚠️ **La CHAPA de zona no está y no es un olvido** (`[owner, 2026-09-09]`: *«ese card negro lo
 * quitamos, no es necesario»*, y su propio artboard la tiene detrás de un interruptor apagado por
 * el recorte de presupuesto del 7 sep). La edad y la altura de cada zona las publica la sección
 * «Para quién», que es donde el visitante las está buscando.
 *
 * ⚠️⚠️ **NO calcula ni un precio.** Pregunta a `TicketType::displayPriceCents()` y a
 * `specialRateSurcharges()`, que son los que el resto de la landing ya usa. *El precio de
 * presentación se resuelve en un sitio*, y esto es un consumidor más — nunca un séptimo camino
 * (`#329`).
 *
 * ⚠️ **Vive en Booking por lo mismo que `ZoneCards`**: compone zonas, entradas y precios, y
 * `Content` solo puede mirar a `Booking\Contracts` (`ModuleBoundariesTest`).
 */
final class RateCards
{
    /*
     * ▶ **Las reglas que esta sección comparte con la página `/precios` viven en el trait**
     * (`#531`): cómo se parte el nombre de su matiz, qué días rige la normal, si un producto se
     * vende en la especial y cuál lidera la zona. Estaban aquí en privado, y la página las pregunta
     * igual: con dos copias, la portada y la página podrían decir cosas distintas del mismo
     * catálogo sin que nada fallara.
     */
    use ReadsRateFacts;
    use WritesLandingValues;

    /**
     * @param  Collection<int, Zone>  $zones  las zonas de la landing, en su orden
     * @param  Collection<int, TicketType>  $tickets  las entradas activas con `prices.rateType`
     * @return list<array<string, mixed>>
     */
    public function compose(Collection $zones, Collection $tickets): array
    {
        $porZona = $tickets->groupBy('zone_id');

        /*
         * ⚠️⚠️ **LOS DÍAS DE LA TARIFA NORMAL SE CALCULAN UNA VEZ PARA TODA LA SECCIÓN, y no por
         * tarjeta.** No es una optimización: es que **son de la SECCIÓN**. La regla del canvas dice
         * que los días se escriben *«una vez por sección»*, así que si cada tarjeta los derivara por
         * su cuenta bastaría con que una entrada tuviera una tarifa especial distinta para que la
         * misma sección publicara dos calendarios sin que nada fallara.
         */
        $diasNormales = $this->plainDaysPhrase();

        return $zones->map(function (Zone $zone) use ($porZona, $diasNormales): array {
            /** @var Collection<int, TicketType> $entradas */
            $entradas = $porZona->get($zone->id, collect())->values();
            $nombreZona = (string) $zone->tr('name');

            /*
             * ❗❗❗ **CUÁL LIDERA LA ZONA, y es UNA sola** (`[DECIDIDO owner, 2026-09-09]`). Sale de
             * `ticket_types.featured`, y de aquí cuelgan las TRES señales que el artboard le da: el
             * ancho mayor, el foco con el que abre el carril y **el chip**.
             *
             * ⚠️⚠️ **Se resuelve por ÍNDICE y no producto a producto, a propósito.** Si el panel
             * marcara dos entradas de la misma zona, preguntarle a cada tarjeta «¿eres destacada?»
             * pintaría **dos** tarjetas anchas con **dos** chips — y con eso la señal deja de decir
             * cuál coger, que es lo único para lo que existe. Con el índice manda la primera y las
             * demás son tarjetas normales: una resolución determinista en vez de un empate.
             * ⚠️ `null` cuando no hay ninguna: entonces no hay chip ni tarjeta ancha y el carril abre
             * por la primera, que es lo que hace un carril sin destacada. **Vacío es una respuesta.**
             */
            $lidera = $this->leadingIndex($entradas);

            /*
             * **LA UNIDAD CONTRA LA QUE SE MIDE EL AHORRO**: la entrada de MENOR duración de la
             * zona. Ver `saving()` para por qué el multiplicador sale de ahí y no del orden.
             */
            $unidad = $entradas
                ->filter(fn (TicketType $t): bool => $t->duration_min > 0)
                ->sortBy('duration_min')->first();

            return [
                'slug' => $zone->slug,
                'name' => $nombreZona,
                'cards' => $entradas
                    ->map(fn (TicketType $t, int $i): array => $this->card($t, $zone, $diasNormales, $i === $lidera, $unidad))
                    ->values()->all(),
                'featured' => $lidera,
            ];
        })->values()->all();
    }

    /**
     * **El «desde» de la entradilla**: el precio más bajo que existe de verdad en el catálogo.
     *
     * ⚠️ Es el criterio de `#324` (`[DECIDIDO owner]`): «desde» señala variabilidad y anuncia el
     * precio real más bajo, no el de la entrada de referencia de una zona. ⚠️ Mira **todas** las
     * zonas a propósito: la sección enseña una cada vez, pero la entradilla presenta la sección
     * entera.
     *
     * ⚠️ `null` sin entradas: entonces la entradilla usa su variante sin precio, porque prometer un
     * «desde» que no existe es peor que no prometer nada.
     *
     * @param  Collection<int, TicketType>  $tickets
     */
    public function cheapest(Collection $tickets): ?string
    {
        $cents = $tickets->map(fn (TicketType $t): int => $t->displayPriceCents())->filter()->min();

        return $cents === null ? null : $this->euros((int) $cents);
    }

    /**
     * **El rótulo de la tarifa especial**, para escribir sus días UNA vez por sección.
     *
     * ⚠️ Es `label` y NO `name`: `rate_types` no tiene columna `name`, y `tr('name')` devolvería
     * cadena vacía **sin fallar** — el defecto que `#478` pagó en el sello de la tarjeta de zona.
     * ⚠️ Dice lo que el DATO diga: hoy «Viernes, findes y festivos» donde el mockup escribe
     * «finde». Acortarlo aquí sería meter el texto de este cliente en el producto.
     */
    public function specialLabel(): ?string
    {
        return RateType::firstSpecial()?->tr('label') ?: null;
    }

    /**
     * Una tarjeta de tarifa, con todo ya escrito.
     *
     * @return array<string, mixed>
     */
    private function card(TicketType $ticket, Zone $zone, ?string $diasNormales, bool $lidera, ?TicketType $unidad): array
    {
        $nombreZona = (string) $zone->tr('name');
        $especial = $ticket->specialRateSurcharges()[0] ?? null;
        [$nombre, $matiz] = $this->nameAndNuance((string) $ticket->tr('name'), $nombreZona);
        $badge = $ticket->tr('badge') ?: null;

        return [
            'id' => $ticket->id,
            /*
             * **LA CHAPA DE ZONA** (`Precios PJP` 15b, ✅ elegida por el dueño el 9 sep). Va en cada
             * tarjeta y con su coste dicho: se repite tantas veces como tarifas tenga la zona.
             * ⚠️ Es de BORDE y no maciza a propósito: la chapa maciza de tinta es la de «para
             * empezar», y en la tarjeta que lidera saldrían las dos juntas.
             */
            'zone' => __('landing.rates.zone_chip', ['zone' => $nombreZona]),
            /*
             * **EL NOMBRE MANDA** (`Precios PJP` 10a): es lo primero que se lee y va en rótulo, no
             * en una etiqueta. Ver `nameAndNuance()` para de dónde sale y qué se le quita.
             */
            'name' => $nombre,
            /*
             * ❗❗❗ **EL CHIP ES EL MARCADOR DE LA QUE LIDERA, y su texto lo escribe el PANEL**
             * (`[DECIDIDO owner, 2026-09-09]`). En el artboard es `esHero` con copy fijo; aquí sale
             * de `ticket_types.badge`, así que la señal es del diseño y **la palabra es del dueño**.
             *
             * ⚠️⚠️ **Antes colgaba SOLO del `badge`, y eso los separaba.** Reproducido con los datos
             * de esta instalación: «Kids · Ilimitada» llevaba el chip **sin ser destacada** —su
             * `badge` dice «Todo el día»— y ninguna entrada estaba marcada, así que ninguna zona
             * tenía tarjeta ancha. *Un marcador que puede aparecer en cualquier tarjeta deja de
             * decir cuál coger, que es lo único para lo que existe.*
             *
             * ⚠️ **Una destacada SIN `badge` no pinta chip**: el marcador lo dan igual el ancho y el
             * foco, y no se inventa aquí una palabra que el panel no ha escrito.
             */
            'badge' => $lidera ? $badge : null,
            /*
             * **EL MATIZ, y por qué el `badge` de una tarjeta que no lidera NO se pierde.**
             *
             * ⚠️⚠️ Si el chip pasa a ser de la destacada, un `badge` escrito en cualquier otra se
             * quedaría sin pintar **en silencio** — el operador lo escribe en el panel y no aparece
             * en ninguna parte. Aquí baja al matiz, que es exactamente el registro donde el artboard
             * pone «sin límite»: un dato del nombre, en mono y junto a él.
             * ▶ **La precedencia está declarada**: si el nombre ya trae matiz propio, manda el del
             * nombre — es parte de cómo se llama el producto, y el `badge` es una etiqueta añadida.
             */
            'nuance' => $matiz ?? ($lidera ? null : $badge),
            // ⚠️ La cifra SIN el símbolo: el artboard los pinta a 38 y a 20, así que el «€» es un
            // elemento aparte del marcado. Ver `WritesLandingValues::numero()`.
            'price' => $this->numero($ticket->displayPriceCents()),
            'unit' => $ticket->tr('period_label') ?: null,
            /*
             * **La frase de día, y aquí está la mitad de la honestidad de la sección.**
             *
             * ⚠️⚠️ Una entrada SIN precio en la tarifa especial no es una entrada más barata el
             * finde: es una entrada que **ese día no se vende** —verificado llamando al dominio:
             * `RateResolver::priceCents()` devuelve `null` un sábado—, así que su frase lleva
             * «solo». Escribir la misma frase en los dos casos publicaría un precio para un día en
             * el que no se puede comprar.
             *
             * ❗❗❗ **Y la pregunta es `sellsOnSpecial()`, NO «¿tiene recargo?»** (`#531`, corregido
             * con control). Hasta entonces colgaba de `specialRateSurcharges()`, que devuelve las
             * especiales **cuyo precio DIFIERE**: una entrada con el MISMO precio los siete días
             * —que se vende el sábado— se anunciaba como «solo de lunes a jueves». *Existir un
             * precio y ser distinto son dos preguntas, y esta frase es de la primera.*
             */
            'days' => $diasNormales === null ? null : ($this->sellsOnSpecial($ticket)
                ? $diasNormales
                : __('landing.rates.days_only', ['days' => $diasNormales])),
            /*
             * La tarifa especial, **con su precio ENTERO y nunca como recargo**
             * (`[DECIDIDO owner, 2026-09-09]`, regla dura del canvas: *«un recargo no se publica
             * como recargo»*). El dato ya venía: `specialRateSurcharges()` devuelve las dos cifras y
             * lo único que cambia es cuál se escribe.
             */
            'special' => isset($especial['priceCents']) ? $this->euros((int) $especial['priceCents']) : null,
            /*
             * **EL BOTÓN YA NO LLEVA LA ZONA** (`[DECIDIDO owner, 2026-09-09]`, sobre la nota del
             * propio turno 15b: *«con la zona en la chapa, en el botón sobra»*). La llevó mientras
             * la zona solo se decía ahí —era el turno 9a— y con la chapa arriba se diría **dos veces
             * por tarjeta**.
             * ⚠️ El argumento de 9a no se pierde: la zona sigue viajando hasta el gesto que cobra,
             * solo que ahora la dice la chapa de la misma tarjeta y no el rótulo del botón.
             */
            'cta' => __('landing.rates.book_name', ['name' => $nombre]),
            /*
             * **EL AHORRO** (`Precios PJP` 14a + 16a). Es el único argumento de VALOR de la tarjeta,
             * y sale del catálogo: ver `saving()`.
             */
            'saving' => $this->saving($ticket, $unidad),
            'sellable' => (bool) $ticket->is_sellable,
            /*
             * ⚠️⚠️ **Los COMPLEMENTOS NO se resuelven aquí, y lo dijo `ModuleBoundariesTest`.**
             * `LandingAddonPresenter` vive en **Content**, y Booking no puede mirar a Content: la
             * flecha va al revés. Los pide la VISTA, que es donde esa dirección ya existe y donde
             * la pide también la tarjeta antigua.
             * ▶ Por eso la tarjeta lleva el `ticket` entero: no es comodidad, es lo que permite que
             * la presentación pregunte lo suyo sin que el dominio cruce una frontera.
             */
            'ticket' => $ticket,
        ];
    }

    /**
     * **EL AHORRO DE UNA ENTRADA LARGA FRENTE A COMPRAR VARIAS CORTAS** (`Precios PJP` 14a).
     *
     * Es el único argumento de VALOR que la tarjeta tiene, y **sale del catálogo**: nadie escribe
     * una cifra. La resta está hecha —*cero sumas para el cliente*— y el resultado solo se pinta si
     * de verdad ahorra.
     *
     * ❗❗❗ **EL MULTIPLICADOR SE DERIVA DE LA DURACIÓN, NO DEL ORDEN DE LAS TARJETAS**
     * (`[DECIDIDO owner, 2026-09-09]`). El artboard lo saca del índice —«la segunda vale por dos, la
     * tercera por tres»—, y eso es cierto **solo mientras las tarjetas estén ordenadas por duración
     * creciente y cada escalón sea un múltiplo exacto de la primera**. Con `duration_min` la
     * comparación es un hecho: 120 ÷ 60 = 2, así que dos horas se comparan con **dos** de una hora.
     *
     * ⚠️⚠️ **Y un producto SIN duración no pinta la línea, que es la mitad de la decisión.** «Todo
     * el día» no declara minutos, así que compararlo con tres sueltas es **suponer cuánto se queda
     * el cliente medio**, no medirlo — y si viene dos horas, la frase le promete un ahorro que no
     * tiene. Medido con este catálogo: contra tres ahorraría 6,00 €, y **contra dos sale a −2,00 €**,
     * o sea que comprar dos de una hora es más barato. *Cuando el dato no existe, la línea no se
     * escribe.*
     *
     * ⚠️ El multiplicador tiene que ser **entero y ≥ 2**: «1,5 veces la de una hora» no se puede
     * decir en una frase, y con 1 no hay nada que comparar.
     *
     * @return array{amount: string, base: string}|null
     */
    private function saving(TicketType $ticket, ?TicketType $unidad): ?array
    {
        if ($unidad === null || $unidad->is($ticket) || ! $ticket->duration_min || ! $unidad->duration_min) {
            return null;
        }

        $veces = $ticket->duration_min / $unidad->duration_min;

        if ($veces < 2 || fmod($veces, 1.0) !== 0.0) {
            return null;
        }

        $ahorro = (int) round($veces) * $unidad->displayPriceCents() - $ticket->displayPriceCents();

        if ($ahorro <= 0) {
            return null;
        }

        [$nombreUnidad] = $this->nameAndNuance((string) $unidad->tr('name'), (string) $unidad->zone?->tr('name'));

        return [
            'amount' => $this->euros($ahorro),
            // ⚠️ El número va ESCRITO —«dos», «tres»— y no en dígito: la frase se lee, no se calcula.
            // Por encima de la lista corta cae al dígito, que es preferible a inventar la palabra.
            'base' => __('landing.rates.saving_base', [
                'count' => __('landing.rates.times.'.(int) round($veces)) === 'landing.rates.times.'.(int) round($veces)
                    ? (string) (int) round($veces)
                    : __('landing.rates.times.'.(int) round($veces)),
                'unit' => $nombreUnidad,
            ]),
        ];
    }

    /**
     * **EL NOMBRE DEL PRODUCTO Y SU MATIZ, de la MISMA cadena** (`Precios PJP`, turno 8).
     *
     * La regla es del canvas y su virtud es que **el catálogo no cambia**: «1 hora · 60 min» se
     * parte en un nombre que manda y un matiz que solo se pinta **cuando añade un dato**. «120 min»
     * es «2 horas» dicho otra vez en otra unidad, y por eso no se pinta; «sin límite», sí.
     *
     * ⚠️⚠️ **Y aquí hay una diferencia con el mockup que NO se puede ignorar: su cadena es
     * `{nombre} · {matiz}` y la de esta instalación es `{ZONA} · {nombre}`** («Jump · 1 hora»).
     * Aplicar su `split` tal cual daría nombre «Jump» y matiz «1 hora», o sea justo lo contrario.
     * ▶ Por eso primero se retira el prefijo de la zona **cuando es EXACTAMENTE su nombre**, que es
     * una comprobación, no una adivinanza: si no coincide, la cadena se deja intacta. Con eso la
     * regla del canvas funciona sobre los dos formatos y **una instalación que no meta la zona en el
     * nombre no nota nada**.
     * ▶ Y es lo que hace cierta la decisión 9a: la zona se dice **una vez por tarjeta**, en el botón.
     *
     * ▶ **Vive en `ReadsRateFacts` desde `#531`**, porque `/precios` parte los mismos nombres.
     */

    /**
     * **Los días en los que rige la tarifa normal, escritos** — o `null` si no se pueden saber.
     *
     * ⚠️⚠️ **Se DERIVAN del complemento de los días especiales, no se teclean.** `rate_types` guarda
     * `weekdays` (aquí `[5, 6, 0]`, o sea viernes, sábado y domingo) y `RateResolver` aplica la
     * normal a todo lo que ninguna especial reclame: los días normales son exactamente los que
     * sobran. Escribir «de lunes a jueves» en una cadena sería cierto en esta instalación y falso en
     * la siguiente **sin que nada fallara**.
     *
     * ⚠️ **`null` cuando no se puede afirmar**: sin tarifas especiales activas, o si alguna no
     * declara sus días, no sobra un conjunto conocido. Y con `null` la tarjeta no escribe la frase,
     * que es preferible a escribir una que no se sostiene.
     *
     * ⚠️ **No dice nada de los festivos y no le hace falta**: el rótulo de la tarifa especial —el
     * dato, hoy «Viernes, findes y festivos»— ya los nombra, así que un martes festivo cae en la
     * especial y la frase de al lado sigue siendo verdad.
     *
     * ▶ **Vive en `ReadsRateFacts` desde `#531`**, porque `/precios` escribe los mismos días —en la
     * tira de la semana, en la cabecera de columna y en la nota de una entrada que no se vende el
     * finde— y dos derivaciones del mismo conjunto pueden separarse sin que nada falle.
     */
}
