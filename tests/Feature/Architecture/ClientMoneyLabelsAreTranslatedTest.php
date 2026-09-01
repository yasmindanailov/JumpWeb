<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * **LO QUE LEE UN CLIENTE ESTÁ TRADUCIDO EN LOS TRES IDIOMAS — O LEE OTRO IDIOMA SIN ENTERARSE.**
 *
 * ⚠️ **Esta clase NO repite a `OrderTotalsBreakdownTest`**, que ya cubre lo suyo (`#154`): que las
 * tres claves del cargo de puerta existan en ES/EN/FR y que la composición real bajo `en` devuelva
 * la frase inglesa. Aquí viven **las cuatro cosas que aquella no puede ver**, y cada una nació de un
 * modo de fallo distinto:
 *
 *  1. **La guarda de la guarda** — que `Lang::has()` sepa decir que NO. Un instrumento que devolviera
 *     `true` siempre dejaría verde todo lo demás sin mirar nada.
 *  2. **Que los tres idiomas digan cosas DISTINTAS.** Es el modo de fallo que el docblock de
 *     `OrderTotalsBreakdownTest` nombra —*«un cliente francés leyendo castellano, que parece texto y
 *     no falla nada»*— pero que ninguna comprobación de EXISTENCIA puede detectar: rellenar
 *     `lang/fr` con el texto español la deja verde. Y ése es justo el arreglo que hace quien tiene
 *     prisa por poner un test en verde.
 *  3. **El MECANISMO, no el síntoma**: el helper compartido no puede volver a citar `admin.*`,
 *     exista o no la traducción ese día.
 *  4. **El barrido ANCHO**: toda clave `tickets.*` que cite el dominio, no solo las tres de hoy. Es
 *     lo que cazará la PRÓXIMA fuga.
 *
 * ▶ **De dónde viene todo esto** (`#156`): `OrderAdjustment::breakdownLabel()` lo comparten el panel
 * (`Order::reservationGateLines()`) y el CLIENTE (`Order::gateBreakdownLines()`, «Mis pedidos»), y
 * traducía dos de sus ramas desde `admin.*`. El fichero `admin.php` **solo existe en español**, así
 * que un cliente EN/FR recibía la clave literal en su desglose de dinero. Lo arregló `#154`.
 * ⚠️ Y la razón de que `Lang::has()` lleve `fallback: false` es que **sin apagarlo, `en` y `fr`
 * heredan el español y la comprobación diría que todo está bien**.
 */
class ClientMoneyLabelsAreTranslatedTest extends TestCase
{
    /** Los tres idiomas que sirve la web pública. El panel es ES y no entra aquí. */
    private const CLIENT_LOCALES = ['es', 'en', 'fr'];

    /** Las etiquetas del cargo de puerta que el CLIENTE lee en su desglose. */
    private const GATE_LABELS = [
        'tickets.gate_change_line',
        'tickets.gate_change_line_slot',
        'tickets.gate_change_line_product',
    ];

    /** El fichero que comparten panel y cliente. Su contenido no puede citar `admin.*`. */
    private const SHARED_LABEL_SOURCE = 'app/Domain/Booking/Models/OrderAdjustment.php';

    /**
     * Las etiquetas del LIBRO (`specs/desglose-libro.md` §4.3, T2 · guarda I): las compone
     * `Booking\Services\MovementLabel` con UN diccionario para cliente y panel. La lista es CERRADA
     * y se cruza con el fichero: una clave nueva en `lang/es` que no entre aquí pone esto en rojo,
     * y una que se borre en `fr`, también (`Lang::has(…, false)`: sin respaldo, la lección de `#134`).
     */
    private const JOURNAL_LABELS = [
        'tickets.journal.booking',
        'tickets.journal.quantity',
        'tickets.journal.addon_quantity',
        'tickets.journal.product_change',
        'tickets.journal.slot_change',
        'tickets.journal.price_change',
        'tickets.journal.edit_fallback',
        'tickets.journal.cancel',
        'tickets.journal.courtesy',
        'tickets.journal.paid_online',
        'tickets.journal.paid_desk',
        'tickets.journal.refund_card',
        'tickets.journal.refund_manual',
        'tickets.journal.refund_pending',
        'tickets.journal.refund_failed',
        'tickets.journal.gate',
        'tickets.journal.with_reservation',
    ];

    /** Plantillas SIN palabras (`:name: :old → :new`): iguales entre idiomas a propósito, no por copia. */
    private const JOURNAL_TEMPLATES = ['tickets.journal.addon_quantity', 'tickets.journal.with_reservation'];

    /**
     * ⚠️ **La guarda de la guarda.** Sin este caso, un `Lang::has()` que dijera `true` siempre
     * dejaría verdes los demás sin mirar nada — que es el modo de fallo que este fichero existe para
     * impedir. Aquí se comprueba que el instrumento **sabe decir que NO**, y que también sabe decir
     * que sí.
     */
    public function test_the_instrument_can_say_no(): void
    {
        foreach (self::CLIENT_LOCALES as $locale) {
            $this->assertFalse(
                Lang::has('tickets.esta_clave_no_existe_y_no_debe_existir', $locale, false),
                "El instrumento dice que existe una clave inventada en «{$locale}»: no puede detectar nada."
            );

            $this->assertTrue(
                Lang::has('tickets.gate_change_line', $locale, false),
                "`tickets.gate_change_line` no existe en «{$locale}» — o el instrumento no ve nada."
            );
        }
    }

    /**
     * ⚠️⚠️ **Existir no es estar traducido.** Si alguien tapa un hueco copiando el castellano dentro
     * de `lang/en` o `lang/fr`, toda comprobación de existencia queda verde y el cliente sigue
     * leyendo español en su pantalla de dinero. No es cosmético: es la diferencia entre traducir y
     * silenciar el aviso, y es el arreglo que se hace con prisa.
     */
    public function test_each_locale_says_something_different(): void
    {
        foreach (self::GATE_LABELS as $key) {
            $rendered = [];

            foreach (self::CLIENT_LOCALES as $locale) {
                $rendered[$locale] = Lang::get($key, [], $locale);
            }

            $this->assertSame(
                count($rendered),
                count(array_unique($rendered)),
                "`{$key}` repite el mismo texto en dos idiomas — alguien tapó el hueco copiando: "
                .json_encode($rendered, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    /**
     * **Las etiquetas del LIBRO existen en los tres idiomas SIN respaldo y dicen cosas distintas**
     * (T2 del libro, guarda I). Mutaciones que muerden: borrar una clave en `fr` · tapar el hueco
     * copiando el castellano en `en` · añadir una clave al grupo sin declararla aquí.
     */
    public function test_the_journal_labels_are_translated_in_the_three_locales(): void
    {
        // La lista de arriba ES el grupo: ni una clave más ni una menos que en `lang/es`.
        $declared = array_map(fn (string $k): string => 'tickets.journal.'.$k, array_keys((array) Lang::get('tickets.journal', [], 'es')));
        sort($declared);
        $listed = self::JOURNAL_LABELS;
        sort($listed);
        $this->assertSame($listed, $declared, 'el grupo `tickets.journal` de `lang/es` y `JOURNAL_LABELS` han divergido: declara la clave nueva (o retira la muerta)');

        foreach (self::JOURNAL_LABELS as $key) {
            $rendered = [];
            foreach (self::CLIENT_LOCALES as $locale) {
                $this->assertTrue(Lang::has($key, $locale, false), "`{$key}` no existe en «{$locale}» (sin respaldo)");
                $rendered[$locale] = Lang::get($key, [], $locale);
            }
            if (in_array($key, self::JOURNAL_TEMPLATES, true)) {
                continue;
            }
            $this->assertSame(
                count($rendered),
                count(array_unique($rendered)),
                "`{$key}` repite el mismo texto en dos idiomas — alguien tapó el hueco copiando: "
                .json_encode($rendered, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    /**
     * **El mecanismo, no el síntoma.** El helper compartido no puede citar `admin.*` NUNCA, exista o
     * no la traducción ese día: `admin.php` es del panel, que es ES, y este método también sirve al
     * cliente. Cae aunque la clave nueva estuviera traducida — que es lo que la separa de una
     * comprobación de contenido.
     */
    public function test_the_shared_label_helper_never_reads_the_admin_namespace(): void
    {
        $path = base_path(self::SHARED_LABEL_SOURCE);

        $this->assertFileExists($path,
            'Se movió `OrderAdjustment`: actualiza `SHARED_LABEL_SOURCE` o esta guarda deja de mirar nada.');

        $source = (string) file_get_contents($path);

        $this->assertMatchesRegularExpression("/__\(\s*['\"]tickets\./", $source,
            'El helper compartido ya no traduce desde `tickets.*`: o cambió de espacio, o esta guarda quedó ciega.');

        $this->assertDoesNotMatchRegularExpression("/__\(\s*['\"]admin\./", $source,
            self::SHARED_LABEL_SOURCE.' vuelve a traducir desde `admin.*`, que solo existe en español. '
            .'Lo lee también el CLIENTE (`Order::gateBreakdownLines()`), así que en EN/FR saldría la clave en crudo.');
    }

    /**
     * Barrido ancho: **toda** clave `tickets.*` que el dominio cite como literal tiene que existir en
     * los tres idiomas. Es lo que caza la PRÓXIMA fuga, no la de hoy.
     *
     * ⚠️ **Con suelo declarado.** Un escáner que deje de encontrar nada daría verde sin mirar el
     * corpus — el modo de fallo que este proyecto ya pagó cuatro veces (`#143` §8: un instrumento que
     * no ve una parte del corpus da un inventario que parece completo y no lo es). Si el suelo salta,
     * la pregunta es por qué el escáner encuentra menos, **no bajar el número**.
     */
    public function test_every_literal_tickets_key_used_by_the_domain_exists_in_the_three_locales(): void
    {
        $keys = $this->literalTicketsKeysUsedByTheDomain();

        $this->assertGreaterThanOrEqual(20, count($keys),
            'El escáner encontró '.count($keys).' claves `tickets.*` en `app/Domain`. Eran más: '
            .'algo dejó de verse (¿comillas dobles, clave partida en dos líneas, fichero movido?).');

        $missing = [];

        foreach ($keys as $key) {
            foreach (self::CLIENT_LOCALES as $locale) {
                if (! Lang::has($key, $locale, false)) {
                    $missing[] = "{$key} ({$locale})";
                }
            }
        }

        $this->assertSame([], $missing,
            "El dominio cita claves `tickets.*` que no existen en algún idioma del cliente:\n  - "
            .implode("\n  - ", $missing));
    }

    /**
     * @return list<string> claves `tickets.x` citadas como literal en `app/Domain`
     */
    private function literalTicketsKeysUsedByTheDomain(): array
    {
        $keys = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Domain'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Solo claves LITERALES y completas. Las dinámicas (`'tickets.statuses.'.$x`) no se
            // pueden resolver estáticamente y se quedan fuera A PROPÓSITO: fingir que se comprueban
            // sería peor que no mirarlas.
            preg_match_all(
                "/__\(\s*['\"](tickets\.[A-Za-z0-9_.]+)['\"]\s*[,)]/",
                (string) file_get_contents($file->getPathname()),
                $matches
            );

            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }

        ksort($keys);

        return array_keys($keys);
    }

    /**
     * **T5 · D9 (`cumple-mixto.md` §25.3, `#284`): las etiquetas del canal de puerta RESUELTO no
     * afirman un cobro que nadie registró.** El cargo se da por resuelto al pasar la visita de un
     * pedido pagado —decisión 7.2e—, pero el registro real del cobro no existe (el hueco reservado
     * era `OrderAdjustment::TYPE_COLLECTED_IN_PERSON`, sin un solo uso y RETIRADO en la T1 del libro): «Pagado en el parque»
     * contaba como hecho lo que el sistema no sabe. La regla se asevera por PALABRAS PROHIBIDAS y
     * no por texto exacto, para no atar la guarda a una implementación (`#251`).
     *
     * ⚠️ `tickets.paid_desk` («Pagado en recepción») queda FUERA a propósito: el pedido de
     * taquilla es un cobro que el operador SÍ registró, y ahí «Pagado» es verdad.
     *
     * Mutación que la valida: restaurar «Pagado en el parque» en cualquiera de las tres claves.
     */
    public function test_the_settled_gate_labels_do_not_claim_an_unrecorded_payment(): void
    {
        $labels = [
            ['tickets.ledger.paid_at_gate', 'es', ['Pagado', 'Cobrado']],
            ['tickets.ledger.paid_at_gate', 'en', ['Paid', 'Collected']],
            ['tickets.ledger.paid_at_gate', 'fr', ['Payé']],
            ['admin.orders.item_financial.collected_at_gate', 'es', ['Pagado', 'Cobrado']],
            ['admin.orders.item_financial.collected_at_gate', 'zh_CN', ['收取', '支付']],
            ['admin.orders.order_financial.pagado_puerta', 'es', ['Pagado', 'Cobrado']],
            ['admin.orders.order_financial.pagado_puerta', 'zh_CN', ['收取', '支付']],
            // T2 del libro: la línea `gate` hereda la regla — y la conserva cuando `ledger.*` se retire (T3).
            ['tickets.journal.gate', 'es', ['Pagado', 'Cobrado']],
            ['tickets.journal.gate', 'en', ['Paid', 'Collected']],
            ['tickets.journal.gate', 'fr', ['Payé']],
            ['tickets.journal.gate', 'zh_CN', ['收取', '支付']],
        ];

        foreach ($labels as [$key, $locale, $forbidden]) {
            $text = Lang::get($key, [], $locale);

            $this->assertNotSame($key, $text, "`{$key}` no existe en «{$locale}»");
            $this->assertNotSame('', trim((string) $text), "`{$key}` está vacía en «{$locale}»");

            foreach ($forbidden as $word) {
                $this->assertStringNotContainsString(
                    $word,
                    (string) $text,
                    "`{$key}` en «{$locale}» vuelve a afirmar un cobro no registrado («{$word}»): D9 lo retiró a propósito."
                );
            }
        }
    }
}
