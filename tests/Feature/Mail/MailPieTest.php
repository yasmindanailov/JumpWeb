<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\OperatingWindow;
use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailPie;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL PIE de todos los correos** (la R1a, `specs/correos-rediseno.md` §4.1.1; brief: «dirección, horario de hoy,
 * teléfono, WhatsApp y correo»): del panel y del calendario de operación, cada línea SOLO si su dato existe, y el horario
 * de hoy con su DÍA, porque el correo se puede leer mañana.
 */
class MailPieTest extends TestCase
{
    use RefreshDatabase;

    /** Un calendario que responde siempre con la misma ventana: el pie no decide la prioridad del dominio, la lee. */
    private function calendario(OperatingWindow $ventana): void
    {
        $this->app->instance(OperatingCalendar::class, new class($ventana) implements OperatingCalendar
        {
            public function __construct(private readonly OperatingWindow $ventana) {}

            public function windowFor(CarbonInterface $date): OperatingWindow
            {
                return $this->ventana;
            }

            public function weeklyOpenings(): array
            {
                return [];
            }

            public function activeSeasons(): array
            {
                return [];
            }

            public function upcomingSpecialDays(int $limit): array
            {
                return [];
            }

            public function hasSpecialDay(CarbonInterface $date): bool
            {
                return false;
            }
        });
    }

    /** @param  array<string, string>  $ajustes */
    private function ajustes(array $ajustes): void
    {
        foreach ($ajustes as $clave => $valor) {
            Setting::updateOrCreate(['key' => $clave], ['value' => $valor, 'group' => 'contact']);
        }
        Setting::flushMemo();
    }

    public function test_every_line_comes_from_the_panel_and_today_carries_its_day(): void
    {
        $this->app->setLocale('es');
        $this->calendario(new OperatingWindow(true, '16:30:00', '21:30:00'));
        $this->ajustes([
            'business.name' => 'Parque Demo',
            'address.line1' => 'Calle Uno, 2',
            'address.line2' => '30800 Lorca',
            'contact.phone' => '641 99 57 14',
            'contact.whatsapp' => '+34 641-99-57-14',
            'contact.email' => 'hola@demo.test',
        ]);

        $pie = MailPie::current(CarbonImmutable::parse('2026-09-24'));

        $this->assertSame('Parque Demo', $pie->nombre);
        $this->assertSame('Calle Uno, 2, 30800 Lorca', $pie->direccion);
        $this->assertSame('Hoy, jueves 24, abrimos de 16:30 a 21:30.', $pie->hoy);
        $this->assertSame('641 99 57 14', $pie->telefono);
        $this->assertSame('641995714', $pie->tel);
        $this->assertSame('34641995714', $pie->whatsapp);
        $this->assertSame('hola@demo.test', $pie->correo);
    }

    public function test_a_closed_day_says_so_and_an_open_day_without_hours_says_nothing(): void
    {
        $this->app->setLocale('es');
        $this->calendario(new OperatingWindow(false, null, null));
        $this->assertSame('Hoy, jueves 24, no abrimos.', MailPie::current(CarbonImmutable::parse('2026-09-24'))->hoy);

        // Abierto SIN ventana (el recinto sin horario configurado): pintar unas horas sería inventarlas.
        $this->calendario(new OperatingWindow(true, null, null));
        $this->assertNull(MailPie::current(CarbonImmutable::parse('2026-09-24'))->hoy);
    }

    /** Sin dato, sin línea: ni una coma huérfana, ni un `tel:` vacío, ni un correo mal escrito hecho enlace. */
    public function test_without_its_data_a_line_is_not_painted(): void
    {
        $this->calendario(new OperatingWindow(true, null, null));
        $this->ajustes(['contact.email' => 'esto no es un correo']);

        $pie = MailPie::current();
        $this->assertNull($pie->direccion);
        $this->assertNull($pie->telefono);
        $this->assertNull($pie->whatsapp);
        $this->assertNull($pie->correo);

        $html = (string) (new BrandedMailMessage)->hero('emails.order_declined', 'info')->render();
        $pie = substr($html, (int) strpos($html, 'data-bloque="pie"'));
        $this->assertStringNotContainsString('tel:', $pie);
        $this->assertStringNotContainsString('wa.me', $pie);
        $this->assertStringNotContainsString('mailto:', $pie);
    }

    /** Y lo que hay llega al correo, en el HTML y en su versión de texto. */
    public function test_the_footer_reaches_both_parts_of_the_mail(): void
    {
        $this->app->setLocale('es');
        $this->calendario(new OperatingWindow(true, '11:00:00', '21:30:00'));
        $this->ajustes([
            'business.name' => 'Parque Demo', 'address.line1' => 'Calle Uno, 2',
            'contact.phone' => '641 99 57 14', 'contact.whatsapp' => '34641995714', 'contact.email' => 'hola@demo.test',
        ]);
        $m = (new BrandedMailMessage)->hero('emails.order_declined', 'info');

        $html = (string) $m->render();
        $texto = (string) view(BrandedMailMessage::VISTAS['text'], $m->data())->render();

        foreach (['href="tel:641995714"', 'href="https://wa.me/34641995714"', 'href="mailto:hola@demo.test"', 'Calle Uno, 2', 'abrimos de 11:00 a 21:30'] as $aguja) {
            $this->assertStringContainsString($aguja, $html);
        }
        $this->assertStringContainsString("Parque Demo\nCalle Uno, 2\n", $texto);
        $this->assertStringContainsString('641 99 57 14 · WhatsApp: https://wa.me/34641995714 · hola@demo.test', $texto);
    }
}
