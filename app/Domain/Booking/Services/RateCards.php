<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\WritesLandingValues;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
            $lidera = $entradas->search(fn (TicketType $t): bool => (bool) $t->featured);
            $lidera = $lidera === false ? null : $lidera;

            return [
                'slug' => $zone->slug,
                'name' => $nombreZona,
                'cards' => $entradas
                    ->map(fn (TicketType $t, int $i): array => $this->card($t, $nombreZona, $diasNormales, $i === $lidera))
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
    private function card(TicketType $ticket, string $nombreZona, ?string $diasNormales, bool $lidera): array
    {
        $especial = $ticket->specialRateSurcharges()[0] ?? null;
        [$nombre, $matiz] = $this->nameAndNuance((string) $ticket->tr('name'), $nombreZona);
        $badge = $ticket->tr('badge') ?: null;

        return [
            'id' => $ticket->id,
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
             */
            'days' => $diasNormales === null ? null : ($especial === null
                ? __('landing.rates.days_only', ['days' => $diasNormales])
                : $diasNormales),
            /*
             * La tarifa especial, **con su precio ENTERO y nunca como recargo**
             * (`[DECIDIDO owner, 2026-09-09]`, regla dura del canvas: *«un recargo no se publica
             * como recargo»*). El dato ya venía: `specialRateSurcharges()` devuelve las dos cifras y
             * lo único que cambia es cuál se escribe.
             */
            'special' => isset($especial['priceCents']) ? $this->euros((int) $especial['priceCents']) : null,
            /*
             * **EL RÓTULO DEL BOTÓN LLEVA LA ZONA DENTRO** (`Precios PJP` 9a, la recomendada y
             * aplicada en 10a): *«se dice una vez por tarjeta, y en el único sitio donde equivocarse
             * cuesta dinero»*. Por eso el nombre de arriba va limpio.
             * ⚠️ Solo la rama de COMPRAR: «Llamar» no compra nada, así que decirle la zona a un
             * teléfono sería rellenar el botón con una promesa que ese botón no cumple.
             */
            'cta' => __('landing.rates.book_in', ['name' => $nombre, 'zone' => $nombreZona]),
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
     * @return array{0: string, 1: ?string} [nombre, matiz]
     */
    private function nameAndNuance(string $nombre, string $nombreZona): array
    {
        $prefijo = $nombreZona.' · ';

        if ($nombreZona !== '' && mb_stripos($nombre, $prefijo) === 0) {
            $nombre = mb_substr($nombre, mb_strlen($prefijo));
        }

        $partes = array_map('trim', explode(' · ', $nombre, 2));
        $matiz = $partes[1] ?? null;

        // ⚠️ El matiz que acaba en «min» se descarta: es la misma duración en otra unidad. La regla
        // sale del propio artboard (`parte()`), no de un criterio nuestro.
        if ($matiz !== null && ($matiz === '' || preg_match('/min\.?$/iu', $matiz))) {
            $matiz = null;
        }

        return [$partes[0], $matiz];
    }

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
     */
    private function plainDaysPhrase(): ?string
    {
        $especiales = RateType::query()->where('is_active', true)->where('is_special', true)->get();

        if ($especiales->isEmpty() || $especiales->contains(fn (RateType $r): bool => ! is_array($r->weekdays) || $r->weekdays === [])) {
            return null;
        }

        /** @var list<int> $tomados */
        $tomados = $especiales->flatMap(fn (RateType $r): array => array_map('intval', (array) $r->weekdays))->unique()->all();

        // La semana como la escribe el sitio: de lunes a domingo. ⚠️ El domingo es `0` en la BD
        // —convenio de `Carbon::dayOfWeek`— y va al FINAL, no al principio: una semana que empieza
        // en domingo daría «de domingo a jueves» para un conjunto que es de lunes a jueves.
        $semana = [1, 2, 3, 4, 5, 6, 0];
        $libres = array_values(array_filter($semana, fn (int $d): bool => ! in_array($d, $tomados, true)));

        if ($libres === []) {
            return null;
        }

        /*
         * ⚠️ El nombre del día lo pone **Carbon**, no una tabla de 21 cadenas nuestras: son los
         * mismos siete nombres en los tres idiomas del sitio y ya vienen traducidos. Escribirlos a
         * mano es crear tres listas que pueden separarse. `startOfWeek(SUNDAY) + $d` cae en el día
         * cuyo `dayOfWeek` es exactamente `$d`, que es el convenio con el que la BD los guarda.
         */
        $nombre = fn (int $d): string => CarbonImmutable::now()
            ->startOfWeek(CarbonInterface::SUNDAY)->addDays($d)
            ->locale(app()->getLocale())->dayName;

        if (count($libres) === 1) {
            return $nombre($libres[0]);
        }

        /*
         * ⚠️ **Solo se escribe como RANGO si los días que sobran son contiguos en la semana.** Con
         * un conjunto suelto —martes y viernes— «de martes a viernes» sería literalmente falso:
         * incluiría el miércoles. En ese caso se enumeran.
         */
        $posicion = array_flip($semana);
        $contiguos = count($libres) - 1 === $posicion[$libres[count($libres) - 1]] - $posicion[$libres[0]];

        if ($contiguos) {
            return __('landing.rates.days_range', [
                'from' => $nombre($libres[0]),
                'to' => $nombre($libres[count($libres) - 1]),
            ]);
        }

        return __('landing.rates.days_list', [
            'days' => implode(', ', array_map($nombre, $libres)),
        ]);
    }
}
