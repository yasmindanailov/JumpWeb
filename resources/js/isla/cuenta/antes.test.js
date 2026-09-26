import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { antesDe, chipDe } from './antes.js';

/**
 * T5c — «Antes de venir» (`antes.js`), contra `PmcAntes` de `paginas/mi-cuenta/bloques.jsx`: una tarea cada vez.
 */
const textos = {
    mi_cuenta: {
        antes: {
            siguiente: 'Siguiente', siguiente_chip: 'Siguiente: :n', hechas: ':a de :b hecho', ver_otra: 'Ver la otra',
            ver_mas: 'Ver las :n', ver_menos: 'Ver menos', hecho: 'Hecho', todo_listo: 'Todo listo para el :dia',
        },
    },
};
const ctx = { textos, locale: 'es', fecha: '2026-09-26' };
const accion = { label: 'Rellenar', url: '/reserva/7/datos-invitados', via: 'link' };
const formulario = (extra = {}) => ({ kind: 'guest_form', type: 'task', done: false, title: 'Formulario de invitados', note: 'Hasta el jueves 24',
    text: 'Formulario de invitados, hasta el jueves 24: quién viene, edades y alergias.', due: null, action: accion, ...extra });
const invitacion = (extra = {}) => ({ kind: 'invitation', type: 'task', done: false, title: 'Invitación', note: '6 de 10 confirmados',
    text: 'Invitación: los padres confirman y firman ellos. 6 de 10 confirmados.', due: 'Para el jueves 24',
    action: { label: 'Compartir por WhatsApp', url: 'https://wa.me/?text=x', via: 'whatsapp' }, ...extra });
const extras = { kind: 'extras', type: 'optional', done: false, title: 'Extras', note: null, text: 'Y si quieres: Tarta, hasta el jueves 24. Se pagan el día de la fiesta.', due: null, action: accion };
const autorizaciones = { kind: 'authorizations', type: 'status', done: false, title: null, note: null, text: 'Autorizaciones: 4 de 6 firmadas. Las que falten se firman en la puerta.', due: null, action: accion };

describe('«Antes de venir»', () => {
    test('sin tareas, el bloque no sale', () => {
        assert.equal(antesDe([], ctx), null);
        assert.equal(antesDe(null, ctx), null);
    });

    test('la siguiente por hacer va entera: «Siguiente», su plazo, la cabeza en negrita y su botón', () => {
        const a = antesDe([formulario(), invitacion(), extras, autorizaciones], ctx);

        assert.equal(a.siguiente.kind, 'guest_form');
        assert.equal(a.siguiente.icon, 'clipboard-list');
        assert.equal(a.siguiente.overline, 'Siguiente');
        assert.equal(a.siguiente.cabeza, 'Formulario de invitados, hasta el jueves 24: ');
        assert.equal(a.siguiente.resto, 'quién viene, edades y alergias.');
        assert.equal(a.siguiente.action.label, 'Rellenar');
        assert.deepEqual(a.progreso, { texto: '0 de 2 hecho', por: 0 });
    });

    test('el resto, en filas con su nota; lo informativo y lo opcional aparte, sin contar', () => {
        const a = antesDe([formulario(), invitacion(), extras, autorizaciones], ctx);

        assert.deepEqual(a.filas.map((f) => [f.kind, f.icon, f.note, f.cta]), [['invitation', 'send', '6 de 10 confirmados', '']]);
        assert.equal(a.estado.icon, 'file-signature');
        assert.equal(a.opcional.icon, 'cake');
        assert.equal(a.plegable, false);
    });

    test('con el formulario hecho, la siguiente es la invitación y lo hecho va al final, apagado, con «Hecho»', () => {
        const a = antesDe([formulario({ done: true }), invitacion()], ctx);

        assert.equal(a.siguiente.kind, 'invitation');
        assert.deepEqual(a.filas.map((f) => [f.kind, f.done, f.note, f.cta]), [['guest_form', true, '', 'Hecho']]);
        assert.deepEqual(a.progreso, { texto: '1 de 2 hecho', por: 0.5 });
    });

    test('con todo hecho, basta la línea verde con el día de la reserva, y las hechas no se repiten', () => {
        const a = antesDe([formulario({ done: true }), invitacion({ done: true }), autorizaciones], ctx);

        assert.equal(a.siguiente, null);
        assert.equal(a.todoListo, 'Todo listo para el sábado 26');
        assert.deepEqual(a.filas, []);
        assert.equal(a.estado.kind, 'authorizations', 'el estado se sigue diciendo');
    });

    test('una sola tarea no lleva «N de M hecho»', () => {
        assert.equal(antesDe([formulario()], ctx).progreso, null);
    });

    test('solo lo opcional o lo informativo: el bloque sale, sin «Todo listo» (no había nada que hacer)', () => {
        const a = antesDe([extras], ctx);

        assert.equal(a.siguiente, null);
        assert.equal(a.todoListo, '');
        assert.equal(a.opcional.kind, 'extras');
    });

    test('más de dos filas se pliegan: «Ver la otra» con una de más, «Ver las N» con más', () => {
        const tres = [formulario(), invitacion(), invitacion({ kind: 'x1' }), invitacion({ kind: 'x2' })];
        assert.equal(antesDe(tres, ctx).plegable, true);
        assert.equal(antesDe(tres, ctx).verMas, 'Ver la otra');
        assert.equal(antesDe([...tres, invitacion({ kind: 'x3' })], ctx).verMas, 'Ver las 2');
        assert.equal(antesDe([formulario(), invitacion(), invitacion({ kind: 'x1' })], ctx).plegable, false);
    });

    test('una frase sin «:» va entera, sin cabeza', () => {
        assert.deepEqual([antesDe([formulario({ text: 'Rellénalo' })], ctx).siguiente.cabeza, antesDe([formulario({ text: 'Rellénalo' })], ctx).siguiente.resto], ['', 'Rellénalo']);
    });
});

describe('el chip de arriba', () => {
    test('«Siguiente: …» con la primera por HACER; lo opcional no enciende nada', () => {
        assert.equal(chipDe([formulario(), invitacion()], { textos }), 'Siguiente: Formulario de invitados');
        assert.equal(chipDe([formulario({ done: true }), invitacion()], { textos }), 'Siguiente: Invitación');
        assert.equal(chipDe([formulario({ done: true }), extras, autorizaciones], { textos }), '');
        assert.equal(chipDe(null, { textos }), '');
    });
});
