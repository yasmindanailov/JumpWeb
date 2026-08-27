import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { ERROR_KEYS, confirmError, gatewayForm, runConfirm } from './pay.js';

/**
 * Fase 4 · paso 4.5·2 — la red del clic que crea el pedido (criterio CE-6).
 *
 * Que los textos coincidan con los del componente Livewire y que el mapa cubra el enum entero del
 * servidor lo compara `SidebarPayParityTest`. Lo que este fichero cubre es lo que aquél no puede: los
 * estados degradados, y sobre todo **el momento en que el pedido ya existe pero algo va mal** — que es
 * el único punto del cajón donde «no ha pasado nada» sería mentira.
 */

const MESSAGES = {
    errors: {
        try_later: 'Demasiados intentos seguidos. Espera un minuto antes de volver a intentarlo.',
        payment_unavailable: 'No hemos podido iniciar el pago.',
        sold_out_line: '«:product» del :when se ha agotado.',
        too_many_pending: 'Tienes :max reservas pendientes (el máximo).',
    },
};

const PAYMENT = {
    provider: 'redsys',
    method: 'POST',
    url: 'https://sis-t.redsys.es:25443/sis/realizarPago',
    fields: {
        Ds_SignatureVersion: 'HMAC_SHA256_V1',
        Ds_MerchantParameters: 'eyJEc19NZXJjaGFudF9BbW91bnQiOiIxOTgwIn0=',
        Ds_Signature: 'firma-que-no-se-puede-tocar',
    },
};

const ok = (data) => ({ ok: true, status: 201, data, error: null, offline: false });
const fail = (status, error) => ({ ok: false, status, data: { error }, error, offline: false });
const offline = () => ({ ok: false, status: 0, data: null, error: null, offline: true });

/**
 * El 422 de la ASIGNACIÓN de menores (Fase 6 · tanda 4, D3): el servidor lo comprueba ANTES del dinero
 * y responde por campo con el mensaje ya traducido. Se enseña ese mensaje —no el genérico— y se devuelven
 * los campos para que el store deje esas líneas sin asignar.
 */
describe('el 422 de la asignación de menores', () => {
    test('el mensaje del servidor se enseña tal cual y los campos viajan con el veredicto', async () => {
        const fields = { 'items.0.dependent_ids.0': ['Ese menor no está en tu cuenta.'] };
        const api = { post: async () => fail(422, { code: 'validation_failed', message: 'Revisa los datos.', fields }) };

        const result = await runConfirm({ items: [], api, messages: MESSAGES });

        assert.equal(result.ok, false);
        assert.equal(result.error, 'Ese menor no está en tu cuenta.');
        assert.deepEqual(result.fields, fields);
    });

    test('un 422 que no es de la asignación sigue cayendo al aviso genérico', () => {
        const verdict = confirmError(fail(422, { code: 'validation_failed', fields: { 'items.0.quantity': ['x'] } }), MESSAGES);

        assert.equal(verdict.error, MESSAGES.errors.try_later);
        assert.equal(verdict.fields, undefined);
    });
});

describe('el formulario de la pasarela', () => {
    /**
     * ⚠️ **Los campos se emiten TAL CUAL y en su orden.** La firma cubre esos valores exactos, así que
     * renombrar, reordenar o normalizar cualquiera es un SIS0042 — con el pedido ya creado y el aforo
     * retenido. Y el diff de árbol no puede verlo: los `name` no son atributos de contrato.
     */
    test('los campos firmados viajan intactos y en orden', () => {
        const form = gatewayForm(PAYMENT);

        assert.deepEqual(form.fields, [
            { name: 'Ds_SignatureVersion', value: 'HMAC_SHA256_V1' },
            { name: 'Ds_MerchantParameters', value: 'eyJEc19NZXJjaGFudF9BbW91bnQiOiIxOTgwIn0=' },
            { name: 'Ds_Signature', value: 'firma-que-no-se-puede-tocar' },
        ]);
        assert.equal(form.url, PAYMENT.url);
        assert.equal(form.method, 'POST');
    });

    /**
     * ⚠️ **El mapa es OPACO**: el cajón no conoce los nombres de Redsys ni debe. El día del segundo
     * driver de pasarela —`PaymentInitiation` es un puerto desde el cierre de Fase 3— llegarán otros y
     * esto tiene que seguir funcionando sin tocar una línea.
     */
    test('un driver con otros campos funciona igual', () => {
        const form = gatewayForm({ url: 'https://otra.pasarela.test/pay', method: 'POST', fields: { token: 'abc', ref: '42' } });

        assert.deepEqual(form.fields, [
            { name: 'token', value: 'abc' },
            { name: 'ref', value: '42' },
        ]);
    });

    /** El verbo lo publica el contrato; si faltara, `POST` es el único que tiene sentido aquí. */
    test('sin método se asume POST', () => {
        assert.equal(gatewayForm({ ...PAYMENT, method: '' }).method, 'POST');
        assert.equal(gatewayForm({ url: PAYMENT.url, fields: PAYMENT.fields }).method, 'POST');
    });

    /**
     * ⚠️ **Un formulario a medias NO se pinta.** Sería un POST a ninguna parte con el cliente creyendo
     * que está pagando — y el pedido ya existe cuando esto llega.
     */
    test('sin destino o sin campos no hay formulario', () => {
        assert.equal(gatewayForm({ ...PAYMENT, url: '' }), null);
        assert.equal(gatewayForm({ ...PAYMENT, fields: {} }), null);
        assert.equal(gatewayForm({ ...PAYMENT, fields: null }), null);
        assert.equal(gatewayForm({ ...PAYMENT, fields: ['no', 'es', 'un', 'mapa'] }), null);
        assert.equal(gatewayForm(null), null);
        assert.equal(gatewayForm(undefined), null);
    });

    /** Un valor nulo se emite como cadena vacía, no como «null»: el `<input>` no puede llevar eso. */
    test('un valor ausente no se convierte en la palabra «null»', () => {
        const form = gatewayForm({ url: PAYMENT.url, method: 'POST', fields: { a: null, b: 0 } });

        assert.deepEqual(form.fields, [{ name: 'a', value: '' }, { name: 'b', value: '0' }]);
    });
});

describe('los «no» del checkout', () => {
    test('un rechazo de línea se pinta con sus parámetros interpolados', () => {
        const { error } = confirmError(
            fail(422, { code: 'line_sold_out', params: { product: 'Cumpleaños', when: '15 ago 10:00' } }),
            MESSAGES,
        );

        assert.equal(error, '«Cumpleaños» del 15 ago 10:00 se ha agotado.');
    });

    test('el tope de pendientes lleva su máximo', () => {
        const { error } = confirmError(fail(409, { code: 'too_many_pending_orders', params: { max: 5 } }), MESSAGES);

        assert.equal(error, 'Tienes 5 reservas pendientes (el máximo).');
    });

    /** El 502 del puerto de pasarela: el pedido ya lo soltó el dominio, el rastro está en `audit_logs`. */
    test('un fallo al abrir el cobro tiene su propio aviso', () => {
        assert.equal(confirmError(fail(502, { code: 'payment_unavailable' }), MESSAGES).error, MESSAGES.errors.payment_unavailable);
    });

    /**
     * ⚠️ **La pausa no compone mensaje: pide releer.** Es lo mismo que en el paso al pago (4.4a·1) y lo
     * que el contrato pedía tras un 409 `reservations_paused` — el último residual del aviso.
     */
    test('la pausa relee el estado en vez de escribir un aviso', () => {
        const verdict = confirmError(fail(409, { code: 'reservations_paused' }), MESSAGES);

        assert.equal(verdict.error, '');
        assert.equal(verdict.rereadStatus, true);
    });

    test('un código desconocido, un 5xx y un corte de red no dejan la pantalla muda', () => {
        assert.equal(confirmError(fail(409, { code: 'algo_nuevo' }), MESSAGES).error, MESSAGES.errors.try_later);
        assert.equal(confirmError(fail(500, null), MESSAGES).error, MESSAGES.errors.try_later);
        assert.equal(confirmError(offline(), MESSAGES).error, MESSAGES.errors.try_later);
    });

    test('el mapa cubre los doce motivos del checkout', () => {
        for (const code of [
            'cart_empty', 'cart_too_large', 'product_unavailable', 'line_unavailable', 'line_past_date',
            'line_too_late', 'line_too_soon', 'line_outside_window', 'line_sold_out', 'line_pack_sold_out',
            'line_pack_guests_range', 'line_event_required',
        ]) {
            assert.ok(ERROR_KEYS[code], `falta el mapa de «${code}»`);
        }
    });
});

describe('la secuencia de confirmar', () => {
    function apiDouble(response) {
        const sent = [];

        return { sent, post: async (path, body) => { sent.push({ path, body }); return response; } };
    }

    const ITEMS = [{ product_id: 1, date: '2026-09-01', time: '10:00:00', quantity: 2 }];

    /**
     * ⚠️ **UNA sola petición.** `POST /orders` admite, crea con su hold y abre el cobro **en ese
     * orden**, porque el orden es una regla del dominio (`CheckoutOrchestrator`). Partirlo en dos
     * llamadas desde el cliente sería reimplementar esa secuencia fuera de `app/Domain`.
     */
    test('crea el pedido con una sola llamada', async () => {
        const api = apiDouble(ok({ order: { code: 'R-ABC123' }, payment: PAYMENT }));

        const result = await runConfirm({ items: ITEMS, api, messages: MESSAGES });

        assert.equal(api.sent.length, 1);
        assert.equal(api.sent[0].path, '/orders');
        assert.deepEqual(api.sent[0].body, { items: ITEMS });
        assert.equal(result.ok, true);
        assert.equal(result.orderCode, 'R-ABC123');
        assert.equal(result.form.fields.length, 3);
    });

    test('un rechazo vuelve con su aviso y sin formulario', async () => {
        const api = apiDouble(fail(422, { code: 'line_sold_out', params: { product: 'X', when: 'Y' } }));

        const result = await runConfirm({ items: ITEMS, api, messages: MESSAGES });

        assert.equal(result.ok, false);
        assert.equal(result.form, null);
        assert.equal(result.error, '«X» del Y se ha agotado.');
    });

    /**
     * ⚠️ **EL caso que este módulo existe para no fallar.** Si el 201 llega pero el formulario viene
     * mal, **el pedido YA EXISTE y retiene aforo**: fingir que no ha pasado nada dejaría al cliente
     * creyendo que puede volver a intentarlo desde cero, con una plaza retenida a su nombre. Se avisa
     * con el mismo texto que el 502 del puerto —«no hemos podido iniciar el pago»— y se conserva el
     * código del pedido, que es lo único que permite recuperarlo.
     */
    test('si el pedido se crea pero el formulario viene mal, se dice y se guarda el código', async () => {
        const api = apiDouble(ok({ order: { code: 'R-HUERFANO' }, payment: { url: '', fields: {} } }));

        const result = await runConfirm({ items: ITEMS, api, messages: MESSAGES });

        assert.equal(result.ok, false, 'no se puede seguir a la pasarela');
        assert.equal(result.form, null);
        assert.equal(result.error, MESSAGES.errors.payment_unavailable);
        assert.equal(result.orderCode, 'R-HUERFANO', 'el código se conserva: el pedido existe');
    });

    test('la pausa pide releer también desde aquí', async () => {
        const api = apiDouble(fail(409, { code: 'reservations_paused' }));

        const result = await runConfirm({ items: ITEMS, api, messages: MESSAGES });

        assert.equal(result.ok, false);
        assert.equal(result.rereadStatus, true);
        assert.equal(result.error, '');
    });

    test('una respuesta sin código de pedido no lanza', async () => {
        const api = apiDouble(ok({ payment: PAYMENT }));

        const result = await runConfirm({ items: ITEMS, api, messages: MESSAGES });

        assert.equal(result.ok, true);
        assert.equal(result.orderCode, '');
    });
});
