<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\WritesLandingValues;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * **LA COMPARATIVA DE `/cumpleanos`** (`docs/specs/rediseno-desde-canvas.md` §5.5 · T3b ·
 * `DECISIONES #528`). Artboard `Cumpleanos Pagina PJP` **1a** (móvil) + **1b** (escritorio).
 *
 * ▶ **Existe por lo mismo que `PartyCards`**: cruzar los packs con sus tarifas, decidir qué va a la
 * tabla y qué al bloque «Igual en los dos» y escribir los importes es lógica, y en Blade serían
 * copias de la misma regla por fila.
 *
 * ❗❗❗ **LA REGLA QUE ORDENA TODO LA ESCRIBE EL SISTEMA DEL CANVAS**: *«una comparativa solo
 * compara lo que DIFIERE; lo común va a su propio bloque»*. Por eso cada HECHO de un pack —su edad,
 * cuántos niños admite, cuánto dura, la señal, lo que incluye— se mira en TODOS los packs a la vez:
 * si coincide va a «Igual en los dos» y si no, a una fila de la tabla. Nada se decide por el nombre
 * del hecho ni por cuántos packs haya: con un pack solo, todo coincide consigo mismo y la tabla
 * se queda con lo que cambia con el número de niños.
 *
 * ⚠️⚠️ **NO calcula ni un precio propio.** El importe por niño sale de
 * `TicketType::priceCentsForRate($tarifa, $n)` —el MISMO que cobra la cesta, con los tramos de
 * volumen dentro (`#329`)— y el total es ese precio por `$n`, que es exactamente la línea de
 * `CartPricer`. Por eso el total no se calcula en el navegador: se calcula aquí para cada número de
 * niños posible y la vista solo elige cuál enseñar. *Un total hecho en JavaScript sería una segunda
 * regla de dinero que nadie vigila.*
 *
 * ⚠️ **La tarifa especial es la que define la nota de la página** (`RateType::firstSpecial()`), no
 * «la primera distinta de cada pack»: la fila y la nota tienen que hablar de la misma tarifa. Y se
 * publica ENTERA, nunca como recargo (`#479`).
 */
final class BirthdayComparison
{
    use WritesLandingValues;

    private PartyCards $cards;

    public function __construct(?PartyCards $cards = null)
    {
        $this->cards = $cards ?? new PartyCards;
    }

    /**
     * @param  Collection<int, TicketType>  $packs  packs de la superficie de cumpleaños, ya ordenados
     * @return array{
     *     columns: list<array{id: int, name: string}>,
     *     rows: list<array{label: string, kind: string, live: ?string, cells: list<mixed>}>,
     *     counter: array{from: int, to: int},
     *     live: array<string, array<int, list<?string>>>,
     *     shared: list<array{title: string, note: ?string}>,
     *     duration: ?string,
     *     deposit: ?string,
     *     special: bool,
     *     mixed: int,
     * }
     */
    public function compose(Collection $packs): array
    {
        $packs = $packs->values();
        [$from, $to] = $this->range($packs);
        $special = RateType::firstSpecial();

        $live = $this->live($packs, $from, $to, $special);
        /*
         * ⚠️ Si la especial cuesta lo mismo que la normal en todos los packs y para todo número de
         * niños, sus dos filas repetirían las de arriba: se retiran, y la nota de los días con ellas.
         * Una fila que no dice nada nuevo no compara nada.
         */
        $withSpecial = $special !== null
            && $live['each_special'] !== $live['each']
            && collect($live['each_special'])->flatten()->filter()->isNotEmpty();

        $rows = [$this->liveRow(__('landing.birthday.row_each'), 'price', 'each', $live, $from)];
        if ($withSpecial) {
            $rows[] = $this->liveRow(__('landing.birthday.row_each_special'), 'strong', 'each_special', $live, $from);
        }

        $shared = [];
        $features = $this->features($packs);
        foreach ($features['common'] as $feature) {
            $shared[] = ['title' => $feature, 'note' => null];
        }

        // ── LOS HECHOS: a la tabla si difieren, a «Igual en los dos» si coinciden ──────────────
        $age = $packs->map(fn (TicketType $p): ?string => $this->cards->ageLabel($p))->all();
        $this->place($rows, $shared, __('landing.birthday.row_age'), $age);

        $kids = $packs->map(fn (TicketType $p): string => $this->kids($p))->all();
        $this->place($rows, $shared, __('landing.birthday.row_kids'), $kids, __('landing.birthday.kids_note'));

        /*
         * ⚠️ La DURACIÓN compartida no va a «Igual»: es el titular del reloj, que la dice más grande
         * y con su regla. Solo si difiere entre packs baja a una fila — y entonces el reloj NO se
         * pinta, porque su titular hablaría de uno de los packs como si fuera de todos.
         */
        $durations = $packs->map(fn (TicketType $p): ?string => $p->duration_min ? $this->duracion((int) $p->duration_min) : null)->all();
        $duration = $this->same($durations) ? $durations[0] : null;
        if ($duration === null && ! $this->blank($durations)) {
            $rows[] = ['label' => __('landing.birthday.row_duration'), 'kind' => 'text', 'live' => null, 'cells' => $durations];
        }

        $deposits = $packs->map(fn (TicketType $p): ?string => $this->deposit($p))->all();
        $deposit = $this->same($deposits) ? $deposits[0] : null;
        if ($deposit !== null) {
            $shared[] = ['title' => __('landing.birthday.deposit_title', ['deposit' => $deposit]), 'note' => __('landing.birthday.deposit_note')];
        } elseif (! $this->blank($deposits)) {
            $rows[] = ['label' => __('landing.birthday.row_deposit'), 'kind' => 'text', 'live' => null, 'cells' => $deposits];
        }

        if (! $this->blank($features['own'])) {
            $rows[] = [
                'label' => __('landing.birthday.row_features'), 'kind' => 'list', 'live' => null,
                'cells' => array_map(fn (array $own): ?array => $own === [] ? null : array_map(fn (string $f): array => ['t' => $f, 's' => null], $own), $features['own']),
            ];
        }

        /*
         * ⚠️⚠️ **LA HORA EXTRA VA A LA TABLA Y NO AL CARRIL POR SU PRECIO**, y lo dice el artboard:
         * *«su precio cambia por zona: un complemento con dos precios no es una tarjeta, es una
         * fila»*. Se reconoce por el MECANISMO (`extends_parent_stay`, `#421`), nunca por el nombre:
         * en otra instalación se llamará de otra forma.
         */
        $extenders = $packs->map(fn (TicketType $p): array => $this->extenders($p, $this->normalRate($p), $withSpecial ? $special : null))->all();
        if (! $this->blank($extenders)) {
            $rows[] = [
                'label' => __('landing.birthday.row_extend'), 'kind' => 'list', 'live' => null,
                'cells' => array_map(fn (array $lines): ?array => $lines === [] ? null : $lines, $extenders),
            ];
        }

        $rows[] = $this->liveRow(__('landing.birthday.row_total'), 'total', 'total', $live, $from);
        if ($withSpecial) {
            $rows[] = $this->liveRow(__('landing.birthday.row_total_special'), 'total', 'total_special', $live, $from);
        }

        return [
            'columns' => $packs->map(fn (TicketType $p): array => ['id' => (int) $p->id, 'name' => (string) $p->tr('name')])->all(),
            'rows' => $rows,
            'counter' => ['from' => $from, 'to' => $to],
            'live' => $live,
            'shared' => $shared,
            'duration' => $duration,
            'deposit' => $deposit,
            'special' => $withSpecial,
            'mixed' => $this->mixedFamilySize($packs),
        ];
    }

    /**
     * **LO QUE SE PIDE Y LO QUE SE AÑADE DESPUÉS DE RESERVAR**, desde el formulario de la reserva.
     *
     * ⚠️⚠️ **Salen del esquema de cada pack, no de una lista escrita**: los campos por niño
     * (`guest_fields`), los del grupo que se piden después (`event_fields` con fase `postform`) y
     * los complementos de venta posterior (`#413`). El artboard escribe un porqué al lado de cada
     * campo —«para la lista del monitor y para la tarta»— y eso **no se puede derivar** de un campo
     * que el panel crea: se publica el rótulo, que es lo que el cliente va a encontrarse.
     *
     * @param  Collection<int, TicketType>  $packs
     * @return array{children: list<string>, group: list<string>, extras: list<array{name: string, price: string, cutoff: ?string}>}
     */
    public function form(Collection $packs): array
    {
        $children = [];
        $group = [];
        $extras = [];
        $special = RateType::firstSpecial();

        foreach ($packs as $pack) {
            foreach ($pack->guestFields() as $field) {
                $children[$field['key']] ??= trim($pack->guestFieldLabel($field));
            }
            foreach ($pack->eventFields(TicketType::EVENT_STAGE_POSTFORM) as $field) {
                $group[$field['key']] ??= trim($pack->eventFieldLabel($field));
            }

            $normal = $this->normalRate($pack);
            foreach ($pack->addonsSoldAfterBooking() as $addon) {
                // ⚠️ Deduplicado por ID: el mismo cubo enganchado a los dos packs sale una vez.
                if (isset($extras[$addon->id])) {
                    continue;
                }
                $cents = $addon->priceCentsForRate($normal);
                if ($cents === null) {
                    continue; // sin precio no se puede comprar: no se anuncia (la regla del presentador)
                }
                $sp = $special ? $addon->priceCentsForRate($special) : null;
                $varies = $sp !== null && $sp !== $cents;

                $extras[$addon->id] = [
                    'name' => (string) $addon->tr('name'),
                    'price' => $cents === 0 && ! $varies
                        ? __('tickets.addon_badge_free')
                        : ($varies ? __('landing.rates.from').' ' : '').$this->euros($varies ? min($cents, $sp) : $cents),
                    'cutoff' => $this->cutoff($addon->pivot->postformCutoffHours()),
                ];
            }
        }

        return [
            'children' => array_values(array_filter($children, fn (string $l): bool => $l !== '')),
            'group' => array_values(array_filter($group, fn (string $l): bool => $l !== '')),
            'extras' => array_values($extras),
        ];
    }

    /**
     * El tramo de niños que recorre el contador: del mínimo contratable más bajo al máximo más alto.
     *
     * ⚠️ Un pack sin máximo declarado no estira el contador hasta el infinito: admite cualquier
     * número desde su mínimo hasta el tope que marquen los demás. Y sin ningún máximo, el contador
     * se queda en el mínimo — la vista no lo pinta, porque un control de un solo valor no elige nada.
     *
     * @param  Collection<int, TicketType>  $packs
     * @return array{0: int, 1: int}
     */
    private function range(Collection $packs): array
    {
        $from = (int) $packs->map(fn (TicketType $p): int => $p->contractableMinimum())->min();
        $to = (int) $packs->map(fn (TicketType $p): int => (int) ($p->max_qty ?? 0))->max();

        return [$from, max($from, $to)];
    }

    private function allows(TicketType $pack, int $n, int $to): bool
    {
        $max = (int) ($pack->max_qty ?? 0);

        return $n >= $pack->contractableMinimum() && $n <= ($max > 0 ? $max : $to);
    }

    /**
     * Los importes que cambian con el número de niños, para CADA número posible.
     *
     * ⚠️ `null` donde el pack no admite ese número: la celda dice «—» en vez de un total que no se
     * puede comprar.
     *
     * @param  Collection<int, TicketType>  $packs
     * @return array<string, array<int, list<?string>>>
     */
    private function live(Collection $packs, int $from, int $to, ?RateType $special): array
    {
        $live = ['each' => [], 'each_special' => [], 'total' => [], 'total_special' => []];
        $normals = $packs->map(fn (TicketType $p): ?RateType => $this->normalRate($p))->all();

        for ($n = $from; $n <= $to; $n++) {
            foreach ($packs as $i => $pack) {
                $ok = $this->allows($pack, $n, $to);
                $each = $ok ? $pack->priceCentsForRate($normals[$i], $n) : null;
                $eachSpecial = $ok && $special ? $pack->priceCentsForRate($special, $n) : null;

                $live['each'][$n][] = $each === null ? null : $this->euros($each);
                $live['each_special'][$n][] = $eachSpecial === null ? null : $this->euros($eachSpecial);
                $live['total'][$n][] = $each === null ? null : $this->euros($each * $n);
                $live['total_special'][$n][] = $eachSpecial === null ? null : $this->euros($eachSpecial * $n);
            }
        }

        return $live;
    }

    /** @param  array<string, array<int, list<?string>>>  $live */
    private function liveRow(string $label, string $kind, string $key, array $live, int $from): array
    {
        return ['label' => $label, 'kind' => $kind, 'live' => $key, 'cells' => $live[$key][$from]];
    }

    /**
     * Coloca un hecho: «Igual» si todos los packs dicen lo mismo, una fila si no. Si ninguno lo
     * declara, no se escribe en ningún sitio — vacío es una respuesta.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{title: string, note: ?string}>  $shared
     * @param  list<?string>  $values
     */
    private function place(array &$rows, array &$shared, string $label, array $values, ?string $note = null): void
    {
        if ($this->blank($values)) {
            return;
        }

        if ($this->same($values)) {
            $shared[] = ['title' => (string) $values[0], 'note' => $note];

            return;
        }

        $rows[] = ['label' => $label, 'kind' => 'text', 'live' => null, 'cells' => $values];
    }

    /**
     * Lo que incluye cada pack, partido en lo COMÚN a todos y lo PROPIO de cada uno.
     *
     * ⚠️ Se compara el texto EXACTO que escribe el panel: «Monitor para la sesión» en los dos packs
     * es lo mismo, y «Acceso exclusivo a la zona Kids» / «… Jump» son dos cosas. Adivinar que dos
     * frases distintas dicen lo mismo sería una segunda redacción del catálogo.
     *
     * @param  Collection<int, TicketType>  $packs
     * @return array{common: list<string>, own: list<list<string>>}
     */
    private function features(Collection $packs): array
    {
        $lists = $packs->map(fn (TicketType $p): array => array_values(array_filter(
            array_map(fn ($f): string => trim((string) $f), (array) ($p->tr('features') ?: [])),
            fn (string $f): bool => $f !== '',
        )))->all();

        $common = array_values(array_filter(
            array_unique($lists[0] ?? []),
            fn (string $f): bool => collect($lists)->every(fn (array $list): bool => in_array($f, $list, true)),
        ));

        return [
            'common' => $common,
            'own' => array_map(fn (array $list): array => array_values(array_diff($list, $common)), $lists),
        ];
    }

    /**
     * La hora extra de un pack: los complementos que ALARGAN la fiesta, con su duración y su precio.
     *
     * ⚠️ La especial va entera y en su propia línea, igual que el precio del pack. Y la unidad sale
     * del PIVOTE: «por niño» solo si el enganche es por invitado.
     *
     * @return list<array{t: string, s: ?string}>
     */
    private function extenders(TicketType $pack, ?RateType $normal, ?RateType $special): array
    {
        $lines = [];

        foreach ($pack->addonsSoldAtBooking() as $addon) {
            if (! $addon->extendsParentStay()) {
                continue;
            }
            $cents = $addon->priceCentsForRate($normal);
            if ($cents === null) {
                continue;
            }

            $unit = $addon->pivot->isPerGuest() ? ' '.__('landing.events.per_child') : '';
            $time = $addon->duration_min ? $this->duracion((int) $addon->duration_min) : null;
            $sp = $special ? $addon->priceCentsForRate($special) : null;

            $lines[] = [
                't' => ($time ? '+'.$time.' · ' : '').$this->euros($cents).$unit,
                's' => $sp !== null && $sp !== $cents ? $this->euros($sp).$unit.' '.__('landing.events.special_suffix') : null,
            ];
        }

        return $lines;
    }

    private function kids(TicketType $pack): string
    {
        $max = (int) ($pack->max_qty ?? 0);

        return $max > 0
            ? __('landing.birthday.kids', ['min' => $pack->contractableMinimum(), 'max' => $max])
            : __('landing.birthday.kids_from', ['min' => $pack->contractableMinimum()]);
    }

    /** La señal, escrita en registro de escaparate. `null` sin señal: se paga el total al reservar. */
    private function deposit(TicketType $pack): ?string
    {
        if (! $pack->hasDeposit()) {
            return null;
        }

        return $pack->deposit_type === TicketType::DEPOSIT_PERCENT
            ? ((int) $pack->deposit_value).' %'
            : $this->euros((int) $pack->deposit_value);
    }

    private function cutoff(?int $hours): ?string
    {
        return match (true) {
            $hours === null => null,
            $hours === 0 => __('landing.birthday.cutoff_start'),
            default => __('landing.birthday.cutoff', ['time' => $this->duracion($hours * 60)]),
        };
    }

    /**
     * Cuántos packs comparten familia de edades (`guest_age_family`). Con dos o más, una fiesta
     * puede ser MIXTA y la página lo explica; con menos, esa tarjeta hablaría de algo que no pasa.
     *
     * @param  Collection<int, TicketType>  $packs
     */
    private function mixedFamilySize(Collection $packs): int
    {
        $size = (int) $packs
            ->map(fn (TicketType $p): string => trim((string) $p->guest_age_family))
            ->filter()
            ->countBy()
            ->max();

        return $size >= 2 ? $size : 0;
    }

    /**
     * La tarifa normal de un pack, sacada de los precios que ya vienen cargados (sin consulta).
     */
    private function normalRate(TicketType $pack): ?RateType
    {
        return $pack->prices->first(fn (Price $price): bool => $price->rateType?->key === RateType::KEY_NORMAL)?->rateType;
    }

    /** @param  list<mixed>  $values */
    private function same(array $values): bool
    {
        if ($values === [] || $this->blank($values)) {
            return false;
        }
        $keys = array_map(fn ($v): string => json_encode($v, JSON_UNESCAPED_UNICODE), $values);

        return count(array_unique($keys)) === 1;
    }

    /** @param  list<mixed>  $values */
    private function blank(array $values): bool
    {
        return array_filter($values, fn ($v): bool => $v !== null && $v !== []) === [];
    }
}
