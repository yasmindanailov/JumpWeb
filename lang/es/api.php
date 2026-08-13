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
    ],
];
