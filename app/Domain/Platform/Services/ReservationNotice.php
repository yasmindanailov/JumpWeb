<?php

namespace App\Domain\Platform\Services;

/**
 * El aviso que sustituye al flujo de compra cuando las reservas online están en pausa (#218).
 *
 * Título, mensaje y canales de contacto son **editables por idioma desde el panel**, así que no
 * pueden vivir quemados en ninguna vista. Los componía a medias el componente Livewire de compra
 * —retirado en 4.7·2b·3—; con `GET /api/v1/booking/status` apareció el segundo consumidor y la
 * composición subió aquí, junto al resto del subsistema de disponibilidad (Fase 4 · paso 4.0b).
 * Hoy el único consumidor es ese endpoint, del que cuelga el cajón SPA.
 *
 * **Qué NO decide**: en qué pasos se enseña el aviso. Eso es del cliente —el sidebar lo muestra en
 * los pasos de reserva y no en los de resultado— y depende de una máquina de estados que el
 * servidor no conoce.
 *
 * ⚠️ **Los dos canales se ofrecen A LA VEZ, no en cascada.** Es una diferencia real y fácil de
 * romper al reescribir: si hay teléfono y WhatsApp, se pintan los dos; el enlace a la página de
 * contacto aparece **solo** cuando no hay ninguno de los dos. Un diseño «en cascada» —teléfono, y
 * si no WhatsApp, y si no contacto— escondería el WhatsApp de toda instalación que tenga teléfono.
 */
final readonly class ReservationNotice
{
    public function __construct(
        /** Título del aviso, ya traducido al idioma pedido. */
        public string $title,
        /** Cuerpo del aviso, ya traducido. */
        public string $message,
        /** Teléfono tal y como lo escribió el operador, para MOSTRARLO. `null` si no hay. */
        public ?string $phone,
        /** El mismo teléfono listo para un `tel:`. `null` si no hay. */
        public ?string $phoneTel,
        /** WhatsApp en dígitos, listo para `wa.me`. `null` si no hay. */
        public ?string $whatsapp,
    ) {}

    /**
     * ¿Hay algún canal DIRECTO que ofrecer?
     *
     * Es lo que decide si procede el enlace a la página de contacto, que es el último recurso. La
     * regla vive aquí —y no en cada cliente— justo para que las cuatro superficies no puedan
     * discrepar sobre cuándo se enseña.
     */
    public function hasDirectContact(): bool
    {
        return $this->phone !== null || $this->whatsapp !== null;
    }
}
