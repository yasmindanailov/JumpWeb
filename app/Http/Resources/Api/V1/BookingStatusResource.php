<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Platform\Services\MaintenanceSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 4 · paso 4.0b — si las reservas online están abiertas, y qué enseñar si no.
 *
 * **Por qué no viaja con `GET /config`**, aunque los dos sean públicos y se pidan en el mismo
 * momento: `/config` es estático entre despliegues y esto es **estado que la dueña cambia con
 * clientes navegando**. Una landing puede quedarse abierta horas; un snapshot inyectado al montar
 * mentiría desde el segundo en que alguien acciona el interruptor. Por eso es un endpoint, y por
 * eso un cliente lo relee tras un 409 `reservations_paused`.
 *
 * **El aviso viaja SIEMPRE, no solo en pausa.** La forma de la respuesta no cambia con el estado:
 * un objeto que aparece y desaparece obliga a cada cliente a distinguir «no está» de «está vacío»,
 * y todo lo que contiene es información pública del negocio de todos modos.
 *
 * ⚠️ **Los canales de contacto se ofrecen A LA VEZ, no en cascada** — si hay teléfono y WhatsApp se
 * pintan los dos. Y `contact_url` llega **ya decidido por el servidor**: no es «la URL de contacto»
 * sino «el último recurso, cuando no hay ningún canal directo». El cliente pinta lo que no sea
 * `null` y no evalúa ninguna condición, que es como se evita que cuatro superficies discrepen.
 */
class BookingStatusResource extends JsonResource
{
    /** El recurso va en la raíz: lo devuelve el propio controlador (§4.3 del spec de la API). */
    public static $wrap = null;

    /** No envuelve ningún modelo: el estado sale de sus lectores defensivos. */
    public function __construct()
    {
        parent::__construct(null);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $notice = MaintenanceSettings::reservationNotice();

        return [
            'reservations_paused' => MaintenanceSettings::reservationsPaused(),
            'notice' => [
                // Ya traducidos al idioma resuelto para ESTA petición, con el literal por defecto si
                // el operador no los ha traducido: el aviso nunca queda mudo.
                'title' => $notice->title,
                'message' => $notice->message,
                'phone' => $notice->phone,
                'phone_tel' => $notice->phoneTel,
                'whatsapp' => $notice->whatsapp,
                // La REGLA de cuándo procede el último recurso vive en el servidor, no repartida.
                'contact_url' => $notice->hasDirectContact() ? null : route('contacto'),
            ],
        ];
    }
}
