import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    CLAVE_REANUDAR, VIGENCIA_MS, almacenDeLaPestana, conVuelta, esRutaPropia, esVuelta, marcaViva, marcarSalida, sinVuelta,
    tomarMarca, vueltaDe, vuelveAqui,
} from './reanudar.js';

/**
 * La compra que sale a Google y vuelve (T3e·4 de `specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #695`): la
 * vuelta con su parámetro, la marca de la pestaña y su caducidad, y que la vuelta solo pueda ser del PROPIO sitio.
 */
function almacen() {
    const datos = new Map();

    return {
        datos,
        getItem: (k) => (datos.has(k) ? datos.get(k) : null),
        setItem: (k, v) => { datos.set(k, String(v)); },
        removeItem: (k) => { datos.delete(k); },
    };
}

const bloqueado = {
    getItem: () => { throw new Error('SecurityError'); },
    setItem: () => { throw new Error('SecurityError'); },
    removeItem: () => { throw new Error('SecurityError'); },
};
const compra = { borrador: { zona: 'kids', fila: 100, dia: '2026-09-26', hora: '17:00:00', n: 2, cal: 1 } };
const T0 = 1_790_000_000_000;

describe('la vuelta', () => {
    test('es ESTA página con `?compra=reanudar`, conservando su búsqueda', () => {
        assert.equal(vueltaDe('/kids'), '/kids?compra=reanudar');
        assert.equal(vueltaDe('/kids', '?utm_source=ig'), '/kids?utm_source=ig&compra=reanudar');
        assert.equal(vueltaDe('/kids', '?compra=reanudar'), '/kids?compra=reanudar', 'no se duplica');
        assert.equal(vueltaDe('//evil.test/x'), '', 'una ruta que no es del sitio no tiene vuelta');
    });

    test('se reconoce por su parámetro, y se quita dejando el resto de la búsqueda', () => {
        assert.equal(esVuelta('?utm_source=ig&compra=reanudar'), true);
        assert.equal(esVuelta('?compra=otra'), false);
        assert.equal(esVuelta(''), false);
        assert.equal(sinVuelta('?utm_source=ig&compra=reanudar'), '?utm_source=ig');
        assert.equal(sinVuelta('?compra=reanudar'), '');
    });

    test('solo del propio sitio: otro host, un protocolo relativo o una barra invertida, no', () => {
        assert.equal(esRutaPropia('/kids?compra=reanudar'), true);
        for (const mala of ['//evil.test/x', '/\\evil.test', 'https://evil.test/', 'kids', '', null, 42]) {
            assert.equal(esRutaPropia(mala), false, `«${String(mala)}» no es una vuelta`);
        }
    });

    test('la ida a Google lleva su `next` escapado, y sin vuelta válida va sin él', () => {
        assert.equal(conVuelta('/auth/google', '/kids?compra=reanudar'), '/auth/google?next=%2Fkids%3Fcompra%3Dreanudar');
        assert.equal(conVuelta('/auth/google?x=1', '/kids'), '/auth/google?x=1&next=%2Fkids');
        assert.equal(conVuelta('/auth/google', '//evil.test'), '/auth/google');
        assert.equal(conVuelta('', '/kids'), '', 'sin la URL del servidor no hay botón');
    });
});

describe('la marca de la pestaña', () => {
    const vuelta = '/kids?compra=reanudar';

    test('se guarda sin datos personales y se lee mientras está viva', () => {
        const a = almacen();

        assert.equal(marcarSalida(a, { vuelta, compra, ahora: T0 }), true);
        assert.deepEqual(JSON.parse(a.datos.get(CLAVE_REANUDAR)), { vuelta, compra, en: T0 });
        assert.deepEqual(marcaViva(a, T0 + 60_000)?.compra, compra);
    });

    test('caduca: quien no volvió no se encuentra la compra a medias mañana', () => {
        const a = almacen();

        marcarSalida(a, { vuelta, compra, ahora: T0 });
        assert.notEqual(marcaViva(a, T0 + VIGENCIA_MS), null);
        assert.equal(marcaViva(a, T0 + VIGENCIA_MS + 1), null);
        assert.equal(marcaViva(a, T0 - 1), null, 'una marca del futuro no vale');
    });

    test('esta página es la vuelta solo con su parámetro Y una marca viva de su ruta', () => {
        const a = almacen();

        marcarSalida(a, { vuelta, compra, ahora: T0 });
        assert.equal(vuelveAqui(a, { ruta: '/kids', busqueda: '?compra=reanudar' }, T0 + 1000), true);
        assert.equal(vuelveAqui(a, { ruta: '/kids', busqueda: '' }, T0 + 1000), false, 'la misma página sin volver de Google');
        assert.equal(vuelveAqui(a, { ruta: '/registro/google', busqueda: '?compra=reanudar' }, T0 + 1000), false, 'otra página');
        assert.equal(vuelveAqui(almacen(), { ruta: '/kids', busqueda: '?compra=reanudar' }, T0), false, 'un enlace con el parámetro, sin marca');
    });

    test('se toma UNA vez', () => {
        const a = almacen();

        marcarSalida(a, { vuelta, compra, ahora: T0 });
        assert.equal(tomarMarca(a, T0 + 1)?.vuelta, vuelta);
        assert.equal(tomarMarca(a, T0 + 2), null);
        assert.equal(a.datos.has(CLAVE_REANUDAR), false);
    });

    test('una marca rota o ajena también se borra al tomarla, y no vale', () => {
        const a = almacen();

        a.setItem(CLAVE_REANUDAR, '{no es json');
        assert.equal(tomarMarca(a, T0), null);
        assert.equal(a.datos.has(CLAVE_REANUDAR), false);

        a.setItem(CLAVE_REANUDAR, JSON.stringify({ vuelta: '//evil.test', en: T0 }));
        assert.equal(marcaViva(a, T0), null, 'una vuelta que no es del sitio no se sigue aunque esté escrita');
    });

    test('sin almacén (cookies bloqueadas, modo privado) nada revienta y nada se reanuda', () => {
        assert.equal(marcarSalida(bloqueado, { vuelta, compra, ahora: T0 }), false);
        assert.equal(marcaViva(bloqueado, T0), null);
        assert.equal(tomarMarca(bloqueado, T0), null);
        assert.equal(marcarSalida(null, { vuelta, compra, ahora: T0 }), false);
        assert.equal(almacenDeLaPestana({ get sessionStorage() { throw new Error('SecurityError'); } }), null);
    });
});
