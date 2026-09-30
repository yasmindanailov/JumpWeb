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
 * ▶ Desde la R1·T (`#802`) los correos al cliente se construyen con `MailPreviews::constructores()` —la MISMA fuente que la
 * vista previa de «Textos de los correos»—, y los textos que el parque guardó en el panel salen en lo que llega.
 */

use App\Domain\Identity\Models\User;
use App\Notifications as N;
use App\Notifications\Support\MailPreviews;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\Notification as Correo;
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
/** @var array<string, Closure(): (Correo|string)> */
$correos = ['GoogleBusinessLocationChanged' => static fn () => new N\GoogleBusinessLocationChanged('Ficha nueva', 'Ficha de antes', 'Ana')];
foreach (MailPreviews::constructores() as $clave => $crear) {
    $correos[Str::studly($clave)] = static fn () => $crear($cliente);
}

$enviados = $fallos = 0;
foreach ($correos as $nombre => $crear) {
    if ($filtro !== '' && ! str_contains($nombre, $filtro)) {
        continue;
    }
    try {
        $correo = $crear();
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
