<?php

namespace App\Http\Api;

use App\Domain\Booking\Exceptions\ReservationException;

/**
 * Fase 3 · paso 4c — traduce el motivo por el que una reserva no se pudo crear al **código público**
 * del sobre de error (spec §4.3).
 *
 * `ReservationException` lleva como mensaje una CLAVE de traducción (`tickets.errors.*`), que es
 * exactamente lo que el contrato público no puede ser: renombrar una clave de `lang/` rompería a
 * todo cliente que se hubiera ramificado sobre ella. La v1 del spec daba por hecho que «los códigos
 * ya existen»; lo que existían eran claves de i18n. Esta es la indirección que las separa.
 *
 * **La lista es exhaustiva por test, no por buena voluntad.** `ReservationErrorMapTest` recorre el
 * código del dominio buscando cada `ReservationException` lanzada y falla si alguna clave no está
 * aquí: una regla de rechazo nueva no puede llegar a producción devolviendo un 500 genérico.
 *
 * El STATUS es 422 para todas: la petición está bien formada y lo que falla es que esa cesta no se
 * puede convertir en un pedido. La precisión la lleva el `code`.
 */
final class ReservationErrorMap
{
    /**
     * Clave de `ReservationException` → código público.
     *
     * @var array<string, ApiErrorCode>
     */
    private const CODES = [
        'tickets.errors.cart_empty' => ApiErrorCode::CartEmpty,
        'tickets.errors.cart_too_large' => ApiErrorCode::CartTooLarge,
        'tickets.errors.unavailable' => ApiErrorCode::ProductUnavailable,
        'tickets.errors.unavailable_line' => ApiErrorCode::LineUnavailable,
        'tickets.errors.past_date_line' => ApiErrorCode::LinePastDate,
        'tickets.errors.too_late_line' => ApiErrorCode::LineTooLate,
        'tickets.errors.too_soon_line' => ApiErrorCode::LineTooSoon,
        'tickets.errors.outside_window_line' => ApiErrorCode::LineOutsideWindow,
        'tickets.errors.sold_out_line' => ApiErrorCode::LineSoldOut,
        'tickets.errors.pack_sold_out_line' => ApiErrorCode::LinePackSoldOut,
        'tickets.errors.pack_guests_range_line' => ApiErrorCode::LinePackGuestsRange,
        'tickets.errors.event_required_line' => ApiErrorCode::LineEventRequired,
        // La HORA EXTRA (`specs/hora-extra.md`): la hija no cabe en la franja siguiente · la suma
        // de ocupantes pide que se queden más de los que entran.
        'tickets.errors.addon_occupancy_line' => ApiErrorCode::LineAddonOccupancy,
        'tickets.errors.addon_over_line' => ApiErrorCode::LineAddonOverQuantity,
        // La hora extra de un PACK (§10): la fiesta no cabe con la extensión.
        'tickets.errors.stay_extension_line' => ApiErrorCode::LineStayExtension,
    ];

    /** El status de TODOS los rechazos de reserva. Ver el docblock de la clase. */
    public const STATUS = 422;

    /** Las claves que el mapa cubre, para que el test de exhaustividad pueda compararlas. */
    public static function knownKeys(): array
    {
        return array_keys(self::CODES);
    }

    /**
     * Código público del rechazo.
     *
     * Una clave desconocida cae en `ProductUnavailable`, que es el rechazo más genérico del dominio.
     * **El fallback no es la red de seguridad**: lo es el test de exhaustividad. Existe para que una
     * clave nueva dé una respuesta coherente en producción mientras alguien la mapea, no para
     * ahorrarse mapearla.
     */
    public static function codeFor(ReservationException $exception): ApiErrorCode
    {
        return self::CODES[$exception->getMessage()] ?? ApiErrorCode::ProductUnavailable;
    }

    /**
     * Datos para interpolar el mensaje en el cliente.
     *
     * Viajan porque sin ellos el cliente pintaría «El producto — no está disponible»: el dominio
     * pone en `context` el `product` y el `when` de la línea culpable (y el rango de invitados de un
     * pack), y son la única forma de que el cliente pueda señalar CUÁL de sus líneas falló.
     *
     * @return array<string, mixed>
     */
    public static function paramsFor(ReservationException $exception): array
    {
        return $exception->context;
    }
}
