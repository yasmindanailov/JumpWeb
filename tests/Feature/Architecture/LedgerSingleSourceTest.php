<?php

namespace Tests\Feature\Architecture;

use App\Domain\Booking\Services\OrderLedger;
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
        'app/Notifications/OrderConfirmation.php',
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
            ['', '▶ Pídeselo a `Booking\Services\OrderLedger`. Ocho superficies enseñan este dinero y',
                '  la última vez que cada una lo compuso, 18 de 23 gestiones del panel dejaron la',
                '  columna del cliente ilegible (`DECISIONES #127`).'],
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
     * **El value object es la fuente, y los seis canales existen todos.**
     *
     * ⚠️ Si alguien retirara un canal «porque siempre vale 0», la identidad seguiría cerrando y el
     * desglose volvería a mentir en el caso que ese canal explicaba — que es literalmente lo que
     * pasaba con «Pagado en el parque», ausente durante toda la vida del producto.
     */
    public function test_the_ledger_exposes_every_channel(): void
    {
        $canales = ['valor', 'pagadoOnline', 'pendienteOnline', 'pagadoPuerta', 'pendientePuerta',
            'compensado', 'cobradoOnline', 'devuelto', 'retenido', 'pendienteDevolucion',
            'facturado', 'gateLines', 'hasDeposit', 'nota'];

        $ref = new \ReflectionClass(OrderLedger::class);
        $presentes = array_map(fn ($p) => $p->getName(), $ref->getProperties());

        $this->assertSame([], array_diff($canales, $presentes), 'falta un canal del desglose');
    }
}
