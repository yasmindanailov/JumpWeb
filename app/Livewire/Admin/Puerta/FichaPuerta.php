<?php

namespace App\Livewire\Admin\Puerta;

use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\ProductIcon;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * **Lo que la Puerta nueva PINTA de una ficha** (`docs/specs/puerta-nueva.md` §4.4, la P1): las filas agrupadas por zona y
 * hora, cada reserva en su línea («Entrada 1 hora · 2 niños»), su hora («empieza en 5 min»), el dinero por clase, las
 * tareas de firmar, «Sus hijos», los menores invitados y la línea de la fiesta. Puro: la ficha (lo que compone
 * `GateProfile`, viaje en el estado de Livewire) y la hora del parque entran; nada sale a la base de datos.
 *
 * Existe para que la plantilla pinte y no decida —como el veredicto, `GateVerdict`— y para que cada regla tenga su prueba
 * y su mutación (`FichaPuertaTest`).
 *
 * ⚠️ La ficha viaja en el snapshot de Livewire: una clave puede faltar en uno abierto antes de un despliegue, así que
 * todo se lee con su valor por defecto. Un 500 con el cliente delante no es el precio de esa ventana.
 */
final class FichaPuerta
{
    public const FIRMAR_PENDIENTE = 'pendiente';

    public const FIRMAR_NADA = 'nada';

    public const FIRMAR_CAMBIADO = 'cambiado';

    public const FIRMAR_HIJOS = 'hijos';

    private const T = 'admin.puerta.ficha.';

    /**
     * @param  array<string, mixed>  $profile
     * @return array{
     *     descargo: string,
     *     filas: list<array{zona: string, cifra: int, fiesta: bool, pulsera: ?array{frase: string, hex: ?string, tinta: string}, reservas: list<array{codigo: string, linea: string, quien: ?string, hora: string, pagado: ?string, complementos: ?string, fiesta: bool, dinero: ?array{clase: string, texto: string}}>}>,
     *     entregas: list<array{clave: string, cifra: int, frase: string, icono: string}>,
     *     sin_reserva: bool,
     *     otros_dias: list<string>,
     *     firmar: list<string>,
     *     hijos: list<array{nombre: string, edad: string, anios: int, descargo: string, excepcion: ?string, cumple: bool}>,
     *     invitados: list<array{nombre: string, edad: ?string, excepcion: ?string}>,
     *     fiesta: ?string
     * }
     */
    public static function de(array $profile, CarbonInterface $now): array
    {
        $today = array_values(array_filter((array) ($profile['today_reservations'] ?? []), 'is_array'));
        $honorees = self::honorees($profile);

        return [
            'descargo' => self::descargo($profile),
            'filas' => self::filas($today, $now),
            'entregas' => self::entregas($today),
            'sin_reserva' => $today === [],
            'otros_dias' => $today === [] ? array_map(self::otroDia(...), array_values(array_filter((array) ($profile['window'] ?? []), 'is_array'))) : [],
            'firmar' => self::firmar($profile, $today),
            'hijos' => self::hijos($profile, $honorees),
            'invitados' => self::invitados($profile),
            'fiesta' => self::fiesta($profile),
        ];
    }

    /** El estado del descargo del TITULAR, para la marca `data-gate-waiver`: `disabled` · `current` · `outdated` · `missing`. */
    private static function descargo(array $profile): string
    {
        $w = (array) ($profile['waiver'] ?? []);

        return match (true) {
            ! ($w['enabled'] ?? false) => 'disabled',
            ! ($w['signed'] ?? false) => 'missing',
            (bool) ($w['outdated'] ?? false) => 'outdated',
            default => 'current',
        };
    }

    /**
     * Una fila por ZONA, COLOR y HORA de inicio —lo que se coge del cajón: la clave del mockup, producto + color + inicio—,
     * con la cifra sumada; un cumpleaños, aparte aunque coincida en hora. Desde la P2, la PULSERA de la fila (la que Booking
     * resolvió: la fija del producto o la de la rueda) con su frase según la cifra («pulsera lila» / «pulseras lilas») y la
     * tinta de la cifra sobre su color. Primero las de solo menores (el mockup: «Kids primero, siempre»), después por hora
     * y por zona.
     *
     * @param  list<array<string, mixed>>  $today
     * @return list<array{zona: string, cifra: int, fiesta: bool, pulsera: ?array{frase: string, hex: ?string, tinta: string}, reservas: list<array<string, mixed>>}>
     */
    private static function filas(array $today, CarbonInterface $now): array
    {
        $grupos = [];
        foreach ($today as $r) {
            $fiesta = (bool) ($r['is_party'] ?? false);
            $zona = (string) ($r['zone_name'] ?? '') !== '' ? (string) $r['zone_name'] : (string) ($r['product'] ?? '');
            $pulsera = self::pulseraDe($r);
            $color = $pulsera === null ? '' : $pulsera['other'].'#'.($pulsera['hex'] ?? '');
            $clave = implode('|', [(string) ($r['zone_slug'] ?? $zona), (string) ($r['start_time'] ?? ''), $fiesta ? 'f' : 'e', $color]);

            $grupos[$clave] ??= [
                'zona' => $zona,
                'cifra' => 0,
                'fiesta' => $fiesta,
                'pulsera' => $pulsera,
                // A igualdad, el orden en que llegan (la ordenación de PHP 8 es estable): la de una hora y la ilimitada de la
                // misma zona y hora, como vengan; nunca por el nombre de su color.
                'orden' => [(bool) ($r['minors_only'] ?? false) ? 0 : 1, (string) ($r['start_time'] ?? '99:99'), $zona],
                'reservas' => [],
            ];
            $grupos[$clave]['cifra'] += (int) ($r['quantity'] ?? 0);
            $grupos[$clave]['reservas'][] = self::reserva($r, $now);
        }

        uasort($grupos, static fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

        return array_values(array_map(static function (array $g): array {
            unset($g['orden']);
            $p = $g['pulsera'];
            $g['pulsera'] = $p === null ? null : [
                'frase' => $g['cifra'] === 1 ? $p['one'] : $p['other'],
                'hex' => $p['hex'],
                'tinta' => $p['hex'] === null ? 'oscura' : self::tinta($p['hex']),
            ];

            return $g;
        }, $grupos));
    }

    /**
     * La pulsera de una reserva tal como viaja en la ficha, leída con cuidado (la ficha viaja en el snapshot: una clave
     * puede faltar en una abierta antes de un despliegue). El hex, otra vez comprobado: va en un `style` (D15).
     *
     * @return array{one: string, other: string, hex: ?string}|null
     */
    private static function pulseraDe(array $r): ?array
    {
        $w = $r['wristband'] ?? null;
        if (! is_array($w)) {
            return null;
        }
        $one = trim((string) ($w['one'] ?? ''));
        $other = trim((string) ($w['other'] ?? ''));
        if ($one === '' && $other === '') {
            return null;
        }
        $hex = (string) ($w['hex'] ?? '');

        return [
            'one' => $one !== '' ? $one : $other,
            'other' => $other !== '' ? $other : $one,
            'hex' => preg_match('/^#[0-9a-f]{6}$/', $hex) === 1 ? $hex : null,
        ];
    }

    /**
     * **La cifra sobre el color de la pulsera**, en tinta o en blanco: la que más contraste dé (el mockup, `ppuTinta`: la
     * luminancia relativa de WCAG contra una tinta de 0,025 y el blanco). `oscura` = la tinta del panel; `clara` = blanco.
     */
    public static function tinta(string $hex): string
    {
        $canal = static function (int $offset) use ($hex): float {
            $v = hexdec(substr($hex, $offset, 2)) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $canal(1) + 0.7152 * $canal(3) + 0.0722 * $canal(5);

        return ($l + 0.05) / 0.075 >= 1.05 / ($l + 0.05) ? 'oscura' : 'clara';
    }

    /**
     * **Lo que se ENTREGA en la puerta** (la P2, D14; el mockup: «2 pares de calcetines», tras las pulseras): por complemento,
     * las unidades de TODAS las reservas de hoy, con su rótulo según la cifra y su icono, en el orden en que aparecen.
     *
     * @param  list<array<string, mixed>>  $today
     * @return list<array{clave: string, cifra: int, frase: string, icono: string}>
     */
    private static function entregas(array $today): array
    {
        $entregas = [];
        foreach ($today as $r) {
            foreach ((array) ($r['handed_at_gate'] ?? []) as $h) {
                if (! is_array($h) || (int) ($h['quantity'] ?? 0) <= 0) {
                    continue;
                }
                $clave = (string) ($h['key'] ?? '');
                $entregas[$clave] ??= [
                    'clave' => $clave,
                    'cifra' => 0,
                    'one' => (string) ($h['one'] ?? ''),
                    'other' => (string) ($h['other'] ?? ''),
                    'icono' => (string) ($h['icon'] ?? ''),
                ];
                $entregas[$clave]['cifra'] += (int) $h['quantity'];
            }
        }

        return array_values(array_map(static fn (array $e): array => [
            'clave' => $e['clave'],
            'cifra' => $e['cifra'],
            'frase' => $e['cifra'] === 1 ? $e['one'] : $e['other'],
            // El icono decide qué componente pinta la vista y la ficha viaja en el snapshot (el navegador puede devolverla
            // cambiada): solo uno de la lista curada.
            'icono' => in_array($e['icono'], ProductIcon::CHOICES, true) ? $e['icono'] : ProductIcon::DEFAULT_OTHER,
        ], $entregas));
    }

    /** @return array{codigo: string, linea: string, quien: ?string, hora: string, pagado: ?string, complementos: ?string, fiesta: bool, dinero: ?array{clase: string, texto: string}} */
    private static function reserva(array $r, CarbonInterface $now): array
    {
        $fiesta = (bool) ($r['is_party'] ?? false);
        $nombres = array_values(array_filter(array_map(static fn (mixed $m): string => is_array($m) ? trim((string) ($m['name'] ?? '')) : '', (array) ($r['minors'] ?? []))));
        $complementos = array_values(array_filter(array_map('strval', (array) ($r['addons'] ?? []))));

        return [
            'codigo' => (string) ($r['order_code'] ?? ''),
            'linea' => self::linea($r),
            'quien' => $nombres === [] ? null : implode(' · ', $nombres),
            'hora' => self::hora($r, $now),
            // Un cumpleaños no cuenta su dinero en la Puerta: se cobra al final, fuera (D6, el mockup).
            'pagado' => $fiesta ? null : self::pagado($r),
            'complementos' => $complementos === [] ? null : '+ '.implode(' · + ', $complementos),
            'fiesta' => $fiesta,
            'dinero' => $fiesta ? null : self::dinero($r),
        ];
    }

    /**
     * «Kids · 1 hora · 2 niños», «Pack Cumpleaños KIDS · 10 niños»: el producto NOMBRADO ENTERO, como lo nombra el catálogo
     * de la instalación (el readme del mockup: «el producto se nombra entero en la tarjeta de la reserva»), y cuántos.
     * «Niños» si son solo menores o es un cumpleaños; si puede entrar un adulto, «personas».
     */
    public static function linea(array $r): string
    {
        $fiesta = (bool) ($r['is_party'] ?? false);
        $n = (int) ($r['quantity'] ?? 0);
        $quien = trans_choice(self::T.($fiesta || ($r['minors_only'] ?? false) ? 'ninos' : 'personas'), $n, ['n' => $n]);

        return trim((string) ($r['product'] ?? '')).' · '.$quien;
    }

    /** «Hoy a las 17:00 · empieza en 5 min» · «… empezó hace 12 min» · «Hoy, cuando llegue» (la ilimitada) · «Hoy». */
    public static function hora(array $r, CarbonInterface $now): string
    {
        if (($r['duration_minutes'] ?? null) === null && ! ($r['is_party'] ?? false)) {
            return __(self::T.'hora_libre');
        }

        $inicio = (string) ($r['start_time'] ?? '');
        if (preg_match('/^\d{2}:\d{2}$/', $inicio) !== 1) {
            return __(self::T.'hora_sin');
        }

        $empieza = $now->copy()->setTimeFromTimeString($inicio.':00');
        $minutos = (int) round(($empieza->getTimestamp() - $now->getTimestamp()) / 60);

        return match (true) {
            $minutos > 0 => __(self::T.'hora_empieza', ['hora' => $inicio, 'falta' => self::duracion($minutos)]),
            $minutos < 0 => __(self::T.'hora_empezo', ['hora' => $inicio, 'paso' => self::duracion(-$minutos)]),
            default => __(self::T.'hora_ahora', ['hora' => $inicio]),
        };
    }

    /** «5 min», «1 h», «1 h 5 min». */
    private static function duracion(int $minutos): string
    {
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return match (true) {
            $h === 0 => __(self::T.'minutos', ['n' => $m]),
            $m === 0 => __(self::T.'solo_horas', ['h' => $h]),
            default => __(self::T.'horas_minutos', ['h' => $h, 'm' => $m]),
        };
    }

    /** «Pagado 32 € (web)» o «(mostrador)»; nada si no hay nada pagado o si el libro no cuadra (su línea lo dice). */
    private static function pagado(array $r): ?string
    {
        $cents = (int) ($r['paid_cents'] ?? 0);
        if ($cents <= 0 || ($r['balance_kind'] ?? Balance::KIND_UNDER_REVIEW) === Balance::KIND_UNDER_REVIEW) {
            return null;
        }

        return __(self::T.(($r['charge_method'] ?? null) === 'desk' ? 'pagado_mostrador' : 'pagado_web'), ['importe' => Money::showcaseWithSymbol($cents)]);
    }

    /**
     * La línea del DINERO por clase (el libro, `Balance`), en ámbar bajo su fila. En la puerta solo entran pedidos
     * cobrados: `pay_online` y `expired` no llegan. ⚠️ Una clave que falta se lee como `under_review`: un libro que no se
     * sabe leer nunca dice «nada pendiente» (D-T3·8).
     *
     * @return array{clase: string, texto: string}|null
     */
    private static function dinero(array $r): ?array
    {
        $kind = (string) ($r['balance_kind'] ?? Balance::KIND_UNDER_REVIEW);
        $cents = (int) ($r['balance_cents'] ?? 0);

        return match ($kind) {
            Balance::KIND_PAY_AT_PARK => ['clase' => 'pending', 'texto' => __(self::T.'falta_pagar', ['importe' => Money::showcaseWithSymbol($cents)])],
            Balance::KIND_REFUND_AT_PARK, Balance::KIND_REFUND_PENDING => ['clase' => 'refund', 'texto' => __(self::T.'devolver', ['importe' => Money::showcaseWithSymbol(-$cents)])],
            Balance::KIND_SETTLED => null,
            default => ['clase' => 'under-review', 'texto' => __(self::T.'no_cuadra')],
        };
    }

    /** «Sábado 26 a las 17:00 · Entrada 1 hora · 2 niños». */
    private static function otroDia(array $r): string
    {
        $dia = Str::ucfirst(DisplayTime::dayInSentence((string) ($r['date'] ?? '')));
        $inicio = (string) ($r['start_time'] ?? '');
        $cuando = preg_match('/^\d{2}:\d{2}$/', $inicio) === 1 ? __(self::T.'dia_a_las', ['dia' => $dia, 'hora' => $inicio]) : $dia;

        return $cuando.' · '.self::linea($r);
    }

    /**
     * Las tareas de FIRMAR, en su orden: la del titular (pendiente · nada · cambiado) y la de sus hijos. «Hijos» es la regla
     * de «Antes de venir» (`#777`, `#825`): una entrada de solo menores hoy sin ningún menor declarado, o un menor a cargo
     * sin descargo vigente.
     *
     * @param  list<array<string, mixed>>  $today
     * @return list<string>
     */
    private static function firmar(array $profile, array $today): array
    {
        $w = (array) ($profile['waiver'] ?? []);
        if (! ($w['enabled'] ?? false)) {
            return [];
        }

        $tareas = [];
        if (! ($w['signed'] ?? false)) {
            $tareas[] = ($w['pending_acceptance'] ?? false) ? self::FIRMAR_PENDIENTE : self::FIRMAR_NADA;
        } elseif ((bool) ($w['outdated'] ?? false)) {
            $tareas[] = self::FIRMAR_CAMBIADO;
        }

        $menores = array_values(array_filter((array) ($profile['dependents'] ?? []), static fn (mixed $m): bool => is_array($m) && (int) ($m['age'] ?? 0) < Dependent::ADULT_AGE));
        $sinDescargo = array_filter($menores, static fn (array $m): bool => in_array($m['waiver'] ?? null, ['missing', 'outdated'], true));
        $sinDeclarar = $menores === [] && array_filter($today, static fn (array $r): bool => (bool) ($r['minors_only'] ?? false) && ! ($r['is_party'] ?? false)) !== [];
        if ($sinDescargo !== [] || $sinDeclarar) {
            $tareas[] = self::FIRMAR_HIJOS;
        }

        return $tareas;
    }

    /**
     * «Sus hijos»: los menores a cargo con su nombre de pila y su edad, y SOLO la excepción del descargo (`#320`, D1).
     * Quien cumple hoy, el primero y con «su cumple».
     *
     * @param  list<array{name: string, age: ?int}>  $honorees
     * @return list<array{nombre: string, edad: string, anios: int, descargo: string, excepcion: ?string, cumple: bool}>
     */
    private static function hijos(array $profile, array $honorees): array
    {
        $hijos = [];
        foreach ((array) ($profile['dependents'] ?? []) as $m) {
            if (! is_array($m) || (int) ($m['age'] ?? 0) >= Dependent::ADULT_AGE) {
                continue;
            }
            $nombre = trim((string) ($m['name'] ?? ''));
            $edad = (int) ($m['age'] ?? 0);
            $hijos[] = [
                'nombre' => $nombre,
                'edad' => trans_choice(self::T.'edad', $edad, ['n' => $edad]),
                // Crudos, para las marcas `data-gate-minor-*` que leen las pruebas y la sonda.
                'anios' => $edad,
                'descargo' => is_string($m['waiver'] ?? null) ? $m['waiver'] : 'unknown',
                'excepcion' => self::excepcion($m['waiver'] ?? null),
                // Solo para PINTARLO: quién lo cubre lo dice la atadura (`GuardianPlaces`), nunca este cruce.
                'cumple' => in_array(['name' => $nombre, 'age' => $edad], $honorees, true),
            ];
        }

        usort($hijos, static fn (array $a, array $b): int => (int) $b['cumple'] <=> (int) $a['cumple']);

        return $hijos;
    }

    /**
     * Los menores INVITADOS fuera de un cumpleaños (el justificante de `#337`): se quedan con su nombre (D8); los de una
     * fiesta van en su línea sin nombres (`#817`). Una fila de fiesta lleva `entry` o es quien cumple.
     *
     * @return list<array{nombre: string, edad: ?string, excepcion: ?string}>
     */
    private static function invitados(array $profile): array
    {
        $fiestas = array_column(array_filter((array) ($profile['today_reservations'] ?? []), static fn (mixed $r): bool => is_array($r) && (bool) ($r['is_party'] ?? false)), 'order_code');
        $invitados = [];
        foreach ((array) ($profile['guest_minors'] ?? []) as $g) {
            if (! is_array($g) || ($g['honoree'] ?? false) || in_array($g['order_code'] ?? null, $fiestas, true)) {
                continue;
            }
            $edad = $g['age'] ?? null;
            $invitados[] = [
                'nombre' => trim((string) ($g['name'] ?? '')),
                'edad' => $edad === null ? null : trans_choice(self::T.'edad', (int) $edad, ['n' => (int) $edad]),
                'excepcion' => self::excepcion($g['waiver'] ?? null),
            ];
        }

        return $invitados;
    }

    /** «8 de 10 con autorización» (`#817`), o `null` si hoy no hay fiesta con invitación. */
    private static function fiesta(array $profile): ?string
    {
        $c = $profile['guest_minors_count'] ?? null;
        if (! is_array($c)) {
            return null;
        }

        return __(self::T.'fiesta', ['firmados' => (int) ($c['signed'] ?? 0), 'esperados' => (int) ($c['expected'] ?? 0)]);
    }

    private static function excepcion(mixed $waiver): ?string
    {
        return match ($waiver) {
            'missing' => __(self::T.'sin_descargo'),
            'outdated' => __(self::T.'descargo_antiguo'),
            default => null,
        };
    }

    /** @return list<array{name: string, age: ?int}> quien cumple hoy, por nombre y edad (para pintarlo, no para cubrirlo) */
    private static function honorees(array $profile): array
    {
        $out = [];
        foreach ((array) ($profile['guest_minors'] ?? []) as $g) {
            if (is_array($g) && ($g['honoree'] ?? false)) {
                $out[] = ['name' => trim((string) ($g['name'] ?? '')), 'age' => $g['age'] === null ? null : (int) $g['age']];
            }
        }

        return $out;
    }
}
