<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **La retirada del sidebar Livewire, vigilada mientras dura** (Fase 4 · paso 4.7·2).
 *
 * `Livewire\Tickets\Purchase` está condenado: la SPA lo sustituye y el paso 4.7 lo borra. Pero la
 * retirada no es borrar un fichero —se midió: **32 ficheros de test lo tocaban**—, así que va por tramos
 * y convive un tiempo con su sustituto.
 *
 * ⚠️ **El riesgo de un desmontaje largo es que alguien siga construyendo encima.** Un test nuevo que
 * se apoye en el componente añade trabajo a la retirada y, peor, cobertura que se perderá al borrarlo.
 * Este guardián no prohíbe tocar los que ya existen: prohíbe que aparezcan MÁS.
 *
 * **La lista solo puede ENCOGER.** Cuando un fichero deje de depender del componente —porque se
 * re-apunte al servidor o porque muera con él— se quita de aquí, y el test lo exige: una entrada que ya
 * no depende es tan mala señal como una dependencia nueva sin declarar.
 *
 * ⚠️ **La promesa del contador es «0 ⟹ `Purchase` se puede borrar»**, y por eso el escaneo mira TRES
 * formas de acoplamiento, no una. La primera versión (2026-08-15) solo reconocía el literal
 * `Livewire::test(Purchase::class)` y **declaraba 25 dependientes cuando había 32**: se le escapaban
 * los cuatro que conducen con `Livewire::actingAs($u)->test(…)`, el que lee una constante del
 * componente y los dos que dependen de sus vistas. Un contador que llega a 0 con siete ficheros
 * todavía enganchados no levanta ningún bloqueo: rompe la suite al borrar. Corregido el 2026-08-15
 * (`DECISIONES #63`), midiendo — y el crecimiento de la lista es la CORRECCIÓN, no una regresión.
 *
 * ⚠️ **No clasifica: inventaría.** Qué hacer con cada fichero es una decisión por fichero, y vive en
 * `DECISIONES #60(d)`. Meter la familia aquí sería fijar en un test una decisión que aún no está tomada.
 */
class PurchaseRetirementTest extends TestCase
{
    /**
     * Las TRES formas de apoyarse en el componente, **medidas sobre el código y no sobre el texto**:
     * el escaneo tokeniza y descarta comentarios, porque hay ficheros que solo lo mencionan como
     * historia («esta regla vivía en `Purchase::mount()`») y esos no se rompen al borrarlo.
     *
     * · `conduce`  — lo EJECUTA. Cualquier receptor: `Livewire::test(…)` y `Livewire::actingAs($u)->test(…)`.
     * · `nombra`   — cualquier referencia estática a la clase, incluida una constante suya. Es el
     *                superconjunto de `conduce` y es lo que de verdad revienta con un fatal al borrarla.
     * · `vistas`   — depende de `purchase.blade.php` o del placeholder, que se van con el componente.
     *
     * @var array<string, string>
     */
    private const COUPLINGS = [
        'conduce' => '/\btest\(\s*Purchase::class\s*\)/',
        'nombra' => '/\bPurchase::/',
        'vistas' => '#livewire[.:/]tickets[.:/]purchase#i',
    ];

    /**
     * Los ficheros que hoy se apoyan en el componente. **Solo puede encoger.**
     *
     * El recuento de casos NO se fija aquí a propósito: cambia con cualquier añadido legítimo a un
     * fichero que todavía existe, y lo que importa vigilar es la superficie, no su grosor.
     *
     * @var list<string>
     */
    private const DEPENDENTS = [
        'tests/Feature/Architecture/ModuleContractsTest.php',
        'tests/Feature/Architecture/SidebarSeamTest.php',
        'tests/Feature/Architecture/SidebarTokenBudgetTest.php',
        'tests/Feature/Auth/DuplicateEmailEdgeCaseTest.php',
        'tests/Feature/Maintenance/ReservationPauseGuardTest.php',
        'tests/Feature/Maintenance/ReservationPauseTest.php',
        'tests/Feature/Sales/AddonDependencyTest.php',
        'tests/Feature/Sales/AddonInclusionPurchaseTest.php',
        'tests/Feature/Sales/CatalogVisibilityAndCartPruneTest.php',
        'tests/Feature/Sales/DepositSurfacesTest.php',
        'tests/Feature/Sales/PurchaseCatalogGroupingTest.php',
        'tests/Feature/Sales/PurchaseConfirmationStatusTest.php',
        'tests/Feature/Sales/PurchaseIdentificationTest.php',
        'tests/Feature/Sales/PurchaseLimitsTest.php',
        'tests/Feature/Sales/PurchasePanelTest.php',
        'tests/Feature/Sales/PurchaseRegistrationPromptTest.php',
        'tests/Feature/Sales/PurchaseRetryAndPollingTest.php',
        'tests/Feature/Sales/SidebarV2Test.php',
        'tests/Feature/Sidebar/SidebarCalendarParityTest.php',
        'tests/Feature/Sidebar/SidebarDomContractTest.php',
        'tests/Feature/Sidebar/SidebarOutcomeParityTest.php',
        'tests/Feature/Ui/SpinnerTest.php',
    ];

    public function test_no_new_test_starts_leaning_on_the_dying_component(): void
    {
        $found = array_keys($this->coupledOnDisk());
        $new = array_values(array_diff($found, self::DEPENDENTS));

        $this->assertSame(
            [], $new,
            "Hay tests NUEVOS apoyados en `Livewire\\Tickets\\Purchase`, que está en retirada:\n  ".
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
        $found = array_keys($this->coupledOnDisk());
        $stale = array_values(array_diff(self::DEPENDENTS, $found));

        $this->assertSame(
            [], $stale,
            "El inventario declara ficheros que YA NO se apoyan en el componente:\n  ".implode("\n  ", $stale)."\n\n".
            'Quítalos de `DEPENDENTS`: la lista es la que dice cuánto falta para poder borrar `Purchase`.'
        );
    }

    /**
     * La guarda de la guarda, **una por forma**: si cualquiera de las tres dejara de encontrar nada,
     * las dos de arriba pasarían solas y el contador mentiría por lo bajo — que es exactamente lo que
     * pasó con la primera versión de este fichero.
     *
     * ⚠️ Cuando la retirada termine, las tres darán 0 y este caso caerá. No es un obstáculo: este
     * fichero se borra en el mismo commit que el componente, y hasta entonces cero significa
     * «el escáner se ha roto», no «ya no queda trabajo» —eso lo dice `DEPENDENTS` vacía—.
     */
    public function test_every_coupling_form_still_finds_something(): void
    {
        $byForm = [];
        foreach ($this->coupledOnDisk() as $forms) {
            foreach ($forms as $form) {
                $byForm[$form] = ($byForm[$form] ?? 0) + 1;
            }
        }

        foreach (array_keys(self::COUPLINGS) as $form) {
            $this->assertGreaterThan(
                0, $byForm[$form] ?? 0,
                "el escaneo no encuentra NINGÚN fichero acoplado por «{$form}»: ¿ha cambiado esa forma ".
                'de apoyarse en el componente? Un patrón que no encuentra nada deja el inventario corto.'
            );
        }
    }

    /**
     * Los ficheros acoplados, con las formas por las que lo están.
     *
     * @return array<string, list<string>> ruta relativa → formas
     */
    private function coupledOnDisk(): array
    {
        $found = [];
        $root = base_path('tests');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // ⚠️ Este mismo fichero contiene los patrones —los declara arriba— y se contaría a sí mismo.
            // Es la misma trampa que `SidebarEntryTest` documenta al excluir a su dueño del escaneo.
            if ($file->getPathname() === __FILE__) {
                continue;
            }

            $code = $this->codeWithoutComments((string) file_get_contents($file->getPathname()));

            $forms = [];
            foreach (self::COUPLINGS as $form => $pattern) {
                if (preg_match($pattern, $code) === 1) {
                    $forms[] = $form;
                }
            }

            if ($forms !== []) {
                $found[str_replace(base_path().'/', '', $file->getPathname())] = $forms;
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * El código sin sus comentarios.
     *
     * ⚠️ **Sin esto el inventario crece con historia**: hay al menos dos ficheros que solo nombran el
     * componente para contar de dónde salió una regla (`SidebarEntryTest`, `SidebarMoneyParityTest`),
     * y esos no se rompen cuando se borre. Medido: con `str_contains` sobre el texto crudo entrarían
     * los dos.
     */
    private function codeWithoutComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                $code .= $token;

                continue;
            }

            $code .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? ' ' : $token[1];
        }

        return $code;
    }
}
