<?php

namespace App\Domain\Booking\Concerns;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * **LOS HECHOS DE UNA TARIFA, CON UNA SOLA LECTURA DE CADA UNO** (`DECISIONES #531`).
 *
 * ▶ **Nace extrayendo lo que `RateCards` tenía en privado**, porque la página `/precios` pregunta
 * exactamente lo mismo que la sección «Cuánto» de la portada: cómo se llama un producto sin el
 * prefijo de su zona, qué días rige la tarifa normal y si esa entrada se vende en la especial. Con
 * dos copias, la portada y la página podrían decir cosas distintas del mismo catálogo **sin que
 * nada fallara** — que es el modo de fallo que este repo persigue desde `#295`.
 *
 * ❗❗❗ **`sellsOnSpecial()` EXISTE PORQUE LA PREGUNTA ANTERIOR ERA OTRA, Y ESTABA MAL.** Hasta
 * `#531` la frase «solo de lunes a jueves» colgaba de `specialRateSurcharges()`, que devuelve las
 * tarifas especiales **cuyo precio DIFIERE del normal**. O sea que una entrada con el MISMO precio
 * los dos días —que se vende los siete— se anunciaba como si el fin de semana no existiera.
 * Reproducido con control sobre el catálogo real (`#531`): con el precio especial igualado al
 * normal, la tarjeta de Kids pasaba a decir «solo de lunes a jueves» mientras el dominio seguía
 * vendiéndola el sábado. ▶ *«¿se vende ese día?» es una pregunta de EXISTENCIA de precio, no de
 * diferencia de precio.*
 *
 * ⚠️ Y las dos preguntas siguen siendo dos: el PRECIO especial solo se escribe cuando dice algo
 * nuevo (si es el mismo número, repetirlo es ruido), pero la frase de días depende de si existe.
 */
trait ReadsRateFacts
{
    private ?RateType $tarifaEspecialMemo = null;

    private bool $tarifaEspecialLeida = false;

    private ?RateType $tarifaNormalMemo = null;

    private bool $tarifaNormalLeida = false;

    /** @var list<int>|null los weekdays que no reclama ninguna especial (memo de instancia) */
    private ?array $diasNormalesMemo = null;

    private bool $diasNormalesLeidos = false;

    /**
     * La primera tarifa especial activa, o `null` si la instalación no tiene ninguna.
     *
     * ⚠️ Memoizada por INSTANCIA además del memo de `RateType::firstSpecial()`: este trait lo usan
     * servicios que preguntan una vez por producto.
     */
    private function specialRate(): ?RateType
    {
        if (! $this->tarifaEspecialLeida) {
            $this->tarifaEspecialMemo = RateType::firstSpecial();
            $this->tarifaEspecialLeida = true;
        }

        return $this->tarifaEspecialMemo;
    }

    /**
     * ¿Este producto se vende en la tarifa especial?
     *
     * ⚠️⚠️ Es la pregunta que decide la frase «solo …», y su respuesta es la EXISTENCIA de un
     * precio para esa tarifa —lo mismo que responde el dominio al vender: `RateResolver::priceCents`
     * devuelve `null` un sábado para una entrada sin precio especial—. Ver el docblock del trait.
     *
     * ⚠️ Sin tarifa especial en la instalación devuelve `true`: no hay ningún día en el que no se
     * venda, así que la frase de excepción no se escribe.
     */
    private function sellsOnSpecial(TicketType $product): bool
    {
        $especial = $this->specialRate();

        return $especial === null || $product->displayPriceCentsForRate($especial) !== null;
    }

    /**
     * El precio de este producto en la tarifa especial, o `null` si ese día no se vende.
     *
     * ⚠️ **Es el precio ENTERO, jamás un recargo** (regla dura del canvas, `#479`): «un recargo no
     * se publica como recargo», y menos cuando no es plano.
     */
    private function specialPriceCents(TicketType $product): ?int
    {
        return $product->displayPriceCentsForRate($this->specialRate());
    }

    /**
     * La tarifa NORMAL de la instalación, o `null` si no existe.
     *
     * ⚠️ `RateResolver` la usa como último recurso con `firstOrFail()`, así que en la práctica
     * siempre está; aquí se pregunta con `null` porque una LECTURA de la web no puede reventar por
     * un catálogo a medias.
     */
    private function normalRate(): ?RateType
    {
        if (! $this->tarifaNormalLeida) {
            $this->tarifaNormalMemo = RateType::query()
                ->where('is_active', true)->where('key', RateType::KEY_NORMAL)->first();
            $this->tarifaNormalLeida = true;
        }

        return $this->tarifaNormalMemo;
    }

    /**
     * El precio de este producto **en la tarifa normal**, o `null` si ese día no se vende.
     *
     * ❗❗❗ **NO es `displayPriceCents()`, y la diferencia se ve en la tabla de `/precios`.** Aquél es
     * el precio de REFERENCIA —la tarifa normal o, si falta, la más baja—, así que un producto que
     * solo tiene precio especial devuelve ESE número: la «Hora extra», que únicamente se vende en
     * tarifa especial, saldría con su cifra en la columna «L a J», anunciando un día en el que no se
     * puede comprar. *Una columna por tarifa pregunta por su tarifa, no por el precio de portada.*
     */
    private function normalPriceCents(TicketType $product): ?int
    {
        return $product->displayPriceCentsForRate($this->normalRate());
    }

    /**
     * **CUÁL LIDERA, y es UNA sola** (`[DECIDIDO owner, 2026-09-09]`, `#479`). Sale de
     * `ticket_types.featured` y de ella cuelgan las señales que el diseño le da: en la portada el
     * ancho mayor, el foco del carril y el chip; en la tabla de `/precios`, el chip.
     *
     * ⚠️⚠️ **Se resuelve por ÍNDICE y no producto a producto, a propósito.** Si el panel marcara dos
     * entradas de la misma zona, preguntarle a cada una «¿eres destacada?» pintaría dos marcadores —y
     * con eso la señal deja de decir cuál coger, que es lo único para lo que existe—. Con el índice
     * manda la primera: una resolución determinista en vez de un empate.
     * ⚠️ `null` cuando no hay ninguna: entonces no se pinta ningún marcador. **Vacío es una respuesta.**
     *
     * @param  Collection<int, TicketType>  $entradas
     */
    private function leadingIndex($entradas): ?int
    {
        $lidera = $entradas->search(fn (TicketType $t): bool => (bool) $t->featured);

        return $lidera === false ? null : $lidera;
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
        $libres = $this->plainWeekdays();

        if ($libres === null || $libres === []) {
            return null;
        }

        if (count($libres) === 1) {
            return $this->dayName($libres[0]);
        }

        /*
         * ⚠️ **Solo se escribe como RANGO si los días que sobran son contiguos en la semana.** Con
         * un conjunto suelto —martes y viernes— «de martes a viernes» sería literalmente falso:
         * incluiría el miércoles. En ese caso se enumeran.
         */
        if ($this->areContiguous($libres)) {
            return __('landing.rates.days_range', [
                'from' => $this->dayName($libres[0]),
                'to' => $this->dayName($libres[count($libres) - 1]),
            ]);
        }

        return __('landing.rates.days_list', [
            'days' => implode(', ', array_map(fn (int $d): string => $this->dayName($d), $libres)),
        ]);
    }

    /**
     * **Los días en los que rige la tarifa ESPECIAL, escritos** — el espejo de `plainDaysPhrase()`,
     * y `null` si no se pueden saber (`DECISIONES #542`).
     *
     * ❗❗❗ **Nace porque la web dejó de decir los días NORMALES** (`[DECIDIDO owner, 2026-09-12]`:
     * «quitamos lo de lunes a jueves, eso es implícito»). Al retirarlos, «tarifa especial» se queda
     * sin definir en ninguna parte, así que el término pasa a explicarse donde se usa — y esa
     * explicación tiene que salir del MISMO sitio del que sale el cálculo.
     *
     * ⚠️⚠️ **Se derivan de `weekdays`, NO del rótulo del panel.** `rate_types` guarda las dos cosas
     * y **no dicen lo mismo**: aquí `weekdays` es `[5, 6, 0]` —viernes, sábado y domingo— mientras
     * el rótulo, tecleado a mano, dice «Viernes, findes y festivos». El rótulo puede desmentir al
     * cálculo sin que nada falle, que es exactamente el defecto que `#531` encontró con la frase
     * «solo de lunes a jueves».
     *
     * ⚠️ **No nombra los festivos ni las vísperas, y no es un olvido**: no viven en `rate_types`
     * sino en `special_dates`, que es otra tabla y otra pregunta. Afirmar aquí que las vísperas
     * cobran especial sería publicar una regla que el cálculo no aplica.
     *
     * @return list<int>|null los weekdays, para quien necesite la lista en vez de la frase
     */
    private function specialWeekdays(): ?array
    {
        $especiales = RateType::query()->where('is_active', true)->where('is_special', true)->get();

        if ($especiales->isEmpty()) {
            return null;
        }

        /** @var list<int> $tomados */
        $tomados = $especiales->flatMap(fn (RateType $r): array => array_map('intval', (array) $r->weekdays))->unique()->all();

        if ($tomados === []) {
            return null;
        }

        return array_values(array_filter(self::WEEK_ORDER, fn (int $d): bool => in_array($d, $tomados, true)));
    }

    /** La frase de los días especiales, con la misma regla de rango que su espejo. */
    private function specialDaysPhrase(): ?string
    {
        $dias = $this->specialWeekdays();

        if ($dias === null || $dias === []) {
            return null;
        }

        if (count($dias) === 1) {
            return $this->dayName($dias[0]);
        }

        /*
         * ⚠️ Rango solo si son contiguos: con un conjunto suelto —viernes y domingo— «de viernes a
         * domingo» sería literalmente falso, porque incluiría el sábado.
         */
        if ($this->areContiguous($dias)) {
            return __('landing.rates.days_range', [
                'from' => $this->dayName($dias[0]),
                'to' => $this->dayName($dias[count($dias) - 1]),
            ]);
        }

        return __('landing.rates.days_list', [
            'days' => implode(', ', array_map(fn (int $d): string => $this->dayName($d), $dias)),
        ]);
    }

    /**
     * Los weekdays de Carbon que NO reclama ninguna tarifa especial, en orden de presentación
     * (lunes → domingo), o `null` cuando no se puede afirmar cuáles son.
     *
     * @return list<int>|null
     */
    private function plainWeekdays(): ?array
    {
        if ($this->diasNormalesLeidos) {
            return $this->diasNormalesMemo;
        }

        $this->diasNormalesLeidos = true;

        $especiales = RateType::query()->where('is_active', true)->where('is_special', true)->get();

        if ($especiales->isEmpty() || $especiales->contains(fn (RateType $r): bool => ! is_array($r->weekdays) || $r->weekdays === [])) {
            return $this->diasNormalesMemo = null;
        }

        /** @var list<int> $tomados */
        $tomados = $especiales->flatMap(fn (RateType $r): array => array_map('intval', (array) $r->weekdays))->unique()->all();

        return $this->diasNormalesMemo = array_values(array_filter(
            self::WEEK_ORDER,
            fn (int $d): bool => ! in_array($d, $tomados, true)
        ));
    }

    /**
     * La semana como la escribe el sitio: de lunes a domingo.
     *
     * ⚠️ El domingo es `0` en la BD —convenio de `Carbon::dayOfWeek`— y va al FINAL, no al
     * principio: una semana que empieza en domingo daría «de domingo a jueves» para un conjunto que
     * es de lunes a jueves.
     */
    private const WEEK_ORDER = [1, 2, 3, 4, 5, 6, 0];

    /** ¿Los días son contiguos dentro de la semana de presentación? */
    private function areContiguous(array $dias): bool
    {
        $posicion = array_flip(self::WEEK_ORDER);

        return count($dias) - 1 === $posicion[$dias[count($dias) - 1]] - $posicion[$dias[0]];
    }

    /**
     * El nombre de un día en el idioma activo.
     *
     * ⚠️ Lo pone **Carbon**, no una tabla de 21 cadenas nuestras: son los mismos siete nombres en
     * los tres idiomas del sitio y ya vienen traducidos. Escribirlos a mano es crear tres listas que
     * pueden separarse. `startOfWeek(SUNDAY) + $d` cae en el día cuyo `dayOfWeek` es exactamente
     * `$d`, que es el convenio con el que la BD los guarda.
     */
    private function dayName(int $weekday): string
    {
        return CarbonImmutable::now()
            ->startOfWeek(CarbonInterface::SUNDAY)->addDays($weekday)
            ->locale(app()->getLocale())->dayName;
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
}
