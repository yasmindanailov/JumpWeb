<?php

namespace Tests\Feature\Architecture;

use App\Domain\Booking\Services\AddonResolver;
use Tests\TestCase;

/**
 * **LA UNIDAD DE UNA LÍNEA VENDIDA SALE DEL SELLO, Y DE NINGÚN OTRO SITIO**
 * (`specs/hora-extra.md` §12.7, `DECISIONES #448`).
 *
 * El modo de fallo que esta guarda vigila no es que alguien rompa el sello: es que alguien **añada
 * un lector nuevo** que le pregunte al catálogo por una línea que ya se vendió. Eso no falla, no se
 * ve en ninguna pantalla y reabre exactamente el agujero de `#448` —dinero y aforo reinterpretados
 * por un cambio de configuración— por una puerta que nadie estaba mirando.
 *
 * ▶ Por eso el censo va en DOS listas con reglas distintas:
 *  - **OFERTA** puede crecer: el catálogo, la cesta, el escaparate y el alta manual preguntan por lo
 *    que se PUEDE vender hoy, y tienen que seguir leyendo el pivote vivo. *Un sello que gobernara la
 *    oferta congelaría el escaparate.*
 *  - **LÍNEA VENDIDA tiene que ser CERO**: los tres sitios que preguntan por una línea ya vendida
 *    pasan por {@see AddonResolver::soldQuantityUnit}.
 *
 * Es el molde de `LedgerSingleSourceTest::STILL_ON_THE_OLD_MODEL`: una lista que solo puede encoger.
 */
class SoldLineUnitHasOneSourceTest extends TestCase
{
    /**
     * Ficheros que leen el modo del PIVOTE y pueden hacerlo: todos preguntan «¿qué se puede vender
     * hoy?», nunca «¿cómo se vendió esto?». Esta lista SÍ puede crecer.
     *
     * @var list<string>
     */
    private const OFERTA = [
        // La venta nueva y su escaparate: la unidad la pone el catálogo, y es lo correcto.
        'app/Domain/Booking/Services/AddonResolver.php',
        'app/Domain/Booking/Services/AddonOfferReader.php',
        'app/Domain/Booking/Services/AddonOccupancy.php',
        'app/Domain/Booking/Services/CartOccupants.php',
        'app/Domain/Booking/Services/CatalogReader.php',
        'app/Domain/Content/Services/LandingAddonPresenter.php',
        // `#528` · el escaparate de `/cumpleanos`: escribe «por niño» en la hora extra si el
        // enganche de HOY es por invitado. Pregunta por lo que se vende hoy, nunca por una línea.
        'app/Domain/Booking/Services/BirthdayComparison.php',
        // El panel: el alta manual vende NUEVO, y el catálogo edita la configuración.
        'app/Filament/Pages/CreateManualOrderPage.php',
        'app/Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php',
        // `ViewOrder` lo lee para los complementos que AÚN NO están en la reserva (lo añadible).
        'app/Filament/Resources/Orders/Pages/ViewOrder.php',
        // El propio modelo, que es donde vive el predicado.
        'app/Domain/Booking/Models/ProductAddon.php',
    ];

    /**
     * Quién puede llamar a `quantityUnit()` sobre un pivote, que es OTRA pregunta y por eso va en su
     * propia lista: `isPerGuest()` DECIDE sobre una línea; `quantityUnit()` **copia la unidad** para
     * sellarla al nacer, o la compara para detectar divergencia. Esta lista es CERRADA y pequeña.
     *
     * ⚠️ La primera versión de esta guarda las mezclaba y acusó a los tres selladores — ficheros
     * sanos. *Buscar un nombre no es buscar un uso.*
     *
     * @var list<string>
     */
    private const SELLADORES = [
        'app/Domain/Booking/Services/AddonResolver.php',      // sella al vender + resuelve el sello
        'app/Domain/Booking/Services/ItemEditPricing.php',    // compone el sello del alta del panel
        'app/Domain/Booking/Services/PostFormAddons.php',     // sella la línea del post-form
        'app/Domain/Booking/Services/OrderItemEditor.php',    // re-sella al cambiar de producto + divergencia
        'app/Domain/Booking/Services/AddonOccupancy.php',     // la oferta, vía `blocksFor()`
        'app/Domain/Booking/Models/ProductAddon.php',         // donde vive
        // ⚠️ `OrderItem` COMPARA para poder CONTARLO —`addonUnitDivergesFromCatalogue()`, que es lo
        // que hace que la ficha del pedido diga «vendido con otra unidad»—, y no decide nada: quien
        // decide sigue siendo `AddonResolver::soldQuantityUnit()`. Entra aquí y **no** en OFERTA
        // porque no pregunta «¿qué se puede vender hoy?»: pregunta si lo vendido y el catálogo
        // discrepan. Lo cazó esta misma guarda al añadirlo, que es exactamente para lo que está.
        'app/Domain/Booking/Models/OrderItem.php',
    ];

    public function test_no_new_reader_asks_the_catalogue_about_a_sold_line(): void
    {
        $infractores = [];
        $selladoresDeMas = [];

        foreach ($this->ficherosPhp() as $ruta) {
            $rel = str_replace(base_path().'/', '', $ruta);
            $codigo = $this->sinComentarios((string) file_get_contents($ruta));

            if (preg_match('/->isPerGuest\(\)/', $codigo) === 1 && ! in_array($rel, self::OFERTA, true)) {
                $infractores[] = $rel;
            }
            if (preg_match('/->quantityUnit\(\)/', $codigo) === 1 && ! in_array($rel, self::SELLADORES, true)) {
                $selladoresDeMas[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $selladoresDeMas,
            'Un fichero fuera de la lista copia la unidad del pivote (`quantityUnit()`). Esa lista es '
            .'CERRADA: solo sellan las tres puertas de venta, el re-sello por cambio de producto y la '
            .'comparación de divergencia.',
        );

        $this->assertSame(
            [],
            $infractores,
            'Un fichero nuevo lee el modo del PIVOTE. Si pregunta por lo que se PUEDE vender hoy, '
            .'añádelo a OFERTA. Si pregunta por una línea YA VENDIDA, tiene que pasar por '
            .'`AddonResolver::soldQuantityUnit()` — si no, un cambio de catálogo volverá a '
            .'reinterpretar dinero y aforo de reservas hechas (`#448`).',
        );
    }

    public function test_the_three_sold_line_readers_go_through_the_seal(): void
    {
        // La otra mitad: que los tres sitios que SÍ preguntan por una línea vendida sigan pasando por
        // el sello. Sin este caso, alguien podría devolver `OrderItemEditor` al pivote vivo y la
        // guarda de arriba seguiría en verde — porque `isPerGuest()` volvería a aparecer en un
        // fichero que no está en OFERTA... y entonces fallaría. Pero si lo AÑADE a OFERTA, no.
        // ⚠️⚠️ **Son CUATRO lectores, no tres, y esta lista lo dice.** El cuarto —la puerta del
        // CLIENTE— llegó en `#449`, y durante todo `#448` la spec dijo «tres»: el editor del panel y
        // el post-form son **dos puertas al mismo hecho**, y contar solo la del operador es cómo se
        // quedó sin construir la del cliente. Lo cazó la revisión adversarial (§12.20 · m3).
        $lectores = [
            'app/Domain/Booking/Services/OrderItemEditor.php' => 'dinero, aforo y permiso',
            'app/Domain/Booking/Services/GuestCountAdjuster.php' => 'dinero, por la puerta del cliente',
        ];

        foreach ($lectores as $ruta => $que) {
            $codigo = $this->sinComentarios((string) file_get_contents(base_path($ruta)));

            $this->assertTrue(
                str_contains($codigo, 'wasSoldPerGuest') || str_contains($codigo, 'soldQuantityUnit'),
                "`{$ruta}` dejó de preguntar por el sello: su lectura de línea vendida ({$que}) "
                .'tiene que salir de `AddonResolver::soldQuantityUnit()`.',
            );

            $this->assertStringNotContainsString(
                '->isPerGuest()',
                $codigo,
                "`{$ruta}` volvió a leer el modo del PIVOTE. Ahí solo hay líneas YA VENDIDAS: "
                .'tiene que preguntarle al sello.',
            );
        }
    }

    /** @return list<string> */
    private function ficherosPhp(): array
    {
        $out = [];
        foreach (['app'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));
            foreach ($it as $f) {
                if ($f->isFile() && $f->getExtension() === 'php') {
                    $out[] = $f->getPathname();
                }
            }
        }
        sort($out);

        return $out;
    }

    /**
     * ⚠️ Los comentarios se limpian ANTES de buscar: media docena de docblocks de este subsistema
     * CITAN `isPerGuest()` para explicar la regla, y contarlos como lecturas haría que la guarda
     * acusara a ficheros sanos. Es la lección de `SidebarIconParityTest` (`#258`), que se tragaba un
     * icono por no limpiar los comentarios de JavaScript.
     */
    private function sinComentarios(string $codigo): string
    {
        $codigo = preg_replace('#/\*.*?\*/#s', '', $codigo) ?? $codigo;

        return preg_replace('#//[^\n]*#', '', $codigo) ?? $codigo;
    }
}
