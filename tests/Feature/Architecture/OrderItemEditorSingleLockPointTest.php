<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Extracción 4b del desmontaje de `ViewOrder` (spec §9.6·4): el editor tiene UN solo punto de
 * lock. `changeSlot()` y `edit()` mutan a través de `withZoneDayLock()`, que abre la transacción y
 * toma `ZoneDaySlotLock` como PRIMERA sentencia (`AFORO-01`/`AFORO-05`). Así el escenario
 * `panel-edit` de `purchase:verify-oversell` —que conduce `changeSlot()` sobre MySQL— vigila el
 * lock de las DOS operaciones; una transacción propia en `edit()` que se saltara el helper sería
 * invisible para el instrumento y para la suite (SQLite no emite `FOR UPDATE`).
 *
 * Esta guarda mira el FUENTE, como `LedgerSingleSourceTest`: es lo único que puede ver una
 * segunda `DB::transaction` o un segundo `acquire()` antes de que una carrera los mida.
 */
class OrderItemEditorSingleLockPointTest extends TestCase
{
    public function test_the_editor_opens_exactly_one_transaction_and_takes_the_lock_exactly_once(): void
    {
        $source = (string) file_get_contents(app_path('Domain/Booking/Services/OrderItemEditor.php'));

        $this->assertSame(
            1,
            substr_count($source, 'DB::transaction('),
            'OrderItemEditor debe abrir UNA sola transacción (en withZoneDayLock): una segunda escaparía al lock de zona/día y al escenario panel-edit.',
        );
        $this->assertSame(
            1,
            substr_count($source, '->zoneDayLock->acquire('),
            'OrderItemEditor debe tomar ZoneDaySlotLock UNA sola vez (en withZoneDayLock): la receta vive en un sitio.',
        );
        $this->assertSame(
            2,
            substr_count($source, '$this->withZoneDayLock('),
            'Las DOS operaciones que mutan aforo (changeSlot y edit) pasan por withZoneDayLock.',
        );
    }

    public function test_the_page_no_longer_opens_a_transaction_over_items(): void
    {
        $page = (string) file_get_contents(app_path('Filament/Resources/Orders/Pages/ViewOrder.php'));

        // La única transacción que queda en la página es la de CANCELAR EL PEDIDO entero (acción a
        // nivel pedido, composición Filament que §1.2 dejó en la entrega); ninguna sobre un ítem.
        $this->assertSame(
            1,
            substr_count($page, 'DB::transaction('),
            'ViewOrder solo debe abrir la transacción de cancelar el PEDIDO; las de ítem viven en el dominio desde la 4b.',
        );
        // Se mira la forma de LLAMADA: los docblocks de la página siguen nombrando `lockForUpdate` al
        // describir lo que hacen los servicios, y eso es doc, no un lock.
        $this->assertSame(0, substr_count($page, '->lockForUpdate('), 'la página no toma locks: eso es del dominio.');
    }
}
