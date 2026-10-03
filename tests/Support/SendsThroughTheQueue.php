<?php

namespace Tests\Support;

use Closure;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as Notifications;
use Illuminate\Support\Facades\Queue;

/**
 * **Un correo encolado, como lo procesa la cola DE VERDAD** (la C1a, `#921`): se encola, ENTRETANTO pasa algo (una baja, un
 * «Borrar» del panel), y el trabajo se deserializa —lo que hay en la base se vuelve a leer, `SerializesModels`— y se procesa
 * con el envío real, el que mira `shouldSend()`. Devuelve cuántas salidas llegó a intentar el envío (`NotificationSending`).
 *
 * ⚠️ Con el envío real y no con el falso de `Notification::fake()`, que ocupa el sitio de `ChannelManager` en el contenedor:
 * el falso mira `shouldSend()` al llamar a `notify()`, no cuando el correo sale de la cola.
 */
trait SendsThroughTheQueue
{
    protected function salidasDeLaCola(object $destinatario, Notification $correo, Closure $entretanto): int
    {
        Queue::fake();
        $envio = new ChannelManager(app());
        Notifications::swap($envio);
        $destinatario->notify($correo);
        $trabajos = Queue::pushed(SendQueuedNotifications::class);
        $this->assertCount(1, $trabajos, 'el correo se encola');

        $entretanto();

        $salidas = 0;
        Event::listen(NotificationSending::class, static function () use (&$salidas): void {
            $salidas++;
        });
        /** @var SendQueuedNotifications $trabajo */
        $trabajo = unserialize(serialize($trabajos->first()));
        $trabajo->handle($envio);

        return $salidas;
    }

    /** Un destinatario sin cuenta, por su correo (el de «Avísame de fechas»). */
    protected function aUnCorreo(string $correo): AnonymousNotifiable
    {
        return (new AnonymousNotifiable)->route('mail', $correo);
    }
}
