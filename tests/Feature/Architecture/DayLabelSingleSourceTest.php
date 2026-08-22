<?php

namespace Tests\Feature\Architecture;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **El rótulo de un día tiene UNA fuente** (`Platform\Services\DisplayTime::dayLabel`).
 *
 * ⚠️ **Nace de un hallazgo, no de una preferencia** (2026-08-22, `specs/area-cliente.md`). La
 * fórmula `Str::ucfirst(Carbon::parse(…)->locale(app()->getLocale())->isoFormat('ddd D MMM'))`
 * estaba **copiada literalmente en cuatro superficies públicas**: el bloque de cuenta del cajón, la
 * página «Mis reservas», el post-form de invitados y la tarjeta de producto de los correos. Es
 * exactamente lo que `openapi/v1.yaml` describe para `shows_deposit_note` — «hay cuatro superficies
 * pintando este bloque, y recomponerlo en cada una es como divergen»— y ahora hay una quinta, la
 * API, que además publica el resultado.
 *
 * Cuatro copias no divergen el día que se escriben: divergen el día que alguien arregla una.
 */
class DayLabelSingleSourceTest extends TestCase
{
    // Solo el último caso toca BD (fija `display_timezone` para probar que NO desplaza el día).
    use RefreshDatabase;

    /**
     * Quién puede componer el rótulo a mano, y por qué.
     *
     * ⚠️ **Con motivo escrito, no como lista de perdonados.** El panel admin es otra superficie con
     * su propio formato —varios llevan `YYYY`, que el cliente no ve— y unificarlo cambiaría lo que
     * enseña sin que nadie lo haya pedido; `ScheduleDisplay` compone SIN `ucfirst`, así que
     * adoptar la fuente única alteraría su texto. Los dos son trabajos aparte, no descuidos.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        // La fuente única. Aquí vive la fórmula.
        'app/Domain/Platform/Services/DisplayTime.php',
        // Contenido público: mismo formato, pero SIN mayúscula inicial. Cambiarlo cambiaría su texto.
        'app/Domain/Content/Services/ScheduleDisplay.php',
        // Panel admin: superficie distinta, formatos propios (varios con `YYYY`).
        'app/Filament/Widgets/ReservationsWidget.php',
        'resources/views/filament/orders/items-list.blade.php',
        'resources/views/filament/orders/partials/manage-item-calendar.blade.php',
        'resources/views/filament/orders/partials/order-audit-view.blade.php',
    ];

    public function test_nobody_else_composes_the_day_label_by_hand(): void
    {
        $offenders = [];

        foreach ($this->sources() as $relative => $path) {
            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            if (str_contains((string) file_get_contents($path), "isoFormat('ddd D MMM')")) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [], $offenders,
            "Estos ficheros componen el rótulo de día a mano:\n  ".implode("\n  ", $offenders)."\n\n".
            "⚠️ Usa `DisplayTime::dayLabel()`. La fórmula llegó a estar copiada en CUATRO superficies\n".
            "públicas; cuatro copias no divergen el día que se escriben, sino el día que alguien\n".
            'arregla una. Si de verdad es otra superficie con otro formato, DECLÁRALA con su motivo.'
        );
    }

    /**
     * **La guarda de la guarda.** Sin esto, un escáner que no encontrara ningún fichero dejaría el
     * caso de arriba pasando solo y para siempre — es lo que le pasó al contador de
     * `PurchaseRetirementTest` en `DECISIONES #63`.
     */
    public function test_the_scan_actually_reads_the_tree(): void
    {
        $sources = $this->sources();

        $this->assertGreaterThan(500, count($sources), 'el escaneo no está leyendo el árbol');

        $this->assertArrayHasKey(
            'app/Domain/Platform/Services/DisplayTime.php', $sources,
            'el escaneo no ve ni la propia fuente única'
        );
    }

    /**
     * Y que la fuente única **siga componiendo exactamente lo que componían las cuatro copias**: si
     * esto cambia, cambian a la vez la web, los correos, el post-form y la API.
     */
    public function test_the_single_source_still_composes_what_the_four_copies_did(): void
    {
        $date = '2026-08-23';

        foreach (['es', 'en', 'fr'] as $locale) {
            $this->app->setLocale($locale);

            $expected = Str::ucfirst(Carbon::parse($date)->locale($locale)->isoFormat('ddd D MMM'));

            $this->assertSame($expected, DisplayTime::dayLabel($date), "divergió en «{$locale}»");
        }
    }

    /**
     * ⚠️ **Una fecha de franja es una fecha CIVIL, no un instante.** `dayLabel()` no le aplica zona
     * horaria a propósito: hacerlo desplazaría el día, y en cualquier zona al oeste de UTC el «23 de
     * agosto» se pintaría como el 22. Es la diferencia con `format()`, que sí la aplica porque un
     * `created_at` sí es un instante.
     */
    public function test_the_day_label_does_not_shift_with_the_installation_timezone(): void
    {
        $this->app->setLocale('es');

        config(['app.timezone' => 'UTC']);
        Setting::query()->updateOrCreate(
            ['key' => 'display_timezone'],
            ['value' => 'America/Los_Angeles'],
        );
        Setting::flushMemo();

        $this->assertSame(
            'Dom. 23 ago.', DisplayTime::dayLabel('2026-08-23'),
            'el rótulo se ha desplazado un día: se le está aplicando zona horaria a una fecha civil'
        );
    }

    /** @return array<string, string> ruta relativa → absoluta */
    private function sources(): array
    {
        $files = [];

        foreach (['app', 'resources/views'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }

                $files[str_replace(base_path().'/', '', $file->getPathname())] = $file->getPathname();
            }
        }

        return $files;
    }
}
