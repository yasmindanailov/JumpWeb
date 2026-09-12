import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { AMOUNT_PLACEHOLDER, buildFooter } from './foot.js';
import { STEPS, canGo } from './machine.js';

/**
 * Fase 4 · paso 4.3·2 — la red del pie (criterio CE-6).
 *
 * Aquí se fija la CONDUCTA; que coincida con `Purchase::footer()` lo comprueba
 * `SidebarCartParityTest` contra el componente real. Lo que este fichero cubre y esa paridad no
 * alcanza son los estados **sin barra**, que son los que un motor se salta con más facilidad: donde
 * el servidor no emite pie, el cliente tampoco.
 */

const MESSAGES = {
    total: 'Total',
    continue: 'Continuar',
    add_to_cart: 'Añadir al carrito',
    go_to_cart: 'Ir al carrito',
    go_to_pay: 'Ir a pagar',
    cart_items: ':count artículo|:count artículos',
    iva_note: 'Precios con IVA incluido',
    footer_pay_now: 'Pagas ahora',
    footer_pay_now_deposit: 'Pagas ahora (señal)',
};

const state = (overrides) => ({ messages: MESSAGES, locale: 'es', ...overrides });

describe('los pasos SIN barra', () => {
    /**
     * ⚠️ `null` es un estado real y no un descuido. Con la cesta vacía el servidor no emite pie en el
     * catálogo ni en el carrito; un cliente que devolviera una barra enseñaría «0,00 €» y un «Ir a
     * pagar» donde la web no enseña nada.
     */
    test('el catálogo y el carrito sin cesta no llevan barra', () => {
        assert.equal(buildFooter(state({ step: 1, cartCount: 0 })), null);
        assert.equal(buildFooter(state({ step: 4, cartCount: 0 })), null);
    });

    test('los pasos que no son del embudo tampoco', () => {
        for (const step of [5, 6, 9, 10, 11]) {
            assert.equal(buildFooter(state({ step })), null, `el paso ${step} no lleva pie`);
        }
    });
});

describe('la barra-carrito del catálogo', () => {
    test('lleva recuento, importe y NINGUNA de las claves de la barra normal', () => {
        const footer = buildFooter(state({ step: 1, cartCount: 2, cartTotalCents: 123450 }));

        assert.equal(footer.type, 'cart');
        assert.equal(footer.action, 'goToCart');
        assert.equal(footer.count, 2);
        assert.equal(footer.amount, '1.234,50 €');
        // La rama `cart` emite UN solo hijo: sin nota de IVA, sin desglose y sin CTA deshabilitable.
        // ⚠️ `sells` entra en las CUATRO formas del pie desde `#551` (el mapa del naranja): la lista de
        // claves se actualiza a propósito, no se relaja — sigue exigiendo el conjunto EXACTO.
        assert.deepEqual(Object.keys(footer), ['type', 'action', 'cta', 'count', 'label', 'amount', 'sells']);
    });

    /** ⚠️ Pluralización de Laravel, no `n === 1`: en francés el cero cae en el SINGULAR. */
    test('el recuento se pluraliza con las reglas del idioma', () => {
        assert.equal(buildFooter(state({ step: 1, cartCount: 1, cartTotalCents: 0 })).label, '1 artículo');
        assert.equal(buildFooter(state({ step: 1, cartCount: 2, cartTotalCents: 0 })).label, '2 artículos');
    });
});

describe('el calendario', () => {
    test('sin día elegido el CTA está inactivo y el importe es un guion', () => {
        const footer = buildFooter(state({ step: 2, hasDate: false }));

        assert.equal(footer.amount, AMOUNT_PLACEHOLDER);
        assert.equal(footer.disabled, true);
        assert.equal(footer.split, null);
    });

    test('elegir día habilita el CTA y NO cambia el importe', () => {
        const footer = buildFooter(state({ step: 2, hasDate: true }));

        assert.equal(footer.disabled, false);
        assert.equal(footer.amount, AMOUNT_PLACEHOLDER, 'el precio depende del día pero no se enseña aquí');
    });
});

describe('la hora', () => {
    /**
     * El CTA solo se inactiva hasta elegir HORA: lo demás —cantidad mínima, campos obligatorios— se
     * valida AL PULSAR, con un aviso que dice qué falta, y no con un botón muerto que no lo explica.
     */
    test('sin hora el importe es un guion y el CTA está inactivo', () => {
        const footer = buildFooter(state({ step: 3, hasTime: false, lineTotalCents: null }));

        assert.equal(footer.amount, AMOUNT_PLACEHOLDER);
        assert.equal(footer.disabled, true);
    });

    test('con hora se pinta el total publicado, sin sumar nada', () => {
        const footer = buildFooter(state({ step: 3, hasTime: true, lineTotalCents: 120000, lineHasDeposit: false }));

        assert.equal(footer.amount, '1.200,00 €');
        assert.equal(footer.disabled, false);
        assert.equal(footer.split, null, 'sin señal no hay desglose que enseñar');
    });

    /**
     * ⚠️ El rótulo del paso 3 dice «(señal)» y el de la cesta NO: allí es un producto único y la señal
     * explica por qué se cobra menos que el total; en una cesta mixta lo que se cobra ahora no es solo
     * señal (#225). El diff de árbol no ve esta diferencia, porque es texto.
     */
    test('con señal el desglose usa el rótulo del PASO, no el neutro', () => {
        const footer = buildFooter(state({
            step: 3, hasTime: true, lineTotalCents: 120000,
            lineHasDeposit: true, lineDepositCents: 3000, lineGateRemainderCents: 117000,
        }));

        assert.equal(footer.split.nowLabel, 'Pagas ahora (señal)');
        assert.equal(footer.split.now, '30,00 €');
        // El resto llega PUBLICADO, no restado: reconstruirlo sería reimplementar la regla de #225
        // (los complementos de una línea con señal van íntegros al parque).
        assert.equal(footer.split.park, '1.170,00 €');
    });
});

describe('la cesta', () => {
    test('el CTA nunca se inactiva y el importe es el total del presupuesto', () => {
        const footer = buildFooter(state({ step: 4, cartCount: 2, cartTotalCents: 129900, cartOnlineCents: 129900 }));

        assert.equal(footer.action, 'checkout');
        assert.equal(footer.disabled, false);
        assert.equal(footer.amount, '1.299,00 €');
        assert.equal(footer.split, null, 'sin señal no hay desglose');
    });

    /** ⚠️ Rótulo NEUTRO: en una cesta mixta no todo lo que se cobra ahora es señal. */
    test('con señal el desglose usa el rótulo neutro', () => {
        const footer = buildFooter(state({ step: 4, cartCount: 2, cartTotalCents: 129900, cartOnlineCents: 30000 }));

        assert.equal(footer.split.nowLabel, 'Pagas ahora');
        assert.equal(footer.split.now, '300,00 €');
        assert.equal(footer.split.park, '999,00 €');
    });
});

/**
 * ❗❗❗ **EL MAPA DEL NARANJA, y lo decide ESTE módulo** (`DECISIONES #551`, la grieta 01 del canvas).
 *
 * El sistema del cliente declara cuatro jerarquías de botón y una regla dura: *«solo un botón de
 * relleno de acción por pantalla»*, y el relleno de acción significa **comprar**. Dentro del cajón eso
 * es **uno solo en las once pantallas del embudo: «Pagar»** (paso 08). Los otros tres CTA del pie
 * —«Continuar», «Añadir al carrito», «Ir a pagar»— son secundarios, y el artboard los dibuja en
 * relleno de tinta.
 *
 * ⚠️ Se prueba aquí y no en la plantilla porque el dato nace aquí: `Foot.vue` solo traduce `sells` a
 * una clase. Si la regla viviera en el marcado, el día que alguien añada un paso que cobre la
 * plantilla no se enteraría — y el botón saldría en tinta **sin que nada fallara**.
 */
describe('el mapa del naranja: quién vende', () => {
    test('solo el pie del paso 08 declara que vende', () => {
        const conPie = [
            { step: 1, cartCount: 2 },
            { step: 2, hasDate: true },
            { step: 3, hasTime: true, lineTotalCents: 1000 },
            { step: 4, cartCount: 2 },
            { step: 8, cartCount: 2 },
        ];

        const venden = conPie
            .map((extra) => [extra.step, buildFooter(state(extra))])
            .filter(([, footer]) => footer !== null && footer.sells === true)
            .map(([step]) => step);

        assert.deepEqual(venden, [8], 'el relleno de acción del embudo es «Pagar» y solo «Pagar»');
    });

    /** Y el campo está escrito en las CUATRO formas: un pie que lo deje en `undefined` no se censa. */
    test('las cuatro formas del pie declaran el campo', () => {
        for (const extra of [{ step: 1, cartCount: 2 }, { step: 2 }, { step: 3 }, { step: 4, cartCount: 2 }, { step: 8 }]) {
            const footer = buildFooter(state(extra));
            assert.ok(footer !== null, `el paso ${extra.step} tiene pie en este escenario`);
            assert.equal(typeof footer.sells, 'boolean', `el pie del paso ${extra.step} no declara \`sells\``);
        }
    });
});

/**
 * ⚠️⚠️ **Todo CTA que el pie OFRECE tiene que ser una transición que la máquina ADMITA**
 * (2026-08-28, `DECISIONES #215`). Existe porque durante dos semanas no fue así y nadie lo vio: la
 * barra-carrito del catálogo («Ir al carrito», desde 4.3·2) hacía `go(CART)` y `machine.js` no tenía
 * la arista `CATALOG → CART`, así que `go()` la rechazaba **en silencio** —su conducta deliberada ante
 * un salto imposible— y el botón era mudo. `machine.test.js` probaba la máquina sola y este fichero
 * probaba el pie solo; el fallo vivía exactamente entre los dos.
 *
 * Lo que se cruza: para cada paso con pie, la acción que publica y **a dónde lleva** esa acción en
 * `PurchaseSection.vue` (`runAction`). Una acción con varios destinos posibles pasa si la máquina
 * admite AL MENOS uno; una sin destino de máquina (ninguna hoy) tendría que declararse aquí.
 */
describe('cada CTA del pie es una transición que la máquina admite', () => {
    /** A dónde lleva cada acción del pie, leído de `PurchaseSection.vue` — no de la máquina. */
    const DESTINATIONS = {
        goToCart: [STEPS.CART],
        goToTime: [STEPS.TIME],
        addToCart: [STEPS.CART],
        checkout: [STEPS.IDENTIFY, STEPS.PAY],
        confirmReservation: [STEPS.REDIRECTING],
    };

    const full = (step) => state({
        step, cartCount: 2, cartTotalCents: 129900, cartOnlineCents: 129900,
        hasDate: true, hasTime: true, lineTotalCents: 990, lineHasDeposit: false,
        lineDepositCents: 0, lineGateRemainderCents: 0,
    });

    test('con cesta, ningún paso del embudo ofrece un botón que la máquina rechace', () => {
        const offered = [];
        const mute = [];

        for (const step of Object.values(STEPS)) {
            const footer = buildFooter(full(step));

            if (footer === null || ! footer.action) continue;

            offered.push(`${step}:${footer.action}`);
            const targets = DESTINATIONS[footer.action];

            assert.ok(targets, `la acción «${footer.action}» del paso ${step} no tiene destino declarado en este test`);

            if (! targets.some((to) => canGo(step, to))) mute.push(`${step}:${footer.action} → ${targets.join('|')}`);
        }

        assert.deepEqual(mute, [], 'botones del pie que la máquina rechazaría en silencio');
        // La guarda de la guarda: si el pie dejara de publicar acciones, el bucle pasaría sin mirar nada.
        assert.ok(offered.includes(`${STEPS.CATALOG}:goToCart`), 'el catálogo con cesta tiene que ofrecer «Ir al carrito»');
        assert.ok(offered.length >= 4, `se han visto pocas acciones: ${offered.join(', ')}`);
    });
});
