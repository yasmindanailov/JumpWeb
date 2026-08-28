import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { destinoIman, rumbo, UMBRAL_CIERRE, MUERTA, MINIMO_UTIL, GESTO_MINIMO } from './scroll-magnet.js';

/**
 * La red del imán de los dos puntos estáticos (`tema-por-instalacion.md` §20, `#252`).
 *
 * ⚠️ **Nada de esto se ve en un árbol ni en una captura**: depende de dónde estás, hacia dónde
 * vas y cuánto recorrido llevas consumido. Es exactamente la clase de regla que se escribe una
 * vez y se rompe sin que nadie lo note — el imán empujando hacia abajo a quien sube, o
 * llevándose a pantalla completa a quien solo quería ver el pie.
 */

/** Una portada de 1280×900 con los dos recorridos, para no repetir el decorado. */
const PORTADA = { vh: 900, maxY: 6000, heroRunway: 420, cierreRunway: 675, dir: 1 };
const ESTATICO_CIERRE = PORTADA.maxY - PORTADA.cierreRunway;   // 5325

describe('el hero de cabecera', () => {
    test('bajando por su recorrido, encaja en el PUNTO ESTÁTICO', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: 200, dir: 1 }), 420,
            'bajando, el estado que sigue es el hero ya encogido con el armazón colocado',
        );
    });

    test('subiendo, encaja en la primera pantalla', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: 200, dir: -1 }), 0,
            'subiendo, el estado que sigue es el hero a pantalla completa',
        );
    });

    test('en los extremos no se toca nada', () => {
        assert.equal(destinoIman({ ...PORTADA, y: MUERTA, dir: 1 }), null, 'arriba del todo ya estás en un estado');
        assert.equal(destinoIman({ ...PORTADA, y: 420 - MUERTA, dir: 1 }), null, 'y en el punto estático, también');
    });

    test('pasado el recorrido, el hero deja de mandar', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: 1500, dir: 1 }), null,
            'a media página no hay ningún estado al que encajar: sería un tirón sin motivo',
        );
    });
});

describe('el hero del cierre', () => {
    test('bajando, el imán RETIENE en el punto estático — no cae a pantalla completa', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: ESTATICO_CIERRE + 100, dir: 1 }), ESTATICO_CIERRE,
            'con poco recorrido consumido, bajar tiene que devolverte al punto donde se ve el pie entero',
        );
    });

    test('y CAPTURA un poco antes de llegar', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: ESTATICO_CIERRE - 150, dir: 1 }), ESTATICO_CIERRE,
            'quien se para justo antes se queda con el pie cortado: el punto estático solo existe si se llega',
        );
    });

    test('pero no captura desde demasiado lejos', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: ESTATICO_CIERRE - 400, dir: 1 }), null,
            'un imán que tira desde media página deja de ser un imán y es un secuestro',
        );
    });

    test('pasado el umbral, el segundo empujón SÍ completa la transición', () => {
        const y = ESTATICO_CIERRE + PORTADA.cierreRunway * (UMBRAL_CIERRE + 0.05);
        assert.equal(
            destinoIman({ ...PORTADA, y, dir: 1 }), PORTADA.maxY,
            'quien ha consumido casi la mitad del recorrido ha decidido ir: retenerlo ahí sería pelearse con él',
        );
    });

    test('justo en el umbral ya completa', () => {
        const y = ESTATICO_CIERRE + PORTADA.cierreRunway * UMBRAL_CIERRE;
        assert.equal(destinoIman({ ...PORTADA, y, dir: 1 }), PORTADA.maxY);
    });

    test('subiendo se sale al punto estático, nunca hacia abajo', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: PORTADA.maxY - 200, dir: -1 }), ESTATICO_CIERRE,
            'subiendo desde pantalla completa, el siguiente estado hacia arriba es el punto estático',
        );
    });

    test('⚠️ subiendo por ENCIMA del punto estático no se empuja hacia abajo', () => {
        const destino = destinoIman({ ...PORTADA, y: ESTATICO_CIERRE - 150, dir: -1 });
        assert.equal(
            destino, null,
            'la banda de captura es solo para quien BAJA: tirar hacia abajo de quien sube es el peor '.
            concat('fallo posible de un imán, y es el que sale gratis si la banda no mira la dirección'),
        );
    });
});

describe('cuándo el imán no manda', () => {
    test('con un superpuesto abierto, nunca', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: 200, dir: 1, locked: true }), null,
            'el scroll que se está oyendo es el del menú o el del cajón, no el de la página',
        );
    });

    test('con movimiento reducido, nunca', () => {
        assert.equal(destinoIman({ ...PORTADA, y: 200, dir: 1, reduce: true }), null);
    });

    test('si el viaje es minúsculo, no compensa el sobresalto', () => {
        assert.equal(
            destinoIman({ ...PORTADA, y: 420 - MINIMO_UTIL + 1, dir: 1 }), null,
            'mover cuatro píxeles no coloca nada y se siente como un tirón',
        );
    });

    test('una página SIN heroes no tiene nada que encajar', () => {
        assert.equal(
            destinoIman({ ...PORTADA, heroRunway: 0, cierreRunway: 0, y: 200, dir: 1 }), null,
            'son once vistas de doce: el imán no puede inventarse un punto estático donde no hay hero',
        );
    });

    test('y un documento que no llega a una pantalla, tampoco', () => {
        assert.equal(destinoIman({ ...PORTADA, maxY: 100, y: 50, dir: 1, heroRunway: 0 }), null);
    });
});

describe('el rumbo — el fallo que costó una sonda de navegador', () => {
    test('el gesto manda sobre el reflujo que lo interrumpe', () => {
        // Medido en navegador: al llegar al final de la portada el documento crece 34 px y el
        // anclaje de scroll compensa. Comparando con el EVENTO anterior, eso decía «baja».
        assert.equal(
            rumbo(11439 - 200 + 34, 11439), -1,
            'un gesto de 200 hacia arriba con un reflujo de 34 en medio sigue siendo un gesto hacia arriba',
        );
    });

    test('un reflujo SIN gesto no decide nada', () => {
        assert.equal(
            rumbo(11439 + 34, 11439), 1,
            'ojo: 34 pasa del mínimo — por eso el enfriamiento es la otra mitad de la defensa',
        );
        assert.equal(rumbo(11439 + GESTO_MINIMO - 1, 11439), 0, 'por debajo del mínimo no hay gesto');
        assert.equal(rumbo(11439, 11439), 0, 'y quieto, tampoco');
    });

    test('el signo es el del movimiento neto', () => {
        assert.equal(rumbo(500, 0), 1);
        assert.equal(rumbo(0, 500), -1);
    });
});
