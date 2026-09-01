<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Platform\Services\AuditLogger;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hoja de reserva imprimible (PDF A4) de un OrderItem PRINCIPAL, para la
 * operativa física del parque (decisión #183).
 *
 * GET /admin/pedidos/{order}/items/{item}/imprimir
 *
 * Defense in depth (patrón estándar de los endpoints del panel):
 *  1. Middleware `web+auth+panel_role` (acceso al panel).
 *  2. Permiso `orders.view` (consulta read-only → no requiere capacidad de edición).
 *  3. IDOR: el item DEBE pertenecer al order del URL (anti cross-pedido).
 *  4. Solo items PRINCIPALES — los complementos se imprimen DENTRO de la hoja de
 *     su producto, no por separado.
 *
 * La hoja se renderiza SIEMPRE en español (documento operativo del personal del
 * parque, en España), independientemente del idioma del panel del staff.
 *
 * NO expone datos de cobro sensibles (tarjeta, `gateway_order`, `auth_code`,
 * `Ds_Response`): la hoja es para puerta/sala, no para conciliación bancaria.
 *
 * PRECIOS (decisión clienta 2026-06-14): por DEFECTO la hoja es OPERATIVA y NO muestra
 * importes ni totales (los complementos CONTRATADOS sí se listan siempre, sin precio). El
 * parámetro `?precios=1` genera la hoja COMPLETA con el desglose económico (totales, a cobrar
 * en puerta, pendiente de devolución). El botón del panel ofrece ambas variantes.
 */
class ReservationSlipController extends Controller
{
    public function __invoke(Request $request, Order $order, OrderItem $item): Response
    {
        abort_unless($request->user()->hasPermission('orders.view'), 403);

        // IDOR: el item debe pertenecer al pedido del URL.
        abort_unless($item->order_id === $order->id, 404);

        // Solo reservas principales (los complementos no tienen hoja propia).
        abort_unless($item->parent_item_id === null, 404);

        // #F11: un principal voided-leftover (cancelado net-cero, nunca cobrado ni
        // reembolsado) no es una reserva real — las demás superficies lo ocultan
        // de sus listados, así que tampoco emitimos su hoja fantasma. Defensivo:
        // sin botón de impresión que lleve aquí, solo alcanzable por URL forjada.
        abort_if($order->isVoidedLeftoverItem($item), 404);

        // Documento operativo → SIEMPRE en español, sea cual sea el locale del panel.
        App::setLocale('es');

        // ¿Hoja CON precios? Por defecto no (hoja de sala). `?precios=1` añade el desglose económico.
        $showPrices = $request->boolean('precios');

        AuditLogger::log('orders.slip_printed', $order, [
            'order_code' => $order->code,
            'order_item_id' => $item->id,
            'ticket_type_id' => $item->ticket_type_id,
            'precios' => $showPrices,
        ]);

        $slip = ReservationSlip::make($order, $item);

        $pdf = Pdf::loadView('pdf.reservation-slip', ['slip' => $slip, 'showPrices' => $showPrices])->setPaper('a4');

        // `stream` (disposición inline) → el navegador abre el PDF en la pestaña
        // nueva, listo para imprimir directamente (decisión clienta 2026-06-05).
        return $pdf->stream("reserva-{$order->code}.pdf");
    }
}
