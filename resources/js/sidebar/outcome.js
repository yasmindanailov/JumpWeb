/**
 * El DESENLACE del pago: lo que el cajón enseña al VOLVER de la pasarela (Fase 4 · paso 4.6).
 *
 * Los TRES desenlaces viven aquí porque son la misma pregunta con tres respuestas —«¿en qué quedó mi
 * pedido?»— y comparten la única pista que sobrevive al viaje, el código: el paso 6 (confirmado) pide
 * el resumen entero, el 10 (denegado) y el 11 (verificando) sondean `payment-status`, y el reintento
 * del 10 vuelve a salir por el mismo formulario firmado que compone `pay.js`.
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

import { t } from './i18n.js';
import { gatewayForm } from './pay.js';

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
 * Los menores a cargo para los que es cada reserva, indexados igual que las respuestas (Fase 6 ·
 * tanda 4, `menores-a-cargo.md` §9.9.3 D7): `event-data` los sirve por la misma llave y por la misma
 * razón — es el nombre de un menor, y no viaja en el pedido.
 *
 * @param {{reservations?: Array<{reservation_id?: number, dependents?: Array<{id: number, name: string}>}>}} eventData
 * @returns {Record<string, Array<{id: number, name: string}>>}
 */
export function dependentsByReservation(eventData) {
    const reservations = Array.isArray(eventData?.reservations) ? eventData.reservations : [];

    return reservations.reduce((map, reservation) => {
        map[String(reservation?.reservation_id)] = Array.isArray(reservation?.dependents) ? reservation.dependents : [];

        return map;
    }, {});
}

/**
 * Lo que un saldo del libro dice PARA una clase concreta, o `0` si es otra clase
 * (`specs/desglose-libro.md` §4.4). `pay_at_park` es lo que queda por pagar en el parque.
 *
 * ⚠️ Se mira la CLASE y no el signo: un saldo negativo «a devolver» también es un número, y leerlo
 * como «pendiente en el parque» diría lo contrario de lo que es.
 *
 * @param {{kind?: string, cents?: number}|null|undefined} balance
 * @param {string} kind
 */
function balanceCentsOf(balance, kind) {
    return balance?.kind === kind ? Number(balance.cents ?? 0) : 0;
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
 * TRES condiciones ya compuestas por el servidor —está cobrado, la reserva nació con señal y queda
 * algo en el parque—. Recomponerlas aquí es como divergen las cuatro superficies que pintan este bloque.
 *
 * ⚠️⚠️ **Los dos importes de la señal salen del LIBRO de la reserva** (T3·1 de
 * `specs/desglose-libro.md`): lo pagado (`ledger.paid_cents`) y lo que queda en el parque
 * (`ledger.balance` de clase `pay_at_park`). Hasta la T3·1 se leían de `paid_online_cents` y
 * `gate_remainder_cents` de la línea, **que la API no publicaba**: el aviso de señal de la reserva
 * recién creada decía «Señal 0,00 €» — medido en `outcome.test.js`, cuyo fixture inventaba los dos
 * campos y por eso no lo vio.
 *
 * @param {object} item  una línea de `GET orders/{code}`
 * @param {Array<{key: string, label: string, value: string}>} answers
 * @param {Array<{id: number, name: string}>} dependents  los menores para los que es (`event-data`)
 */
export function confirmationLine(item, answers = [], dependents = []) {
    return {
        product_name: String(item?.product_name ?? ''),
        is_pack: item?.is_pack === true,
        // ⚠️ El icono que marca el producto, tal cual lo manda el servidor (`DECISIONES #140`). No se
        // deriva de `is_pack`: derivarlo aquí sería la tercera copia de la regla que este trabajo
        // vino a retirar, y dejaría el resumen de la reserva creada pintando un ticket genérico
        // sobre un producto que sí eligió el suyo.
        icon: item?.icon ?? undefined,
        quantity: Number(item?.quantity ?? 0),
        date: item?.date ?? null,
        // `start_time` y no `time_window`: el segundo es un texto YA compuesto para mostrar
        // («10:00–11:00») y el resumen enseña solo la hora de inicio, como el carrito.
        time: item?.start_time ?? null,
        subtotal_cents: Number(item?.charged_subtotal_cents ?? 0),
        has_deposit: item?.shows_deposit_note === true,
        deposit_cents: Number(item?.ledger?.paid_cents ?? 0),
        gate_remainder_cents: balanceCentsOf(item?.ledger?.balance, 'pay_at_park'),
        addons: (Array.isArray(item?.addons) ? item.addons : []).map((addon) => ({
            product_name: String(addon?.product_name ?? ''),
            quantity: Number(addon?.quantity ?? 0),
            free_quantity: Number(addon?.free_quantity ?? 0),
            subtotal_cents: Number(addon?.charged_subtotal_cents ?? 0),
        })),
        event: answers,
        dependents: Array.isArray(dependents) ? dependents : [],
        // T3e·5 (`specs/isla-y-landing-nueva.md` §4.10): lo que «¡Fiesta reservada!» de la isla ofrece hacer después
        // —el formulario de invitados hasta su plazo y la invitación—, tal cual lo compone el SERVIDOR (URL y plazo).
        guest_form_url: typeof item?.guest_form_url === 'string' ? item.guest_form_url : null,
        guest_count_deadline: typeof item?.guest_count_deadline === 'string' ? item.guest_count_deadline : null,
        invitation_url: typeof item?.invitation_url === 'string' ? item.invitation_url : null,
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
    const dependents = dependentsByReservation(eventData);
    const items = Array.isArray(order?.items) ? order.items : [];

    return {
        code: String(order?.code ?? ''),
        status: String(order?.status ?? ''),
        // ⚠️ Del LIBRO, que es el único sitio donde vive el dinero (`DECISIONES #305`). Aquí se
        // enseña lo que el pedido VALE, no lo facturado: en el paso 6 los dos coinciden —el pedido
        // acaba de nacer— pero leer el campo correcto es lo que hace que siga siendo cierto cuando
        // el pedido cambie después.
        total_cents: Number(order?.ledger?.total_cents ?? 0),
        online_cents: Number(order?.online_amount_cents ?? 0),
        // Lo que queda por pagar EN EL PARQUE: el SALDO del libro cuando es de esa clase. **No se
        // resta de nada**: el servidor lo publica compuesto y `total − online` no es lo mismo.
        park_cents: balanceCentsOf(order?.ledger?.balance, 'pay_at_park'),
        has_guest_form: order?.guest_form_pending === true,
        lines: items.map((item) => confirmationLine(item, answers[String(item?.id)] ?? [], dependents[String(item?.id)] ?? [])),
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

// ── Los otros dos desenlaces: denegado (paso 10) y verificando (paso 11) ──────────────────────

/**
 * El motivo del rechazo, traducido.
 *
 * ⚠️ **`declined_reason` NO necesita tabla de traducción, y conviene saber por qué**: el servidor lo
 * compone con `RedsysResponseCode::reasonKey()`, que devuelve exactamente la clave bajo
 * `tickets.payment_failed.reasons.*` — el mismo literal que pinta el Blade, del mismo fichero de
 * `lang/`, y **ya está en el payload de montaje** porque el grupo `tickets` viaja entero. Escribir aquí
 * un mapa código→clave sería inventar una segunda fuente para un texto que ya llega resuelto.
 *
 * ⚠️ **Por eso la caída a `default` es obligatoria y no defensiva**: `i18n.js` devuelve cadena vacía
 * cuando la clave no existe, así que un motivo NUEVO en el servidor pintaría el rótulo «Motivo:» con
 * nada detrás. `SidebarOutcomeParityTest` recorre el mapa entero del servidor para que eso lo nombre un
 * test y no un cliente.
 *
 * @param {object} messages  el grupo `tickets`
 * @param {string|null} code  `declined_reason` del contrato
 */
export function declinedReasonText(messages, code) {
    const key = typeof code === 'string' && code !== '' ? code : 'default';
    const text = t(messages, `payment_failed.reasons.${key}`);

    return text === '' ? t(messages, 'payment_failed.reasons.default') : text;
}

/**
 * **HASTA QUÉ HORA se guarda la plaza**, para el paso 10 (`#563`).
 *
 * La pantalla del pago denegado decía «unos minutos» **teniendo el dato**: `expires_at` viaja en
 * `payment-status` desde que ese contrato existe, con su descripción escrita —«hasta cuándo se retiene
 * la plaza; es el margen que queda para reintentar»—. Decir la hora convierte una vaguedad en algo que
 * el cliente puede usar: sabe si le da tiempo a buscar otra tarjeta.
 *
 * ⚠️ **Se formatea en la hora del NAVEGADOR, y aquí eso es lo correcto**: `expires_at` es un instante
 * real y viaja con su offset (`toIso8601String`), así que lo que se pinta es la hora del reloj de quien
 * mira. No es el caso de las FRANJAS, que guardan hora de pared del parque y no se pueden parsear como
 * instantes (`#426`) — son dos cosas distintas y se tratan distinto.
 *
 * ⚠️ **Una fecha que no se puede leer devuelve cadena vacía, nunca «Invalid Date»**: la pantalla
 * decide con eso si promete una hora o se queda en la frase de siempre. Un desenlace de dinero no
 * puede enseñar basura donde va una promesa.
 *
 * @param {string|null|undefined} expiresAt  el `expires_at` del contrato, en ISO 8601
 * @param {string} locale
 * @returns {string}  la hora ya formateada, o `''` si no hay nada que prometer
 */
export function holdUntilLabel(expiresAt, locale = 'es') {
    if (typeof expiresAt !== 'string' || expiresAt === '') return '';

    const instante = new Date(expiresAt);

    if (Number.isNaN(instante.getTime())) return '';

    return new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(instante);
}

/**
 * Sondea el desenlace del pago.
 *
 * `GET orders/{code}/payment-status` es deliberadamente pequeño porque se pregunta EN BUCLE; para el
 * resumen entero está `GET orders/{code}`, que es lo que pide el paso 6.
 *
 * @param {{orderCode: string, api: {get: (path: string) => Promise<object>}}} deps
 * @returns {Promise<object|null>}  `null` si no se pudo preguntar
 */
export async function loadPaymentStatus({ orderCode, api }) {
    const code = String(orderCode ?? '');

    if (code === '') {
        return null;
    }

    const response = await api.get(`/orders/${encodeURIComponent(code)}/payment-status`);

    return response.ok ? response.data : null;
}

/**
 * Qué hacer con lo que devuelve el sondeo del paso 11.
 *
 * ⚠️ **Solo DOS salidas mueven el cajón, y la acotación es de Livewire**, no una simplificación:
 * `checkPaymentStatus()` mira `paid` y `expired` y en cualquier otro caso **no hace nada**, dejando que
 * el sondeo siga. Ampliarla —por ejemplo, saltar al paso 10 en cuanto el último intento falle— cambiaría
 * la conducta de una pantalla cuyo sentido es esperar a la notificación de la pasarela: un `failed` con
 * la notificación todavía en vuelo es exactamente el caso que este paso existe para no malinterpretar.
 *
 * ⚠️ **Un fallo al preguntar tampoco mueve nada.** Es el espejo del `if (! $order) return;` de Livewire:
 * un 401 pasajero, un 429 del limitador o un corte de red no son un desenlace, y tratarlos como tal
 * sacaría al cliente de la pantalla que le está diciendo la verdad.
 *
 * @param {object|null} status  la respuesta de `loadPaymentStatus()`
 * @returns {'confirmed'|'expired'|'wait'}
 */
export function pollVerdict(status) {
    if (status?.order_status === 'paid') return 'confirmed';
    if (status?.order_status === 'expired') return 'expired';

    return 'wait';
}

/**
 * Los códigos de error de `POST /orders/{code}/payment` → la clave del diccionario.
 *
 * Es el hermano de `ERROR_KEYS` de `pay.js` y son conjuntos DISTINTOS a propósito: crear un pedido y
 * reintentar su cobro no fallan por lo mismo. Aquí solo hay tres motivos y ninguno es de cesta —el
 * pedido ya existe y su contenido ya no se discute—.
 *
 * ⚠️ **`reservations_paused` no está, igual que en `pay.js`**: no compone mensaje, pide releer el estado.
 */
export const RETRY_ERROR_KEYS = {
    order_not_retryable: 'errors.retry_expired',
    too_many_requests: 'errors.try_later',
    payment_unavailable: 'errors.payment_unavailable',
};

/** El código de pausa, que no se traduce a un mensaje: se relee el estado. */
export const RESERVATIONS_PAUSED = 'reservations_paused';

/**
 * Reintenta el cobro de un pedido que sigue vivo. Espejo de `Purchase::retryPayment()`.
 *
 * ⚠️ **La respuesta es la MISMA que la de crear el pedido** (`OrderPayment`), así que el formulario lo
 * compone `gatewayForm()` de `pay.js` y no una copia: la firma cubre esos valores exactos, y dos sitios
 * emitiendo campos firmados es la forma más cara de divergir que tiene este cajón.
 *
 * ⚠️ **Y no se orquesta nada aquí**: extender la retención con el UPDATE atómico de `PAY-04` y solo
 * después reabrir el cobro es `ReservationCheckout::retry()`, en el servidor. El reintento **no** aplica
 * el tope de pedidos pendientes, porque no crea aforo nuevo.
 *
 * @param {{orderCode: string, api: {post: (path: string, body: object) => Promise<object>}, messages: object}} deps
 * @returns {Promise<{ok: boolean, form: object|null, error: string, rereadStatus: boolean, goTo: string|null}>}
 */
export async function runRetry({ orderCode, api, messages = {} }) {
    const code = String(orderCode ?? '');

    // Espejo del guardián de Livewire (`! $user || ! $this->orderCode`): sin código no hay pedido que
    // reintentar, y la salida es volver al catálogo.
    if (code === '') {
        return { ok: false, form: null, error: '', rereadStatus: false, goTo: 'catalog' };
    }

    const response = await api.post(`/orders/${encodeURIComponent(code)}/payment`, {});

    if (response.ok) {
        const form = gatewayForm(response.data?.payment);

        // El pedido sigue vivo con su hold recién extendido —al revés que al crear, aquí un fallo de
        // pasarela NO lo suelta—, así que esto se avisa y se queda donde está.
        return form === null
            ? { ok: false, form: null, error: t(messages, 'errors.payment_unavailable'), rereadStatus: false, goTo: null }
            : { ok: true, form, error: '', rereadStatus: false, goTo: null };
    }

    // ⚠️ La sesión se perdió entre la vuelta de la pasarela y el clic. Livewire lo mira ANTES de llamar
    // (`$this->step = $user ? 1 : 5`) porque tiene el guard delante; un cliente de API solo puede
    // enterarse por el 401, y la salida es la misma: la pantalla de identificación.
    if (response.status === 401) {
        return { ok: false, form: null, error: '', rereadStatus: false, goTo: 'identify' };
    }

    const failure = response.error?.code ?? null;

    if (failure === RESERVATIONS_PAUSED) {
        return { ok: false, form: null, error: '', rereadStatus: true, goTo: null };
    }

    return {
        ok: false,
        form: null,
        // Un código que este cajón no conoce, un 5xx o un corte de red caen en el genérico: el aviso
        // no puede quedarse vacío aunque hoy no se pinte en esta pantalla.
        error: t(messages, RETRY_ERROR_KEYS[failure] ?? 'errors.try_later'),
        rereadStatus: false,
        // ⚠️ **Solo `order_not_retryable` obliga a rehacer la reserva**: el hold cruzó y la plaza pudo
        // cederse. La pausa y el límite de frecuencia dejan al cliente donde está —su reserva sigue
        // viva—, y confundirlos le diría a quien pulsó dos veces que ha perdido la plaza.
        goTo: failure === 'order_not_retryable' ? 'catalog' : null,
    };
}
