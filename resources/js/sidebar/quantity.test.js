import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nextQuantity, unitPriceToShow } from './quantity.js';

/**
 * `#327` — la cantidad del paso 3 y el precio que se enseña con ella.
 *
 * Lo que fija, en orden de importancia:
 *  1. **El precio mostrado es el del SERVIDOR**, no el del calendario. Con tramos, el del calendario
 *     es el más barato del día («desde 12 €») y enseñarlo mientras se elige la cantidad hacía que un
 *     grupo de 30 leyera 12 € y se le cobraran 15.
 *  2. **`+`/`−` y lo TECLEADO comparten acotado**, o un camino admite lo que el otro rechaza.
 *  3. **Lo tecleado se pega al extremo; un `+` fuera de rango NO se mueve** — el botón ya sale
 *     deshabilitado en ese borde, así que llegar ahí es un estado que no debería existir.
 */

test('unitPriceToShow prefiere el precio TARIFICADO por el servidor', () => {
    assert.equal(unitPriceToShow({ unit_price_cents: 1300 }, 1200), 1300);
});

test('unitPriceToShow cae al precio del dia mientras no hay linea tarificada', () => {
    // Antes de elegir hora no hay línea, y el precio del día es la única respuesta honesta.
    assert.equal(unitPriceToShow(null, 1200), 1200);
    assert.equal(unitPriceToShow(undefined, 1200), 1200);
    assert.equal(unitPriceToShow({}, 1200), 1200);
});

test('unitPriceToShow no inventa un cero cuando no hay ningun precio', () => {
    assert.equal(unitPriceToShow(null, null), null);
});

/** ⚠️ Un 0 tarificado es un precio REAL (producto gratuito) y tiene que ganar al del día. */
test('unitPriceToShow respeta un precio de cero del servidor', () => {
    assert.equal(unitPriceToShow({ unit_price_cents: 0 }, 1200), 0);
});

test('nextQuantity acota lo TECLEADO al rango vendible', () => {
    const range = { floor: 30, ceiling: 100, current: 30 };

    assert.equal(nextQuantity({ raw: '70' }, range), 70);
    assert.equal(nextQuantity({ raw: '500' }, range), 100, 'se pega al techo');
    assert.equal(nextQuantity({ raw: '1' }, range), null, 'ya está en el suelo: no cambia nada');
});

test('nextQuantity devuelve null cuando el valor no cambia', () => {
    const range = { floor: 30, ceiling: 100, current: 70 };

    assert.equal(nextQuantity({ raw: '70' }, range), null, 'y así el llamante ahorra la consulta');
    assert.equal(nextQuantity({ raw: '' }, range), null, 'el campo vacío conserva lo vigente');
});

test('nextQuantity mueve de uno en uno con los botones', () => {
    const range = { floor: 30, ceiling: 100, current: 70 };

    assert.equal(nextQuantity({ delta: 1 }, range), 71);
    assert.equal(nextQuantity({ delta: -1 }, range), 69);
});

/** ⚠️ Un `+` en el borde NO se pega al extremo: no se mueve. El botón ya estaba deshabilitado. */
test('nextQuantity no mueve un boton fuera de rango', () => {
    assert.equal(nextQuantity({ delta: 1 }, { floor: 30, ceiling: 100, current: 100 }), null);
    assert.equal(nextQuantity({ delta: -1 }, { floor: 30, ceiling: 100, current: 30 }), null);
});
