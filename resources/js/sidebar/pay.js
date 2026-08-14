/**
 * CONFIRMAR la reserva y abrir el cobro (Fase 4 · paso 4.5·2).
 *
 * Es el clic que convierte una cesta en un pedido que **retiene aforo** y en un formulario firmado
 * hacia la pasarela. Todo lo que importa de esa operación ocurre en el servidor y ya tiene dueño:
 * `POST /api/v1/orders` ejecuta `Booking\Contracts\ReservationCheckout` —admitir consumiendo ficha →
 * crear con la ventana de retención (`AFORO-10`) → abrir el cobro sobre el pedido persistido— y
 * devuelve el pedido **y** el formulario en la misma respuesta. Aquí no se orquesta nada: se pide, y
 * se traduce el «no».
 *
 * ⚠️ **`payment.fields` es un mapa OPACO y hay que tratarlo como tal.** El cliente no conoce
 * `Ds_MerchantParameters` ni `Ds_Signature`, y no debe: itera el mapa y emite un campo oculto por
 * entrada. Así, el día que haya un segundo driver de pasarela —`PaymentInitiation` es un puerto desde
 * el cierre de Fase 3— el cajón no se entera. Inventarse los nombres sería además la peor forma de
 * romperlo: la firma cubre esos valores exactos y Redsys rechazaría el cobro con SIS0042.
 *
 * ⚠️ **Ningún importe se recalcula aquí** (`PAY-12`): el precio lo pone el servidor y este módulo solo
 * mira códigos de error.
 */

import { t, tp } from './i18n.js';

/**
 * Los códigos de error del contrato → la clave del diccionario que pinta el cajón.
 *
 * ⚠️ **Es el mapa INVERSO de `Http\Api\ReservationErrorMap`**, que traduce cada `ReservationException`
 * del dominio a un código estable. El componente Livewire no necesita esta tabla porque recibe la
 * clave directamente (`__($e->getMessage(), $e->context)`); un cliente de API recibe el CÓDIGO, que es
 * lo estable, y tiene que volver a la clave. Los doce primeros son los del checkout; los tres últimos,
 * los que puede añadir la admisión y el fallo de la pasarela.
 *
 * ⚠️ **`reservations_paused` NO está aquí a propósito**: su tratamiento es otro —releer el estado y
 * dejar hablar al cartel de mantenimiento—, exactamente como en 4.4a·1.
 *
 * `SidebarPayParityTest` recorre el enum del servidor entero: un código nuevo que nadie mapee lo
 * nombra el test, en vez de salir como un aviso vacío en el cajón.
 */
export const ERROR_KEYS = {
    cart_empty: 'errors.cart_empty',
    cart_too_large: 'errors.cart_too_large',
    product_unavailable: 'errors.unavailable',
    line_unavailable: 'errors.unavailable_line',
    line_past_date: 'errors.past_date_line',
    line_too_late: 'errors.too_late_line',
    line_too_soon: 'errors.too_soon_line',
    line_outside_window: 'errors.outside_window_line',
    line_sold_out: 'errors.sold_out_line',
    line_pack_sold_out: 'errors.pack_sold_out_line',
    line_pack_guests_range: 'errors.pack_guests_range_line',
    line_event_required: 'errors.event_required_line',
    // Los de admisión, que `POST /orders` puede devolver porque consume ficha al crear.
    too_many_pending_orders: 'errors.too_many_pending',
    too_many_requests: 'errors.try_later',
    // El 502 del puerto de pasarela. El pedido ya lo soltó el dominio y el rastro está en `audit_logs`.
    payment_unavailable: 'errors.payment_unavailable',
};

/** El código de pausa, que no se traduce a un mensaje: se relee el estado y habla el cartel. */
export const RESERVATIONS_PAUSED = 'reservations_paused';

/**
 * El «no» de `POST /orders` traducido a lo que enseña el cajón.
 *
 * ⚠️ **Los `params` del sobre se interpolan tal cual.** Son los que el dominio puso en el `context` de
 * su excepción —`product`, `when`, `min`, `max`— y los literales de `tickets.errors.*` los esperan con
 * esos nombres: `«:product» del :when se ha agotado`. Reescribirlos aquí sería inventar una segunda
 * fuente para un texto que ya viaja resuelto en el otro motor.
 *
 * @param {{ok: boolean, status: number, error: {code?: string, params?: object}|null}} response
 * @param {object} messages  el grupo `tickets`
 * @returns {{error: string, rereadStatus: boolean}}
 */
export function confirmError(response, messages = {}) {
    if (response?.ok) {
        return { error: '', rereadStatus: false };
    }

    const code = response?.error?.code ?? null;

    // La pausa no compone mensaje: el cartel de mantenimiento sustituye el flujo entero, igual que en
    // el paso al pago (4.4a·1). Es además lo que el contrato pide releer tras un 409.
    if (code === RESERVATIONS_PAUSED) {
        return { error: '', rereadStatus: true };
    }

    const key = ERROR_KEYS[code] ?? null;

    if (key === null) {
        // Un código que este cajón no conoce, un 5xx o un corte de red. No puede quedarse mudo en la
        // pantalla donde el cliente espera pagar.
        return { error: t(messages, 'errors.try_later'), rereadStatus: false };
    }

    return { error: tp(messages, key, response?.error?.params ?? {}), rereadStatus: false };
}

/**
 * El formulario de la pasarela, listo para pintar y enviar.
 *
 * @typedef {{url: string, method: string, fields: Array<{name: string, value: string}>}} GatewayForm
 */

/**
 * Traduce el `payment` del contrato al formulario que se pinta.
 *
 * ⚠️ **El mapa se convierte en LISTA conservando su orden, y los valores se dejan intactos.** Un
 * campo que falte, sobre o cambie invalida la firma; por eso aquí no se filtra, no se renombra y no se
 * normaliza nada. Y el `method` sale del servidor: hoy es `POST` en todos los casos, pero el contrato
 * lo publica y el día del segundo driver puede no serlo.
 *
 * @param {{url?: string, method?: string, fields?: Record<string, string>}} payment
 * @returns {GatewayForm|null}
 */
export function gatewayForm(payment) {
    const url = typeof payment?.url === 'string' ? payment.url : '';
    const fields = payment?.fields;

    // Sin destino o sin campos no hay formulario que enviar: mejor no pintar nada que pintar un POST
    // a ninguna parte, que en el mejor caso es un 404 y en el peor una redirección a un sitio raro.
    if (url === '' || fields === null || typeof fields !== 'object' || Array.isArray(fields)) {
        return null;
    }

    const entries = Object.entries(fields).map(([name, value]) => ({ name, value: String(value ?? '') }));

    if (entries.length === 0) {
        return null;
    }

    return {
        url,
        method: typeof payment?.method === 'string' && payment.method !== '' ? payment.method : 'POST',
        fields: entries,
    };
}

/**
 * Crea la reserva y devuelve con qué pagarla.
 *
 * `api` entra por parámetro, como en el resto de módulos del cajón y por lo mismo: así la secuencia se
 * prueba en Node sin red ni navegador (`CE-6`).
 *
 * ⚠️ **Una sola petición, y no es una simplificación**: `POST /orders` admite, crea y abre el cobro en
 * el mismo acto porque el ORDEN entre esas tres cosas es una regla del dominio (`CheckoutOrchestrator`,
 * `DECISIONES #37`). Partirlo en dos llamadas desde el cliente sería reimplementar aquí esa secuencia,
 * que es justo lo que `CheckoutSequenceTest` prohíbe fuera de `app/Domain`.
 *
 * @param {{
 *   items: Array<object>,
 *   api: {post: (path: string, body: object) => Promise<object>},
 *   messages: object,
 * }} deps
 * @returns {Promise<{ok: boolean, orderCode: string, form: GatewayForm|null, error: string, rereadStatus: boolean}>}
 */
export async function runConfirm({ items, api, messages = {} }) {
    const response = await api.post('/orders', { items });

    if (! response.ok) {
        return { ok: false, orderCode: '', form: null, ...confirmError(response, messages) };
    }

    const form = gatewayForm(response.data?.payment);

    // El pedido EXISTE y retiene aforo aunque el formulario venga mal: no se puede fingir que no pasó
    // nada. Se avisa con el mismo aviso que el 502 del puerto —«no hemos podido iniciar el pago»—, que
    // es exactamente lo que ha ocurrido desde el punto de vista del cliente.
    if (form === null) {
        return {
            ok: false,
            orderCode: String(response.data?.order?.code ?? ''),
            form: null,
            error: t(messages, 'errors.payment_unavailable'),
            rereadStatus: false,
        };
    }

    return {
        ok: true,
        orderCode: String(response.data?.order?.code ?? ''),
        form,
        error: '',
        rereadStatus: false,
    };
}
