<?php

namespace App\Notifications;

use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **HA CAMBIADO LA FICHA DE GOOGLE DEL PARQUE** (`specs/google-business-profile.md` §4.2·4).
 *
 * ⚠️⚠️ **No es un correo de «se ha guardado un ajuste».** Cambiar la ficha cambia **de qué negocio
 * son las reseñas que se publican en la portada**: si alguien se equivoca de ficha —y en una cuenta
 * con varias no es raro—, el parque empieza a enseñar a sus visitantes las opiniones de otro. El
 * cambio ya exige una confirmación en el panel; esto es la otra mitad, la que se entera **quien no
 * estaba delante**.
 *
 * ⚠️ **Dice QUIÉN lo hizo**, con su nombre y no su correo: la pregunta que contesta este aviso es
 * «¿has sido tú?», y sin nombre no se puede contestar. El correo de la persona no añade nada y sería
 * dato personal de más en una bandeja compartida (`RGPD-02`).
 *
 * ⚠️ **No lleva ningún identificador de Google** —ni `placeId`, ni el nombre de recurso—: no le
 * sirven a quien lo lee y son ruido en un correo.
 *
 * `ShouldQueue` como el resto (`PAY-14`): avisar no bloquea el gesto del admin.
 */
class GoogleBusinessLocationChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        /** El rótulo de la ficha que pasa a usarse. */
        public string $locationTitle,
        /** El de la anterior, o `null` si no se sabe. */
        public ?string $previousTitle,
        /** Quién lo hizo, por su nombre. */
        public string $actor,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Sin UTM ni `email_sent` aunque pase `$this`: su clave está en `EmailUtm::NOT_TO_CUSTOMERS`.
        $message = (new BrandedMailMessage($this))
            // ❗ El dato delante (`#506`): en el corte de una lista de móvil tiene que entrar el
            // rótulo de la ficha, no la palabra «aviso».
            ->subject(__('emails.google_business_location.subject', ['name' => $this->locationTitle]))
            ->line(__('emails.google_business_location.intro', [
                'name' => $this->locationTitle,
                'actor' => $this->actor,
            ]));

        if ($this->previousTitle !== null && $this->previousTitle !== '') {
            $message->line(__('emails.google_business_location.previous', ['name' => $this->previousTitle]));
        }

        return $message
            ->hero('emails.google_business_location', 'warn')
            // La frase que convierte el aviso en una acción: quien lo lee tiene que saber qué hacer
            // si no fue él, y que arreglarlo es volver a la misma pantalla.
            ->line(__('emails.google_business_location.what_to_do'));
    }
}
