/**
 * El DESENLACE del pago: lo que el cajón enseña al VOLVER de la pasarela (Fase 4 · paso 4.6·1).
 *
 * La costura ya estaba hecha desde 4.0a: `Http\Sidebar\SidebarEntry` es el dueño único de las tres
 * claves de sesión que dejó la vuelta de Redsys, el layout las CONSUME —con la SPA el motor es el
 * propio documento— y `machine.enterOutcome()` sabe en qué paso abrir. Lo que faltaba es **de dónde
 * sale lo que se pinta**, y no puede salir de la memoria del cajón: entre el clic de pagar y la
 * vuelta hubo una navegación completa a otro dominio, así que el estado del cliente **ya no existe**.
 * Lo único que sobrevive es el CÓDIGO del pedido, que viaja con el HTML.
 *
 * ⚠️ **Por eso este módulo no compone: TRADUCE.** El resumen de la reserva confirmada lo publica el
 * servidor entero desde Fase 4 · paso 4.0b —importes por reserva incluidos— y aquí solo se cambia de
 * vocabulario. Recalcular un total, decidir si procede la nota de señal o agregar `needs_guest_form`
 * de las líneas sería reimplementar reglas que ya tienen dueño (`PAY-12`, y la trampa concreta está
 * anotada en cada campo).
 *
 * ⚠️ **Son DOS peticiones y la segunda no es opcional**: las respuestas del pack (nombre del
 * homenajeado, edad, alergias) son datos de un MENOR y del art. 9, así que `GET orders/{code}` **no
 * las lleva** —hay test de ello— y viven en `GET orders/{code}/event-data`, que se pide aparte a
 * propósito. Un resumen sin ellas pintaría la reserva confirmada sin lo que el cliente contestó.
 */

/**
 * Las respuestas del pack, indexadas por el `id` de su reserva.
 *
 * ⚠️ **La llave es `reservation_id`, que es el `id` de la línea de `GET orders/{code}`** — el propio
 * contrato lo dice y es su única forma de emparejar: el endpoint no repite ni el nombre del producto
 * ni la fecha, justamente para no duplicar lo que el otro ya publica. Recorrer las dos listas en
 * paralelo pintaría las respuestas de una reserva sobre otra en cuanto una línea no apareciera, que
 * es el mismo fallo que el emparejado por `index` del carrito ya cerró.
 *
 * @param {{reservations?: Array<{reservation_id?: number, answers?: Array<object>}>}} eventData
 * @returns {Record<string, Array<{key: string, label: string, value: string}>>}
 */
export function answersByReservation(eventData) {
    const reservations = Array.isArray(eventData?.reservations) ? eventData.reservations : [];

    return reservations.reduce((map, reservation) => {
        map[String(reservation?.reservation_id)] = Array.isArray(reservation?.answers) ? reservation.answers : [];

        return map;
    }, {});
}

/**
 * Una línea del pedido traducida a la FILA que pinta el resumen.
 *
 * ⚠️ **La forma de destino es la del presupuesto, no una nueva**, y es la decisión que permite que la
 * pantalla de pagar y la de reserva creada compartan `SummaryLine.vue`: el marcado de las dos es el
 * mismo hasta el último nodo (medido contra el Blade), y tener dos copias de él sería exactamente lo
 * que el paso 4.0b·5 dejó por escrito que no se hace.
 *
 * ⚠️ **Los tres campos de dinero salen de la LÍNEA, no del pedido** (#225 F3): en una cesta mixta
 * entrada+pack el agregado del pedido no sirve para etiquetar una reserva, y `shows_deposit_note` son
 * TRES condiciones ya compuestas por el servidor —está pagado, el producto usa señal y queda algo en
 * puerta—. Recomponerlas aquí es como divergen las cuatro superficies que pintan este bloque.
 *
 * @param {object} item  una línea de `GET orders/{code}`
 * @param {Array<{key: string, label: string, value: string}>} answers
 */
export function confirmationLine(item, answers = []) {
    return {
        product_name: String(item?.product_name ?? ''),
        is_pack: item?.is_pack === true,
        quantity: Number(item?.quantity ?? 0),
        date: item?.date ?? null,
        // `start_time` y no `time_window`: el segundo es un texto YA compuesto para mostrar
        // («10:00–11:00») y el resumen enseña solo la hora de inicio, como el carrito.
        time: item?.start_time ?? null,
        subtotal_cents: Number(item?.charged_subtotal_cents ?? 0),
        has_deposit: item?.shows_deposit_note === true,
        deposit_cents: Number(item?.paid_online_cents ?? 0),
        gate_remainder_cents: Number(item?.gate_remainder_cents ?? 0),
        addons: (Array.isArray(item?.addons) ? item.addons : []).map((addon) => ({
            product_name: String(addon?.product_name ?? ''),
            quantity: Number(addon?.quantity ?? 0),
            free_quantity: Number(addon?.free_quantity ?? 0),
            subtotal_cents: Number(addon?.charged_subtotal_cents ?? 0),
        })),
        event: answers,
    };
}

/**
 * El pedido y sus respuestas → el view-model del paso 6.
 *
 * ⚠️ **`has_guest_form` sale de `guest_form_pending` del PEDIDO y no del `any()` de las líneas**, y el
 * contrato avisa de por qué se llaman distinto: el servidor descarta antes las líneas CANCELADAS, así
 * que agregarlas aquí prometería un formulario que nadie va a pedir.
 *
 * @param {object} order  `GET orders/{code}`
 * @param {object} eventData  `GET orders/{code}/event-data`
 */
export function buildConfirmation(order, eventData = {}) {
    const answers = answersByReservation(eventData);
    const items = Array.isArray(order?.items) ? order.items : [];

    return {
        code: String(order?.code ?? ''),
        status: String(order?.status ?? ''),
        total_cents: Number(order?.total_cents ?? 0),
        online_cents: Number(order?.online_amount_cents ?? 0),
        // Lo que queda por cobrar EN PUERTA. **No se resta de nada**: el servidor lo publica compuesto
        // y `total − online` no es lo mismo (hay ajustes que no viven en ninguno de los dos).
        park_cents: Number(order?.pending_at_gate_cents ?? 0),
        has_guest_form: order?.guest_form_pending === true,
        lines: items.map((item) => confirmationLine(item, answers[String(item?.id)] ?? [])),
    };
}

/**
 * Pide lo que el paso 6 necesita y lo devuelve listo para pintar, o `null`.
 *
 * ⚠️ **Un `null` aquí NO es un error que haya que gritar**, y por eso no compone ningún aviso: la
 * pantalla de reserva creada sigue teniendo sentido sin el resumen —el Blade la pinta igual con
 * `$confirmation` a null, con su código de pedido y su «te hemos enviado un correo»—. El caso real es
 * un 404 por sesión perdida entre la ida a la pasarela y la vuelta; enseñar «ha fallado algo» a quien
 * acaba de pagar sería mucho peor que enseñar el código de su pedido.
 *
 * ⚠️ **Las dos peticiones van en PARALELO.** Son independientes y esta es la pantalla en la que el
 * cliente acaba de pagar: encadenarlas duplicaría la espera sin comprar nada.
 *
 * @param {{orderCode: string, api: {get: (path: string) => Promise<object>}}} deps
 */
export async function loadConfirmation({ orderCode, api }) {
    const code = String(orderCode ?? '');

    if (code === '') {
        return null;
    }

    const path = `/orders/${encodeURIComponent(code)}`;
    const [order, eventData] = await Promise.all([api.get(path), api.get(`${path}/event-data`)]);

    if (! order.ok) {
        return null;
    }

    // Las respuestas del pack sí pueden faltar sin que el resumen deje de valer: se pintan bajo cada
    // línea y su ausencia es un bloque menos, no una pantalla rota.
    return buildConfirmation(order.data, eventData.ok ? eventData.data : {});
}
