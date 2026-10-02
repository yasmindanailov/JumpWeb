<?php

namespace Tests\Feature\Puerta;

use App\Domain\Booking\Models\WristbandColor;
use App\Domain\Booking\Services\WristbandWheel;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La rueda de las pulseras** (`docs/specs/puerta-nueva.md` §4.4, la P2; D11): qué color lleva cada hora de inicio. El
 * caso de referencia es la tabla `HORAS` del mockup —seis colores cada media hora desde las 11:00: 12:00 el tercero, 17:00
 * el primero, 21:30 el cuarto—, con colores NEUTROS (el producto no sabe de qué color es ningún parque).
 */
class WristbandWheelTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<WristbandColor> seis colores, sin guardar: la rueda es pura */
    private function six(): array
    {
        return array_map(
            static fn (string $n): WristbandColor => new WristbandColor(['name_one' => "pulsera {$n}", 'name_other' => "pulseras {$n}", 'hex' => '#000000']),
            ['A', 'B', 'C', 'D', 'E', 'F'],
        );
    }

    private function nombre(?WristbandColor $color): ?string
    {
        return $color?->name_one;
    }

    public function test_the_mockup_table_comes_out_of_the_wheel(): void
    {
        $wheel = new WristbandWheel(11 * 60, 30, $this->six());

        // La tabla `HORAS` de `paginas/puerta/datos.js`, con sus colores por su PUESTO en la rueda.
        $expected = [
            '11:00' => 'A', '11:30' => 'B', '12:00' => 'C', '12:30' => 'D', '13:00' => 'E', '13:30' => 'F',
            '14:00' => 'A', '14:30' => 'B', '15:00' => 'C', '15:30' => 'D', '16:00' => 'E', '16:30' => 'F',
            '17:00' => 'A', '17:30' => 'B', '18:00' => 'C', '18:30' => 'D', '19:00' => 'E', '19:30' => 'F',
            '20:00' => 'A', '20:30' => 'B', '21:00' => 'C', '21:30' => 'D',
        ];
        foreach ($expected as $time => $letter) {
            $this->assertSame("pulsera {$letter}", $this->nombre($wheel->colorFor($time)), "a las {$time}");
        }
        $this->assertSame('pulsera A', $this->nombre($wheel->colorFor('17:00:00')), 'con segundos, como viene de la franja');
    }

    public function test_the_wheel_runs_backwards_and_a_time_inside_a_step_takes_its_step(): void
    {
        $wheel = new WristbandWheel(11 * 60, 30, $this->six());

        $this->assertSame('pulsera F', $this->nombre($wheel->colorFor('10:30')), 'antes de la primera hora, la rueda hacia atrás');
        $this->assertSame('pulsera E', $this->nombre($wheel->colorFor('10:00')));
        $this->assertSame('pulsera F', $this->nombre($wheel->colorFor('10:59')), 'un minuto antes de las 11:00 es el paso de las 10:30');
        $this->assertSame('pulsera A', $this->nombre($wheel->colorFor('17:15')), 'a mitad de paso, el del paso en curso');
        $this->assertSame('pulsera A', $this->nombre($wheel->colorFor('17:29')));
        $this->assertSame('pulsera B', $this->nombre($wheel->colorFor('17:30')));

        $hourly = new WristbandWheel(9 * 60, 60, $this->six());
        $this->assertSame('pulsera C', $this->nombre($hourly->colorFor('11:45')), 'otro paso, otra cuenta');
    }

    public function test_without_a_start_without_colors_or_without_a_time_there_is_no_colour(): void
    {
        $this->assertNull((new WristbandWheel(null, 30, $this->six()))->colorFor('17:00'));
        $this->assertNull((new WristbandWheel(11 * 60, 30, []))->colorFor('17:00'));

        $wheel = new WristbandWheel(11 * 60, 30, $this->six());
        $this->assertNull($wheel->colorFor(null));
        $this->assertNull($wheel->colorFor('25:00'));
        $this->assertNull($wheel->colorFor('cuando llegue'));
    }

    /** Del panel: la hora del primero y el paso (acotado; vacío o fuera de rango, 30), y solo los colores EN LA RUEDA, en su orden. */
    public function test_the_wheel_of_the_panel_reads_its_two_settings_and_only_the_colours_in_the_wheel_in_their_order(): void
    {
        $this->assertNull(WristbandWheel::fromSettings()->colorFor('17:00'), 'sin ajustes, sin rueda');

        WristbandColor::create(['name_one' => 'segunda', 'name_other' => 'segundas', 'hex' => '#222222', 'in_wheel' => true, 'position' => 2]);
        WristbandColor::create(['name_one' => 'fija', 'name_other' => 'fijas', 'hex' => '#333333', 'in_wheel' => false, 'position' => 0]);
        WristbandColor::create(['name_one' => 'primera', 'name_other' => 'primeras', 'hex' => '#111111', 'in_wheel' => true, 'position' => 1]);
        Setting::updateOrCreate(['key' => WristbandWheel::KEY_START], ['value' => '17:00', 'group' => 'puerta']);

        $wheel = WristbandWheel::fromSettings();
        $this->assertSame('primera', $this->nombre($wheel->colorFor('17:00')), 'la de menor posición EN LA RUEDA; la fija no cuenta');
        $this->assertSame('segunda', $this->nombre($wheel->colorFor('17:30')), 'sin paso guardado, cada 30 min');
        $this->assertSame('primera', $this->nombre($wheel->colorFor('18:00')));

        foreach (['0', '4', '241', 'cada rato'] as $bad) {
            Setting::updateOrCreate(['key' => WristbandWheel::KEY_STEP], ['value' => $bad, 'group' => 'puerta']);
            $this->assertSame(WristbandWheel::STEP_DEFAULT, WristbandWheel::step(), "«{$bad}» no es un paso");
        }
        Setting::updateOrCreate(['key' => WristbandWheel::KEY_STEP], ['value' => '60', 'group' => 'puerta']);
        $this->assertSame('segunda', $this->nombre(WristbandWheel::fromSettings()->colorFor('18:00')), 'cada hora');
    }
}
