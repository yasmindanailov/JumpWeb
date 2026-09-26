<?php

/**
 * **Los datos de la sonda de Mi cuenta** (`scripts/sonda-cuenta.mjs`, T5b de `docs/specs/isla-y-landing-nueva.md` §4.13):
 * reservas PRÓXIMAS de la cuenta de pruebas, con la forma que deja el pedido real —su cobro (`Payment`) y, en el
 * cumpleaños, el reparto de la señal como hecho del libro (`deposit_split`)—, para ver en un navegador «Tu próxima
 * reserva», «Otras reservas», «Tu reserva» con su señal y «Cambiar o cancelar». Sus códigos empiezan por `R-SNDC`, y se
 * borran al acabar (con las franjas que hubo que crear).
 *
 * SOLO EN LOCAL. Se ejecuta desde la sonda con tinker, fijando antes `$modo`:
 *   · `montar`: un Jump dentro de cinco días (la próxima, en plazo) y un cumpleaños con señal dentro de diez (la otra);
 *   · `hoy`: lo mismo y, además, unas Kids HOY con calcetines (la próxima: el QR grande, fuera de plazo, «Cómo llegar»);
 *   · `fiesta` (T5c): SOLO el cumpleaños, dentro de tres días —la próxima, con «Antes de venir»—: su invitación creada
 *     (quien cumple, «Vera», 7 años), dos fichas de diez rellenas y dos «sí»;
 *   · `borrar`: nada.
 *   php artisan tinker --execute='$modo = "montar"; require base_path("scripts/sonda-cuenta-datos.php");'
 *
 * Imprime una línea JSON: `{"hoy": bool}` (a partir de las 20:00 del parque no hay «hoy» que montar).
 */

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! app()->environment('local')) {
    exit("solo en LOCAL\n");
}

$prefijo = 'R-SNDC';
// Las franjas que la sonda tuvo que CREAR (la app las materializa al vender): se borran con sus pedidos.
$llaveFranjas = 'sonda-cuenta:franjas';
$borrar = function () use ($prefijo, $llaveFranjas): void {
    foreach (Order::where('code', 'like', $prefijo.'%')->get() as $pedido) {
        DB::transaction(function () use ($pedido): void {
            $lineas = OrderItem::where('order_id', $pedido->id)->pluck('id');
            InvitationReply::whereIn('order_item_id', $lineas)->delete();
            PartyInvitation::whereIn('order_item_id', $lineas)->delete();
            OrderAdjustment::where('order_id', $pedido->id)->delete();
            Payment::where('payable_type', $pedido->getMorphClass())->where('payable_id', $pedido->id)->delete();
            OrderItem::where('order_id', $pedido->id)->whereNotNull('parent_item_id')->delete();
            OrderItem::where('order_id', $pedido->id)->delete();
            $pedido->delete();
        });
    }
    $creadas = Cache::pull($llaveFranjas, []);
    Slot::whereIn('id', $creadas)->whereNotIn('id', OrderItem::whereIn('slot_id', $creadas)->select('slot_id'))->delete();

    // Los hijos que la sonda declara (T5d): se QUITAN como lo haría su titular —con firma detrás se desvinculan y la
    // prueba se conserva; sin ella, se borran (`DependentRegistry::remove`)—, así «Añade a tus hijos» vuelve a salir.
    $sonda = User::where('email', 'probe-card@jumpweb.test')->first();
    foreach ($sonda === null ? [] : app(DependentRegistry::class)->activeFor($sonda) as $hijo) {
        app(DependentRegistry::class)->remove($sonda, (int) $hijo->getKey());
    }
};

$borrar();

if (! in_array($modo ?? '', ['montar', 'hoy', 'fiesta'], true)) {
    echo json_encode(['borrado' => true]), "\n";

    return;
}

$titular = User::where('email', 'probe-card@jumpweb.test')->firstOrFail();
$ahora = DisplayTime::now();

/** Una reserva pagada: su franja, su línea, sus complementos, su cobro y, si la hay, la parte de la señal que queda en el parque. */
$reserva = function (string $sufijo, int $producto, string $fecha, string $hora, int $cantidad, int $unidad, array $complementos = [], int $enElParque = 0) use ($prefijo, $titular, $llaveFranjas): void {
    $tipo = TicketType::findOrFail($producto);
    $inicio = Carbon::parse($fecha.' '.$hora, DisplayTime::timezone());
    $franja = Slot::firstOrCreate(
        ['zone_id' => $tipo->zone_id, 'date' => $fecha, 'start_time' => $hora],
        ['end_time' => $inicio->copy()->addMinutes($tipo->duration_min ?: 60)->format('H:i:s'), 'capacity' => 50, 'online_capacity' => 50],
    );
    if ($franja->wasRecentlyCreated) {
        Cache::forever($llaveFranjas, [...Cache::get($llaveFranjas, []), $franja->id]);
    }
    $total = $cantidad * $unidad + array_sum(array_map(fn ($c) => $c[1] * $c[2], $complementos));
    $pedido = Order::create([
        'user_id' => $titular->id, 'code' => $prefijo.$sufijo, 'status' => Order::STATUS_PAID,
        'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR', 'paid_at' => now(),
    ]);
    $linea = $pedido->items()->create(['ticket_type_id' => $tipo->id, 'slot_id' => $franja->id, 'quantity' => $cantidad, 'unit_price' => $unidad, 'seats' => $cantidad]);

    foreach ($complementos as [$id, $n, $precio]) {
        OrderItem::create(['order_id' => $pedido->id, 'parent_item_id' => $linea->id, 'ticket_type_id' => $id, 'slot_id' => $franja->id, 'quantity' => $n, 'unit_price' => $precio, 'seats' => 0]);
    }

    if ($enElParque > 0) {
        // Como `OrderCreator::recordDepositRemainder`: el reparto es un hecho de nacimiento de la línea.
        OrderAdjustment::create(['order_id' => $pedido->id, 'order_item_id' => $linea->id, 'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT, 'amount_cents' => $enElParque, 'currency' => 'EUR', 'reason' => 'deposit_split', 'applied_by' => $titular->id]);
    }

    Payment::create([
        'payable_type' => $pedido->getMorphClass(), 'payable_id' => $pedido->id, 'provider' => Payment::PROVIDER_REDSYS,
        'amount' => $total - $enElParque, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'paid_at' => now(),
    ]);
};

if ($modo === 'fiesta') {
    // El cumpleaños como PRÓXIMA (tres días: los extras, a 48 h, siguen en plazo), con lo que deja un anfitrión a medias.
    $reserva('CUMPLE', 105, $ahora->copy()->addDays(3)->toDateString(), '17:00:00', 10, 1695, [], 11950);
    $linea = OrderItem::whereHas('order', fn ($q) => $q->where('code', $prefijo.'CUMPLE'))->whereNull('parent_item_id')->firstOrFail();
    $linea->forceFill(['guest_data' => [['name' => 'Hugo', 'age' => '7'], ['name' => 'Ana', 'age' => '6']]])->save();
    $invitaciones = app(PartyInvitations::class);
    $inv = $invitaciones->forReservation($linea->fresh(['ticketType', 'slot', 'order']));
    $inv->forceFill(['honoree_name' => 'Vera', 'honoree_age' => 7])->save();
    foreach (['Hugo Ruiz', 'Ana Gil'] as $nino) {
        $invitaciones->reply($inv, $nino, true);
    }
    echo json_encode(['fiesta' => $linea->id]), "\n";

    return;
}

// HOY, si da tiempo (su hora, dentro de dos horas en punto): Kids 1 hora, 2 niños, y 2 pares de calcetines.
$hoy = $modo === 'hoy' && $ahora->hour < 20;
if ($hoy) {
    $reserva('HOY', 100, $ahora->toDateString(), $ahora->copy()->addHours(2)->startOfHour()->format('H:i:s'), 2, 800, [[110, 2, 200]]);
}
// Un cumpleaños dentro de diez días, con señal: 169,50 €, 50 € pagados y 119,50 € en el parque.
$reserva('CUMPLE', 105, $ahora->copy()->addDays(10)->toDateString(), '17:00:00', 10, 1695, [], 11950);
// Jump dentro de cinco días, 2 personas.
$reserva('JUMP', 103, $ahora->copy()->addDays(5)->toDateString(), '11:30:00', 2, 1400);

echo json_encode(['hoy' => $hoy]), "\n";
