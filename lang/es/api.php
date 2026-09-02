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
        'line_addon_occupancy' => 'La hora extra ya no cabe: la franja siguiente está completa o cerrada.',
        'line_addon_over_quantity' => 'No pueden quedarse más personas de las que entran.',
        'reservations_paused' => 'Las reservas online están cerradas temporalmente.',
        'too_many_pending_orders' => 'Ya tienes varias reservas pendientes de pago. Complétalas o espera a que caduquen.',
        'order_not_retryable' => 'Esa reserva ya no se puede pagar.',
        'payment_unavailable' => 'No hemos podido abrir la pasarela de pago. Inténtalo de nuevo.',
        'guest_form_closed' => 'Esa reserva ya se ha celebrado: sus datos se pueden consultar, pero ya no se editan.',
        'waiver_not_internal' => 'El descargo de responsabilidad no se firma en esta web.',
        'waiver_document_stale' => 'El texto del descargo de responsabilidad ha cambiado. Vuelve a leerlo y acéptalo de nuevo.',
        'waiver_email_unverified' => 'Para firmar el descargo de responsabilidad primero hay que verificar el correo.',
        'dependent_not_minor' => 'La persona a cargo tiene que ser menor de edad.',
        'dependents_limit_reached' => 'Ya has llegado al máximo de menores a cargo de tu cuenta (:max).',
        // T5 · D8: con una reserva por celebrar la cuenta no se puede borrar (`cumple-mixto.md` §25.4).
        'account_has_upcoming_reservations' => 'No podemos eliminar tu cuenta todavía: tienes reservas por celebrar. Podrás eliminarla cuando hayan pasado o si se cancelan.',
    ],

    // Avisos POR CAMPO de la ASIGNACIÓN de entradas a menores a cargo (`POST /orders`, Fase 6 · tanda 4):
    // llegan en el 422 bajo `items.{i}.dependent_ids[.{j}]` y el cajón los enseña en su línea. Viven
    // aquí y no en `account.dependents` por lo mismo que los del alta: el servidor es quien los dice.
    'dependents' => [
        'not_yours' => 'Ese menor no está en tu cuenta.',
        'not_minor_on_date' => 'Ese día ya tendrá 18 años: compra su entrada como adulto.',
        'waiver_unsigned' => 'Falta su descargo firmado: fírmalo en «Menores a cargo» antes de asignarle una entrada.',
        'too_many' => 'Has elegido más menores que entradas.',
        'entries_only' => 'Los menores solo se asignan a entradas: un pack ya pide a sus invitados.',
    ],

    // Avisos POR CAMPO del alta (`POST /auth/register`) que solo emite el servidor en el 422.
    // Viven aquí y no en `account.register` a propósito: ese grupo viaja en el montaje de CADA
    // página (`SidebarMountTest` mide su presupuesto), y un texto que el cliente nunca pinta por su
    // cuenta no tiene por qué pagar ese peaje.
    'register' => [
        'waiver_stale' => 'El texto del descargo de responsabilidad ha cambiado. Vuelve a leerlo y acéptalo de nuevo.',
        'waiver_not_internal' => 'El descargo de responsabilidad no se firma en esta web.',
        'waiver_document_required' => 'Para aceptar el descargo hay que indicar qué texto se ha leído.',
        'waiver_required' => 'Para crear la cuenta hay que leer y aceptar el descargo de responsabilidad.',
        // Lanzamiento 2026-09-01: la compra online puede estar cerrada (`sales.online_enabled=0`).
        'online_sales_disabled' => 'La compra online no está disponible por ahora. Llámanos o ven al parque para reservar.',
    ],

    // Entrar y registrarse con Google (`specs/auth-con-google.md`).
    'google' => [
        // Entre que se pintó la pantalla y se envió, esa identidad dejó de poder entrar: apareció una
        // cuenta con ese correo que se ha eliminado, o que ya tiene otra cuenta de Google vinculada.
        'refused' => 'No hemos podido completar el alta con esa cuenta de Google. Vuelve a intentarlo o entra con tu correo y contraseña.',
    ],
];
