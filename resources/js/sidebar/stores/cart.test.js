import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useCartStore } from './cart.js';
import { STORAGE_KEY } from '../cart.js';

/**
 * La red del store de la CESTA.
 *
 * ⚠️ **El almacén se dobla, `cart.js` NO.** El saneado, la propiedad y la caducidad ya tienen sus
 * casos y su paridad; aquí se prueba el estado y —lo que más importa— **que las respuestas del evento
 * no lleguen NUNCA al almacén**, que es una obligación de RGPD y no una preferencia (`#38(d)`: son el
 * nombre de un menor, su edad y sus alergias, art. 9).
 */
function fakeStorage() {
    const datos = new Map();

    return {
        volcado: datos,
        getItem: (k) => (datos.has(k) ? datos.get(k) : null),
        setItem: (k, v) => datos.set(k, String(v)),
        removeItem: (k) => datos.delete(k),
    };
}

function store() {
    setActivePinia(createPinia());

    return useCartStore();
}

const LINEA = (extra = {}) => ({
    product_id: 3, date: '2026-09-28', time: '10:00', quantity: 2, addons: [], event_data: {}, ...extra,
});

describe('el store de la cesta', () => {
    beforeEach(() => {
        globalThis.window = { localStorage: fakeStorage() };
    });

    test('arranca vacía, de invitado y sin presupuesto', () => {
        const c = store();

        assert.deepEqual(c.lines, []);
        assert.equal(c.owner, null);
        assert.equal(c.quote, null);
        assert.equal(c.count, 0);
        assert.equal(c.isEmpty, true);
    });

    test('el contador sale del PRESUPUESTO, no de las líneas', () => {
        const c = store();
        c.setLines([LINEA(), LINEA()]);

        assert.equal(c.count, 0, 'sin presupuesto no hay nada tarificado que contar');

        c.setQuote({ lines: [{ total_cents: 100 }] });
        assert.equal(c.count, 1, 'la verdad la dice el servidor, no lo que haya en memoria');
    });

    test('persistir y volver a leer devuelve la cesta', () => {
        const c = store();
        c.setLines([LINEA()]);
        c.persist();

        const otra = store();
        const { lines } = otra.restore('2026-09-01');

        assert.equal(lines.length, 1);
        assert.equal(lines[0].product_id, 3);
    });

    /**
     * ⚠️⚠️ **La cesta del PROPIO titular se PURGABA cuando el cajón nacía abierto** (medido en headless
     * el 2026-08-27, `specs/menores-a-cargo.md` §9.9.6): se restauraba con el dueño a `null` porque
     * nadie lo sembraba desde el HTML, y `decideOwnership(N, null)` es la casilla del logout. La
     * secuencia correcta es sembrar ANTES de restaurar; este caso fija las tres casillas que dependen
     * de ese orden, y la última es literalmente lo que hacía el arranque nacido abierto.
     */
    test('sembrar el dueño ANTES de restaurar conserva la cesta del propio titular y purga la ajena', () => {
        const mia = store();
        mia.setOwner(457);
        mia.setLines([LINEA()]);
        mia.persist();

        const misma = store();
        misma.setOwner(457);
        assert.equal(misma.restore('2026-09-01').lines.length, 1, 'el mismo titular, sembrado antes de restaurar: se conserva');

        const otra = store();
        otra.setOwner(99);
        assert.equal(otra.restore('2026-09-01').lines.length, 0, 'otro titular: se purga (la tablet compartida)');

        mia.persist();
        const sinSembrar = store();
        assert.equal(sinSembrar.restore('2026-09-01').lines.length, 0, 'sin sembrar el dueño —el logout, o el defecto— se purga');
        assert.equal(sinSembrar.restore('2026-09-01').lines.length, 0, 'y olvidada: no vuelve en la siguiente carga');
    });

    /**
     * Los menores asignados a una línea (Fase 6 · tanda 4): marcar y desmarcar persiste —son ids—, el
     * conjunto se acota por la cantidad, la lista viva quita lo que ya no vale, y el 422 del checkout
     * deja la línea rechazada sin asignar con su aviso.
     */
    test('asignar menores a una línea persiste los ids y respeta la cantidad', () => {
        const c = store();
        c.setLines([LINEA({ quantity: 2 }), LINEA({ product_id: 4, quantity: 1 })]);

        c.assign(0, 12);
        c.assign(0, 15);
        c.assign(0, 18);
        c.assign(1, 12);
        c.assign(9, 12);

        assert.deepEqual(c.lines.map((l) => l.dependent_ids), [[12, 15], [12]], 'acotado por la cantidad; un índice que no existe no hace nada');
        assert.deepEqual(store().restore('2026-09-01').lines.map((l) => l.dependent_ids), [[12, 15], [12]], 'los ids sí se guardan');

        c.assign(0, 12);
        assert.deepEqual(c.lines[0].dependent_ids, [15], 'desmarcar quita');
    });

    test('la lista viva quita de la cesta lo que ya no se puede asignar, y persiste', () => {
        const c = store();
        c.setLines([LINEA({ dependent_ids: [12, 15] })]);
        c.persist();

        assert.equal(c.dropUnassignable([15]), true);
        assert.deepEqual(c.lines[0].dependent_ids, [15]);
        assert.deepEqual(store().restore('2026-09-01').lines[0].dependent_ids, [15]);
        assert.equal(c.dropUnassignable([15]), false, 'sin nada que quitar no cambia nada');
    });

    test('el 422 de la asignación deja la línea sin asignar y enseña el aviso del servidor', () => {
        const c = store();
        c.setLines([LINEA({ dependent_ids: [12] }), LINEA({ product_id: 4, dependent_ids: [15] })]);

        const changed = c.applyAssignmentRejections({ 'items.1.dependent_ids.0': ['Falta su descargo firmado.'] });

        assert.equal(changed, true);
        assert.deepEqual(c.lines.map((l) => l.dependent_ids), [[12], []]);
        assert.equal(c.error, 'Falta su descargo firmado.');
        assert.equal(c.applyAssignmentRejections(undefined), false);
    });

    test('el aviso de la puerta 2 se enciende con el veredicto y se apaga al asignar', () => {
        const c = store();
        c.setLines([LINEA()]);
        c.setNotice('assign');

        assert.equal(c.notice, 'assign');
        c.assign(0, 12);
        assert.equal(c.notice, '');
    });

    /**
     * ⚠️⚠️ EL CASO QUE NO PUEDE FALTAR. Las respuestas del evento son datos del art. 9 y **no salen
     * de memoria**. Se busca un centinela en el volcado ENTERO del almacén, no en el campo esperado:
     * si algún día se persistieran por otra vía, el caso muerde igual.
     */
    test('las respuestas del evento NO llegan al almacén', () => {
        const c = store();
        c.setLines([LINEA()]);
        c.updateField(0, 'nombre', 'CENTINELA-MENOR');
        c.updateField(0, 'alergias', 'CENTINELA-SALUD');
        c.persist();

        assert.equal(c.lines[0].event_data.nombre, 'CENTINELA-MENOR', 'en memoria sí están');

        const volcado = [...globalThis.window.localStorage.volcado.entries()].map(([k, v]) => k + v).join('|');

        assert.ok(volcado.includes(STORAGE_KEY) || volcado.length > 0, 'algo se guardó');
        assert.ok(! volcado.includes('CENTINELA-MENOR'), 'el nombre de un menor no puede quedar en el navegador');
        assert.ok(! volcado.includes('CENTINELA-SALUD'), 'ni un dato de salud (art. 9)');
    });

    test('contestar un campo BORRA el aviso y no toca el resto', () => {
        const c = store();
        c.setLines([LINEA()]);
        c.setError('Falta algo');

        c.updateField(0, 'nombre', 'Ana');

        assert.equal(c.error, '');
        assert.equal(c.lines[0].event_data.nombre, 'Ana');

        c.updateField(99, 'nombre', 'X');
        assert.equal(c.lines.length, 1, 'un índice que no existe no crea líneas');
    });

    /** El store dice QUÉ pasó; no navega. Volver al catálogo es del embudo. */
    test('quitar la última línea avisa de que la cesta quedó vacía', () => {
        const c = store();
        c.setLines([LINEA(), LINEA({ product_id: 4 })]);
        c.setQuote({ lines: [{}, {}] });

        assert.equal(c.remove(0), false, 'todavía queda una');
        assert.equal(c.lines.length, 1);
        assert.notEqual(c.quote, null);

        assert.equal(c.remove(0), true, 'y esta sí la vacía');
        assert.equal(c.quote, null, 'sin líneas, el presupuesto anterior ya no significa nada');
    });

    /**
     * ⚠️ **Reconciliar y volver a presupuestar es UNA operación.** Si el servidor poda una línea, los
     * índices se desplazan; pintar el presupuesto viejo sobre la cesta podada emparejaría las
     * respuestas de OTRA línea y dejaría el botón de quitar mudo. Por eso la acción se llama sola.
     */
    test('si el servidor poda una línea, se vuelve a presupuestar sobre la cesta ya podada', async () => {
        const c = store();
        c.setLines([LINEA(), LINEA({ product_id: 4 })]);

        // ⚠️ El presupuesto empareja por `index` y una línea sin `unit_price_cents` está PODADA: es
        // la forma real que compone el servidor, no una inventada (`cart.js::reconcile()`).
        let vuelta = 0;
        const api = {
            post: async () => {
                vuelta += 1;

                // La primera vez el servidor solo tarifica la línea 0: la 1 viene podada.
                return { ok: true, status: 200, data: { lines: [{ index: 0, unit_price_cents: 1590, addons: [] }] } };
            },
        };

        await c.refreshQuote({ api });

        assert.equal(vuelta, 2, 'tras podar hay que volver a preguntar: el presupuesto viejo ya no vale');
        assert.equal(c.lines.length, 1, 'y la cesta se queda con lo que el servidor admitió');
        assert.equal(c.count, 1);
    });

    test('con la cesta vacía no se pregunta nada y el presupuesto se olvida', async () => {
        const c = store();
        c.setQuote({ lines: [{}] });

        let llamadas = 0;
        await c.refreshQuote({ api: { post: async () => { llamadas += 1; return { ok: true, data: {} }; } } });

        assert.equal(llamadas, 0, 'preguntar por una cesta vacía es una petición regalada');
        assert.equal(c.quote, null);
    });

    /**
     * ⚠️ **Vaciar y OLVIDAR no son lo mismo, y confundirlos cuesta una compra doble.** Al crear la
     * reserva hay que vaciar y **persistir vacío**: si solo se vaciara en memoria, una recarga
     * resucitaría la cesta guardada y el cliente compraría dos veces lo mismo.
     */
    test('vaciar no borra lo guardado: son dos operaciones', () => {
        const c = store();
        c.setLines([LINEA()]);
        c.persist();
        c.setQuote({ lines: [{}] });
        c.setError('algo');

        c.empty();

        assert.deepEqual(c.lines, []);
        assert.equal(c.quote, null);
        assert.equal(c.error, '');
        assert.equal(c.restore('2026-09-01').lines.length, 1, 'lo GUARDADO sigue ahí: vaciar no olvida');

        c.forget();
        assert.equal(c.restore('2026-09-01').lines.length, 0, 'y olvidar sí');
    });

    /**
     * ⚠️⚠️ **La candidata NO va dentro de `items`, y confundirlo devuelve un tope MENOR del real.**
     * `items` es lo que YA retiene cupo; meter ahí la línea que se está validando la haría competir
     * consigo misma. Es un fallo de AFORO, no de presentación.
     */
    test('validar una línea manda la cesta en `items` y la candidata FUERA', async () => {
        const c = store();
        c.setLines([LINEA(), LINEA({ product_id: 4 })]);

        let cuerpo = null;
        const api = { post: async (url, body) => { cuerpo = { url, body }; return { ok: true, data: { valid: true } }; } };
        const candidata = LINEA({ product_id: 99 });

        await c.validateLine({ api, line: candidata });

        assert.equal(cuerpo.url, '/cart/validate-line');
        assert.equal(cuerpo.body.line.product_id, 99, 'la candidata va en `line`');
        assert.equal(cuerpo.body.items.length, 2, 'y en `items` va SOLO lo que ya retiene cupo');
        assert.ok(
            ! cuerpo.body.items.some((i) => i.product_id === 99),
            'la candidata dentro de `items` competiría consigo misma y daría un tope menor del real',
        );
    });

    test('el tope de líneas solo acepta números', () => {
        const c = store();

        c.setMaxLines('muchas');
        assert.equal(c.maxLines, 50, 'lo que no es número se ignora: el tope lo publica el servidor');
        c.setMaxLines(8);
        assert.equal(c.maxLines, 8);
    });

    test('aplicar los problemas de una línea SUMA los errores por campo y sustituye el aviso', () => {
        const c = useCartStore();
        c.setFieldErrors({ celebrant: 'Obligatorio' });
        c.setError('antes');

        c.applyLineProblems({ fieldErrors: { date: 'Sin fecha' }, error: 'Revisa la línea' });
        assert.deepEqual(c.fieldErrors, { celebrant: 'Obligatorio', date: 'Sin fecha' }, 'los de otros campos se conservan');
        assert.equal(c.error, 'Revisa la línea');

        c.applyLineProblems({});
        assert.equal(c.error, '', 'sin problemas, sin aviso');
    });
});
