import test from 'node:test';
import assert from 'node:assert/strict';

import { dayPriceCents, initialQuantity, maxQuantityFor, minQuantityFor, timeAt } from './offer.js';

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
