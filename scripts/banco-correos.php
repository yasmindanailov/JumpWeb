<?php

/**
 * EL BANCO DE CORREOS (`specs/correos-rediseno.md` §4.1.2, la R1a; lo usarán la R1c, la R2 y la C1): manda a Mailpit
 * (`:8028`) los correos del molde, construidos con datos de la BASE LOCAL, por el canal de correo de verdad
 * (`Notification::sendNow`, sin la cola): lo que llega es exactamente lo que se enviaría, en HTML y en texto.
 *
 *   docker compose exec -u sail -T laravel.test php scripts/banco-correos.php [filtro]      # p. ej. «Order» o «Guest»
 *   CLIENTE=otro@correo.test  IDIOMA=en  …                                                   # a quién y en qué idioma
 *
 * ⚠️ Solo en local: escribe lo que el producto escribe al enviar (filas de `email_sends` y el evento `email_sent`) y el
 * correo en Mailpit, a nombre del cliente de sondas. No limpia el buzón: una sonda que borra Mailpit se lleva por delante
 * lo que el owner tenía que mirar (`#503`).
 * ⚠️ Los datos son los que haya en la base: el último pedido pagado con franja, la última fiesta (un pack con franja),
 * la última firma y la primera encuesta. Un correo que no encuentra los suyos se salta con su motivo, no revienta el resto.
 */

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\PostFormAddonChanges;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Platform\Models\Survey;
use App\Notifications as N;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Notification;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$filtro = $argv[1] ?? '';
$cliente = User::query()->where('email', getenv('CLIENTE') ?: 'probe-card@jumpweb.test')->firstOrFail();
if (($idioma = getenv('IDIOMA')) !== false && $idioma !== '') {
    $cliente->forceFill(['locale' => $idioma]);   // sin guardar: solo para este envío
}

$pedido = Order::query()
    ->where('status', Order::STATUS_PAID)
    ->whereHas('items', static fn ($q) => $q->whereNull('parent_item_id')->whereNotNull('slot_id'))
    ->latest('id')->first();
$reserva = $pedido?->items()->whereNull('parent_item_id')->whereNotNull('slot_id')->first();
$fiesta = OrderItem::query()
    ->whereNull('parent_item_id')->whereNotNull('slot_id')
    ->whereHas('ticketType', static fn ($q) => $q->where('type', TicketType::TYPE_PACK))
    ->latest('id')->first();
$firma = class_exists(WaiverSignature::class)
    ? WaiverSignature::query()->latest('id')->first()
    : null;
$encuesta = class_exists(Survey::class) ? Survey::query()->first() : null;

$falta = static fn (string $que): Closure => static fn () => throw new RuntimeException("no hay {$que} en la base local");

/** @var array<string, Closure(): Illuminate\Notifications\Notification> */
$correos = [
    'AccountAlreadyExists' => static fn () => new N\AccountAlreadyExists,
    'AnalyticsLinkNotice' => static fn () => new N\AnalyticsLinkNotice,
    'BirthdayComingNotice' => static fn () => new N\BirthdayComingNotice(1, 'Vera', 7, 'octubre', null),
    'CustomerAccountCreated' => static fn () => new N\CustomerAccountCreated('temporal-123'),
    'EmailChangeCompleted' => static fn () => new N\EmailChangeCompleted('n***@example.com'),
    'EmailChangeRequested' => static fn () => new N\EmailChangeRequested('n***@example.com'),
    'GoogleBusinessLocationChanged' => static fn () => new N\GoogleBusinessLocationChanged('Ficha nueva', 'Ficha de antes', 'Ana'),
    'GuardianAuthorizationRequest' => $fiesta ? static fn () => new N\GuardianAuthorizationRequest($fiesta) : $falta('una fiesta'),
    'GuardianAuthorizationSigned' => $firma ? static fn () => new N\GuardianAuthorizationSigned($firma) : $falta('una firma'),
    'GuestFormRequest' => $fiesta ? static fn () => new N\GuestFormRequest($fiesta) : $falta('una fiesta'),
    'MixedPartySurchargeChanged' => $fiesta ? static fn () => new N\MixedPartySurchargeChanged($fiesta, 0, 400) : $falta('una fiesta'),
    'OrderCancelled' => $pedido ? static fn () => new N\OrderCancelled($pedido) : $falta('un pedido pagado'),
    'OrderConfirmation' => $pedido ? static fn () => new N\OrderConfirmation($pedido) : $falta('un pedido pagado'),
    'OrderExpiredWithoutPayment' => $pedido ? static fn () => new N\OrderExpiredWithoutPayment($pedido) : $falta('un pedido pagado'),
    'OrderItemCancelled' => $reserva ? static fn () => new N\OrderItemCancelled($pedido, $reserva) : $falta('una reserva'),
    'OrderItemModified' => $reserva ? static fn () => new N\OrderItemModified($pedido, $reserva) : $falta('una reserva'),
    'OrderItemRefunded' => $reserva ? static fn () => new N\OrderItemRefunded($pedido, $reserva, 500) : $falta('una reserva'),
    'OrderPaymentDeclined' => $pedido ? static fn () => new N\OrderPaymentDeclined($pedido, '0190') : $falta('un pedido pagado'),
    'OrderProcessedAfterExpiration' => $pedido ? static fn () => new N\OrderProcessedAfterExpiration($pedido) : $falta('un pedido pagado'),
    'OrderRefunded' => $pedido ? static fn () => new N\OrderRefunded($pedido) : $falta('un pedido pagado'),
    'PasswordReset' => static fn () => new N\PasswordReset('token-de-prueba'),
    'PostFormAddonsChanged' => $fiesta ? static fn () => new N\PostFormAddonsChanged($fiesta, new PostFormAddonChanges(
        [['addon_id' => 1, 'name' => 'Cubo de refrescos', 'from' => 0, 'to' => 2]], [], 1200,
    )) : $falta('una fiesta'),
    'SocialIdentityLinked' => static fn () => new N\SocialIdentityLinked('google'),
    'SurveyInvitation' => $encuesta ? static fn () => new N\SurveyInvitation($encuesta, 'token-de-prueba') : $falta('una encuesta'),
    'VerifyEmailAddress' => static fn () => new N\VerifyEmailAddress,
    'VerifyEmailForPurchase' => static fn () => new N\VerifyEmailForPurchase('R-ABC123'),
    'VerifyPendingEmail' => static fn () => new N\VerifyPendingEmail,
    'VisitEveNotice' => $fiesta ? static fn () => new N\VisitEveNotice($fiesta, new PendingWork(6, 10, 2, 1, 11950, true)) : $falta('una fiesta'),
];

$enviados = $fallos = 0;
foreach ($correos as $nombre => $crear) {
    if ($filtro !== '' && ! str_contains($nombre, $filtro)) {
        continue;
    }
    try {
        Notification::sendNow($cliente, $crear(), ['mail']);
        $enviados++;
        echo "  ✓ {$nombre}\n";
    } catch (Throwable $e) {
        $fallos++;
        echo "  ✗ {$nombre}: ".mb_substr($e->getMessage(), 0, 160)."\n";
    }
}

printf("%d enviados a %s · %d sin enviar · Mailpit en http://localhost:8028\n", $enviados, $cliente->email, $fallos);
exit($fallos > 0 ? 1 : 0);
