<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **La retirada del sidebar Livewire, vigilada mientras dura** (Fase 4 · paso 4.7·2).
 *
 * `Livewire\Tickets\Purchase` está condenado: la SPA lo sustituye y el paso 4.7 lo borra. Pero la
 * retirada no es borrar un fichero — se midió: **26 ficheros de test lo EJECUTAN**, con ~160 casos —,
 * así que va por tramos y convive un tiempo con su sustituto.
 *
 * ⚠️ **El riesgo de un desmontaje largo es que alguien siga construyendo encima.** Un test nuevo que
 * conduzca por el componente añade trabajo a la retirada y, peor, cobertura que se perderá al borrarlo.
 * Este guardián no prohíbe tocar los que ya existen: prohíbe que aparezcan MÁS.
 *
 * **La lista solo puede ENCOGER.** Cuando un fichero deje de depender del componente —porque se
 * re-apunte al servidor o porque muera con él— se quita de aquí, y el test lo exige: una entrada que ya
 * no depende es tan mala señal como una dependencia nueva sin declarar.
 *
 * ⚠️ **No clasifica: inventaría.** Qué hacer con cada fichero es una decisión por fichero, y vive en
 * `DECISIONES #60(d)`. Meter la familia aquí sería fijar en un test una decisión que aún no está tomada.
 */
class PurchaseRetirementTest extends TestCase
{
    /** Cómo se reconoce que un test CONDUCE por el componente (no que lo mencione en un comentario). */
    private const DRIVES = 'Livewire::test(Purchase::class)';

    /**
     * Los ficheros que hoy conducen por el componente. **Solo puede encoger.**
     *
     * El recuento de casos NO se fija aquí a propósito: cambia con cualquier añadido legítimo a un
     * fichero que todavía existe, y lo que importa vigilar es la superficie, no su grosor.
     *
     * @var list<string>
     */
    private const DEPENDENTS = [
        'tests/Feature/Api/V1/AvailabilityTest.php',
        'tests/Feature/Api/V1/QuoteTest.php',
        'tests/Feature/Architecture/ModuleContractsTest.php',
        'tests/Feature/Auth/DuplicateEmailEdgeCaseTest.php',
        'tests/Feature/Maintenance/ReservationPauseTest.php',
        'tests/Feature/Sales/AddonDependencyTest.php',
        'tests/Feature/Sales/AddonInclusionPurchaseTest.php',
        'tests/Feature/Sales/CatalogVisibilityAndCartPruneTest.php',
        'tests/Feature/Sales/PurchaseCatalogGroupingTest.php',
        'tests/Feature/Sales/PurchaseConfirmationStatusTest.php',
        'tests/Feature/Sales/PurchaseIdentificationTest.php',
        'tests/Feature/Sales/PurchaseLimitsTest.php',
        'tests/Feature/Sales/PurchasePanelTest.php',
        'tests/Feature/Sales/PurchaseRegistrationPromptTest.php',
        'tests/Feature/Sales/SidebarV2Test.php',
        'tests/Feature/Sidebar/SidebarAddonsParityTest.php',
        'tests/Feature/Sidebar/SidebarAdmissionParityTest.php',
        'tests/Feature/Sidebar/SidebarCalendarParityTest.php',
        'tests/Feature/Sidebar/SidebarCartParityTest.php',
        'tests/Feature/Sidebar/SidebarDomContractTest.php',
        'tests/Feature/Sidebar/SidebarOutcomeParityTest.php',
        'tests/Feature/Sidebar/SidebarPausedParityTest.php',
        'tests/Feature/Sidebar/SidebarPayParityTest.php',
        'tests/Feature/Sidebar/SidebarProgressParityTest.php',
        'tests/Feature/Ui/SpinnerTest.php',
    ];

    public function test_no_new_test_starts_driving_through_the_dying_component(): void
    {
        $found = $this->driversOnDisk();
        $new = array_values(array_diff($found, self::DEPENDENTS));

        $this->assertSame(
            [], $new,
            "Hay tests NUEVOS que conducen por `Livewire\\Tickets\\Purchase`, que está en retirada:\n  ".
            implode("\n  ", $new)."\n\n".
            "⚠️ Ese componente se borra en el paso 4.7, así que lo que construyas encima habrá que\n".
            "rehacerlo o se perderá. Conduce por la API (`/api/v1/…`) o por el dominio, que es donde\n".
            'vive la regla y lo que sobrevive a la retirada.'
        );
    }

    /**
     * ⚠️ **Y la lista solo puede encoger.** Una entrada que ya no depende del componente significa que
     * el tramo de retirada avanzó y nadie lo anotó: la lista deja de ser el inventario que dice cuánto
     * falta. Es la misma disciplina que las baselines del arch-test de Fase 2.
     */
    public function test_the_inventory_only_shrinks(): void
    {
        $found = $this->driversOnDisk();
        $stale = array_values(array_diff(self::DEPENDENTS, $found));

        $this->assertSame(
            [], $stale,
            "El inventario declara ficheros que YA NO conducen por el componente:\n  ".implode("\n  ", $stale)."\n\n".
            'Quítalos de `DEPENDENTS`: la lista es la que dice cuánto falta para poder borrar `Purchase`.'
        );
    }

    /** La guarda de la guarda: si el patrón dejara de encontrar nada, las dos de arriba pasarían solas. */
    public function test_the_scan_actually_finds_the_dependents(): void
    {
        $this->assertGreaterThan(
            10, count($this->driversOnDisk()),
            'el escaneo encuentra muy pocos dependientes: ¿ha cambiado la forma de conducir el componente?'
        );
    }

    /** @return list<string> */
    private function driversOnDisk(): array
    {
        $files = [];
        $root = base_path('tests');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // ⚠️ Este mismo fichero contiene el patrón —lo declara arriba— y se contaría a sí mismo.
            // Es la misma trampa que `SidebarEntryTest` documenta al excluir a su dueño del escaneo.
            if ($file->getPathname() === __FILE__) {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), self::DRIVES)) {
                $files[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }
}
