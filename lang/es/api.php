<?php

/*
 * Fase 3 · paso 0 — mensajes legibles del sobre de error de `/api/v1` (spec §4.3).
 *
 * Las CLAVES de este fichero NO son el contrato público: el contrato es el `code` del sobre
 * (`App\Http\Api\ApiErrorCode`), y la indirección existe justamente para poder reorganizar estas
 * claves sin romper a ningún cliente. El texto es el último recurso del cliente: lo muestra tal
 * cual cuando no sabe hacer nada mejor con el `code`.
 *
 * Tono: el mismo del resto del sitio (segunda persona, sin culpar al usuario, sin jerga técnica).
 */

return [
    'errors' => [
        'unauthenticated' => 'Necesitas iniciar sesión para continuar.',
        'invalid_credentials' => 'El correo o la contraseña no son correctos.',
        'unauthorized' => 'No tienes permiso para hacer esto.',
        'not_found' => 'No hemos encontrado lo que buscas.',
        'method_not_allowed' => 'Esta operación no está disponible en esta dirección.',
        'session_expired' => 'Tu sesión ha caducado. Vuelve a iniciar sesión.',
        'validation_failed' => 'Revisa los datos que has enviado.',
        'too_many_requests' => 'Has hecho demasiadas peticiones seguidas. Inténtalo de nuevo en unos instantes.',
        'maintenance' => 'Estamos haciendo tareas de mantenimiento. Vuelve a intentarlo en unos minutos.',
        'bad_request' => 'No hemos podido interpretar la petición.',
        'server_error' => 'Ha ocurrido un error inesperado. Inténtalo de nuevo.',

        // ── Negocio (Fase 3 · paso 4c) ───────────────────────────────────────────────────
        'cart_empty' => 'Tu cesta está vacía.',
        'cart_too_large' => 'La cesta tiene demasiadas líneas. Quita alguna para continuar.',
        'product_unavailable' => 'Ese producto no está disponible.',
        'line_unavailable' => 'Una de las líneas de tu cesta ya no está disponible.',
        'line_past_date' => 'Esa fecha ya ha pasado.',
        'line_too_late' => 'Esa hora ya ha pasado. Elige otra.',
        'line_too_soon' => 'Esa hora es demasiado próxima para reservar. Elige otra.',
        'line_outside_window' => 'Esa hora queda fuera del horario disponible.',
        'line_sold_out' => 'No quedan plazas para esa hora.',
        'line_pack_sold_out' => 'No queda sitio para esa reserva en esa hora.',
        'line_pack_guests_range' => 'El número de invitados no es válido para ese servicio.',
        'line_event_required' => 'Faltan datos obligatorios de la reserva.',
        'reservations_paused' => 'Las reservas online están cerradas temporalmente.',
        'too_many_pending_orders' => 'Ya tienes varias reservas pendientes de pago. Complétalas o espera a que caduquen.',
        'order_not_retryable' => 'Esa reserva ya no se puede pagar.',
        'payment_unavailable' => 'No hemos podido abrir la pasarela de pago. Inténtalo de nuevo.',
        'guest_form_closed' => 'Esa reserva ya se ha celebrado: sus datos se pueden consultar, pero ya no se editan.',
        'waiver_not_internal' => 'El waiver no se firma en esta web.',
        'waiver_document_stale' => 'El texto del waiver ha cambiado. Vuelve a leerlo y acéptalo de nuevo.',
    ],

    // Avisos POR CAMPO del alta (`POST /auth/register`) que solo emite el servidor en el 422.
    // Viven aquí y no en `account.register` a propósito: ese grupo viaja en el montaje de CADA
    // página (`SidebarMountTest` mide su presupuesto), y un texto que el cliente nunca pinta por su
    // cuenta no tiene por qué pagar ese peaje.
    'register' => [
        'waiver_stale' => 'El texto del waiver ha cambiado. Vuelve a leerlo y acéptalo de nuevo.',
        'waiver_not_internal' => 'El waiver no se firma en esta web.',
        'waiver_document_required' => 'Para aceptar el waiver hay que indicar qué texto se ha leído.',
        'waiver_required' => 'Para crear la cuenta hay que leer y aceptar la exención de responsabilidad (waiver).',
    ],
];
