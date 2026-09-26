import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    PLEGABLES, correoDe, datosDe, descargoDe, googleDe, hayCambios, idiomasDe, interruptoresDe, recibosDe, reservaQueImpide,
    revisarCorreo, revisarDatosCuenta,
} from './ajustes.js';

/**
 * T5e — los Ajustes de Mi cuenta (`ajustes.js`), contra `PmcAjustes` del mockup y lo que la verdad cambia (`#773`·d).
 */
const NB = ' ';
const textos = {
    compra: { datos: { errores: { nombre: 'Escribe tu nombre y apellidos.', correo: 'Revisa el correo: falta algo.', clave: 'Escribe tu contraseña.' } } },
    mi_cuenta: {
        proxima: { numero: 'Nº :code' },
        ajustes: { firmado_el: 'Firmado el :fecha · versión :version' },
        correo: {
            enviado: 'Te hemos enviado un enlace a :nuevo. Hasta que lo abras, sigues entrando con :actual.',
            caduca: 'El enlace caduca en :minutos min.', caducado: 'El enlace ha caducado: pide otro.', mismo: 'Es el correo que ya tienes.',
        },
        descargo: { sin_firma: 'Aún no lo has firmado.', anterior: 'El descargo ha cambiado desde que lo firmaste: vuelve a firmarlo.' },
        borrar: { reserva: 'Tienes una reserva el :dia a las :hora. Mientras tengas una por celebrar, la cuenta no se puede borrar.' },
    },
};
const motor = { account: { privacy: { waiver: { status_external: 'Lo gestiona el parque fuera de esta web.', status_awaiting_verification: 'Quedará firmado en cuanto verifiques tu correo.' } } } };
const messages = { journal: { balance_pay_at_park: 'A pagar en el parque', balance_refund_pending: 'Pendiente de devolución' } };
const usuario = (extra = {}) => ({ name: 'Ana García López', email: 'ana@correo.es', phone: '612345214', locale: 'es', marketing_opt_in: true, analytics_opt_out: false, surveys_opt_out: false, pending_email: null, ...extra });

describe('los plegables', () => {
    test('cuatro, en el orden del diseño y con su icono', () => {
        assert.deepEqual(PLEGABLES.map((p) => [p.id, p.icono]), [['datos', 'user-round'], ['acceso', 'key-round'], ['privacidad', 'shield-check'], ['recibos', 'receipt']]);
    });
});

describe('Tus datos', () => {
    test('el formulario sale de GET /me, y «Guardar los cambios» solo con algo cambiado', () => {
        const d = datosDe(usuario());

        assert.deepEqual(d, { nombre: 'Ana García López', telefono: '612345214', idioma: 'es' });
        assert.equal(hayCambios(d, usuario()), false);
        assert.equal(hayCambios({ ...d, telefono: '612345215' }, usuario()), true);
        assert.equal(hayCambios({ ...d, idioma: 'fr' }, usuario()), true);
        assert.equal(hayCambios(d, null), false, 'sin el perfil aún no hay nada que guardar');
    });

    test('lo que falta se dice antes: el nombre', () => {
        assert.deepEqual(revisarDatosCuenta({ nombre: '  ', telefono: '', idioma: 'es' }, textos), { nombre: 'Escribe tu nombre y apellidos.' });
        assert.deepEqual(revisarDatosCuenta({ nombre: 'Ana', telefono: '', idioma: 'es' }, textos), {});
    });

    test('los idiomas son los de la instalación, no los dos del mockup', () => {
        const locales = [{ value: 'es', label: 'Español' }, { value: 'en', label: 'English' }, { value: 'fr', label: 'Français' }];

        assert.deepEqual(idiomasDe(locales).map((o) => o.value), ['es', 'en', 'fr']);
        assert.deepEqual(idiomasDe(undefined), []);
    });
});

describe('el correo', () => {
    const AHORA = Date.parse('2026-09-26T10:00:00Z');

    test('sin cambio pendiente, el actual', () => {
        assert.deepEqual(correoDe(usuario(), { textos, ahora: AHORA }), { actual: 'ana@correo.es', pendiente: null });
    });

    test('pendiente: a dónde, con cuál se sigue entrando y cuánto le queda (hacia arriba)', () => {
        const c = correoDe(usuario({ pending_email: 'ana@nuevo.es', pending_email_expires_at: '2026-09-26T10:41:30Z' }), { textos, ahora: AHORA });

        assert.equal(c.pendiente.correo, 'ana@nuevo.es');
        assert.equal(c.pendiente.texto, 'Te hemos enviado un enlace a ana@nuevo.es. Hasta que lo abras, sigues entrando con ana@correo.es.');
        assert.equal(c.pendiente.caduca, 'El enlace caduca en 42 min.');
    });

    test('pendiente y caducado: lo dice (reenviar lo resella)', () => {
        const c = correoDe(usuario({ pending_email: 'ana@nuevo.es', pending_email_expires_at: '2026-09-26T09:00:00Z' }), { textos, ahora: AHORA });

        assert.equal(c.pendiente.caduca, 'El enlace ha caducado: pide otro.');
    });

    test('el nuevo, antes de preguntar: con forma de correo, distinto del actual, y la contraseña (la pide el servidor)', () => {
        const deps = { actual: 'ana@correo.es', textos };

        assert.deepEqual(revisarCorreo({ correo: 'ana@', clave: 'x' }, deps), { correo: 'Revisa el correo: falta algo.' });
        assert.deepEqual(revisarCorreo({ correo: ' ANA@correo.es ', clave: 'x' }, deps), { correo: 'Es el correo que ya tienes.' });
        assert.deepEqual(revisarCorreo({ correo: 'ana@nuevo.es', clave: '' }, deps), { clave: 'Escribe tu contraseña.' });
        assert.deepEqual(revisarCorreo({ correo: 'ana@nuevo.es', clave: 'secreta123' }, deps), {});
    });
});

describe('Acceso · Google', () => {
    test('sin la lista aún, nada: «Vincular» a quien ya lo tiene sería mentir', () => {
        assert.equal(googleDe(null, { puedeVincular: true }), null);
    });

    test('vinculada, con el correo con que se vinculó', () => {
        assert.deepEqual(googleDe([{ provider: 'google', email_at_link: 'ana@gmail.com' }], { puedeVincular: true }), { vinculada: true, correo: 'ana@gmail.com' });
    });

    test('sin vincular: «Vincular» solo si la instalación lo ofrece', () => {
        assert.deepEqual(googleDe([], { puedeVincular: true }), { vinculada: false });
        assert.equal(googleDe([], { puedeVincular: false }), null);
    });
});

describe('Privacidad · el descargo', () => {
    const deps = { textos, motor };
    const firmas = [
        { id: 12, subject: 'dependent', dependent_id: 3, pdf_url: '/api/v1/me/waiver/12/pdf' },
        { id: 9, subject: 'holder', dependent_id: null, pdf_url: '/api/v1/me/waiver/9/pdf' },
    ];

    test('sin estado, desactivado o sin modo: la fila no sale', () => {
        assert.equal(descargoDe(null, deps), null);
        assert.equal(descargoDe({ mode: 'desactivado' }, deps), null);
        assert.equal(descargoDe({}, deps), null);
    });

    test('lo gestiona el parque: lo dice, y nada que firmar', () => {
        assert.deepEqual(descargoDe({ mode: 'externo', signed: true }, deps), { texto: 'Lo gestiona el parque fuera de esta web.', pdf: '', firmar: false });
    });

    test('aceptado al darse de alta y sin confirmar el correo: se firmará al confirmarlo (no «Firmar»: daría 409)', () => {
        assert.deepEqual(descargoDe({ mode: 'interno', signed: false, pending: true }, deps), { texto: 'Quedará firmado en cuanto verifiques tu correo.', pdf: '', firmar: false });
    });

    test('vigente: la fecha, la versión y SU PDF (no el de un hijo)', () => {
        const d = descargoDe({ mode: 'interno', signed: true, outdated: false, accepted_label: '14/09/2026', version: 3, signatures: firmas }, deps);

        assert.deepEqual(d, { texto: 'Firmado el 14/09/2026 · versión 3', pdf: '/api/v1/me/waiver/9/pdf', firmar: false });
    });

    test('sin firma: lo dice y ofrece firmar', () => {
        assert.deepEqual(descargoDe({ mode: 'interno', signed: false, pending: false, signatures: [] }, deps), { texto: 'Aún no lo has firmado.', pdf: '', firmar: true });
    });

    test('de una versión anterior: lo dice, conserva el PDF de lo firmado y ofrece firmar la nueva', () => {
        const d = descargoDe({ mode: 'interno', signed: true, outdated: true, signatures: firmas }, deps);

        assert.deepEqual(d, { texto: 'El descargo ha cambiado desde que lo firmaste: vuelve a firmarlo.', pdf: '/api/v1/me/waiver/9/pdf', firmar: true });
    });
});

describe('Privacidad · los interruptores', () => {
    test('encendido = lo recibe o lo vincula', () => {
        assert.deepEqual(interruptoresDe(usuario()), { novedades: true, analitica: true, encuestas: true });
        assert.deepEqual(interruptoresDe(usuario({ marketing_opt_in: false, analytics_opt_out: true, surveys_opt_out: true })), { novedades: false, analitica: false, encuestas: false });
    });
});

describe('Recibos: el libro de cada pedido', () => {
    const item = { date: '2026-09-26', product_name: 'Kids 1 hora', quantity_label: '2 niños' };
    const pedido = (ledger, extra = {}) => ({
        code: 'R-7K2P4', status: 'paid', items: [item],
        ledger: { total_cents: 2400, paid_cents: 2400, is_consistent: true, note: null, balance: { kind: 'settled', cents: 0, rest_at_park_cents: 0 }, settlements: [], ...ledger },
        ...extra,
    });
    const pago = (cents, extra = {}) => ({ kind: 'payment', label: 'Pagado online', amount_cents: cents, status: 'succeeded', ...extra });

    test('un pedido sin dinero movido (a medio pagar, caducado) no tiene recibo', () => {
        assert.deepEqual(recibosDe([pedido({ settlements: [] }, { status: 'pending' })], { textos, messages }), []);
    });

    test('qué, cuándo y su número arriba; debajo, las líneas de dinero con los rótulos del servidor', () => {
        const [r] = recibosDe([pedido({ settlements: [pago(2400)] })], { textos, messages });

        assert.equal(r.selection, 'sáb 26 sep · Kids 1 hora · 2 niños · Nº R-7K2P4');
        assert.deepEqual(r.lines, [{ label: 'Pagado online', value: `24${NB}€`, tone: undefined }]);
        assert.equal(r.total, `24${NB}€`, 'el total del libro: la pieza pinta su fila siempre');
        assert.equal(r.note, '');
    });

    test('una devolución que volvió, en positivo; una en curso, sin él (la dice su rótulo y no cuenta)', () => {
        const [r] = recibosDe([pedido({ settlements: [pago(2000), { kind: 'refund', label: 'Devuelto a la tarjeta', amount_cents: -2000, status: 'succeeded' }, { kind: 'refund', label: 'Devolución en curso', amount_cents: -400, status: 'pending' }] })], { textos, messages });

        assert.deepEqual(r.lines.map((l) => [l.label, l.value, l.tone]), [
            ['Pagado online', `20${NB}€`, undefined],
            ['Devuelto a la tarjeta', `−20${NB}€`, 'positive'],
            ['Devolución en curso', `−4${NB}€`, undefined],
        ]);
    });

    test('con señal, lo que queda se paga en el parque: su rótulo es el del libro del cajón', () => {
        const [r] = recibosDe([pedido({ total_cents: 16950, paid_cents: 5000, balance: { kind: 'pay_at_park', cents: 11950, rest_at_park_cents: 0 }, settlements: [pago(5000)] })], { textos, messages });

        assert.deepEqual(r.lines.at(-1), { label: 'A pagar en el parque', value: `119,50${NB}€` });
        assert.equal(r.total, `169,50${NB}€`, 'lo que vale el pedido, no lo pagado');
    });

    test('un libro que NO cierra: solo lo cobrado y la frase del servidor', () => {
        const [r] = recibosDe([pedido({ is_consistent: false, note: 'En revisión.', balance: { kind: 'under_review', cents: 0, rest_at_park_cents: 0 }, settlements: [pago(2400), { kind: 'refund', label: 'Devuelto a la tarjeta', amount_cents: -2400, status: 'succeeded' }] })], { textos, messages });

        assert.deepEqual(r.lines.map((l) => l.label), ['Pagado online']);
        assert.equal(r.note, 'En revisión.');
    });
});

describe('Borrar tu cuenta', () => {
    test('con una reserva por celebrar lo dice (el servidor lo niega); sin ninguna, nada', () => {
        const proxima = { reservation: { date: '2026-10-03', start_time: '11:30:00' } };

        assert.equal(reservaQueImpide(proxima, { textos }), 'Tienes una reserva el sábado 3 a las 11:30. Mientras tengas una por celebrar, la cuenta no se puede borrar.');
        assert.equal(reservaQueImpide(null, { textos }), '');
    });
});
