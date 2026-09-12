import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { backPlan, buildProgress, shortDate } from './progress.js';
import { STEPS } from './machine.js';

/**
 * Fase 4 · paso 4.3·1 — la red de la banda de progreso (criterio CE-6).
 *
 * Que estos estados coincidan con los de `Purchase::bookingProgress()` lo comprueba
 * `SidebarProgressParityTest` contra el componente real; aquí se fija la conducta y, sobre todo, los
 * casos frontera que un test contra el servidor no alcanza (el huso del navegador).
 */

const MESSAGES = {
    phase_date: 'Fecha',
    phase_time: 'Hora',
    phase_cart: 'Tu cesta',
    phase_identify: 'Quién eres',
    phase_pay: 'Pagar',
    back: 'Volver',
    back_to_cart: 'Volver al carrito',
};

const base = {
    productName: 'Entrada 1 hora',
    date: null,
    time: null,
    messages: MESSAGES,
    locale: 'es',
};

describe('en qué pasos existe la banda', () => {
    /**
     * ⚠️ **Las CINCO pantallas del camino la llevan** (`#555`). Antes solo el 2 y el 3, y desde la
     * cesta hasta pagar el cliente no sabía cuánto le quedaba ni tenía «Volver» en la banda — los
     * pasos 4, 5 y 8 traían el suyo propio, que es lo que esta tanda retiró.
     */
    test('las cinco pantallas del camino la llevan, y ninguna más', () => {
        for (const step of [2, 3, 4, 5, 8]) {
            assert.notEqual(buildProgress({ ...base, step }), null, `el paso ${step} tiene que llevar banda`);
        }

        // ⚠️ El catálogo NO es una fase: ahí todavía no se ha empezado a reservar nada. Y los
        // desenlaces tampoco — de tres de ellos no se sale, así que contarles una fase prometería un
        // camino que no existe.
        for (const step of [1, 6, 7, 9, 10, 11]) {
            assert.equal(buildProgress({ ...base, step }), null, `el paso ${step} NO lleva banda`);
        }
    });
});

describe('avance dentro del flujo', () => {
    /**
     * ❗❗ **Una fase por PANTALLA, y eso es lo que hace honesto el contador** (`#555`,
     * `[DECIDIDO owner]`). Hasta esta tanda la tercera fase se encendía DENTRO de la pantalla de la
     * hora al elegirla, así que el mismo «Paso 3 de N» salía en dos pantallas distintas — y una banda
     * que existe para decir cuánto queda no puede repetir su número.
     */
    test('cada pantalla es una fase, y el contador no repite', () => {
        const vistos = [2, 3, 4, 5, 8].map((step) => buildProgress({ ...base, step }).active);

        assert.deepEqual(vistos, [1, 2, 3, 4, 5]);
        assert.equal(buildProgress({ ...base, step: 2 }).total, 5);
    });

    test('el paso de fecha es la fase 1 y las otras cuatro están por hacer', () => {
        const progress = buildProgress({ ...base, step: 2 });

        assert.deepEqual(progress.steps.map((s) => s.state), ['current', 'todo', 'todo', 'todo', 'todo']);
    });

    /**
     * ⚠️ **Elegir la hora YA NO mueve la fase**, y es la consecuencia de la decisión de arriba: el
     * avance dentro de una pantalla es justo lo que hacía repetir el número.
     */
    test('elegir la hora no mueve la fase: la pantalla es la misma', () => {
        const sinHora = buildProgress({ ...base, step: 3, date: '2026-09-05' });
        const conHora = buildProgress({ ...base, step: 3, date: '2026-09-05', time: '10:00:00' });

        assert.equal(sinHora.active, 2);
        assert.equal(conHora.active, 2);
        assert.deepEqual(conHora.steps.map((s) => s.state), ['done', 'current', 'todo', 'todo', 'todo']);
    });

    test('en la última pantalla todas las anteriores están hechas', () => {
        const progress = buildProgress({ ...base, step: 8 });

        assert.deepEqual(progress.steps.map((s) => s.state), ['done', 'done', 'done', 'done', 'current']);
        assert.deepEqual(progress.steps.map((s) => s.label), ['Fecha', 'Hora', 'Tu cesta', 'Quién eres', 'Pagar']);
    });
});

describe('el «Volver» de la banda', () => {
    /**
     * ⚠️ **El rótulo sigue al DESTINO, no a la pantalla** (`#555`): desde quién-eres y desde pagar se
     * vuelve al carrito y hay que decirlo —es el rótulo que esas dos pantallas ya traían en su botón
     * propio, y el que dibuja el artboard del paso 08—. Componerlo en la plantilla con un `v-if` sobre
     * el paso sería la misma regla escrita en dos sitios.
     */
    test('dice a dónde vuelve', () => {
        const rotulos = [2, 3, 4, 5, 8].map((step) => buildProgress({ ...base, step }).backLabel);

        assert.deepEqual(rotulos, ['Volver', 'Volver', 'Volver', 'Volver al carrito', 'Volver al carrito']);
    });

    /**
     * ❗❗ **Y no puede MENTIR: dice «al carrito» si y solo si va al carrito.**
     *
     * Es una EQUIVALENCIA y no dos listas, porque el hueco que cierra lo encontró el arnés: poner los
     * dos campos en la misma fila los mantiene juntos, pero **no impide que se contradigan dentro de
     * ella**. Cambiar el destino de «quién eres» al catálogo dejaba un botón que dice «Volver al
     * carrito» y lleva a otra parte — y ninguna de las doce mutaciones anteriores lo cazaba.
     */
    test('el rótulo del Volver no puede contradecir a su destino', () => {
        for (const step of [2, 3, 4, 5, 8]) {
            const dice = buildProgress({ ...base, step }).backLabel === 'Volver al carrito';
            const va = backPlan(step).to === STEPS.CART;

            assert.equal(dice, va, `el paso ${step} dice una cosa y hace otra: rótulo=${dice}, destino=${va}`);
        }
    });

    /** El plan solo existe donde hay «Volver» que pulsar: preguntarlo fuera es un error de quien llama. */
    test('los pasos sin banda no tienen plan de vuelta', () => {
        for (const step of [1, 6, 7, 9, 10, 11]) {
            assert.equal(backPlan(step), null, `el paso ${step} no tiene «Volver»`);
        }
    });
});

describe('la línea de contexto (sigue)', () => {
    /**
     * ⚠️ **El contexto muere en la cesta, y no es un olvido**: dice «producto · día · hora» de UNA
     * línea, y de ahí en adelante puede haber varias — rotularía la del producto que quedó
     * seleccionado, que no es «la reserva» de nadie. El artboard del paso 08 también lo dibuja sin él.
     */
    test('solo las dos primeras pantallas lo llevan', () => {
        for (const step of [4, 5, 8]) {
            assert.equal(buildProgress({ ...base, step }).context, '', `el paso ${step} no lleva contexto`);
        }
    });
});

describe('la línea de contexto', () => {
    test('se va llenando conforme el cliente elige', () => {
        assert.equal(buildProgress({ ...base, step: 2 }).context, 'Entrada 1 hora');
        assert.equal(
            buildProgress({ ...base, step: 3, date: '2026-09-05' }).context,
            'Entrada 1 hora · Sáb 5 sept'
        );
        assert.equal(
            buildProgress({ ...base, step: 3, date: '2026-09-05', time: '10:00:00' }).context,
            'Entrada 1 hora · Sáb 5 sept · 10:00'
        );
    });

    /** Sin tramos que enseñar no puede quedar un separador suelto: el servidor los filtra igual. */
    test('los tramos vacíos no dejan separadores sueltos', () => {
        assert.equal(buildProgress({ ...base, step: 2, productName: '' }).context, '');
        assert.equal(
            buildProgress({ ...base, step: 3, productName: '', date: '2026-09-05' }).context,
            'Sáb 5 sept'
        );
    });

    /**
     * ⚠️ **El huso del navegador no puede mover la fecha.** `new Date('2026-09-05')` se interpreta
     * como medianoche UTC y al oeste de Greenwich es la víspera: el contexto diría «Vie 4 sept». Es
     * el mismo agujero que el calendario ya cerró; aquí se comprueba forzando el huso del proceso.
     *
     * El caso se ejecuta de verdad solo cuando el runner corre con `TZ` puesto —lo hace el test de
     * paridad—, pero la aserción de abajo ya fija la conducta con la fecha construida por partes.
     */
    test('la fecha se construye por PARTES, no parseando la cadena', () => {
        // Si el módulo usara `new Date('2026-09-01')`, en un huso al oeste esto sería «31 ago».
        assert.match(shortDate('2026-09-01', 'es'), /1 sept$/);
        assert.match(shortDate('2026-01-01', 'es'), /1 ene$/);
    });

    test('una fecha ilegible no rompe el contexto', () => {
        assert.equal(shortDate('', 'es'), '');
        assert.equal(shortDate('no-es-fecha', 'es'), '');
    });

    /**
     * ⚠️ Medido: con el patrón fijado por nosotros, inglés y francés coinciden EXACTAMENTE con
     * Carbon. Si alguien «limpia» los puntos de la abreviatura para acercar el español, rompe el
     * francés, que hoy está bien. El caso está aquí para que ese cambio no pase inadvertido.
     */
    test('el francés conserva los puntos de abreviatura, que es como los pinta el servidor', () => {
        assert.equal(shortDate('2026-09-05', 'fr'), 'sam. 5 sept.');
        assert.equal(shortDate('2026-09-05', 'en'), 'Sat 5 Sep');
    });
});
