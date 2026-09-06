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
 * `ApiExceptionRenderer`. Los códigos de NEGOCIO llegaron en el paso 4c: el mapa clave-i18n → código
 * es `ReservationErrorMap` y `ReservationErrorMapTest` cae si alguien lanza una clave sin mapear.
 *
 * **Por qué cada motivo tiene su código y no uno genérico**: añadir un caso es evolutivo, pero
 * PARTIR uno existente rompe a todo cliente que se hubiera ramificado sobre él. Un «no disponible»
 * único habría sido cómodo hoy y un cambio incompatible mañana, en cuanto alguien quisiera
 * distinguir «agotado» —que se arregla refrescando la disponibilidad— de «fuera de horario», que no.
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

    // ── Negocio: la reserva no se puede crear (Fase 3 · paso 4c) ─────────────────────────────
    // Todos llegan con **422**: la petición está bien formada y lo que falla es que esa cesta no se
    // puede convertir en un pedido. La precisión la lleva el `code`, no el status — distinguir 409
    // de 422 no le daría al cliente nada que no le diga ya el código.
    //
    // Los que terminan en `line_*` traen en `params` el `product` y el `when` de la línea culpable:
    // sin ellos el cliente pintaría «El producto — no está disponible» (spec §4.3).

    /** 422 — la cesta enviada no tiene ninguna línea utilizable. */
    case CartEmpty = 'cart_empty';

    /** 422 — la cesta supera el tope de líneas del servidor (`PAY-12`). */
    case CartTooLarge = 'cart_too_large';

    /** 422 — el producto no se puede vender (no está en venta, o no tiene precio para ese día). */
    case ProductUnavailable = 'product_unavailable';

    /** 422 — esa línea no se puede vender: producto o franja inexistentes, cerrados o no ofrecidos. */
    case LineUnavailable = 'line_unavailable';

    /** 422 — la fecha de la línea ya pasó. */
    case LinePastDate = 'line_past_date';

    /** 422 — la franja es de hoy y su hora ya pasó (`PAY-13`). */
    case LineTooLate = 'line_too_late';

    /** 422 — la franja no respeta la antelación mínima de reserva del producto. */
    case LineTooSoon = 'line_too_soon';

    /** 422 — la hora queda fuera de la ventana de disponibilidad del producto ese día. */
    case LineOutsideWindow = 'line_outside_window';

    /** 422 — no quedan plazas para esa entrada. Es el que invita a refrescar la disponibilidad. */
    case LineSoldOut = 'line_sold_out';

    /** 422 — no queda cupo de invitados para ese pack en esa franja. */
    case LinePackSoldOut = 'line_pack_sold_out';

    /** 422 — el número de invitados cae fuera del rango del pack. Lleva `params.min`/`params.max`. */
    case LinePackGuestsRange = 'line_pack_guests_range';

    /** 422 — faltan respuestas obligatorias del formulario del pack. */
    case LineEventRequired = 'line_event_required';

    /**
     * 422 — un complemento que OCUPA (la hora extra, `specs/hora-extra.md`) no cabe: la franja
     * siguiente al tramo de su línea no existe, está cerrada o está completa. Lleva `params.addon`
     * además de `product`/`when`. El remedio es quitarlo o cambiar de hora — como `line_sold_out`,
     * invita a refrescar la disponibilidad.
     */
    case LineAddonOccupancy = 'line_addon_occupancy';

    /**
     * 422 — la suma de complementos que OCUPAN pide que se queden más personas de las que entran
     * (`specs/hora-extra.md` §4.4·5). Lleva `params.staying`/`params.entering`; el remedio es bajar
     * la cantidad de horas extra.
     */
    case LineAddonOverQuantity = 'line_addon_over_quantity';

    /**
     * 422 — la fiesta cabe, pero **alargada no** (la hora extra de un pack,
     * `specs/hora-extra.md` §10): la sala está ocupada después, o la extensión se sale del horario.
     * Lleva `params.product`/`params.when`.
     *
     * ⚠️ Es un código PROPIO y no `line_pack_sold_out` a propósito: el remedio es distinto y sólo el
     * cliente puede elegirlo — **quitar la hora extra conserva la reserva**, cambiar de franja la
     * mueve. Un código que no distingue las dos acciones obliga a adivinar.
     */
    case LineStayExtension = 'line_stay_extension';

    // ── Negocio: la reserva no se admite, o el cobro no se puede abrir ───────────────────────

    /**
     * 409 — las reservas online están en PAUSA desde el panel (#218). No es culpa del cliente ni se
     * arregla reintentando: es un interruptor del operador, y por eso no es un 422.
     */
    case ReservationsPaused = 'reservations_paused';

    /**
     * 409 — el titular ya tiene el máximo de pedidos pendientes vivos reteniendo aforo sin pagar
     * (cierra el hallazgo E de la auditoría del origen). Lleva `params.max`.
     */
    case TooManyPendingOrders = 'too_many_pending_orders';

    /**
     * 409 — ese pedido ya no admite otro intento de cobro: no existe para este titular, no está
     * pendiente, o su retención de aforo venció y la plaza volvió al inventario. Los tres casos dan
     * la MISMA respuesta a propósito (anti-IDOR: no se filtra si el código existe).
     */
    case OrderNotRetryable = 'order_not_retryable';

    /**
     * 409 — el post-form de esa reserva ya no se puede editar: la fiesta se ha celebrado. Se
     * consulta, pero no se escribe. **409 y no 403**: el permiso no ha cambiado, ha cambiado el
     * momento — y el enlace sigue siendo válido hasta su caducidad para poder consultar.
     */
    case GuestFormClosed = 'guest_form_closed';

    /**
     * El formulario cambió mientras el cliente lo tenía abierto (`#413`, T3). No es un permiso que
     * falte ni un recurso que no exista: es que el ENVÍO habla de un estado que ya no es el actual, y
     * aplicarlo pisaría lo que el operador acabara de hacer.
     */
    case GuestFormStale = 'guest_form_stale';

    /**
     * 502 — el cobro no se pudo abrir contra la pasarela. En un primer intento el pedido se suelta
     * en el acto (no retiene aforo sin nadie que lo vaya a pagar) y el cliente puede volver a
     * empezar; en un reintento el pedido sigue vivo y se puede volver a intentar.
     */
    case PaymentUnavailable = 'payment_unavailable';

    // ── Fase 6 · waiver (`specs/waiver-probatorio.md` §4.4) ───────────────────────────────────

    /**
     * 409 — el waiver no se firma en esta instalación (`waiver.mode` ≠ `interno`). Es un dato de la
     * instalación que `GET /legal/waiver` ya publica; no se arregla reintentando.
     */
    case WaiverNotInternal = 'waiver_not_internal';

    /**
     * 409 — el identificador de texto que trae la aceptación no es el de la versión VIGENTE: el
     * texto se publicó de nuevo entre servirlo y aceptarlo (o el id no es del waiver). El cliente
     * vuelve a pedir `GET /legal/waiver` y lo presenta otra vez.
     */
    case WaiverDocumentStale = 'waiver_document_stale';

    /**
     * 409 — el titular no tiene el correo verificado: el waiver solo se firma con él (`[DECIDIDO
     * owner]`, spec §7·5, `#179`). La aceptación marcada en el alta espera a la verificación.
     */
    case WaiverEmailUnverified = 'waiver_email_unverified';

    // ── Fase 6 · menores a cargo (`specs/menores-a-cargo.md` §4.1, §4.5) ──────────────────────

    /**
     * 422 — la fecha de nacimiento no es la de un MENOR: hoy, en el reloj del parque, ya tiene 18 o
     * más. Un adulto firma su propio waiver; no se declara «a cargo» de otro.
     */
    case DependentNotMinor = 'dependent_not_minor';

    /**
     * 422 — la cuenta ya tiene el máximo de personas a cargo de la instalación
     * (`dependents.max_per_account`; tope de SERVIDOR, `PAY-12`). Lleva `params.max`.
     */
    case DependentsLimitReached = 'dependents_limit_reached';

    // ── T5 · supresión (art. 17) — `cumple-mixto.md` §25.4 (D8) ───────────────────────────────

    /**
     * 409 — la cuenta tiene reservas POR CELEBRAR (pagadas, con franja sin pasar): la supresión
     * espera a que pasen o se cancelen, y se le explica al titular. No se arregla reintentando —
     * es el mismo registro que los otros 409 de negocio.
     */
    case AccountHasUpcomingReservations = 'account_has_upcoming_reservations';

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
