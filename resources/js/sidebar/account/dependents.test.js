import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    DEPENDENTS_PER_PAGE, DEPENDENT_WAIVER_SIGN, DEPENDENT_WAIVER_VERIFY,
    bornOnLabel, clampPage, coverageKey, dependentForm, dependentNeedsSignature,
    dependentWaiverAction, dependentWaiverKey, dependentsPager, dependentsView, lastPageOf,
    pageSlice, replaceDependent, signupNeedsWaiver, signupWaiverDocumentId,
} from './dependents.js';

/**
 * Lo que el cliente SÍ decide sobre un menor a cargo: qué frase, si se ofrece firmar y cómo se lee su
 * fecha. Lo que NO decide —edad, minoría, estado de la exención— lo publica el servidor y aquí se
 * fija que solo se TRADUCE (`CE-4`).
 */
const minor = (waiver = {}) => ({ id: 1, name: 'Lucas', born_on: '2017-03-12', age: 9, is_minor: true, waiver: { mode: 'interno', signed: false, outdated: false, ...waiver } });

describe('el formulario de alta', () => {
    // `#236`: eran dos campos y ahora son CUATRO. `relationship` nace vacío a propósito para que
    // el desplegable obligue a elegir, en vez de colar un valor por defecto que nadie ha mirado.
    // `#441`: y la CASILLA de la exención, desmarcada — declarar y aceptar son un solo gesto.
    test('nace vacío, con los campos que el servidor acepta y la casilla SIN marcar', () => {
        assert.deepEqual(dependentForm(), { name: '', surname: '', relationship: '', born_on: '', accept_waiver: false });
    });
});

/**
 * `#441` · qué decide si el alta PINTA la casilla. ⚠️ Lo dice el TEXTO SERVIDO y no el cliente: sin
 * documento firmable —modo `externo`, o `interno` sin versión publicada— no hay nada que aceptar y
 * exigirlo dejaría a esa instalación sin poder declarar un menor.
 */
describe('la exención en el alta', () => {
    test('con texto servido se pide, y su id viaja con el alta', () => {
        assert.equal(signupNeedsWaiver({ id: 7, sections: [] }), true);
        assert.equal(signupWaiverDocumentId({ id: 7, sections: [] }), 7);
    });

    test('⚠️ sin texto servido NO se pide nada, y no viaja ningún id', () => {
        // Los dos campos se OMITEN del cuerpo: el esquema es `additionalProperties: false`, así que
        // mandarlos a `null` sería un 422 en una instalación que no los pide.
        for (const vacio of [null, undefined, {}, { id: null }]) {
            assert.equal(signupNeedsWaiver(vacio), false);
            assert.equal(signupWaiverDocumentId(vacio), null);
        }
    });
});

describe('la fecha de nacimiento', () => {
    test('se reordena a dd/mm/aaaa sin pasar por Date', () => {
        assert.equal(bornOnLabel('2017-03-12'), '12/03/2017');
    });

    test('lo que no tenga esa forma sale tal cual, nunca vacío', () => {
        assert.equal(bornOnLabel('12/03/2017'), '12/03/2017');
        assert.equal(bornOnLabel(null), '');
    });
});

describe('la frase de la exención', () => {
    test('fuera del modo interno no hay nada que decir', () => {
        assert.equal(dependentWaiverKey(minor({ mode: 'externo' })), '');
        assert.equal(dependentWaiverKey(minor({ mode: 'desactivado' })), '');
        assert.equal(dependentWaiverKey(null), '');
    });

    test('en interno: sin firmar, anterior o firmada, según lo que dijo el servidor', () => {
        assert.equal(dependentWaiverKey(minor()), 'account.dependents.waiver_unsigned');
        assert.equal(dependentWaiverKey(minor({ signed: true, outdated: true })), 'account.dependents.waiver_outdated');
        assert.equal(dependentWaiverKey(minor({ signed: true })), 'account.dependents.waiver_current');
    });
});

describe('cuándo se ofrece firmar en su nombre', () => {
    test('en interno, siendo menor, sin firma o con una anterior', () => {
        assert.equal(dependentNeedsSignature(minor()), true);
        assert.equal(dependentNeedsSignature(minor({ signed: true, outdated: true })), true);
        assert.equal(dependentNeedsSignature(minor({ signed: true })), false);
    });

    test('⚠️ nunca fuera del modo interno ni para quien ya tiene 18: el servidor lo rechazaría', () => {
        assert.equal(dependentNeedsSignature(minor({ mode: 'externo' })), false);
        assert.equal(dependentNeedsSignature({ ...minor(), is_minor: false }), false);
        assert.equal(dependentNeedsSignature(null), false);
    });
});

/**
 * `#441` · **«falta firma» y «puede firmarla» no son la misma pregunta**, y confundirlas fue el
 * defecto: `WaiverSigner` exige el correo del titular verificado para firmar por un menor a cargo,
 * así que con el correo sin verificar la tarjeta ofrecía un botón que **solo podía devolver 409**.
 * Es el mismo defecto que `#329` arregló para el TITULAR, que no pasó por esta puerta.
 */
describe('qué se le OFRECE en la tarjeta', () => {
    test('con el correo verificado, firmar', () => {
        assert.equal(dependentWaiverAction(minor(), true), DEPENDENT_WAIVER_SIGN);
    });

    test('❗ sin el correo verificado, VERIFICAR — nunca firmar', () => {
        assert.equal(dependentWaiverAction(minor(), false), DEPENDENT_WAIVER_VERIFY);
        assert.equal(dependentWaiverAction(minor({ signed: true, outdated: true }), false), DEPENDENT_WAIVER_VERIFY);
    });

    test('⚠️ sin contexto cargado (undefined) se ofrece FIRMAR: esconderlo a quien sí puede es peor', () => {
        assert.equal(dependentWaiverAction(minor(), undefined), DEPENDENT_WAIVER_SIGN);
    });

    test('y si no falta firma, no se ofrece nada — tampoco verificar', () => {
        // ⚠️ CONTROL: sin esta rama, un menor ya firmado le pediría al titular verificar su correo
        // por una firma que no hace falta.
        assert.equal(dependentWaiverAction(minor({ signed: true }), false), null);
        assert.equal(dependentWaiverAction(minor({ mode: 'externo' }), false), null);
        assert.equal(dependentWaiverAction(null, false), null);
    });
});

describe('la cobertura', () => {
    test('se marca a quien ya tiene 18; a un menor no se le dice nada', () => {
        assert.equal(coverageKey({ ...minor(), is_minor: false }), 'account.dependents.adult');
        assert.equal(coverageKey(minor()), '');
        assert.equal(coverageKey(null), '');
    });
});

describe('colocar la respuesta del servidor en la lista', () => {
    test('sustituye por id sin cambiar el orden, y añade si no estaba', () => {
        const list = [minor(), { ...minor(), id: 2, name: 'Vera' }];
        const signed = minor({ signed: true });

        assert.deepEqual(replaceDependent(list, signed).map((d) => [d.id, d.waiver.signed]), [[1, true], [2, false]]);
        assert.equal(replaceDependent(list, { ...minor(), id: 3 }).length, 3);
        assert.deepEqual(replaceDependent(null, minor()), [minor()]);
    });
});

/**
 * **La lista paginada y el alta desplegable** (2026-08-28, encargo del owner).
 *
 * ⚠️ Lo que de verdad se prueba aquí no es «cortar un array de seis en seis»: son las dos
 * transiciones que la pantalla no puede equivocarse — dónde queda el cliente DESPUÉS de añadir (el
 * nuevo cae al final de la lista, y con paginación ese final puede estar en otra página) y después de
 * quitar (la página en la que estaba puede haberse quedado vacía).
 */
const many = (n) => Array.from({ length: n }, (_, i) => ({ id: i + 1, name: `M${i + 1}` }));

const messages = {
    account: {
        dependents: {
            pagination: { label: 'Paginación de menores', prev: 'Anteriores', next: 'Siguientes', page: 'Página :current de :last' },
        },
    },
};

describe('cuántas páginas hay', () => {
    test('una lista vacía sigue estando en la página 1: nunca 0', () => {
        assert.equal(lastPageOf(0), 1);
        assert.equal(lastPageOf(null), 1);
    });

    test('el corte es exacto en el múltiplo y sube en el siguiente', () => {
        assert.equal(lastPageOf(6, 6), 1);
        assert.equal(lastPageOf(7, 6), 2);
    });

    test('con el tope por defecto del servidor (20) y con el máximo que admite el panel (100)', () => {
        assert.equal(lastPageOf(20, DEPENDENTS_PER_PAGE), 4);
        assert.equal(lastPageOf(100, DEPENDENTS_PER_PAGE), 17);
    });
});

describe('la página pedida se mete en lo que existe', () => {
    test('por debajo de la primera y por encima de la última', () => {
        assert.equal(clampPage(0, 20, 6), 1);
        assert.equal(clampPage(-3, 20, 6), 1);
        assert.equal(clampPage(99, 20, 6), 4);
    });

    test('lo que no es un número cae en la primera, no en NaN', () => {
        assert.equal(clampPage(undefined, 20, 6), 1);
        assert.equal(clampPage('x', 20, 6), 1);
    });
});

describe('las filas de una página', () => {
    test('la ventana es la que toca y la última página trae el resto', () => {
        assert.deepEqual(pageSlice(many(7), 1, 3).map((r) => r.id), [1, 2, 3]);
        assert.deepEqual(pageSlice(many(7), 3, 3).map((r) => r.id), [7]);
    });

    test('una página fuera de rango devuelve la última CON filas, nunca un hueco', () => {
        assert.deepEqual(pageSlice(many(7), 9, 3).map((r) => r.id), [7]);
    });

    test('sin lista no revienta', () => {
        assert.deepEqual(pageSlice(null, 1, 3), []);
    });
});

describe('el paginador', () => {
    test('⚠️ es `null` si todo cabe en una página: una cuenta con dos menores no ve una barra de páginas', () => {
        assert.equal(dependentsPager(2, 1, messages, 6), null);
        assert.equal(dependentsPager(6, 1, messages, 6), null);
        assert.equal(dependentsPager(0, 1, messages, 6), null);
    });

    test('con más de una página trae los rótulos del grupo y las dos puertas', () => {
        const pager = dependentsPager(7, 1, messages, 6);

        assert.equal(pager.current, 1);
        assert.equal(pager.last, 2);
        assert.equal(pager.canPrev, false);
        assert.equal(pager.canNext, true);
        assert.equal(pager.label, 'Paginación de menores');
        assert.equal(pager.prevLabel, 'Anteriores');
        assert.equal(pager.nextLabel, 'Siguientes');
        assert.equal(pager.pageLabel, 'Página 1 de 2');
    });

    test('en la última se puede volver y no avanzar, y la página se recorta', () => {
        const pager = dependentsPager(7, 99, messages, 6);

        assert.equal(pager.current, 2);
        assert.equal(pager.canPrev, true);
        assert.equal(pager.canNext, false);
        assert.equal(pager.pageLabel, 'Página 2 de 2');
    });
});

describe('el estado de la pantalla', () => {
    test('nace en la primera página y con el alta PLEGADA', () => {
        const view = dependentsView();

        assert.equal(view.page, 1);
        assert.equal(view.adding, false);
        assert.deepEqual(view.form, dependentForm());
    });

    test('abrir despliega con el formulario limpio; cancelar pliega y tira lo tecleado', () => {
        const view = dependentsView();

        view.form.name = 'a medias';
        view.open();
        assert.equal(view.adding, true);
        assert.deepEqual(view.form, dependentForm());

        view.form.name = 'otra vez';
        view.cancel();
        assert.equal(view.adding, false);
        assert.deepEqual(view.form, dependentForm());
    });

    test('⚠️ tras AÑADIR salta a la página donde ha caído el nuevo, que es la última', () => {
        const view = dependentsView(6);

        view.form.name = 'Lior';
        view.added(7);

        assert.equal(view.adding, false, 'el alta se pliega sola al guardar bien');
        assert.deepEqual(view.form, dependentForm());
        assert.equal(view.page, 2, 'con seis por página, el séptimo está en la 2 — si no se salta, no se ve');
    });

    test('añadir dentro de la primera página no mueve al cliente de sitio', () => {
        const view = dependentsView(6);

        view.added(3);
        assert.equal(view.page, 1);
    });

    test('⚠️ tras QUITAR el único de la última página, la página se recoloca en vez de quedarse en blanco', () => {
        const view = dependentsView(6);

        view.go(2, 7);
        assert.equal(view.page, 2);

        view.removed(6);
        assert.equal(view.page, 1);
    });

    test('quitar sin vaciar la página deja al cliente donde estaba', () => {
        const view = dependentsView(6);

        view.go(2, 13);
        view.removed(12);

        assert.equal(view.page, 2);
    });

    test('ir a una página que no existe no lleva a ninguna parte rara', () => {
        const view = dependentsView(6);

        view.go(99, 7);
        assert.equal(view.page, 2);

        view.go(0, 7);
        assert.equal(view.page, 1);
    });

    test('`rows()` devuelve la página que se mira, no la lista entera', () => {
        const view = dependentsView(3);

        view.go(2, 7);

        assert.deepEqual(view.rows(many(7)).map((r) => r.id), [4, 5, 6]);
    });
});
