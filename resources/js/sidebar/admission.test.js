import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { decideCheckout, runCheckout } from './admission.js';
import { STEPS } from './machine.js';

/**
 * Fase 4 · paso 4.4a·1 — la red del paso del carrito al pago (criterio CE-6).
 *
 * Aquí se fija la CONDUCTA ante cada respuesta posible del servidor; que coincida con la del
 * componente Livewire —el paso destino y el texto exacto del aviso, en los tres idiomas— lo compara
 * `SidebarAdmissionParityTest`. Lo que este fichero cubre y esa paridad no puede alcanzar son los
 * estados DEGRADADOS: la sesión que muere entre dos peticiones, el 429 del limitador de la API y el
 * corte de red — ninguno de los tres es un estado que el componente Livewire pueda tener.
 */

const MESSAGES = {
    errors: {
        cart_empty: 'Añade al menos una visita para continuar.',
        too_many_pending: 'Tienes :max reservas pendientes (el máximo).',
        try_later: 'Demasiados intentos seguidos. Espera un minuto antes de volver a intentarlo.',
        reservations_paused: 'Las reservas online están pausadas. Llámanos al :phone para reservar.',
    },
};

/** Una respuesta de `api.js`: la forma es fija pase lo que pase, incluido el fallo de red. */
const ok = (data) => ({ ok: true, status: 200, data, error: null, offline: false });
const fail = (status, data = null) => ({ ok: false, status, data, error: { code: 'x', message: '' }, offline: false });
const offline = () => ({ ok: false, status: 0, data: null, error: null, offline: true });

const decide = (overrides = {}) => decideCheckout({
    cartCount: 1,
    me: ok({ id: 7 }),
    eligibility: ok({ allowed: true, reason: null, max_pending_orders: null }),
    messages: MESSAGES,
    ...overrides,
});

describe('el camino feliz y sus dos puertas', () => {
    test('identificado y admitido → la pantalla de pago', () => {
        assert.deepEqual(decide(), { step: STEPS.PAY, error: '', rereadStatus: false });
    });

    test('invitado → la pantalla de identificación, sin aviso', () => {
        const verdict = decide({ me: fail(401), eligibility: fail(401) });

        assert.deepEqual(verdict, { step: STEPS.IDENTIFY, error: '', rereadStatus: false });
    });

    /**
     * ⚠️ Espejo de la primera guarda de `checkout()`. El pie no pinta el CTA sin cesta, así que es
     * defensiva — y por eso mismo se conserva: la web la tiene, y quien llame a esto desde otro sitio
     * mañana obtiene la misma respuesta que obtendría de Livewire.
     */
    test('cesta vacía → se queda en el carrito y lo dice', () => {
        const verdict = decide({ cartCount: 0 });

        assert.equal(verdict.step, STEPS.CART);
        assert.equal(verdict.error, MESSAGES.errors.cart_empty);
    });

    test('la cesta vacía se comprueba ANTES de preguntar nada', () => {
        // Con la sesión caída y sin cesta, lo que manda es la cesta: el orden de `checkout()`.
        const verdict = decideCheckout({ cartCount: 0, me: offline(), eligibility: offline(), messages: MESSAGES });

        assert.equal(verdict.error, MESSAGES.errors.cart_empty);
    });
});

describe('los tres motivos de rechazo', () => {
    /**
     * ⚠️ **La pausa no se enseña como error de carrito, y está MEDIDO**: Livewire escribe
     * `errors.reservations_paused` en su bag y el aviso de mantenimiento lo tapa antes de pintarse.
     * Lo que el cajón hace en su lugar es releer el estado, que es además lo que cierra el residual
     * de 4.3·3 (un cajón ya abierto no se enteraba del interruptor).
     */
    test('pausa → releer el estado, y NINGÚN mensaje', () => {
        const verdict = decide({ eligibility: ok({ allowed: false, reason: 'reservations_paused', max_pending_orders: null }) });

        assert.deepEqual(verdict, { step: STEPS.CART, error: '', rereadStatus: true });
    });

    test('el mensaje de pausa NO se pinta aunque el diccionario lo tenga', () => {
        const verdict = decide({ eligibility: ok({ allowed: false, reason: 'reservations_paused', max_pending_orders: null }) });

        assert.notEqual(verdict.error, MESSAGES.errors.reservations_paused);
        assert.equal(verdict.error, '');
    });

    /** El tope viaja en el sobre: el cliente no conoce el número ni lo quema. */
    test('tope de pendientes → el aviso con el máximo del servidor', () => {
        const verdict = decide({ eligibility: ok({ allowed: false, reason: 'too_many_pending_orders', max_pending_orders: 5 }) });

        assert.equal(verdict.step, STEPS.CART);
        assert.equal(verdict.error, 'Tienes 5 reservas pendientes (el máximo).');
        assert.equal(verdict.rereadStatus, false);
    });

    /**
     * ⚠️ **El código público es `too_many_pending_orders`, no `too_many_pending`.** Los dos nombres
     * existen a propósito —el segundo es la constante del dominio— y `AdmissionCodeMap` existe para
     * traducir entre ellos. Ramificar sobre el nombre interno cae al cajón de sastre en silencio.
     */
    test('el nombre INTERNO del dominio no vale como código público', () => {
        const verdict = decide({ eligibility: ok({ allowed: false, reason: 'too_many_pending', max_pending_orders: 5 }) });

        assert.equal(verdict.error, MESSAGES.errors.try_later, 'el código interno no debe acertar');
    });

    test('límite de frecuencia → el aviso genérico de esperar', () => {
        const verdict = decide({ eligibility: ok({ allowed: false, reason: 'too_many_requests', max_pending_orders: null }) });

        assert.equal(verdict.step, STEPS.CART);
        assert.equal(verdict.error, MESSAGES.errors.try_later);
    });

    /**
     * Un motivo NUEVO cae en el mismo sitio que cae en el servidor. `reportAdmissionDenial()` manda a
     * `try_later` todo lo que no sea pausa ni tope, así que esto no es tolerancia: es paridad.
     */
    test('un motivo desconocido cae donde cae en la web', () => {
        const verdict = decide({ eligibility: ok({ allowed: false, reason: 'algo_nuevo', max_pending_orders: null }) });

        assert.equal(verdict.error, MESSAGES.errors.try_later);
    });

    test('sin `max_pending_orders` el aviso no pinta «undefined»', () => {
        const verdict = decide({ eligibility: ok({ allowed: false, reason: 'too_many_pending_orders', max_pending_orders: null }) });

        assert.equal(verdict.error, 'Tienes 0 reservas pendientes (el máximo).');
    });
});

describe('lo que NO puede pasar: llevar a pagar sin saber', () => {
    /**
     * ⚠️ **`allowed` tiene que ser `true`, no «no falso».** Un cuerpo sin el campo —una respuesta
     * recortada por un proxy, un contrato mal leído— no autoriza a nadie.
     */
    test('un cuerpo sin `allowed` no autoriza', () => {
        assert.equal(decide({ eligibility: ok({}) }).step, STEPS.CART);
        assert.equal(decide({ eligibility: ok(null) }).step, STEPS.CART);
    });

    test('`allowed` con un valor que no es booleano tampoco', () => {
        assert.equal(decide({ eligibility: ok({ allowed: 'true' }) }).step, STEPS.CART);
        assert.equal(decide({ eligibility: ok({ allowed: 1 }) }).step, STEPS.CART);
    });

    /**
     * El 429 del limitador de la API (`SEC-06`): el cajón **no reintenta solo** ni suaviza el aviso.
     * No es un estado que Livewire pueda tener, así que solo lo cubre esta red.
     */
    test('un 429 de la API no lleva a pagar', () => {
        const verdict = decide({ eligibility: fail(429) });

        assert.equal(verdict.step, STEPS.CART);
        assert.equal(verdict.error, MESSAGES.errors.try_later);
    });

    test('un 503 tampoco', () => {
        assert.equal(decide({ eligibility: fail(503) }).step, STEPS.CART);
    });

    test('un corte de red tampoco', () => {
        const verdict = decide({ eligibility: offline() });

        assert.equal(verdict.step, STEPS.CART);
        assert.equal(verdict.error, MESSAGES.errors.try_later);
    });
});

describe('la identidad manda sobre el veredicto', () => {
    /**
     * ⚠️ **Un fallo de `GET /me` no es un logout, pero tampoco es una autorización.** Con la identidad
     * sin confirmar no se sigue: llevar a pagar con un veredicto que puede ser de otro titular es
     * exactamente lo que la defensa anti-cesta-cruzada existe para impedir.
     */
    test('sin saber quién eres no se va a pagar, aunque la elegibilidad diga que sí', () => {
        const verdict = decide({ me: offline() });

        assert.equal(verdict.step, STEPS.CART);
        assert.equal(verdict.error, MESSAGES.errors.try_later);
    });

    test('un 500 en la identidad tampoco deja pasar', () => {
        assert.equal(decide({ me: fail(500) }).step, STEPS.CART);
    });

    /**
     * La carrera real: `GET /me` respondió con sesión y la elegibilidad ya no la tiene (la sesión
     * expiró entre las dos, o alguien cerró sesión en otra pestaña). Manda el «no hay nadie».
     */
    test('si la sesión muere entre las dos peticiones, el destino es identificarse', () => {
        const verdict = decide({ me: ok({ id: 7 }), eligibility: fail(401) });

        assert.equal(verdict.step, STEPS.IDENTIFY);
        assert.equal(verdict.error, '');
    });

    test('un 401 en la identidad basta para mandar a identificarse', () => {
        // La elegibilidad ni siquiera se mira: quien manda es el guard.
        const verdict = decide({ me: fail(401), eligibility: ok({ allowed: true }) });

        assert.equal(verdict.step, STEPS.IDENTIFY);
    });
});

describe('la secuencia del clic', () => {
    /**
     * Un doble de `api` que APUNTA lo que se le pide y cuándo. Es lo que permite comprobar cosas que
     * ningún árbol enseña: a quién se preguntó, en qué orden y si las dos peticiones se solaparon.
     */
    function apiDouble(responses = {}) {
        const asked = [];
        let inFlight = 0;
        let maxInFlight = 0;

        return {
            asked,
            get maxInFlight() {
                return maxInFlight;
            },
            get: async (path) => {
                asked.push(path);
                inFlight++;
                maxInFlight = Math.max(maxInFlight, inFlight);

                // Un tick de espera: sin él las dos promesas se resolverían antes de que la segunda
                // llegue a lanzarse y el caso del paralelismo pasaría siendo secuencial.
                await new Promise((resolve) => setTimeout(resolve, 0));
                inFlight--;

                return responses[path] ?? ok({ id: 7 });
            },
        };
    }

    const identityKeeper = () => 'keep';
    const statusOk = async () => true;

    /** Un veredicto de pausa, que es el único que dispara la relectura del estado. */
    const paused = () => ({ '/me/reservation-eligibility': ok({ allowed: false, reason: 'reservations_paused' }) });

    test('se preguntan las DOS cosas, y solo esas', async () => {
        const api = apiDouble();

        await runCheckout({ cartCount: 1, api, messages: MESSAGES, applyIdentity: identityKeeper, refreshStatus: statusOk });

        assert.deepEqual(api.asked.sort(), ['/me', '/me/reservation-eligibility']);
    });

    /**
     * ⚠️ **En paralelo, no en cadena.** Es el clic más caro del embudo y ya arrastra el velo de carga;
     * encadenar las dos preguntas duplica su latencia sin ganar nada, porque ninguna depende de la otra.
     */
    test('las dos peticiones se solapan', async () => {
        const api = apiDouble();

        await runCheckout({ cartCount: 1, api, messages: MESSAGES, applyIdentity: identityKeeper, refreshStatus: statusOk });

        assert.equal(api.maxInFlight, 2, 'las dos preguntas tienen que viajar a la vez');
    });

    /** Sin cesta no se molesta al servidor: la guarda va antes que las peticiones. */
    test('con la cesta vacía no se pide nada', async () => {
        const api = apiDouble();

        const result = await runCheckout({ cartCount: 0, api, messages: MESSAGES, applyIdentity: identityKeeper, refreshStatus: statusOk });

        assert.deepEqual(api.asked, []);
        assert.equal(result.error, MESSAGES.errors.cart_empty);
        assert.equal(result.purged, false);
    });

    /**
     * ⚠️ **La identidad se aplica con la respuesta de `GET /me`, no con la de la elegibilidad.** Son
     * dos preguntas distintas y solo una publica el `id`; pasarle la otra dejaría la comparación de
     * titular mirando un objeto sin `id`, que es exactamente una purga que nunca ocurre.
     */
    test('la identidad se resuelve con la respuesta de /me', async () => {
        const seen = [];
        const api = apiDouble({
            '/me': ok({ id: 42 }),
            '/me/reservation-eligibility': ok({ allowed: true }),
        });

        await runCheckout({
            cartCount: 1,
            api,
            messages: MESSAGES,
            refreshStatus: statusOk,
            applyIdentity: (response) => {
                seen.push(response);

                return 'keep';
            },
        });

        assert.equal(seen.length, 1, 'se aplica una vez y solo una');
        assert.deepEqual(seen[0].data, { id: 42 });
    });

    /**
     * ⚠️ **Si el titular cambió, no hay checkout.** La purga vacía la cesta y devuelve el cajón al
     * catálogo: continuar con el veredicto llevaría a pagar una cesta que acaba de dejar de existir —y
     * que era de otra persona, que es el caso de la tablet compartida.
     */
    test('una purga corta la secuencia y no deja veredicto', async () => {
        const api = apiDouble({ '/me/reservation-eligibility': ok({ allowed: true }) });

        const result = await runCheckout({
            cartCount: 1, api, messages: MESSAGES, applyIdentity: () => 'purge', refreshStatus: statusOk,
        });

        assert.equal(result.purged, true);
        assert.equal(result.step, STEPS.CATALOG, 'la purga devuelve al catálogo, no lleva a pagar');
        assert.equal(result.error, '');
        assert.equal(result.rereadStatus, false);
    });

    test('sin purga, el veredicto es el que decide el módulo', async () => {
        const api = apiDouble(paused());

        const result = await runCheckout({ cartCount: 1, api, messages: MESSAGES, applyIdentity: identityKeeper, refreshStatus: statusOk });

        assert.equal(result.purged, false);
        assert.equal(result.rereadStatus, true);
        assert.equal(result.step, STEPS.CART);
    });

    test('el estado se relee cuando —y solo cuando— el veredicto es la pausa', async () => {
        let veces = 0;
        const cuenta = async () => {
            veces++;

            return true;
        };

        await runCheckout({ cartCount: 1, api: apiDouble(paused()), messages: MESSAGES, applyIdentity: identityKeeper, refreshStatus: cuenta });
        assert.equal(veces, 1, 'la pausa tiene que releer');

        await runCheckout({ cartCount: 1, api: apiDouble(), messages: MESSAGES, applyIdentity: identityKeeper, refreshStatus: cuenta });
        assert.equal(veces, 1, 'un cliente sin problemas no paga una petición de más');
    });

    /**
     * ⚠️ **Si la relectura falla, el clic NO puede quedarse mudo.** El veredicto de pausa no compone
     * mensaje porque el cartel de mantenimiento habla por él; si el estado que lo pinta no llega, el
     * botón no enseñaría nada —ni cartel, ni aviso, ni movimiento— y parecería roto. Se degrada al
     * aviso genérico, cuyo consejo (esperar y reintentar) también sirve para esto.
     */
    test('si el estado no se puede releer, el aviso genérico ocupa el sitio del cartel', async () => {
        const result = await runCheckout({
            cartCount: 1,
            api: apiDouble(paused()),
            messages: MESSAGES,
            applyIdentity: identityKeeper,
            refreshStatus: async () => false,
        });

        assert.equal(result.error, MESSAGES.errors.try_later);
        assert.equal(result.step, STEPS.CART);
        assert.equal(result.rereadStatus, true, 'se intentó releer, y eso se sigue diciendo');
    });

    test('si el estado SÍ se relee, no se pinta ningún aviso: habla el cartel', async () => {
        const result = await runCheckout({
            cartCount: 1, api: apiDouble(paused()), messages: MESSAGES, applyIdentity: identityKeeper, refreshStatus: statusOk,
        });

        assert.equal(result.error, '');
    });
});

describe('el diccionario', () => {
    /** Sin textos inyectados no se rompe: se pinta vacío, como hace `i18n.js` en todo el cajón. */
    test('un diccionario vacío no lanza', () => {
        const verdict = decideCheckout({ cartCount: 0, me: ok({ id: 1 }), eligibility: ok({ allowed: true }), messages: {} });

        assert.equal(verdict.error, '');
    });

    test('sin `messages` tampoco', () => {
        assert.doesNotThrow(() => decideCheckout({ cartCount: 0, me: ok({ id: 1 }), eligibility: ok({ allowed: true }) }));
    });
});
