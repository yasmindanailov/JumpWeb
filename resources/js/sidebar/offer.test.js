import test from 'node:test';
import assert from 'node:assert/strict';

import { dayPriceCents, initialQuantity, isAlmostFull, maxQuantityFor, minQuantityFor, timeAt } from './offer.js';

/**
 * Lo que la oferta dice del paso 3 (Fase 4 · paso 4.7·2b·2·B).
 *
 * ⚠️ Estas derivaciones vivían dentro de `Sidebar.vue` y no las alcanzaba ningún test: ni `node --test`
 * —están en un componente— ni el diff de árbol, que recibía sus resultados ya cocinados. El selector
 * de cantidad del paso más denso del embudo dependía entero de ellas.
 */

/** La oferta de horas tal y como la publica `POST availability/{producto}/times`. */
const TIMES = [
    { time: '10:00:00', available: 60, max_quantity: 20, sellable: true },
    { time: '12:00:00', available: 4, max_quantity: 4, sellable: true },
];

const PACK = { type: 'pack', min_quantity: 8 };
const ENTRY = { type: 'entry' };

test('localiza la hora elegida dentro de la oferta', () => {
    assert.equal(timeAt(TIMES, '12:00:00').available, 4);
});

/**
 * ⚠️ Una hora que ya no se ofrece da `null`, y **no es un caso defensivo**: entre que el cliente ve la
 * lista y elige, otra persona puede llenar la franja.
 */
test('una hora que ya no se ofrece da null, no revienta', () => {
    assert.equal(timeAt(TIMES, '18:00:00'), null);
    assert.equal(timeAt(TIMES, null), null);
    assert.equal(timeAt([], '10:00:00'), null);
    assert.equal(timeAt(null, '10:00:00'), null);
});

/**
 * ⚠️ **El techo es `max_quantity`, NO `available`**, y el fixture los hace distintos a propósito: en un
 * pack `available` son las plazas de la franja (60) y `max_quantity` los invitados que admite la
 * fiesta (20). Acotar con el primero dejaría pedir invitados que el checkout rechazaría.
 */
test('el techo del selector es max_quantity y no available', () => {
    assert.equal(maxQuantityFor(TIMES, '10:00:00'), 20);
    assert.notEqual(maxQuantityFor(TIMES, '10:00:00'), TIMES[0].available);
});

/** Sin hora elegida el selector queda inerte, no «sin tope». */
test('sin hora elegida el techo es 0', () => {
    assert.equal(maxQuantityFor(TIMES, null), 0);
    assert.equal(maxQuantityFor(TIMES, '18:00:00'), 0);
    assert.equal(maxQuantityFor([], '10:00:00'), 0);
});

test('el suelo es el mínimo del pack, y 1 para una entrada', () => {
    assert.equal(minQuantityFor(PACK), 8);
    assert.equal(minQuantityFor(ENTRY), 1);
    assert.equal(minQuantityFor({ type: 'pack' }), 1, 'un pack sin mínimo declarado cae a 1');
    assert.equal(minQuantityFor(null), 1);
});

/**
 * ⚠️ **La equivalencia con el servidor, escrita como caso porque parece que no la hay.**
 *
 * `Purchase::selectTime()` hace `qty = techo >= minimo ? minimo : 0` y aquí se hace
 * `min(minimo, techo)`. Solo diferirían con `0 < techo < minimo`, y ese caso **no es alcanzable**: la
 * oferta retira la hora entera cuando el mínimo no cabe (medido el 2026-08-15 con un pack de mínimo 8
 * y el aforo de invitados casi agotado — con 5 libres los dos motores ofrecen lista VACÍA).
 *
 * Los tres casos de abajo son los alcanzables, y en los tres coinciden.
 */
test('la cantidad inicial coincide con la del servidor en todo caso alcanzable', () => {
    const comoElServidor = (min, techo) => (techo >= min ? min : 0);

    for (const [product, time, etiqueta] of [
        [PACK, '10:00:00', 'pack con techo 20 y mínimo 8'],
        [ENTRY, '12:00:00', 'entrada con techo 4'],
        [PACK, '18:00:00', 'hora que ya no se ofrece → techo 0'],
    ]) {
        const min = minQuantityFor(product);
        const techo = maxQuantityFor(TIMES, time);

        assert.equal(initialQuantity(product, TIMES, time), comoElServidor(min, techo), etiqueta);
    }
});

test('una hora retirada deja la cantidad en 0', () => {
    assert.equal(initialQuantity(PACK, TIMES, '18:00:00'), 0);
    assert.equal(initialQuantity(ENTRY, [], '10:00:00'), 0);
});

const DATES = [
    { date: '2026-08-14', price_cents: 990, rate_key: 'normal' },
    { date: '2026-08-15', price_cents: 1200, rate_key: 'special' },
    { date: '2026-08-16', price_cents: null, rate_key: 'normal' },
];

/**
 * ⚠️ El precio del día NO es el «desde» del catálogo: aquel es el mínimo de todas las tarifas y este
 * el del día concreto, que puede ser el especial del fin de semana. El fixture los distingue.
 */
test('el precio es el del DÍA elegido, no el mínimo del producto', () => {
    assert.equal(dayPriceCents(DATES, '2026-08-15'), 1200);
    assert.equal(dayPriceCents(DATES, '2026-08-14'), 990);
});

/** `null` es legítimo: «este producto no tiene tarifa ese día», y el paso 3 lo pinta como tal. */
test('un día sin tarifa da null, igual que un día que no está en la oferta', () => {
    assert.equal(dayPriceCents(DATES, '2026-08-16'), null);
    assert.equal(dayPriceCents(DATES, '2026-09-01'), null);
    assert.equal(dayPriceCents(DATES, null), null);
    assert.equal(dayPriceCents(null, '2026-08-14'), null);
});

// ── El aviso de «casi llena» (`DECISIONES #239`) ──────────────────────────────────────────────────

test('una hora se anuncia casi llena cuando sus plazas libres NO SUPERAN el umbral', () => {
    assert.equal(isAlmostFull({ time: '10:00:00', available: 8, max_quantity: 8 }, 8), true, 'el borde entra');
    assert.equal(isAlmostFull({ time: '10:00:00', available: 9, max_quantity: 9 }, 8), false, 'uno por encima, no');
    assert.equal(isAlmostFull({ time: '10:00:00', available: 1, max_quantity: 1 }, 8), true);
});

/**
 * ⚠️ **`0` es «no avisar», no «avisar cuando no queden plazas».** Sin esta rama, un operador que
 * apaga el aviso lo vería aparecer en la única franja donde más chirría.
 */
test('con umbral 0 no se anuncia ninguna hora, ni siquiera una sin plazas', () => {
    assert.equal(isAlmostFull({ time: '10:00:00', available: 0, max_quantity: 0 }, 0), false);
    assert.equal(isAlmostFull({ time: '10:00:00', available: 3, max_quantity: 3 }, 0), false);
});

/** Sin `/config` el umbral no se pudo leer: no se inventa escasez. */
test('sin umbral leído no se anuncia nada', () => {
    assert.equal(isAlmostFull({ time: '10:00:00', available: 1 }, undefined), false);
    assert.equal(isAlmostFull({ time: '10:00:00', available: 1 }, null), false);
    assert.equal(isAlmostFull({ time: '10:00:00', available: 1 }, '8'), false, 'una cadena no es un umbral');
});

/**
 * ⚠️ **Lee `available`, NO `max_quantity`**, y en un pack no son el mismo número: medido en la
 * instalación de referencia, `available = 60` (plazas de la franja) y `max_quantity = 20` (invitados
 * de ESA fiesta). Con el campo equivocado la franja vacía se anunciaría «casi llena».
 */
test('en un pack el aviso mira las plazas de la FRANJA, no el tope de la fiesta', () => {
    // ⚠️ Los dos números tienen que caer a LADOS DISTINTOS del umbral, o el caso no distingue el
    // campo: la primera versión usaba 60/20 con umbral 8 —los dos por encima— y la mutación que
    // cambiaba `available` por `max_quantity` pasaba en verde. Un pack de máximo 6 invitados en una
    // franja con 60 plazas libres es el estado real que separa los dos.
    assert.equal(isAlmostFull({ time: '10:00:00', available: 60, max_quantity: 6 }, 8), false,
        'franja vacía con fiesta pequeña: NO está casi llena');
    assert.equal(isAlmostFull({ time: '10:00:00', available: 6, max_quantity: 20 }, 8), true,
        'franja casi llena aunque la fiesta admita más: SÍ está casi llena');
});

test('una hora sin el campo no lanza y no se anuncia', () => {
    assert.equal(isAlmostFull(null, 8), false);
    assert.equal(isAlmostFull({ time: '10:00:00' }, 8), false);
});
