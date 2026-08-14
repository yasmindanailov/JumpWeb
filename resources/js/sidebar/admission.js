/**
 * El paso del CARRITO al PAGO (Fase 4 · paso 4.4a·1).
 *
 * Es el momento en que una cesta deja de ser una lista y se convierte en la intención de crear un
 * pedido, y por eso el cajón hace aquí **dos preguntas al servidor y no una**:
 *  1. **¿quién eres?** (`GET /me`) — no para saludar: es la única ocasión en que el cajón puede
 *     detectar que la sesión cambió en OTRA pestaña. Con la cesta en `localStorage` (4.3·4) esa es la
 *     casilla que sostiene la defensa anti-cesta-cruzada (§4.6);
 *  2. **¿puedes reservar ahora?** (`GET /me/reservation-eligibility`) — el aviso TEMPRANO que la web
 *     da desde siempre al pasar del carrito a la identificación, para no llevar a la pantalla de pago
 *     a quien el servidor va a rechazar.
 *
 * ⚠️ **Aquí no se decide si se admite**: eso es `Booking\Contracts\ReservationAdmission`, y su
 * veredicto llega ya tomado con un código estable (`AdmissionCodeMap`). Lo de este módulo es lo que
 * ningún endpoint puede saber: **a qué pantalla se va y qué se enseña**, que es exactamente el
 * reparto de `Purchase::checkout()` + `reportAdmissionDenial()`.
 *
 * ⚠️ **La pausa NO se enseña como error de carrito, y eso está MEDIDO, no supuesto.** El componente
 * Livewire escribe el mensaje `errors.reservations_paused` en su bag… y **nunca se ve**: al volver al
 * paso 4 se cumple `showPausedNotice()` y el aviso de mantenimiento sustituye el flujo entero,
 * mensaje incluido. Un motor que pintara ese error donde la web pinta el cartel enseñaría un texto
 * que la web no enseña en ninguna instalación. Por eso el veredicto de pausa pide **releer el
 * estado**: es también lo que cierra el residual que 4.3·3 dejó declarado —un cajón ya ABIERTO
 * cuando se acciona el interruptor no se enteraba hasta cerrarlo y volver a abrirlo—.
 */

import { t, tp } from './i18n.js';
import { STEPS } from './machine.js';

/**
 * Los códigos de `reason` que publica `GET /me/reservation-eligibility`.
 *
 * Son los MISMOS que devolvería el sobre de error de `POST /orders` en esa situación (lo garantiza
 * `AdmissionCodeMap`), así que ramificar aquí y ramificar en el checkout es ramificar sobre lo mismo.
 * ⚠️ No se listan por completitud: `TOO_MANY_PENDING` es el único que necesita un dato del sobre
 * (`max_pending_orders`), y confundirlo con el nombre de la constante del dominio —`too_many_pending`,
 * sin `_orders`— es el error que `AdmissionCodeMap` existe para evitar.
 */
export const RESERVATIONS_PAUSED = 'reservations_paused';
export const TOO_MANY_PENDING = 'too_many_pending_orders';

/**
 * El veredicto del cajón ante un clic en «Ir a pagar».
 *
 * `step` es **a dónde iría la web**, y viaja aunque el paso todavía no esté transcrito: es lo que
 * `SidebarAdmissionParityTest` compara contra el `step` real del componente Livewire, y lo que hace
 * que enchufar los pasos 5 y 8 sea cablear y no volver a decidir.
 *
 * @typedef {{step: number, error: string, rereadStatus: boolean}} CheckoutVerdict
 */

/**
 * Qué hacer al pulsar «Ir a pagar».
 *
 * Recibe los resultados de la API **ya obtenidos** —no los pide— para que la decisión entera se pueda
 * probar en Node sin red ni navegador, que es la razón de ser de los módulos planos del cajón (CE-6).
 *
 * @param {{
 *   cartCount: number,
 *   me: {ok: boolean, status: number, data: any},
 *   eligibility: {ok: boolean, status: number, data: any},
 *   messages: object,
 * }} state
 * @returns {CheckoutVerdict}
 */
export function decideCheckout({ cartCount, me, eligibility, messages = {} }) {
    // Espejo de la primera guarda de `checkout()`. El pie no pinta el CTA con la cesta vacía, así que
    // es defensiva en los dos motores — y se conserva por eso mismo: la web la tiene.
    if (! (cartCount > 0)) {
        return verdict(STEPS.CART, t(messages, 'errors.cart_empty'));
    }

    // ⚠️ **Un 401 es la ÚNICA respuesta que significa «no hay nadie».** Cualquier otro fallo deja la
    // identidad como estaba (`refreshIdentity()` aplica la misma regla, y por el mismo motivo: un
    // corte de red no es un cierre de sesión). Aquí, además, no se puede continuar sin saber quién
    // es: seguir con el veredicto de elegibilidad sería decidir sobre un titular sin confirmar.
    // ⚠️ El encadenamiento opcional NO es decoración: una respuesta que falta se trata como una que
    // falló, y el único destino seguro con la identidad sin resolver es quedarse donde se está.
    if (! me?.ok && me?.status !== 401) {
        return verdict(STEPS.CART, t(messages, 'errors.try_later'));
    }

    // Invitado → la pantalla de identificación, que es lo que hace `checkout()` cuando no hay guard.
    // El 401 de la elegibilidad cuenta igual: si la sesión murió entre las dos peticiones, el destino
    // es el mismo y quien manda es el «no hay nadie», no el orden en que llegaron las respuestas.
    if (! me.ok || eligibility?.status === 401) {
        return verdict(STEPS.IDENTIFY, '');
    }

    // ⚠️ El endpoint responde **200 aunque el veredicto sea que no**: la consulta salió bien y el
    // motivo viaja dentro. Un `!ok` aquí es un error de verdad (429 del limitador de la API, 5xx o
    // red), y ninguno de ellos autoriza a llevar a nadie a la pantalla de pago.
    if (! eligibility?.ok) {
        return verdict(STEPS.CART, t(messages, 'errors.try_later'));
    }

    if (eligibility.data?.allowed === true) {
        return verdict(STEPS.PAY, '');
    }

    return denial(eligibility.data ?? {}, messages);
}

/**
 * El «no» del dominio traducido a lo que el cajón enseña. Espejo de `reportAdmissionDenial()`.
 *
 * ⚠️ **El cajón de sastre no es un descuido, es la conducta del servidor**: el componente Livewire
 * manda a `try_later` todo lo que no sea pausa ni tope —hoy el límite de frecuencia, mañana un motivo
 * nuevo—, así que un código sin traducir sale igual en los dos motores en vez de dejar el aviso vacío.
 */
function denial(data, messages) {
    if (data.reason === RESERVATIONS_PAUSED) {
        // Sin mensaje **a propósito**: el cartel de mantenimiento sustituye el flujo entero y el
        // error del carrito no llega a pintarse en ninguno de los dos motores.
        return verdict(STEPS.CART, '', true);
    }

    if (data.reason === TOO_MANY_PENDING) {
        // El tope lo publica el sobre (`max_pending_orders`); el cliente no conoce el número ni debe
        // conocerlo — es el mismo dato que viaja en `error.params.max` del 409 de `POST /orders`.
        return verdict(STEPS.CART, tp(messages, 'errors.too_many_pending', { max: data.max_pending_orders ?? 0 }));
    }

    return verdict(STEPS.CART, t(messages, 'errors.try_later'));
}

/** @returns {CheckoutVerdict} */
function verdict(step, error, rereadStatus = false) {
    return { step, error, rereadStatus };
}

/**
 * La SECUENCIA del clic: preguntar, aplicar la identidad y decidir.
 *
 * Vive aquí y no en el componente **por CE-6**: la lógica que baja a un `.vue` pierde su red —los
 * componentes se comparan por su árbol, y un árbol no dice a quién se le preguntó ni en qué orden—.
 * Es literalmente el fallo que 4.3·1 pagó con la banda de progreso: módulo verde, cableado roto y el
 * gate sin verlo. Por eso `api` y `applyIdentity` entran **por parámetro**, igual que `cart.js` recibe
 * el almacén: así se pueden doblar y la secuencia entera se prueba en Node.
 *
 * ⚠️ **Las dos peticiones van en PARALELO y las dos hacen falta.** En serie, el clic más caro del
 * embudo pagaría dos viajes; y sin `GET /me` no hay forma de enterarse de que la sesión cambió en otra
 * pestaña — con la cesta en `localStorage`, ese es el único momento en que la defensa
 * anti-cesta-cruzada puede actuar antes de convertir la cesta en un pedido.
 *
 * @param {{
 *   cartCount: number,
 *   api: {get: (path: string) => Promise<object>},
 *   messages: object,
 *   applyIdentity: (response: object) => 'keep'|'purge',
 *   refreshStatus: () => Promise<boolean>,
 * }} deps
 * @returns {Promise<CheckoutVerdict & {purged: boolean}>}
 */
export async function runCheckout({ cartCount, api, messages = {}, applyIdentity, refreshStatus }) {
    // Sin cesta no se pregunta NADA: es la primera guarda de `checkout()` y ahorra dos peticiones por
    // un clic que el pie ni siquiera ofrece.
    if (! (cartCount > 0)) {
        return { ...decideCheckout({ cartCount, me: null, eligibility: null, messages }), purged: false };
    }

    const [me, eligibility] = await Promise.all([
        api.get('/me'),
        api.get('/me/reservation-eligibility'),
    ]);

    // ⚠️ **La identidad se aplica ANTES de mirar el veredicto.** Si el titular cambió, la cesta se
    // purga y el cajón vuelve al catálogo: ya no hay compra que continuar, y seguir sería llevar a
    // pagar una cesta que acaba de dejar de existir.
    if (applyIdentity(me) === 'purge') {
        return { step: STEPS.CATALOG, error: '', rereadStatus: false, purged: true };
    }

    return settle({ cartCount, me, eligibility, messages }, refreshStatus);
}

/**
 * Lo que pasa DESPUÉS de identificarse dentro del cajón (Fase 4 · paso 4.4a·2).
 *
 * Espejo de `Purchase::onAuthenticated()`, que llama a `proceed()`: el mismo camino que el clic de
 * pagar, menos la pregunta por la identidad — quien acaba de entrar ya la trajo. `POST auth/login`
 * devuelve el perfil con la misma forma que `GET /me` **justo para esto**, así que identificarse no
 * cuesta una petición de más.
 *
 * ⚠️ No se salta la elegibilidad: el aviso temprano vale igual para quien acaba de entrar, y en la web
 * lo da el mismo `proceed()`. Quien se identifique con el tope de pendientes lleno tiene que verlo aquí
 * y no en la pantalla de pago.
 *
 * @param {{
 *   cartCount: number,
 *   me: {ok: boolean, status: number, data: any},
 *   api: {get: (path: string) => Promise<object>},
 *   messages: object,
 *   refreshStatus: () => Promise<boolean>,
 * }} deps
 * @returns {Promise<CheckoutVerdict & {purged: boolean}>}
 */
export async function continueAfterIdentification({ cartCount, me, api, messages = {}, refreshStatus }) {
    const eligibility = await api.get('/me/reservation-eligibility');

    return settle({ cartCount, me, eligibility, messages }, refreshStatus);
}

/**
 * Decide y, si el veredicto es la pausa, relee el estado para que el cartel pueda aparecer.
 *
 * ⚠️ **La relectura es lo que HACE aparecer el cartel, así que si falla el clic se queda mudo.** El
 * veredicto de pausa no compone mensaje —el cartel habla por él—, de modo que un fallo aquí dejaría un
 * botón que no enseña nada: ni cartel, ni aviso, ni movimiento. Se degrada al aviso genérico, cuyo
 * consejo —esperar y reintentar— es correcto también para esto.
 */
async function settle(state, refreshStatus) {
    const decision = decideCheckout(state);

    if (! decision.rereadStatus) {
        return { ...decision, purged: false };
    }

    const reread = await refreshStatus();

    return { ...decision, error: reread ? '' : t(state.messages, 'errors.try_later'), purged: false };
}
