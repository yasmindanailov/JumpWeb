<?php

namespace Tests\Feature\Admin\Puerta;

use App\Livewire\Admin\Puerta\GateSemaphore;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Filament\Support\Icons\Heroicon;
use ReflectionClass;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — el SEMÁFORO como dato (`docs/specs/identidad-qr-puerta.md` §9.7 C·5).
 *
 * Antes había OCHO tarjetas en un `@switch`, cada una con su paleta escrita a mano. El riesgo de esa
 * forma no era el aspecto: era que **un estado nuevo saliera sin tarjeta** y el empleado no viera nada
 * tras pulsar «Verificar». Al colapsarlas en un callout ese riesgo se concentra en una tabla, y una
 * tabla sí se puede vigilar: este test la recorre contra las constantes `STATUS_*` por reflexión.
 */
class GateSemaphoreTest extends TestCase
{
    /** @return list<string> los valores de las constantes `ValidarRegistro::STATUS_*` */
    private function statuses(): array
    {
        $constants = (new ReflectionClass(ValidarRegistro::class))->getConstants();

        return array_values(array_filter(
            $constants,
            static fn (string $name): bool => str_starts_with($name, 'STATUS_'),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    public function test_every_status_has_a_tone_and_the_tone_uses_a_registered_color(): void
    {
        $tones = GateSemaphore::tones();
        $statuses = $this->statuses();

        $this->assertGreaterThanOrEqual(9, count($statuses), 'si esta cuenta baja, alguien retiró un estado: revisa la tabla');

        foreach ($statuses as $status) {
            $this->assertArrayHasKey($status, $tones, "el estado «{$status}» se pintaría sin callout: el empleado no vería NADA");
            [$color, $icon] = $tones[$status];
            $this->assertContains($color, GateSemaphore::ALLOWED_COLORS, "«{$color}» no es un color que el panel registre: el callout saldría sin color");
            $this->assertInstanceOf(Heroicon::class, $icon);
        }

        $this->assertSame(
            count($statuses),
            count($tones),
            'la tabla tiene tonos para estados que ya no existen: sobra una fila',
        );
    }

    public function test_every_status_resolves_a_heading_or_a_body_never_a_callout_vacio(): void
    {
        foreach ($this->statuses() as $status) {
            $s = GateSemaphore::for(['status' => $status, 'date' => '05/09/2026']);

            $this->assertTrue(
                $s['heading'] !== null || $s['body'] !== null,
                "«{$status}» pintaría un callout sin una sola palabra dentro",
            );
        }
    }

    /**
     * Los tres estados que hablan de la BÚSQUEDA (no del cliente) van en gris o ámbar, nunca en rojo:
     * un carné no reconocido NO significa «no registrado», y teñirlo de rojo empuja al empleado a
     * negar la entrada a alguien que sí está en el sistema (§4.6, los tres estados «en voz alta»).
     */
    public function test_only_not_registered_is_red(): void
    {
        $reds = array_keys(array_filter(
            GateSemaphore::tones(),
            static fn (array $tone): bool => $tone[0] === 'danger',
        ));

        $this->assertSame([ValidarRegistro::STATUS_NOT_REGISTERED], $reds);
    }

    public function test_the_outdated_note_only_appears_with_a_signed_and_outdated_waiver(): void
    {
        $signed = ['status' => ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER, 'date' => '05/09/2026'];

        $this->assertNull(GateSemaphore::for($signed)['note']);
        $this->assertNull(GateSemaphore::for($signed + ['outdated' => false])['note']);
        $this->assertNotNull(GateSemaphore::for($signed + ['outdated' => true])['note']);

        // Y NO se cuela en otro estado que arrastre la bandera por accidente.
        $this->assertNull(GateSemaphore::for(['status' => ValidarRegistro::STATUS_REGISTERED, 'outdated' => true])['note']);
    }

    /** El semáforo lee la fecha del resultado; si dejara de hacerlo, el rótulo diría «el .». */
    public function test_the_signed_date_travels_into_the_body(): void
    {
        $s = GateSemaphore::for(['status' => ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER, 'date' => '05/09/2026']);

        $this->assertStringContainsString('05/09/2026', (string) $s['body']);
    }
}
