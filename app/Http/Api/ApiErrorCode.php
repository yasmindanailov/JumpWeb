<?php

namespace App\Http\Api;

/**
 * Fase 3 · paso 0 — **códigos de error del contrato público** de `/api/v1` (spec §4.3).
 *
 * Son parte del CONTRATO, no de la implementación: la app móvil de Fase 6 los lee para decidir
 * («reintenta», «vuelve a iniciar sesión», «muestra estos campos en rojo»). Por eso:
 *
 *  - **NO son claves de traducción.** El spec §4.3 corrigió esto de la v1: atar el contrato a
 *    los ficheros de `lang/` significaría que renombrar una clave rompe la API en silencio. La i18n del
 *    mensaje se deriva AQUÍ (`messageKey()`), y puede cambiar sin tocar el código público.
 *  - **`snake_case` estable.** Añadir un caso es evolutivo; renombrar o quitar uno es un cambio
 *    incompatible y exige versión nueva (`/api/v2`).
 *
 * El sobre que los transporta lo construye `ApiErrorResponse`; el mapeo excepción → código vive en
 * `ApiExceptionRenderer`. Los códigos de negocio (`ReservationException` y compañía) llegan en el
 * paso 4 con su propio mapa y su test de exhaustividad — no se inventan aquí sin lector.
 */
enum ApiErrorCode: string
{
    /** 401 — no hay identidad (sin cookie de sesión ni Bearer válido). */
    case Unauthenticated = 'unauthenticated';

    /**
     * 401 — las credenciales enviadas no casan. **Nunca dice cuál de las dos falló** (`SEC-06`,
     * anti-enumeración): distinguir «esa cuenta no existe» de «esa contraseña no es» convierte el
     * login en un oráculo de qué correos están registrados.
     *
     * Se separa de `Unauthenticated` porque el cliente los programa distinto: uno significa
     * «vuelve a intentarlo» y el otro «tu sesión ya no vale, identifícate otra vez».
     */
    case InvalidCredentials = 'invalid_credentials';

    /** 403 — hay identidad pero no permiso (incluye titularidad: anti-IDOR). */
    case Unauthorized = 'unauthorized';

    /** 404 — el recurso no existe, o no existe PARA QUIEN PREGUNTA (no se filtra la diferencia). */
    case NotFound = 'not_found';

    /** 405 — verbo no admitido en esa ruta. */
    case MethodNotAllowed = 'method_not_allowed';

    /** 419 — sesión/CSRF caducados (modo SPA stateful). */
    case SessionExpired = 'session_expired';

    /** 422 — la petición está bien formada pero sus datos no validan. Lleva `fields`. */
    case ValidationFailed = 'validation_failed';

    /** 429 — limitador. Lleva `params.retry_after` (segundos) cuando el limitador lo informa. */
    case TooManyRequests = 'too_many_requests';

    /** 503 — kill-switch de mantenimiento del sitio (#218). Lleva `Retry-After`. */
    case Maintenance = 'maintenance';

    /** 400 — petición malformada (JSON ilegible, tipo de contenido inservible). */
    case BadRequest = 'bad_request';

    /** 500 — fallo no previsto. Nunca transporta detalles internos fuera de `APP_DEBUG`. */
    case ServerError = 'server_error';

    /** Clave i18n del mensaje legible. Indirección deliberada: el código público no la conoce. */
    public function messageKey(): string
    {
        return 'api.errors.'.$this->value;
    }

    /** Mensaje legible en el idioma ya resuelto por `ApiLocale`. */
    public function message(): string
    {
        return __($this->messageKey());
    }
}
