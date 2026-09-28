import { test } from 'node:test';
import assert from 'node:assert/strict';
import { marcarEscaleras } from './escalera.js';

/** Un elemento de mentira con lo que `marcarEscaleras` toca: `dataset`, `hidden`, sus hijos por selector y sus atributos. */
function elemento(dataset, hijos = {}) {
    const atributos = new Map();

    return {
        dataset, hidden: false, atributos,
        querySelectorAll: (selector) => hijos[selector] ?? [],
        toggleAttribute: (nombre, si) => (si ? atributos.set(nombre, '') : atributos.delete(nombre)),
        setAttribute: (nombre, valor) => atributos.set(nombre, valor),
        removeAttribute: (nombre) => atributos.delete(nombre),
    };
}
/** La página de colegios: una escalera por pack, tres tramos y dos tarifas en cada uno. */
function pagina() {
    const escalera = (id) => elemento({ jwEscalera: String(id) }, {
        '[data-jw-tramo]': [30, 70, 100].map((desde) => elemento({ jwTramo: String(desde) }, {
            '[data-jw-tarifa]': ['normal', 'special'].map((tarifa) => elemento({ jwTarifa: tarifa })),
        })),
    });
    const escaleras = [escalera(395), escalera(396)];

    return { escaleras, querySelectorAll: (selector) => (selector === '[data-jw-escalera]' ? escaleras : []) };
}
/**
 * Lo marcado, legible y CADA MARCA POR SEPARADO (una que se queda colgada se ve): `395:70` (una fila), `395:70:special`
 * (una celda), `395:70:special:lector` (su `aria-current`), `396 oculta`.
 */
function marcado(raiz) {
    return raiz.escaleras.flatMap((e) => [
        ...(e.hidden ? [`${e.dataset.jwEscalera} oculta`] : []),
        ...e.querySelectorAll('[data-jw-tramo]').flatMap((f) => [
            ...(f.atributos.has('data-jw-activo') ? [`${e.dataset.jwEscalera}:${f.dataset.jwTramo}`] : []),
            ...f.querySelectorAll('[data-jw-tarifa]').flatMap((c) => [
                ...(c.atributos.has('data-jw-activo') ? [`${e.dataset.jwEscalera}:${f.dataset.jwTramo}:${c.dataset.jwTarifa}`] : []),
                ...(c.atributos.get('aria-current') === 'true' ? [`${e.dataset.jwEscalera}:${f.dataset.jwTramo}:${c.dataset.jwTarifa}:lector`] : []),
            ]),
        ]),
    ]);
}

test('enseña la escalera de la fila elegida y marca el tramo de esa gente; sin día, ninguna celda', () => {
    const raiz = pagina();

    marcarEscaleras(raiz, { fila: 395, tramo: 70, tarifa: null });
    assert.deepEqual(marcado(raiz), ['395:70', '396 oculta']);
});

test('con día, la celda de su tarifa, y lo de antes se desmarca al cambiar de fila, de gente o de día', () => {
    const raiz = pagina();

    marcarEscaleras(raiz, { fila: 395, tramo: 70, tarifa: 'special' });
    assert.deepEqual(marcado(raiz), ['395:70', '395:70:special', '395:70:special:lector', '396 oculta']);
    marcarEscaleras(raiz, { fila: 396, tramo: 100, tarifa: 'normal' });
    assert.deepEqual(marcado(raiz), ['395 oculta', '396:100', '396:100:normal', '396:100:normal:lector'], 'ni la celda ni su lector se quedan en la de antes');
});

test('sin marca, o sin escalera de esa fila, no toca nada: la página se queda como la pintó', () => {
    const raiz = pagina();

    marcarEscaleras(raiz, null);
    marcarEscaleras(raiz, { fila: 999, tramo: 30, tarifa: 'normal' });
    marcarEscaleras(null, { fila: 395, tramo: 30, tarifa: null });
    assert.deepEqual(marcado(raiz), []);
});
