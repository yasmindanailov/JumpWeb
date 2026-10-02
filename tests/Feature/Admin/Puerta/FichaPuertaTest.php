<?php

namespace Tests\Feature\Admin\Puerta;

use App\Livewire\Admin\Puerta\FichaPuerta;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * **Lo que la Puerta nueva pinta de una ficha** (`docs/specs/puerta-nueva.md` §4.4, la P1): cada regla del presentador con
 * los textos del mockup ESCRITOS A MANO (una aserción con `__()` pasa aunque se vacíe la clave, `#734`).
 */
class FichaPuertaTest extends TestCase
{
    private const NBSP = "\u{00A0}";

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    private function now(string $hora = '16:55'): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-02 '.$hora, 'Europe/Madrid');
    }

    /** @return array<string, mixed> */
    private function fila(array $over = []): array
    {
        return $over + [
            'order_code' => 'R-7K2P4', 'order_item_id' => 1, 'date' => '2026-10-02', 'time_window' => '17:00–18:00',
            'product' => 'Kids · 1 hora', 'is_entry' => true, 'quantity' => 2, 'addons' => [], 'paid_cents' => 3200,
            'balance_kind' => 'settled', 'balance_cents' => 0, 'charge_method' => 'redsys', 'paid_at' => null,
            'created_at' => '2026-09-30T10:00:00+02:00', 'minors' => [],
            'zone_name' => 'Kids', 'zone_slug' => 'kids', 'duration_minutes' => 60, 'start_time' => '17:00',
            'is_party' => false, 'minors_only' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function ficha(array $today = [], array $over = []): array
    {
        return $over + [
            'waiver' => ['enabled' => true, 'signed' => true, 'accepted_on' => '2026-09-01', 'outdated' => false, 'pending_acceptance' => false],
            'today_reservations' => $today, 'window' => [], 'dependents' => [], 'guest_minors' => [], 'guest_minors_count' => null,
        ];
    }

    public function test_rows_group_by_zone_and_start_with_the_minors_only_first_and_a_party_apart(): void
    {
        $f = FichaPuerta::de($this->ficha([
            $this->fila(['order_code' => 'R-JUMP', 'zone_name' => 'Jump', 'zone_slug' => 'jump', 'quantity' => 1, 'minors_only' => false, 'product' => 'Jump · 1 hora']),
            $this->fila(['order_code' => 'R-K1', 'minors' => [['name' => 'Vera', 'age' => 6, 'waiver' => 'current'], ['name' => 'Leo', 'age' => 3, 'waiver' => 'current']], 'addons' => ['1 × Tarta', '2 × Calcetines']]),
            $this->fila(['order_code' => 'R-K2', 'quantity' => 1]),
            $this->fila(['order_code' => 'R-FIESTA', 'product' => 'Pack Cumpleaños KIDS', 'is_party' => true, 'quantity' => 10, 'duration_minutes' => 120, 'minors_only' => false, 'paid_cents' => 10000, 'balance_kind' => 'pay_at_park', 'balance_cents' => 9000]),
        ]), $this->now());

        $this->assertSame([['Kids', 3, false], ['Jump', 1, false], ['Kids', 10, true]], array_map(fn (array $g): array => [$g['zona'], $g['cifra'], $g['fiesta']], $f['filas']), 'Kids primero; el cumpleaños, su propia fila aunque coincida en zona y hora');

        $kids = $f['filas'][0]['reservas'];
        $this->assertSame(['R-K1', 'R-K2'], array_column($kids, 'codigo'));
        $this->assertSame('Kids · 1 hora · 2 niños', $kids[0]['linea'], 'el producto nombrado entero, como lo nombra el catálogo');
        $this->assertSame('Vera · Leo', $kids[0]['quien']);
        $this->assertSame('+ 1 × Tarta · + 2 × Calcetines', $kids[0]['complementos']);
        $this->assertNull($kids[1]['quien'], 'sin menores asignados, sin «quién»');
        $this->assertSame('Jump · 1 hora · 1 persona', $f['filas'][1]['reservas'][0]['linea'], 'con adultos: «personas»');

        $fiesta = $f['filas'][2]['reservas'][0];
        $this->assertSame('Pack Cumpleaños KIDS · 10 niños', $fiesta['linea'], 'un cumpleaños cuenta niños');
        $this->assertNull($fiesta['pagado'], 'un cumpleaños no cuenta su dinero en la Puerta (D6)');
        $this->assertNull($fiesta['dinero']);
    }

    public function test_the_time_says_when_it_starts_or_started_and_the_unlimited_comes_when_it_comes(): void
    {
        $hora = fn (array $over, string $now): string => FichaPuerta::hora($this->fila($over), $this->now($now));

        $this->assertSame('Hoy a las 17:00 · empieza en 5 min', $hora([], '16:55'));
        $this->assertSame('Hoy a las 17:00 · empezó hace 12 min', $hora([], '17:12'));
        $this->assertSame('Hoy a las 18:00 · empieza en 1 h 5 min', $hora(['start_time' => '18:00'], '16:55'));
        $this->assertSame('Hoy a las 18:00 · empieza en 2 h', $hora(['start_time' => '18:00'], '16:00'));
        $this->assertSame('Hoy a las 17:00 · empieza ahora', $hora([], '17:00'));
        $this->assertSame('Hoy, cuando llegue', $hora(['duration_minutes' => null], '16:55'), 'la ilimitada');
        $this->assertSame('Hoy a las 17:00 · empieza en 5 min', $hora(['duration_minutes' => null, 'is_party' => true], '16:55'), 'un cumpleaños sin duración sigue teniendo su hora');
    }

    public function test_money_is_one_line_per_class_and_a_book_that_does_not_add_up_never_says_paid(): void
    {
        $de = fn (array $over): array => FichaPuerta::de($this->ficha([$this->fila($over)]), $this->now())['filas'][0]['reservas'][0];

        $this->assertSame(['Pagado 32'.self::NBSP.'€ (web)', null], [$de([])['pagado'], $de([])['dinero']]);
        $this->assertSame('Pagado 32'.self::NBSP.'€ (mostrador)', $de(['charge_method' => 'desk'])['pagado']);
        $this->assertSame(['clase' => 'pending', 'texto' => 'Falta pagar 12'.self::NBSP.'€: avisa al encargado.'], $de(['balance_kind' => 'pay_at_park', 'balance_cents' => 1200])['dinero']);
        $this->assertSame(['clase' => 'refund', 'texto' => 'Hay que devolverle 12'.self::NBSP.'€: avisa al encargado.'], $de(['balance_kind' => 'refund_at_park', 'balance_cents' => -1200])['dinero']);
        $this->assertSame('refund', $de(['balance_kind' => 'refund_pending', 'balance_cents' => -500])['dinero']['clase']);

        $noCuadra = $de(['balance_kind' => 'under_review']);
        $this->assertSame(['clase' => 'under-review', 'texto' => 'El dinero de esta reserva no cuadra: avisa al encargado.'], $noCuadra['dinero']);
        $this->assertNull($noCuadra['pagado'], 'un libro que no cuadra nunca dice «Pagado» (D-T3·8)');

        $sinClase = $this->fila();
        unset($sinClase['balance_kind']);
        $this->assertSame('under-review', FichaPuerta::de($this->ficha([$sinClase]), $this->now())['filas'][0]['reservas'][0]['dinero']['clase'], 'una clave que falta en un snapshot viejo se lee como «no cuadra»');
    }

    public function test_the_signing_tasks_follow_the_holder_and_the_children(): void
    {
        $tareas = fn (array $waiver, array $deps = [], array $today = []): array => FichaPuerta::de($this->ficha($today, [
            'waiver' => $waiver + ['enabled' => true, 'signed' => true, 'accepted_on' => null, 'outdated' => false, 'pending_acceptance' => false],
            'dependents' => $deps,
        ]), $this->now())['firmar'];

        $this->assertSame([], $tareas(['enabled' => false, 'signed' => false]), 'con el descargo apagado no hay nada que firmar');
        $this->assertSame(['pendiente'], $tareas(['signed' => false, 'pending_acceptance' => true]));
        $this->assertSame(['nada'], $tareas(['signed' => false]));
        $this->assertSame(['cambiado'], $tareas(['outdated' => true]));
        $this->assertSame([], $tareas([]));
        $this->assertSame(['hijos'], $tareas([], [['name' => 'Leo', 'age' => 3, 'waiver' => 'missing']]));
        $this->assertSame(['hijos'], $tareas([], [], [$this->fila()]), 'una entrada de solo menores sin ningún hijo declarado');
        $this->assertSame([], $tareas([], [], [$this->fila(['minors_only' => false])]), 'si puede entrar un adulto, no se da por hecho que vengan hijos');
        $this->assertSame(['nada', 'hijos'], $tareas(['signed' => false], [['name' => 'Leo', 'age' => 3, 'waiver' => 'outdated']]));
    }

    public function test_the_children_show_only_the_exception_and_the_birthday_child_first(): void
    {
        $f = FichaPuerta::de($this->ficha([], [
            'dependents' => [
                ['name' => 'Leo', 'age' => 3, 'waiver' => 'missing'],
                ['name' => 'Vera', 'age' => 6, 'waiver' => 'current'],
                ['name' => 'Hugo', 'age' => 1, 'waiver' => 'outdated'],
                ['name' => 'Marta', 'age' => 19, 'waiver' => 'current'],
            ],
            'guest_minors' => [['order_code' => 'R-F', 'name' => 'Vera', 'age' => 6, 'waiver' => 'current', 'entry' => 'signed', 'honoree' => true]],
        ]), $this->now());

        $this->assertSame([
            ['nombre' => 'Vera', 'edad' => '6 años', 'anios' => 6, 'descargo' => 'current', 'excepcion' => null, 'cumple' => true],
            ['nombre' => 'Leo', 'edad' => '3 años', 'anios' => 3, 'descargo' => 'missing', 'excepcion' => 'sin descargo', 'cumple' => false],
            ['nombre' => 'Hugo', 'edad' => '1 año', 'anios' => 1, 'descargo' => 'outdated', 'excepcion' => 'descargo antiguo', 'cumple' => false],
        ], $f['hijos'], 'solo la excepción (#320); el mayor de edad no es un hijo a cargo en la puerta');
    }

    public function test_invited_minors_outside_a_party_keep_their_name_and_a_party_is_one_line(): void
    {
        $f = FichaPuerta::de($this->ficha([$this->fila(['order_code' => 'R-FIESTA', 'is_party' => true])], [
            'guest_minors' => [
                ['order_code' => 'R-FIESTA', 'name' => 'Nora', 'age' => 7, 'waiver' => 'current', 'entry' => 'signed', 'honoree' => false],
                ['order_code' => 'R-OTRO', 'name' => 'Iker', 'age' => null, 'waiver' => 'missing', 'entry' => null, 'honoree' => false],
            ],
            'guest_minors_count' => ['signed' => 8, 'expected' => 10],
        ]), $this->now());

        $this->assertSame([['nombre' => 'Iker', 'edad' => null, 'excepcion' => 'sin descargo']], $f['invitados'], 'los de la fiesta no salen con nombre (#817); los demás sí (D8)');
        $this->assertSame('8 de 10 con autorización', $f['fiesta']);
        $this->assertNull(FichaPuerta::de($this->ficha(), $this->now())['fiesta'], 'sin fiesta con invitación, sin línea');
    }

    public function test_without_a_booking_today_another_day_is_told_in_a_sentence(): void
    {
        $f = FichaPuerta::de($this->ficha([], ['window' => [$this->fila(['date' => '2026-09-26'])]]), $this->now());

        $this->assertTrue($f['sin_reserva']);
        $this->assertSame(['Sábado 26 a las 17:00 · Kids · 1 hora · 2 niños'], $f['otros_dias']);
        $this->assertSame([], FichaPuerta::de($this->ficha([$this->fila()], ['window' => [$this->fila(['date' => '2026-09-26'])]]), $this->now())['otros_dias'], 'con reserva hoy, el otro día no se cuenta');
    }
}
