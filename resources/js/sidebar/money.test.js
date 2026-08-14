import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { dayPrice, money } from './money.js';

/**
 * Fase 4 · paso 4.3·1 — la red del formato de importes (`sidebar-spa.md` §6, criterio CE-6).
 *
 * Esto fija la CONDUCTA del módulo; que esa conducta sea la de PHP lo comprueba aparte
 * `SidebarMoneyParityTest`, que corre este mismo módulo contra `number_format` sobre un barrido.
 * Hacen falta los dos: sin el barrido, aquí solo se estarían fijando los casos que a uno se le
 * ocurren; sin estos casos, un barrido en verde no diría QUÉ regla se está cumpliendo.
 */

describe('importes de dos decimales', () => {
    test('los céntimos siempre salen con dos posiciones', () => {
        assert.equal(money(0), '0,00 €');
        assert.equal(money(5), '0,05 €');
        assert.equal(money(50), '0,50 €');
        assert.equal(money(990), '9,90 €');
    });

    /**
     * ⚠️ El caso que motiva el módulo entero. `(100000/100).toFixed(2)` da «1000.00» y el separador
     * de millares no aparece por ninguna parte; PHP lo pone SIEMPRE a partir de cuatro dígitos.
     */
    test('a partir de mil euros se agrupa, que es donde fallaban las dos salidas obvias de JS', () => {
        assert.equal(money(100000), '1.000,00 €');
        assert.equal(money(123450), '1.234,50 €');
        assert.equal(money(999999), '9.999,99 €');
        assert.equal(money(1000000), '10.000,00 €');
        assert.equal(money(12345678), '123.456,78 €');
    });

    test('justo por debajo del millar NO se agrupa', () => {
        assert.equal(money(99999), '999,99 €');
    });

    /**
     * No es un importe alcanzable en la compra, pero el formateador no puede inventarse un signo:
     * un reembolso o una diferencia mal restada tienen que verse, no salir como un número positivo.
     */
    test('un importe negativo conserva su signo delante del millar', () => {
        assert.equal(money(-100000), '-1.000,00 €');
        assert.equal(money(-5), '-0,05 €');
    });

    /**
     * ⚠️ **El signo se decide sobre el RESULTADO, no sobre la entrada.** Lo encontró el barrido de
     * `SidebarMoneyParityTest`, no la lectura: `number_format(-0.05, 0)` devuelve «0» porque el
     * redondeo se comió la magnitud, y un signo puesto a la entrada da «-0€».
     */
    test('un cero con signo se escribe sin signo, como en PHP', () => {
        assert.equal(dayPrice(-5), '0€');
        assert.equal(dayPrice(-49), '0€');
        assert.equal(dayPrice(-50), '-1€');
        assert.equal(money(0), '0,00 €');
    });
});

describe('precio de una celda del calendario', () => {
    test('sin decimales y con el € PEGADO, que es como lo emite el blade', () => {
        assert.equal(dayPrice(990), '10€');
        assert.equal(dayPrice(1190), '12€');
        assert.equal(dayPrice(0), '0€');
    });

    /**
     * ⚠️ **Redondea, no trunca.** El repo tiene un precedente de lo contrario (`TicketType::euros()`
     * usa `intdiv`), así que copiar por costumbre cambia el precio que ve el cliente.
     */
    test('redondea el medio hacia arriba', () => {
        assert.equal(dayPrice(950), '10€');
        assert.equal(dayPrice(949), '9€');
    });

    /**
     * ⚠️ **La divergencia de agrupación empieza un céntimo ANTES de lo que se supone**: a cero
     * decimales, 999,50 € ya se redondea a 1000 y PHP le mete el punto. Un caso puesto en 100000
     * céntimos deja sin cubrir la franja 99950-99999, que es donde el árbol ya diverge.
     */
    test('el redondeo cruza el millar antes que el importe', () => {
        assert.equal(dayPrice(99949), '999€');
        assert.equal(dayPrice(99950), '1.000€');
        assert.equal(dayPrice(99999), '1.000€');
    });
});
