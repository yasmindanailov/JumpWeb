<?php

namespace Tests\Feature\Architecture;

use App\Domain\Booking\Services\OrderBook;
use Tests\TestCase;

/**
 * **EL DESGLOSE SE COMPONE EN UN SOLO SITIO** (`DECISIONES #127`, `specs/desglose-dinero-cliente.md`).
 *
 * ⚠️⚠️ El defecto de fondo nunca fue de rótulos: **ocho superficies enseñan este dinero y cada una
 * componía el suyo** a partir de piezas sueltas. Por eso divergieron — medido sobre las acciones
 * reales del panel, 18 de 23 gestiones dejaban la columna del cliente ilegible, y el «Pagado online»
 * de una reserva se derivaba con DOS fórmulas distintas que coincidían por álgebra, no por
 * construcción.
 *
 * Esta guarda vigila que no vuelva a pasar, y lo hace de la única forma que sirve: **prohibiendo el
 * mecanismo, no persiguiendo el síntoma**. Una superficie que vuelva a restar canales a mano cae
 * aquí aunque su resultado sea correcto hoy — porque «correcto hoy» fue exactamente el estado del
 * que salió todo esto.
 *
 * Es hermana de `DayLabelSingleSourceTest`, que hace lo mismo con el rótulo de día tras la misma
 * clase de divergencia (cuatro copias del mismo formato).
 */
class LedgerSingleSourceTest extends TestCase
{
    /**
     * Superficies que enseñan dinero del desglose. La lista **solo encoge**: si una se retira, se
     * borra su entrada; si nace una nueva, entra aquí y se somete a la misma regla.
     *
     * @var list<string>
     */
    private const SURFACES = [
        'resources/views/filament/orders/partials/order-totals.blade.php',
        'resources/views/filament/orders/partials/reservation-financials.blade.php',
        'resources/views/pdf/reservation-slip.blade.php',
        'resources/js/sidebar/account/orders.js',
        'app/Http/Resources/Api/V1/OrderResource.php',
        'app/Http/Resources/Api/V1/OrderItemResource.php',
        // T3·3 del LIBRO: los correos de dinero inyectan el bloque compartido, que es la superficie.
        'app/Domain/Booking/Services/EmailBookBlock.php',
        'resources/views/emails/partials/book.blade.php',
        'app/Notifications/OrderConfirmation.php',
        'app/Notifications/OrderItemModified.php',
        'app/Notifications/OrderItemRefunded.php',
        'app/Notifications/OrderRefunded.php',
        'app/Notifications/MixedPartySurchargeChanged.php',
        // Fase 6 · subsistema A: la PUERTA pasa a ser una superficie del ledger (`identidad-qr-puerta.md` §4.7).
        'app/Domain/Booking/Services/GateReservationsReader.php',
        'resources/views/livewire/admin/puerta/partials/reservation.blade.php',
        // T3·2 del LIBRO (`specs/desglose-libro.md` §6.3.2): los lectores del panel que componían
        // por su cuenta —tres fórmulas para el mismo dinero— pasan a pedirle el libro a `OrderBook`.
        'resources/views/filament/orders/items-list.blade.php',
        'resources/views/filament/admin/calendar/item-detail.blade.php',
        'app/Filament/Pages/CalendarPage.php',
        'app/Filament/Resources/Orders/Tables/OrdersTable.php',
        'app/Filament/Resources/Users/RelationManagers/OrdersRelationManager.php',
        'app/Filament/Resources/Orders/Pages/ViewOrder.php',
        'app/Domain/Booking/Services/ReservationSlip.php',
    ];

    /**
     * **Ninguna superficie DERIVA un canal restando otros.**
     *
     * ⚠️ El patrón prohibido es literal y nació de un defecto real: el panel calculaba
     * `pagadoOnline = valorFinal − aCobrar − pagadoPuerta` mientras la API lo calculaba con otra
     * fórmula. Daban lo mismo por álgebra y **divergían en cuanto un ítem tenía un `extra_due` mayor
     * que su propio valor** — nadie las cruzaba (`specs/desglose-dinero-cliente.md` §4.5).
     */
    public function test_no_surface_derives_a_channel_by_subtracting_others(): void
    {
        $prohibidos = [
            // Derivar el canal online restando los de puerta.
            '/totalFinalNeto\(\)\s*-\s*[^;\n]{0,40}[pP]ending/',
            '/valorFinal\s*-\s*\$aCobrar/',
            // Derivar lo retenido o lo pendiente de devolver a mano.
            '/grossPaidOnline\s*-\s*[^;\n]{0,40}[rR]efunded/',
            // Recomponer el resto de la señal restando lo pagado del total.
            '/total\s*-\s*[^;\n]{0,40}[oO]nline[A-Za-z]*\(?/',
        ];

        $hallazgos = [];
        foreach (self::SURFACES as $rel) {
            $ruta = base_path($rel);
            if (! is_file($ruta)) {
                $hallazgos[] = "$rel · la superficie ya no existe: bórrala de la lista";

                continue;
            }
            $codigo = (string) file_get_contents($ruta);
            foreach ($prohibidos as $patron) {
                if (preg_match($patron, $codigo, $m)) {
                    $hallazgos[] = "$rel · deriva un canal a mano: «".trim($m[0]).'»';
                }
            }
        }

        $this->assertSame([], $hallazgos, implode("\n", array_merge(
            ['Una superficie está componiendo el desglose por su cuenta:'],
            $hallazgos,
            ['', '▶ Pídeselo a `Booking\Services\OrderBook`. Nueve superficies enseñan este dinero y',
                '  la última vez que cada una lo compuso, 18 de 23 gestiones del panel dejaron la',
                '  columna del cliente ilegible (`DECISIONES #127`).'],
        )));
    }

    /**
     * **Ninguna superficie lee ya el modelo viejo** (guarda L; T3·2 de `specs/desglose-libro.md`
     * §6.3.2 el panel, la hoja y la puerta; T3·3 los correos — la lista de excepciones que hubo
     * entre las dos se vació y se borró). Lo que se prohíbe es literal:
     * las clases y los métodos del modelo de dos ejes (`#127`) que la T3·4 retira — hasta entonces
     * siguen existiendo, y un `git grep` verde no distingue «nadie los usa» de «los usa una vista».
     */
    public function test_no_surface_reads_the_old_model(): void
    {
        $viejo = '/OrderLedger|OrderFinancialSummary|financialSummary\(|ReservationFinancials'
            .'|reservationFinancialsByPrincipal|reservationGateLines|pendingAtGateLines|depositRemainderPendingByProduct'
            .'|onlineBackingProductsCents|totalWithChangesCents|amountCollectedCents|totalFinalNeto/';

        $hallazgos = [];
        foreach (self::SURFACES as $rel) {
            if (preg_match($viejo, (string) file_get_contents(base_path($rel)), $m)) {
                $hallazgos[] = "$rel · lee el modelo viejo: «{$m[0]}»";
            }
        }

        $this->assertSame([], $hallazgos, implode("\n", array_merge(
            ['Una superficie ha vuelto al modelo de dos ejes:'], $hallazgos,
            ['', '▶ El libro es `Booking\Services\OrderBook` (`forOrder` / `forReservation`), y cada',
                '  superficie lo TRANSCRIBE. Tres compositores del mismo dinero es como el panel y el',
                '  cliente divergieron (`DECISIONES #127`); el libro existe para que no vuelva a pasar.'],
        )));
    }

    /**
     * **La guarda de la guarda**: los patrones prohibidos SÍ cazan si vuelven a aparecer.
     *
     * ⚠️ Sin esto, una expresión mal escrita dejaría el test verde para siempre sin mirar nada —que
     * es el modo de fallo de toda guarda por `grep`, y el motivo por el que
     * `SidebarStyleWiringTest` lleva el suyo.
     */
    public function test_the_forbidden_patterns_actually_match(): void
    {
        $muestras = [
            '$x = $s->totalFinalNeto() - $s->pendingAtGate();' => '/totalFinalNeto\(\)\s*-\s*[^;\n]{0,40}[pP]ending/',
            '$pagadoOnline = max(0, $valorFinal - $aCobrar - $pagadoPuerta);' => '/valorFinal\s*-\s*\$aCobrar/',
            '$held = $summary->grossPaidOnline - $summary->effectiveRefunded();' => '/grossPaidOnline\s*-\s*[^;\n]{0,40}[rR]efunded/',
            '$resto = $order->total - $order->onlineDueCents();' => '/total\s*-\s*[^;\n]{0,40}[oO]nline[A-Za-z]*\(?/',
        ];

        foreach ($muestras as $codigo => $patron) {
            $this->assertMatchesRegularExpression(
                $patron, $codigo,
                'un patrón prohibido ha dejado de cazar su propio ejemplo: la guarda es decorativa',
            );
        }
    }

    /**
     * **El value object es la fuente, y publica el libro ENTERO** (T3·1 de
     * `specs/desglose-libro.md`): las líneas de valor, las de dinero, el Total, lo pagado, el saldo
     * con su clase, la señal, si cierra y la frase.
     *
     * ⚠️ Si alguien retirara un campo «porque siempre vale lo mismo», alguna superficie volvería a
     * derivarlo por su cuenta — que es literalmente cómo nació la divergencia de `#127`.
     */
    public function test_the_book_exposes_every_field(): void
    {
        $campos = ['currency', 'movements', 'settlements', 'totalCents', 'paidCents', 'balance',
            'hasDeposit', 'isConsistent', 'note'];

        $ref = new \ReflectionClass(OrderBook::class);
        $presentes = array_map(fn ($p) => $p->getName(), $ref->getProperties());

        $this->assertSame([], array_diff($campos, $presentes), 'falta un campo del libro');
    }

    /**
     * **LA TARJETA DE UNA RESERVA NO PINTA DINERO** (2026-08-24, `DECISIONES #130`, owner).
     *
     * «Mis reservas» responde a «¿qué tengo y cuándo?». El dinero es del PEDIDO y vive entero en «Mis
     * pedidos», a un clic: tener aquí el importe de la línea y la nota de la señal repetía media
     * contabilidad en la pantalla que menos la necesita y competía con lo único que el cliente viene
     * a mirar, la fecha.
     *
     * ⚠️⚠️ **La guarda mira el MARCADO y no la composición, y no hay alternativa**: `lineRow()` sigue
     * componiendo `priceLabel` porque **«Mis pedidos» la pinta** —las dos pantallas comparten la
     * composición a propósito, para que no nazcan dos—. Lo que se decidió es qué pinta cada una, y
     * eso solo se ve en su plantilla. Es un hueco con nombre convertido en guarda (`TESTING.md`
     * §2.quater).
     */
    public function test_the_reservation_card_paints_no_money(): void
    {
        $marcado = (string) file_get_contents(base_path('resources/js/sidebar/account/zones/ReservationCard.vue'));

        foreach (['priceLabel', 'depositNote', 'totalLabel', 'financials'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido, $marcado,
                "`ReservationCard.vue` vuelve a pintar dinero (`{$prohibido}`). Esa pantalla es «qué ".
                'tengo y cuándo»; el desglose vive en «Mis pedidos» (`PurchaseCard.vue`).'
            );
        }
    }

    /**
     * **La guarda de la guarda**: la pantalla que SÍ pinta el dinero sigue pintándolo.
     *
     * Sin este caso, borrar el desglose de las dos pantallas dejaría el de arriba en verde — y el
     * cliente sin ningún sitio donde ver su dinero, que es exactamente lo contrario de lo decidido.
     */
    public function test_the_order_card_still_paints_the_whole_breakdown(): void
    {
        $marcado = (string) file_get_contents(base_path('resources/js/sidebar/account/zones/PurchaseCard.vue'));

        // ⚠️ `line.addons` está en la lista porque **su ausencia no la caza nada más**: la guarda de
        // composición mira `purchaseRows()`, que sí compone los complementos, así que un `v-for` que
        // dejara de recorrerlos la deja verde. Medido por mutación el 2026-08-24 — y el síntoma sería
        // el de `R-UPFQAB`: la reserva pone 120,00 € y el total 124,00 €, sin nada que lo explique.
        // ▶ Desde la T3·1 del libro (guarda O, `specs/desglose-libro.md` §6·T3): los MOVIMIENTOS, las
        // LIQUIDACIONES, el Total, lo pagado, el saldo y la frase — un `v-for` sobre `[]` deja verde
        // la composición y vacía la pantalla.
        foreach (['financials.movements', 'financials.settlements', 'financials.total', 'financials.paid',
            'financials.balance', 'financials.note', 'priceLabel', 'line.addons'] as $obligatorio) {
            $this->assertStringContainsString(
                $obligatorio, $marcado,
                "`PurchaseCard.vue` ha dejado de pintar `{$obligatorio}`: el cliente se queda sin esa ".
                'parte de su dinero y ninguna otra pantalla la enseña.'
            );
        }
    }
}
