import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { addLine, allPendingAnswered, cartRows, decideOwnership, eventAnswers, hasPendingEventFields, load, pendingAnswers, pendingEventFields, reconcile, removeLine, sanitizeLine, save, toApiItems, toCheckoutItems, todayIso } from './cart.js';

/**
 * Fase 4 · paso 4.3·2 — la red de la cesta (criterio CE-6).
 *
 * Aquí se fija la CONDUCTA del módulo; que su resultado coincida con el del servidor lo comprueba
 * `SidebarCartParityTest`. Hacen falta los dos: esa paridad no puede llegar a los estados que solo
 * existen en el cliente —una fusión que el servidor señala, una cesta que se queda vacía al quitar—.
 */

const line = (overrides = {}) => ({
    product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2, event_data: {}, addons: [], ...overrides,
});

describe('añadir', () => {
    test('una línea nueva se añade al final con la cantidad EFECTIVA', () => {
        const cart = addLine([], line({ quantity: 5 }), { quantity: 3, merges_with_index: null });

        assert.equal(cart.length, 1);
        // ⚠️ Entra la cantidad del veredicto, no la pedida: el servidor recorta al cupo en silencio
        // desde siempre, y pintar la pedida enseña una reserva que no se tiene.
        assert.equal(cart[0].quantity, 3);
    });

    /**
     * ⚠️ La fusión la SEÑALA el servidor y solo ocurre entre entradas del mismo producto, día y hora
     * sin complementos. Reinventarla en el cliente crea una línea duplicada donde el servidor habría
     * sumado cantidades.
     */
    test('una línea que se funde SUMA su cantidad a la señalada, sin crecer la cesta', () => {
        const existing = [line({ quantity: 2 }), line({ product_id: 9, quantity: 1 })];
        const cart = addLine(existing, line({ quantity: 4 }), { quantity: 4, merges_with_index: 0 });

        assert.equal(cart.length, 2, 'la cesta no crece al fundir');
        assert.equal(cart[0].quantity, 6);
        assert.equal(cart[1].quantity, 1, 'las demás líneas no se tocan');
    });

    test('un índice de fusión que no existe no puede perder la línea', () => {
        const cart = addLine([line()], line({ product_id: 7 }), { quantity: 1, merges_with_index: 99 });

        assert.equal(cart.length, 2);
    });

    test('añadir no muta la cesta anterior', () => {
        const before = [line()];
        addLine(before, line({ product_id: 7 }), { quantity: 1, merges_with_index: null });

        assert.equal(before.length, 1);
    });

    /**
     * Los menores asignados (Fase 6 · tanda 4, `menores-a-cargo.md` §9.9.3 D8): al fundir se UNEN, al
     * recortar la cantidad se RECORTAN con ella. Nunca más menores que unidades, tampoco cuando es el
     * servidor quien decide cuántas entran.
     */
    test('los menores marcados entran con la línea, recortados a la cantidad EFECTIVA', () => {
        const cart = addLine([], line({ quantity: 3, dependent_ids: [12, 15, 18] }), { quantity: 2, merges_with_index: null });

        assert.deepEqual(cart[0].dependent_ids, [12, 15], 'el servidor recortó a 2: el tercero se queda fuera');
        assert.deepEqual(addLine([], line({ quantity: 2 }), { quantity: 2, merges_with_index: null })[0].dependent_ids, [], 'sin marcar, vacío y no ausente');
    });

    test('al fundir, los menores de las dos líneas se unen sin repetir, los existentes primero', () => {
        const existing = [line({ quantity: 2, dependent_ids: [12] })];
        const cart = addLine(existing, line({ quantity: 2, dependent_ids: [15, 12] }), { quantity: 2, merges_with_index: 0 });

        assert.equal(cart[0].quantity, 4);
        assert.deepEqual(cart[0].dependent_ids, [12, 15]);
    });
});

describe('lo que viaja al checkout', () => {
    /** `toApiItems()` alimenta tres endpoints PÚBLICOS: los ids de menores viajan SOLO a `POST /orders`. */
    test('toApiItems no lleva los menores; toCheckoutItems sí, y solo cuando hay', () => {
        const cart = [line({ dependent_ids: [12] }), line({ product_id: 7, dependent_ids: [] })];

        assert.equal('dependent_ids' in toApiItems(cart)[0], false);
        assert.deepEqual(toCheckoutItems(cart)[0].dependent_ids, [12]);
        assert.equal('dependent_ids' in toCheckoutItems(cart)[1], false, 'una línea sin menores no manda la clave');
        assert.deepEqual(toCheckoutItems(cart)[0].product_id, 1);
    });

    test('las filas del carrito llevan los ids y los NOMBRES que resuelve el mapa en memoria', () => {
        const rows = cartRows(
            [{ index: 0, product_id: 1, quantity: 2 }],
            [line({ dependent_ids: [12, 99] })],
            {},
            { 12: { id: 12, name: 'Lucas' } },
        );

        assert.deepEqual(rows[0].dependent_ids, [12, 99]);
        assert.deepEqual(rows[0].dependents, [{ id: 12, name: 'Lucas' }], 'un id sin nombre en el mapa no se pinta');
    });

    test('todayIso da el día del reloj que se le pasa, acolchado', () => {
        assert.equal(todayIso(new Date(2026, 8, 5)), '2026-09-05');
    });
});

describe('quitar', () => {
    test('quita la posición pedida y conserva el resto en orden', () => {
        const cart = [line({ product_id: 1 }), line({ product_id: 2 }), line({ product_id: 3 })];

        assert.deepEqual(removeLine(cart, 1).map((l) => l.product_id), [1, 3]);
    });

    test('quitar la última deja la cesta vacía', () => {
        assert.deepEqual(removeLine([line()], 0), []);
    });
});

describe('lo que viaja a la API', () => {
    /**
     * ⚠️ La hora se guarda CANÓNICA (`HH:MM:SS`). El dominio compara franjas por cadena, así que una
     * hora sin segundos no casa con ninguna y falla en silencio: presupuesto sin líneas, no error.
     */
    test('la línea viaja con el vocabulario de la API y la hora canónica', () => {
        const [item] = toApiItems([line()]);

        assert.deepEqual(item, { product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2 });
    });

    /**
     * ⚠️ **Las líneas RESTAURADAS llegan sin esas claves**, y hasta 4.3·4 ninguna prueba las tenía:
     * la fábrica de este fichero siempre ponía `event_data: {}` y `addons: []`, así que las dos
     * guardas de `toApiItems` no estaban probadas. Medido: quitar el `?? {}` y el `?.` dejaba la suite
     * JS **entera en verde** y el módulo lanzaba `TypeError` con la primera cesta restaurada.
     *
     * Y la forma tiene que ser EXACTAMENTE la misma en los tres casos, porque `event_data: null` da
     * 422 en el servidor: la regla es `['sometimes','array']`, y `sometimes` no implica `nullable`.
     */
    test('una línea sin esas claves, o con ellas a null, produce el mismo cuerpo de cuatro claves', () => {
        const esperado = { product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2 };

        assert.deepEqual(toApiItems([{ product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2 }]), [esperado]);
        assert.deepEqual(toApiItems([{ ...esperado, event_data: null, addons: null }]), [esperado]);
        assert.deepEqual(toApiItems([{ ...esperado, event_data: {}, addons: [] }]), [esperado]);
    });

    /** Las claves vacías no viajan: el contrato las declara opcionales y mandarlas vacías es ruido. */
    test('las respuestas del pack y los complementos solo viajan si los hay', () => {
        const [item] = toApiItems([line({
            event_data: { celebrant: 'Mara' },
            addons: [{ product_id: 4, quantity: 1 }],
        })]);

        assert.deepEqual(item.event_data, { celebrant: 'Mara' });
        assert.deepEqual(item.addons, [{ product_id: 4, quantity: 1 }]);
    });
});

describe('las filas que se pintan', () => {
    /**
     * ⚠️ **El emparejado va por `index`, no por posición.** Una línea cuyo producto ya no se vende no
     * se tarifica y desaparece del presupuesto; el hueco en la secuencia es la única señal de que
     * existió. Recorrer las dos listas en paralelo pinta las respuestas de una línea sobre otra.
     */
    test('cada fila toma las respuestas de SU línea aunque falten índices', () => {
        const cart = [
            line({ product_id: 1, event_data: { celebrant: 'Mara' } }),
            line({ product_id: 2 }),
            line({ product_id: 3, event_data: { celebrant: 'Leo' } }),
        ];
        const quoteLines = [
            { index: 0, product_id: 1 },
            { index: 2, product_id: 3 },
        ];
        const fields = { 1: [{ key: 'celebrant', label: 'Homenajeado' }], 3: [{ key: 'celebrant', label: 'Homenajeado' }] };

        const rows = cartRows(quoteLines, cart, fields);

        assert.equal(rows[0].event[0].value, 'Mara');
        assert.equal(rows[1].event[0].value, 'Leo', 'la segunda fila es el índice 2, no la posición 1');
    });
});

describe('respuestas del pack emparejadas con su etiqueta', () => {
    test('el orden lo pone el ESQUEMA, no las respuestas', () => {
        const fields = [{ key: 'a', label: 'A' }, { key: 'b', label: 'B' }];

        assert.deepEqual(
            eventAnswers(fields, { b: 'dos', a: 'uno' }),
            [{ key: 'a', label: 'A', value: 'uno' }, { key: 'b', label: 'B', value: 'dos' }]
        );
    });

    /** Una respuesta vacía no se pinta: es el mismo criterio que `TicketType::eventAnswers()`. */
    test('las respuestas vacías o ausentes no dan fila', () => {
        const fields = [{ key: 'a', label: 'A' }, { key: 'b', label: 'B' }, { key: 'c', label: 'C' }];

        assert.deepEqual(eventAnswers(fields, { a: '', c: null }), []);
        assert.deepEqual(eventAnswers(fields, {}), []);
    });

    /** Un cero SÍ es una respuesta: es `''` lo que significa «sin responder», no lo falso. */
    test('un cero es una respuesta', () => {
        assert.deepEqual(eventAnswers([{ key: 'age', label: 'Edad' }], { age: 0 }), [{ key: 'age', label: 'Edad', value: '0' }]);
    });

    /** Un valor que no es escalar tampoco: pintarlo daría «[object Object]». */
    test('un valor que no es escalar no se pinta', () => {
        assert.deepEqual(eventAnswers([{ key: 'a', label: 'A' }], { a: { x: 1 } }), []);
    });
});

// ── La PERSISTENCIA (Fase 4 · paso 4.3·4) ─────────────────────────────────────────────────────

/**
 * Un almacén doblado que GRABA todas las llamadas, no solo las de nuestra clave.
 *
 * Grabarlas todas es deliberado: el canario de más abajo tiene que poder cazar que alguien guarde las
 * respuestas del pack en una SEGUNDA clave «para no perder el trabajo del usuario».
 */
function fakeStorage(initial = {}) {
    const data = new Map(Object.entries(initial));
    const writes = [];

    return {
        getItem: (key) => (data.has(key) ? data.get(key) : null),
        setItem: (key, value) => { writes.push([key, value]); data.set(key, value); },
        removeItem: (key) => { data.delete(key); },
        dump: () => JSON.stringify([...data.entries()]) + JSON.stringify(writes),
        raw: (key) => (data.has(key) ? data.get(key) : null),
        has: (key) => data.has(key),
    };
}

/** El sobre tal y como lo escribiría una versión anterior o el propio módulo. */
const envelope = (lines, owner = null) => JSON.stringify({ v: 1, owner, lines });

describe('el saneador de líneas restauradas', () => {
    test('una línea buena sobrevive y sale con la hora canónica', () => {
        assert.deepEqual(
            sanitizeLine({ product_id: 1, date: '2026-09-05', time: '10:00', quantity: 2 }),
            { product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2, event_data: {}, addons: [], dependent_ids: [], guardian_authorization: false }
        );
    });

    /**
     * El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2).
     *
     * ⚠️ A diferencia de `dependent_ids`, un valor raro **no descarta la línea**: se trata como «no».
     * El criterio no es incoherente, es el mismo de siempre aplicado a lo que cada campo significa —
     * un id corrupto es una compra que no se sabe para quién, y esto es una pregunta de sí o no cuya
     * peor lectura es la de siempre—.
     */
    test('el justificante se restaura como booleano y nunca descarta la línea', () => {
        const base = { product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2 };

        assert.equal(sanitizeLine({ ...base, guardian_authorization: true }).guardian_authorization, true);

        for (const raro of [undefined, false, 'true', 1, null, {}, []]) {
            assert.equal(
                sanitizeLine({ ...base, guardian_authorization: raro }).guardian_authorization,
                false,
                `guardian_authorization ${JSON.stringify(raro)}`
            );
        }
    });

    /**
     * Los menores asignados (Fase 6 · tanda 4) se restauran como IDS y con el veredicto del servidor:
     * enteros ≥ 1 sin repetidos, o la línea se descarta entera — como una cantidad imposible, y por lo
     * mismo: en un almacén que el usuario puede editar, corregir en silencio es inventarse una compra.
     */
    test('los menores asignados se restauran como ids, y una lista corrupta descarta la línea', () => {
        const base = { product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2 };

        assert.deepEqual(sanitizeLine({ ...base, dependent_ids: [12, '7'] }).dependent_ids, [12, 7]);
        assert.deepEqual(sanitizeLine({ ...base, dependent_ids: [] }).dependent_ids, []);

        for (const bad of [['x'], [0], [-1], [2.5], [7, 7], 'siete', 7]) {
            assert.equal(sanitizeLine({ ...base, dependent_ids: bad }), null, `dependent_ids ${JSON.stringify(bad)}`);
        }
    });

    /**
     * ⚠️ **Se DESCARTA la línea, no se corrige.** `Cart::sanitize()` del servidor convierte `qty: 0`,
     * `-5` y `'abc'` en **1**; copiarlo aquí convertiría una cesta corrupta en una compra de una unidad
     * que nadie pidió, con su precio pintado. En un almacén que el usuario puede editar y que sobrevive
     * a los despliegues, eso es dinero.
     */
    test('una cantidad imposible descarta la línea en vez de corregirla', () => {
        for (const quantity of [0, -5, 'abc', 2.5, '3.5', null, true, undefined]) {
            assert.equal(
                sanitizeLine({ product_id: 1, date: '2026-09-05', time: '10:00:00', quantity }),
                null,
                `cantidad ${JSON.stringify(quantity)}`
            );
        }
    });

    /**
     * ⚠️ **Una expresión regular NO basta para la fecha**, y es la única divergencia que salió al pasar
     * un corpus por los dos lados: `/^\d{4}-\d{2}-\d{2}$/` acepta el 30 de febrero, que el servidor
     * rechaza porque reconstruye la fecha y la compara.
     */
    test('una fecha que no existe se descarta, aunque tenga la forma correcta', () => {
        assert.equal(sanitizeLine({ product_id: 1, date: '2026-02-30', time: '10:00:00', quantity: 1 }), null);
        assert.equal(sanitizeLine({ product_id: 1, date: '2026-13-01', time: '10:00:00', quantity: 1 }), null);
        assert.notEqual(sanitizeLine({ product_id: 1, date: '2028-02-29', time: '10:00:00', quantity: 1 }), null, '2028 es bisiesto');
    });

    test('una fecha sin acolchar o una hora imposible descartan la línea', () => {
        for (const date of ['', '2026-8-5', '2026-08-17T10:00', null, 20260905]) {
            assert.equal(sanitizeLine({ product_id: 1, date, time: '10:00:00', quantity: 1 }), null, `fecha ${date}`);
        }

        for (const time of ['1000', '25:00', '10:60', '10:00:00.000', '', null]) {
            assert.equal(sanitizeLine({ product_id: 1, date: '2026-09-05', time, quantity: 1 }), null, `hora ${time}`);
        }
    });

    /**
     * ⚠️ La regla `integer` de Laravel **no es estricta**: acepta la cadena `'3'` (medido, el servidor
     * la tarifica como 3). Descartarla borraría líneas que el servidor sí acepta.
     */
    test('las cadenas numéricas enteras se aceptan y se normalizan a número', () => {
        const line = sanitizeLine({ product_id: '7', date: '2026-09-05', time: '10:00:00', quantity: '3' });

        assert.equal(line.product_id, 7);
        assert.equal(line.quantity, 3);
        assert.equal(typeof line.quantity, 'number', 'con cadenas, sumar unidades concatenaría');
    });

    test('un complemento mal formado se descarta SOLO él; la línea sigue siendo comprable', () => {
        const line = sanitizeLine({
            product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 1,
            addons: [{ product_id: 4, quantity: 1 }, { product_id: 0, quantity: 1 }, { quantity: 2 }, null, 'basura'],
        });

        assert.deepEqual(line.addons, [{ product_id: 4, quantity: 1 }]);
    });

    /** Un `addons` que no es lista se trata como ausente, y no revienta al recorrerlo. */
    test('un `addons` que no es una lista no rompe la línea', () => {
        assert.deepEqual(sanitizeLine({ product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 1, addons: 'x' }).addons, []);
    });

    test('lo que no es un objeto no es una línea', () => {
        for (const raw of [null, 'x', 42, [], undefined]) {
            assert.equal(sanitizeLine(raw), null);
        }
    });
});

describe('de quién es la cesta guardada', () => {
    /**
     * **La tabla entera, y con los tipos mezclados.** `localStorage` solo guarda texto, así que el
     * dueño puede volver como cadena: un `70 !== '70'` purgaría la cesta de su propio dueño en cada
     * carga. El servidor castea los dos lados a propósito.
     */
    test('las cinco casillas', () => {
        assert.equal(decideOwnership(null, null), 'keep', 'anónimo que sigue anónimo');
        assert.equal(decideOwnership(null, 7), 'keep', 'EL FLUJO PRINCIPAL: cesta de invitado que se identifica');
        assert.equal(decideOwnership(7, 7), 'keep', 'mismo titular');
        assert.equal(decideOwnership(7, 9), 'purge', 'la tablet compartida');
        // ⚠️ La casilla que el servidor NO TIENE: en sesión, el logout vacía cesta y marcador a la vez.
        assert.equal(decideOwnership(7, null), 'purge', 'LOGOUT: la fuga que introduce localStorage');
    });

    test('el dueño se compara casteado, venga como número o como cadena', () => {
        assert.equal(decideOwnership(70, '70'), 'keep');
        assert.equal(decideOwnership('70', 70), 'keep');
        assert.equal(decideOwnership('70', '9'), 'purge');
    });

    /** Sin marcador es una cesta de invitado: se conserva pase lo que pase. */
    test('una cesta sin dueño nunca se purga', () => {
        assert.equal(decideOwnership(undefined, 7), 'keep');
        assert.equal(decideOwnership(null, 7), 'keep');
    });
});

describe('restaurar', () => {
    const HOY = '2026-09-05';

    test('una cesta guardada vuelve saneada', () => {
        const storage = fakeStorage({
            'jw.cart.v1': envelope([{ product_id: 1, date: '2026-09-06', time: '10:00', quantity: 2 }]),
        });

        const { lines, purged } = load(storage, { owner: null, today: HOY, maxLines: 50 });

        assert.equal(purged, false);
        assert.equal(lines.length, 1);
        assert.equal(lines[0].time, '10:00:00');
    });

    /**
     * ⚠️ La sesión caducaba a los 120 minutos; `localStorage` no caduca nunca. Y medido: el
     * presupuesto tarifica **con importes completos** una fecha de hace 19 meses, así que sin este
     * corte el cliente ve un total creíble y el rechazo le llega al pulsar pagar, ya identificado.
     */
    test('las líneas de días pasados se descartan al restaurar', () => {
        const storage = fakeStorage({
            'jw.cart.v1': envelope([
                { product_id: 1, date: '2026-09-04', time: '10:00:00', quantity: 1 },
                { product_id: 2, date: HOY, time: '10:00:00', quantity: 1 },
                { product_id: 3, date: '2026-09-06', time: '10:00:00', quantity: 1 },
            ]),
        });

        const { lines } = load(storage, { owner: null, today: HOY, maxLines: 50 });

        assert.deepEqual(lines.map((l) => l.product_id), [2, 3], 'hoy SÍ se conserva');
    });

    /** Con 51 líneas los TRES endpoints que reciben la cesta dan 422 sobre el array entero. */
    test('la cesta se recorta al tope de líneas', () => {
        const many = Array.from({ length: 60 }, (_, i) => ({ product_id: i + 1, date: '2026-09-06', time: '10:00:00', quantity: 1 }));
        const storage = fakeStorage({ 'jw.cart.v1': envelope(many) });

        assert.equal(load(storage, { owner: null, today: HOY, maxLines: 50 }).lines.length, 50);
    });

    test('la cesta de otro titular se purga y se BORRA del almacén', () => {
        const storage = fakeStorage({
            'jw.cart.v1': envelope([{ product_id: 1, date: '2026-09-06', time: '10:00:00', quantity: 1 }], 7),
        });

        const { lines, purged } = load(storage, { owner: 9, today: HOY, maxLines: 50 });

        assert.deepEqual(lines, []);
        assert.equal(purged, true);
        assert.equal(storage.has('jw.cart.v1'), false, 'no basta con no devolverla: hay que borrarla');
    });

    /**
     * ⚠️ `JSON.parse` no filtra nada: con la clave ausente devuelve `null` sin lanzar, con la clave
     * vacía LANZA, y basura estructuralmente válida pasa el parseo y revienta después. La forma se
     * valida a mano.
     */
    test('un almacén con basura no rompe el cajón', () => {
        for (const raw of ['', 'null', '42', '[]', '"hola"', '{"v":1}', '{"v":9,"lines":[]}', '{lines:[]}', '{"v":1,"lines":{}}']) {
            const storage = fakeStorage({ 'jw.cart.v1': raw });

            assert.deepEqual(load(storage, { owner: null, today: HOY, maxLines: 50 }).lines, [], `payload ${raw}`);
        }
    });

    test('un formato de otra versión se descarta entero', () => {
        const storage = fakeStorage({ 'jw.cart.v1': JSON.stringify({ v: 2, owner: null, lines: [{ product_id: 1 }] }) });

        assert.deepEqual(load(storage, { owner: null, today: HOY, maxLines: 50 }).lines, []);
    });

    /** Sin almacén —o con uno que lanza al leer— la cesta arranca vacía y el cajón funciona igual. */
    test('un almacén que lanza no tumba el cajón', () => {
        const roto = { getItem: () => { throw new Error('SecurityError'); }, setItem: () => {}, removeItem: () => {} };

        assert.deepEqual(load(roto, { owner: null, today: HOY, maxLines: 50 }).lines, []);
        assert.deepEqual(load(null, { owner: null, today: HOY, maxLines: 50 }).lines, []);
    });
});

describe('lo que una línea restaurada tiene que volver a pedir', () => {
    const ESQUEMA = [
        { key: 'celebrant', label: 'Nombre del homenajeado/a', required: true, type: 'text' },
        { key: 'age', label: 'Edad que cumple', required: false, type: 'number' },
        { key: 'notes', label: 'Notas (alergias…)', required: false, type: 'textarea' },
    ];

    /**
     * ⚠️ **El caso que da sentido al paso.** La cesta persistida vuelve SIN `event_data` (`#38(d)`), así
     * que una línea de pack restaurada está incompleta **por construcción** — y el presupuesto la
     * tarifica igual, con su total correcto. Sin esto, el fallo aparece al pagar.
     */
    test('una línea restaurada pide sus campos obligatorios', () => {
        assert.deepEqual(
            pendingEventFields(ESQUEMA, {}).map((f) => f.key),
            ['celebrant'],
        );
    });

    test('los campos OPCIONALES no se piden: la línea se compra sin ellos', () => {
        const pendientes = pendingEventFields(ESQUEMA, { celebrant: 'Mara' });

        assert.deepEqual(pendientes, []);
    });

    /**
     * ❗❗ **El borrador y la lista de pendientes tienen que decir lo MISMO** (`#560`).
     *
     * El bloque «faltan datos» se pinta mientras `pendingEventFields()` devuelva algo, y su botón de
     * guardar se activa con `allPendingAnswered()`. Si los dos no compartieran criterio de «vacío»,
     * el botón se ofrecería con un valor que la lista sigue considerando pendiente — y guardar no
     * cerraría el bloque, que es un botón que parece roto.
     */
    test('el borrador está completo exactamente cuando la lista se vaciaría', () => {
        const pendientes = pendingEventFields(ESQUEMA, {});

        for (const valor of ['', '   ', null, undefined]) {
            assert.equal(allPendingAnswered(pendientes, { celebrant: valor }), false, `«${valor}» no es una respuesta`);
            assert.equal(pendingEventFields(ESQUEMA, { celebrant: valor }).length, 1);
        }

        assert.equal(allPendingAnswered(pendientes, { celebrant: 'Mara' }), true);
        assert.equal(pendingEventFields(ESQUEMA, { celebrant: 'Mara' }).length, 0);
    });

    /**
     * ⚠️ **Se recorren los campos que se PIDEN, no las claves del borrador**: lo que quedara ahí de un
     * campo retirado del catálogo entre dos visitas no puede colarse en el pedido. Y el valor viaja
     * recortado, que es lo que hace que «  Mara  » y «Mara» sean la misma respuesta.
     */
    test('solo se emite lo que se pidió, y recortado', () => {
        assert.deepEqual(
            pendingAnswers(pendingEventFields(ESQUEMA, {}), { celebrant: '  Mara  ', fantasma: 'x' }),
            [{ key: 'celebrant', value: 'Mara' }],
        );
    });

    /** Una respuesta en blanco no cuenta como contestada, ni con espacios. */
    test('el blanco y los espacios no cuentan como respuesta', () => {
        assert.equal(pendingEventFields(ESQUEMA, { celebrant: '' }).length, 1);
        assert.equal(pendingEventFields(ESQUEMA, { celebrant: '   ' }).length, 1);
        assert.equal(pendingEventFields(ESQUEMA, { celebrant: null }).length, 1);
        assert.equal(pendingEventFields(ESQUEMA, { celebrant: {} }).length, 1);
    });

    /**
     * ⚠️ **Esto ENUMERA, no valida** (`#38(f)`). Una edad contestada «cinco» el SERVIDOR la ve vacía
     * —`sanitizeEventData()` aplica `preg_replace('/\\D+/','')` a los `number`—, y el cliente no puede
     * saberlo sin copiar esa regla, que es justo lo que aquella decisión prohibió. Aquí se fija la
     * frontera: hay algo escrito, así que no se pide; el «no» lo dará el servidor.
     */
    test('no reimplementa el saneo del servidor: solo mira si hay algo escrito', () => {
        const conTexto = pendingEventFields(
            [{ key: 'age', label: 'Edad', required: true, type: 'number' }],
            { age: 'cinco' },
        );

        assert.deepEqual(conTexto, [], 'el cliente lo ve contestado; quien decide si vale es el servidor');
    });

    test('un esquema vacío o ausente no pide nada ni lanza', () => {
        assert.deepEqual(pendingEventFields([], {}), []);
        assert.deepEqual(pendingEventFields(undefined, undefined), []);
    });

    test('el campo pedido lleva su etiqueta y su tipo, para poder pintarlo', () => {
        const [campo] = pendingEventFields(ESQUEMA, {});

        assert.equal(campo.label, 'Nombre del homenajeado/a');
        assert.equal(campo.type, 'text');
        assert.equal(campo.required, true);
    });

    /** La guarda del checkout mira las FILAS, que es lo que de verdad se pinta. */
    test('la cesta sabe si alguna de sus filas está incompleta', () => {
        assert.equal(hasPendingEventFields([{ pending: [] }, { pending: [{ key: 'celebrant' }] }]), true);
        assert.equal(hasPendingEventFields([{ pending: [] }, { pending: [] }]), false);
        assert.equal(hasPendingEventFields([]), false);
        assert.equal(hasPendingEventFields(undefined), false);
    });

    /** Y `cartRows` las compone: es de donde salen las filas que mira la guarda. */
    test('las filas del carrito traen lo que falta de cada línea', () => {
        const filas = cartRows(
            [{ index: 0, product_id: 7 }],
            [{ product_id: 7, event_data: {} }],
            { 7: ESQUEMA },
        );

        assert.deepEqual(filas[0].pending.map((f) => f.key), ['celebrant']);
    });
});

describe('guardar', () => {
    /**
     * ⚠️ **EL CANARIO DEL RGPD.** No basta con mirar la clave `event_data` de la primera línea: eso lo
     * pasarían en verde cuatro mutaciones distintas —guardarlas en una segunda clave, anidarlas dentro
     * de un complemento o de un `meta`, o dejarlas en un marcador de «línea incompleta»—. Aquí se
     * siembran centinelas únicos y se comprueba que NINGUNO aparece en el volcado ENTERO del almacén,
     * incluidas todas las escrituras de cualquier clave.
     *
     * El dato es art. 9: en la instalación sembrada son el nombre de un menor, su edad y sus alergias.
     * Y el cliente es la ÚNICA fuente, así que la fuga solo puede salir por aquí.
     */
    test('las respuestas del pack NO llegan al almacén, por ninguna vía', () => {
        const storage = fakeStorage();

        save(storage, {
            owner: 7,
            lines: [line({
                event_data: {
                    celebrant: '__CANARIO_NOMBRE__',
                    age: '__CANARIO_EDAD__',
                    notes: '__CANARIO_ALERGIA__',
                },
                // Y desde la tanda 4 de menores: si alguien colgara el NOMBRE del menor de la línea
                // (`dependents: [{id, name}]`, como lo sirve `event-data`), tampoco puede salir de aquí.
                dependents: [{ id: 12, name: '__CANARIO_MENOR__' }],
                dependent_name: '__CANARIO_MENOR_2__',
            })],
        });

        assert.equal(
            storage.dump().includes('__CANARIO_'),
            false,
            'una respuesta del pack —o el nombre de un menor— ha llegado al navegador: es dato personal de un menor y no puede persistirse'
        );
    });

    test('lo guardado conserva producto, día, hora, cantidad, complementos y los IDS de los menores', () => {
        const storage = fakeStorage();

        save(storage, { owner: 7, lines: [line({ addons: [{ product_id: 4, quantity: 2 }], dependent_ids: [12, 15] })] });

        const payload = JSON.parse(storage.raw('jw.cart.v1'));

        assert.equal(payload.owner, 7);
        assert.deepEqual(payload.lines[0], {
            product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2,
            addons: [{ product_id: 4, quantity: 2 }],
            dependent_ids: [12, 15],
            guardian_authorization: false,
        });
    });

    /**
     * El JUSTIFICANTE sobrevive a una recarga (`specs/waiver-por-reserva.md` §12.2).
     *
     * ⚠️ **Lo que guarda `save()` es una LISTA BLANCA**: una clave que no se nombre allí no se
     * escribe, y la casilla saldría desmarcada al volver **sin que nada falle**. El cliente no se
     * enteraría de que se le olvidó hasta la puerta del parque, que es el mismo daño que el defecto
     * del anti-bot de la T2.
     */
    test('el justificante marcado sobrevive al guardado', () => {
        const storage = fakeStorage();

        save(storage, { owner: 7, lines: [line({ guardian_authorization: true })] });

        assert.equal(JSON.parse(storage.raw('jw.cart.v1')).lines[0].guardian_authorization, true);
    });

    /**
     * ⚠️ Dos pestañas: si en otra se identificó otro titular, su login ya purgó el almacén. Escribir
     * la cesta de esta pestaña encima la RESUCITARÍA. Se relee antes de escribir.
     */
    test('no se pisa la cesta de otro titular: la purga de otra pestaña no se deshace', () => {
        const storage = fakeStorage({ 'jw.cart.v1': envelope([{ product_id: 1, date: '2026-09-06', time: '10:00:00', quantity: 1 }], 9) });

        assert.equal(save(storage, { owner: 7, lines: [line()] }), false);
        assert.equal(storage.has('jw.cart.v1'), false);
    });

    /** Un almacén lleno o bloqueado no puede tumbar la compra: la cesta sigue viva en memoria. */
    test('un almacén que lanza al escribir se degrada a memoria', () => {
        const roto = { getItem: () => null, setItem: () => { throw new Error('QuotaExceededError'); }, removeItem: () => {} };

        assert.equal(save(roto, { owner: null, lines: [line()] }), false);
        assert.equal(save(null, { owner: null, lines: [line()] }), false);
    });

    test('lo guardado se puede volver a leer tal cual', () => {
        const storage = fakeStorage();

        save(storage, { owner: 7, lines: [line({ addons: [{ product_id: 4, quantity: 2 }] })] });

        const { lines } = load(storage, { owner: 7, today: '2026-09-05', maxLines: 50 });

        assert.equal(lines.length, 1);
        assert.deepEqual(lines[0].event_data, {}, 'la línea vuelve sin respuestas: hay que volver a pedirlas');
        assert.deepEqual(lines[0].addons, [{ product_id: 4, quantity: 2 }]);
    });
});

describe('reconciliar con el presupuesto', () => {
    /** El hueco en la secuencia de `index` es la señal de que el producto dejó de venderse. */
    test('una línea que el presupuesto no devuelve se BORRA', () => {
        const cart = [line({ product_id: 1 }), line({ product_id: 2 }), line({ product_id: 3 })];
        const quote = [
            { index: 0, unit_price_cents: 990, addons: [] },
            { index: 2, unit_price_cents: 990, addons: [] },
        ];

        const { lines, changed } = reconcile(cart, quote);

        assert.deepEqual(lines.map((l) => l.product_id), [1, 3]);
        assert.equal(changed, true);
    });

    /**
     * ⚠️ **El hueco de `index` no cubre todas las líneas inservibles.** Una cuyo producto se vende
     * pero no tiene precio para la tarifa de ese día vuelve con `unit_price_cents: null`: cuenta en el
     * badge, suma 0 al total y el checkout la rechaza sin decir cuál es. Es el bug P8 por otra puerta.
     */
    test('una línea sin precio para ese día también se borra', () => {
        const cart = [line({ product_id: 1 }), line({ product_id: 2 })];
        const quote = [
            { index: 0, unit_price_cents: 990, addons: [] },
            { index: 1, unit_price_cents: null, addons: [] },
        ];

        assert.deepEqual(reconcile(cart, quote).lines.map((l) => l.product_id), [1]);
    });

    /**
     * ⚠️ El presupuesto atrapa el error del resolutor y tarifica la línea **sin ningún** complemento,
     * así que un complemento retirado hace que el total mienta a la baja mientras la cesta guardada lo
     * conserva — y el checkout revienta con un código sin contexto.
     */
    test('los complementos que el presupuesto no devuelve se quitan de la línea', () => {
        const cart = [line({ addons: [{ product_id: 4, quantity: 1 }] })];
        const quote = [{ index: 0, unit_price_cents: 990, addons: [] }];

        const { lines, changed } = reconcile(cart, quote);

        assert.deepEqual(lines[0].addons, []);
        assert.equal(changed, true);
    });

    test('una cesta que no ha perdido nada no se toca', () => {
        const cart = [line({ addons: [{ product_id: 4, quantity: 1 }] })];
        const quote = [{ index: 0, unit_price_cents: 990, addons: [{ product_id: 4 }] }];

        const { lines, changed } = reconcile(cart, quote);

        assert.equal(changed, false);
        assert.equal(lines[0], cart[0], 'la misma referencia: nada que reescribir');
    });
});
