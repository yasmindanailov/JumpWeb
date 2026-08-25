<?php

namespace Tests\Feature\Architecture;

use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * **El registro de un pedido no puede volver a enseñar claves crudas** (`DECISIONES #145`).
 *
 * ## Qué pasó
 *
 * El owner abrió el registro del pedido `R-S9XDYB` en staging y encontró esto, entero:
 *
 *     orders.gate_credit_applied
 *     Motivo: item_edit_reduction
 *
 * Sin importe, sin fechas, sin precios. Y el registro **tenía el dato completo**: la entrada
 * hermana `orders.item_edited` guardaba `from_unit_price 1890 → to_unit_price 1590` y
 * `price_diff_cents -2400`. El defecto nunca fue de datos: era que **nadie los pintaba y casi
 * ninguna acción tenía etiqueta**.
 *
 * Medido el 2026-08-25 sobre la base de staging: de las **9 acciones de pedido realmente emitidas
 * allí, 8 no tenían etiqueta**. Y al revés, de las 18 etiquetas escritas:
 *  - **4 estaban archivadas bajo el grupo equivocado** (`order_items.item_refunded` mientras el
 *    código emite `orders.item_refunded`) → escritas y **jamás usadas**;
 *  - **4 etiquetaban cosas que nadie emite** (el ciclo preparado / sin preparar, retirado, y un
 *    `processed_after_expiration` que en realidad se audita como incidencia de cobro).
 *
 * O sea: el fichero de idioma describía un vocabulario de acciones que el código ya no usaba, y
 * nada lo comparaba. Esta guarda es esa comparación, **en las dos direcciones**.
 *
 * ## Por qué el catálogo NO basta y hace falta además la validación de `AuditLogger`
 *
 * ⚠️ Un escaneo estático del código **no puede** enumerar las acciones: tres se construyen
 * concatenando (`'orders.item_'.$actionKey.'_blocked'`) y dos llegan por constante dentro de un
 * array de incidencia. Se comprobó por las malas: la primera extracción de esa sesión se dejó
 * `orders.refund_blocked`, que viaja a través de un helper.
 *
 * ▶ Por eso la completitud la garantiza `AuditLogger::assertKnownAction()` **ejecutando**, y esta
 * clase solo empareja catálogo ↔ etiquetas. Cuando se activó, la suite cazó en el acto tres
 * acciones que ningún grep había visto — dos de ellas escritas por tests como fixture.
 *
 * ## Lo que esta guarda declara que NO mira
 *
 * ⚠️⚠️ **Solo el español, y el motivo NO es que sea el único idioma.** Esta guarda declaró primero
 * que `lang/es/admin.php` era el único `admin.php` del repo, y **era falso**: existe también
 * `lang/zh_CN/admin.php` (112 KB, versionado), y el chino **es un idioma soportado del panel** —
 * `Http\Middleware\SetAdminLocale::SUPPORTED` son `es` y `zh_CN`—. La primera medición solo miró
 * `es`, `en` y `fr` y concluyó de más.
 *
 * ▶ **El motivo real es que el chino es herencia del repo origen y no lo usa nadie** (owner,
 * 2026-08-25). Medido ese día: de las 25 acciones del catálogo, `zh_CN` etiqueta 18 — **15 saldrían
 * en crudo** y **8 son huérfanas**, las mismas cuatro misarchivadas y cuatro muertas que tenía el
 * español antes de `DECISIONES #145`. O sea: **el panel chino conserva el defecto entero**, y es un
 * hueco CON NOMBRE, no un descuido. Ficha en `DEUDA.md`.
 *
 * ▶ Si algún día el chino vuelve a usarse, el alcance se amplía aquí: recorrer `SetAdminLocale::SUPPORTED`
 * en vez de asumir `es`. Se deja sin hacer a propósito — traducir 25 etiquetas a un idioma que nadie
 * revisa produce copy que nadie puede validar.
 *
 * ⚠️ **Solo el registro DEL PEDIDO.** El visor global de incidencias usa otro espacio de nombres
 * (`admin.audit.actions.*`) y solo etiqueta las 8 acciones críticas, que son las que filtra por
 * defecto. El resto sale en crudo ahí, y es un hueco con nombre — no lo cierra este cambio.
 */
class AuditActionCatalogTest extends TestCase
{
    /** La raíz del árbol de etiquetas del registro del pedido. */
    private const LABELS_KEY = 'admin.orders.audit_modal.actions';

    /**
     * Guarda de la guarda: si el catálogo o el árbol de etiquetas se quedaran vacíos —un fichero
     * movido, una clave renombrada— los dos emparejamientos de abajo pasarían **comparando nada**
     * y quedarían verdes para siempre. Es el modo de fallo que `#112` documentó y que
     * `LedgerSingleSourceTest` también se cubre.
     */
    public function test_the_catalogue_and_the_labels_are_not_empty(): void
    {
        $this->assertGreaterThan(50, count(AuditLog::ACTIONS),
            'El catálogo `AuditLog::ACTIONS` está sospechosamente vacío: esta guarda no estaría comparando nada.');

        $this->assertGreaterThan(15, count(AuditLog::orderActions()),
            'Las acciones de pedido salen del catálogo por prefijo; si son cuatro, el prefijo cambió.');

        $this->assertNotEmpty($this->declaredLabels(),
            'El árbol de etiquetas del registro del pedido está vacío o se movió de sitio.');
    }

    /**
     * **Toda acción de pedido tiene etiqueta.** Es la mitad que el owner sufrió: 8 de 9 acciones
     * emitidas en staging salían como clave cruda.
     */
    public function test_every_order_action_has_a_label(): void
    {
        $missing = [];

        foreach (AuditLog::orderActions() as $action) {
            if (! Lang::has(self::LABELS_KEY.'.'.$action)) {
                $missing[] = $action;
            }
        }

        $this->assertSame([], $missing, implode("\n", [
            'Estas acciones se registran y el panel enseñaría su CLAVE CRUDA:',
            ...array_map(static fn (string $a): string => "  · {$a}", $missing),
            '▶ Dales etiqueta en `lang/es/admin.php`, bajo `orders.audit_modal.actions`,',
            '  en el grupo que case con el PREFIJO de la acción (no con el tipo del target).',
        ]));
    }

    /**
     * **Toda etiqueta corresponde a una acción real.** Es la otra mitad, y la que explica por qué
     * había traducciones correctas que no se usaban nunca: estaban bajo `order_items.*` mientras
     * el código emitía `orders.item_*`. Una etiqueta huérfana no da error en ninguna parte — solo
     * hace creer que el caso está cubierto.
     */
    public function test_every_label_matches_an_emitted_action(): void
    {
        $known = AuditLog::orderActions();
        $orphans = array_values(array_diff($this->declaredLabels(), $known));

        $this->assertSame([], $orphans, implode("\n", [
            'Estas etiquetas no corresponden a ninguna acción del catálogo:',
            ...array_map(static fn (string $a): string => "  · {$a}", $orphans),
            '▶ O la acción se retiró y sobra la etiqueta, o está bajo el grupo equivocado:',
            '  el grupo tiene que ser el PREFIJO de la acción tal y como el código la escribe.',
        ]));
    }

    /** Las acciones críticas del visor global también son acciones: no pueden faltar del catálogo. */
    public function test_critical_actions_are_part_of_the_catalogue(): void
    {
        $missing = array_values(array_diff(AuditLog::CRITICAL_ACTIONS, AuditLog::ACTIONS));

        $this->assertSame([], $missing,
            'Una acción crítica fuera del catálogo: `AuditLogger` la rechazaría al escribirla. '.implode(', ', $missing));
    }

    /**
     * La validación de `AuditLogger` **muerde**. Sin este caso, `assertKnownAction` podría quedarse
     * inerte —un `return` de más, una condición invertida— y la completitud del catálogo dejaría de
     * estar garantizada sin que nada lo dijera.
     *
     * ⚠️ Y comprueba lo que de verdad importa de su colocación: que la excepción **sale**. Vive
     * ANTES del `try` de `write()`, que se traga cualquier `Throwable` a propósito; metida dentro
     * quedaría muda para siempre.
     */
    public function test_an_uncatalogued_action_is_rejected_outside_production(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/no catalogada/');

        AuditLogger::log('inventada.para_el_test');
    }

    /**
     * Las claves declaradas, aplanadas a `grupo.clave`, que es la forma en que el blade las busca
     * (`admin.orders.audit_modal.actions.{$entry->action}`, y Laravel descompone por el punto).
     *
     * @return list<string>
     */
    private function declaredLabels(): array
    {
        $tree = Lang::get(self::LABELS_KEY);

        if (! is_array($tree)) {
            return [];
        }

        $flat = [];
        foreach ($tree as $group => $rows) {
            foreach ((array) $rows as $key => $_) {
                $flat[] = $group.'.'.$key;
            }
        }

        sort($flat);

        return $flat;
    }
}
