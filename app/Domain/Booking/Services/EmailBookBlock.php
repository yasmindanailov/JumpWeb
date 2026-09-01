<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use Illuminate\Support\HtmlString;

/**
 * EL LIBRO del pedido en los correos de dinero (`DECISIONES #305`; T3·3 de
 * `specs/desglose-libro.md` §6.3.4, D-T3·5): cada gestión como una línea + o − con su fecha, el
 * Total, los pagos y devoluciones, lo Pagado y el saldo con su clase — lo MISMO que el cliente lee
 * en «Mis pedidos» y el operador en la ficha, compuesto por {@see OrderBook} AL ENVIAR.
 *
 * ▶ Al enviar y no al gestionar: los correos de pedido se REENVÍAN desde el panel, y para entonces
 * el pedido puede haber cambiado. Un correo de gestión pasa a ser el estado de la cuenta ese día;
 * la narrativa de cada correo (qué cambió, qué se devolvió y por qué canal) sigue siendo suya.
 *
 * Como {@see EmailProductCard}: una tabla con estilos EN LÍNEA (los clientes de correo no respetan
 * CSS externo) que cada notificación inyecta con UNA línea; y DEFENSIVO (D-T3·20): si el libro no
 * se puede componer, el correo sale sin el bloque y el fallo se reporta — una tarea en cola que
 * revienta dejaría al cliente sin correo, que es peor que sin desglose.
 */
final class EmailBookBlock
{
    public static function forOrder(?Order $order): HtmlString
    {
        if ($order === null) {
            return new HtmlString('');
        }

        try {
            $order->loadMissing(['items.ticketType', 'items.slot', 'adjustments', 'payments.refunds']);

            return new HtmlString(self::collapse(view('emails.partials.book', [
                'book' => OrderBook::forOrder($order),
            ])->render()));
        } catch (\Throwable $e) {
            report($e);

            return new HtmlString('');
        }
    }

    /** Quita SOLO el espacio en blanco entre etiquetas → una línea, sin líneas en blanco que el
     *  parser Markdown del correo interpretaría como fin del bloque HTML. */
    private static function collapse(string $html): string
    {
        return trim((string) preg_replace('/>\s+</', '><', $html));
    }
}
