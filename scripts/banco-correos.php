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
 * la última firma en el idioma del cliente y la primera encuesta. Un correo que no encuentra los suyos se salta con su
 * motivo, no revienta el resto.
 * ▶ Desde la R1·T (`#802`) los correos al cliente se construyen con `MailPreviews::constructores()` —la MISMA fuente que la
 * vista previa de «Textos de los correos»—, y los textos que el parque guardó en el panel salen en lo que llega.
 */

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\QrCode;
use App\Mail\ContactMessageMail;
use App\Mail\PaymentIncidentMail;
use App\Notifications as N;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailPreviews;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification as Correo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$filtro = $argv[1] ?? '';
$cliente = User::query()->where('email', getenv('CLIENTE') ?: 'probe-card@jumpweb.test')->firstOrFail();
if (($idioma = getenv('IDIOMA')) !== false && $idioma !== '') {
    $cliente->forceFill(['locale' => $idioma]);   // sin guardar: solo para este envío
}

// Los del cliente, de la fuente de la vista previa; el del EQUIPO, que no está en su catálogo, aquí. El nombre, el de la clase.
/** @var array<string, Closure(): (Correo|Mailable|string)> */
$correos = ['GoogleBusinessLocationChanged' => static fn () => new N\GoogleBusinessLocationChanged('Ficha nueva', 'Ficha de antes', 'Ana')];
// El idioma del cliente elige el caso cuando el correo trae el suyo (la copia de una autorización: una firma en ese idioma).
$locale = (string) ($cliente->locale ?: config('app.locale'));
// ⚠️ Con su tercer argumento, la «Situación» de la vista previa (R1·T2, `#809`): `null` es el caso de siempre. Sin él, quince
// correos se saltaban con «Too few arguments» (medido al construir la R1c, el 03-10).
foreach (MailPreviews::constructores() as $clave => $crear) {
    $correos[Str::studly($clave)] = static fn () => $crear($cliente, $locale, null);
}
// Los dos avisos al EQUIPO (la R1c, §4.1.4): `Mailable` y no notificaciones, con datos de EJEMPLO (nada de la base).
$correos['ContactMessageMail'] = static fn () => new ContactMessageMail([
    'name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'phone' => '600 11 22 33', 'topic' => 'groups',
    'message' => "Somos un colegio.\n¿Podemos venir un jueves de mayo con 40 niños?", 'locale' => 'fr',
]);
$correos['PaymentIncidentMail'] = static fn () => new PaymentIncidentMail([
    'kind' => 'duplicate', 'action' => 'refund', 'order_id' => 0, 'order_code' => 'R-7K2P4', 'order_status' => 'paid',
    'payment_id' => 0, 'gateway_order' => '0000600031', 'source' => 'notification',
]);
// LA MUESTRA DE BLOQUES de la R2a (§4.3): el cuerpo en orden con todos los bloques nuevos, para el ojo ANTES de que los use un
// correo de verdad (la R2b). Datos de EJEMPLO; el QR, uno de prueba.
$correos['MuestraDeBloquesR2a'] = static fn () => new class extends Correo
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): BrandedMailMessage
    {
        return (new BrandedMailMessage)->subject('Muestra de bloques · R2a')->hero('emails.order_confirmation', 'ok', [], ['day' => 'sábado 26'])
            ->links(['whatsapp' => 'https://wa.me/34600000000', 'menores' => url('/#mi-cuenta/hijos')])
            ->slip(['dow' => 'sáb', 'n' => '26', 'month' => 'sep'], 'Sábado 26 de septiembre', '17:00', 'Kids 1 hora · 2 niños', '24 € pagados', 'Nº R-7K2P4',
                [], [['Cómo llegar', 'https://www.google.com/maps', 'map-pin'], ['Añadir al calendario', url('/'), 'calendar-plus']])
            ->qr(QrCode::png('MUESTRA-R2A-0000'), 'MUES TRA2 A000', 'Enséñalo en la puerta: tu QR es tu entrada.', 'Si la cámara falla, dicta este código:', 'Abrir Mi QR', url('/#mi-cuenta'))
            ->checklist('Antes de venir', [
                ['texto' => '**Menores a tu cargo:** [añádelos y firma por ellos](menores), si aún no están en tu cuenta. Un minuto.', 'icono' => 'user-round-plus', 'tarea' => true],
                ['texto' => '**Otros adultos:** cada uno firma el suyo, desde casa o en el mostrador.', 'icono' => 'users'],
                ['texto' => 'Calcetines antideslizantes para todos: tenéis 2 pares comprados.', 'icono' => 'footprints'],
                ['texto' => 'Tu tiempo empieza a las 17:00: llegad unos minutos antes.', 'icono' => 'clock'],
            ])
            ->section('Si cambian los planes', 'Puedes cambiar o cancelar hasta el viernes 25 a las 17:00: [escríbenos por WhatsApp](whatsapp).')
            ->steps('Ahora, dos cosas', [
                ['texto' => 'Rellena el formulario de invitados, hasta el jueves 24.', 'boton' => 'Rellenar el formulario', 'url' => url('/')],
                ['texto' => 'Comparte la invitación por WhatsApp.', 'boton' => 'Compartir la invitación', 'url' => 'https://wa.me/'],
            ])
            ->buttons('Pagar con Bizum', url('/'), 'Pagar con tarjeta', url('/'))
            ->reason('Motivo', 'Operación denegada por tu banco')
            ->small('Una línea menor, después de todo.')
            ->replies('Responde a este correo si tienes cualquier duda.');
    }
};

$enviados = $fallos = 0;
foreach ($correos as $nombre => $crear) {
    if ($filtro !== '' && ! str_contains($nombre, $filtro)) {
        continue;
    }
    try {
        $correo = $crear();
        if ($correo instanceof Mailable) {
            Mail::to($cliente->email)->sendNow($correo);   // sin la cola, como el resto
            $enviados++;
            echo "  ✓ {$nombre}\n";

            continue;
        }
        if (! $correo instanceof Correo) {
            $fallos++;
            echo "  ✗ {$nombre}: no hay caso en la base local ({$correo})\n";

            continue;
        }
        Notification::sendNow($cliente, $correo, ['mail']);
        $enviados++;
        echo "  ✓ {$nombre}\n";
    } catch (Throwable $e) {
        $fallos++;
        echo "  ✗ {$nombre}: ".mb_substr($e->getMessage(), 0, 160)."\n";
    }
}

printf("%d enviados a %s · %d sin enviar · Mailpit en http://localhost:8028\n", $enviados, $cliente->email, $fallos);
exit($fallos > 0 ? 1 : 0);
