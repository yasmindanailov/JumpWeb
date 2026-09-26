import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { cambiarDe, estadoDe, filaHistorial, hojaDelDia, lineasDe, pagoDe, reservasDeCuenta, tarjetaDe, tituloDe } from './reservas.js';

/** Las reservas de Mi cuenta (T5b, `DECISIONES #775`, spec §4.13), con tarjetas de la forma de la API. */

const NB = ' ';
const textos = {
    mi_cuenta: {
        proxima: {
            numero: 'Nº :code', aria: ':dia a las :hora', senal: 'Señal pagada: :importe', resto: 'El día de la fiesta: :importe',
            senal_rotulo: 'Señal pagada', resto_rotulo: 'El día de la fiesta', complemento: ':nombre · :cantidad',
            plazo: 'Puedes cambiar o cancelar hasta el :dia a las :hora.',
            fuera: 'Quedan menos de :tramo: ya no se puede cambiar ni cancelar. Si ha pasado algo, escríbenos y lo vemos.',
            fuera_sin_tramo: 'Ya no se puede cambiar ni cancelar. Si ha pasado algo, escríbenos y lo vemos.',
            total: 'Total', incluido: 'Incluido',
        },
        otras: { estado: { pasada: 'Pasada', cancelada: 'Cancelada', devuelta: 'Devuelta', sin_pagar: 'Sin pagar' } },
        cambiar: {
            texto: 'Para tu reserva del :dia a las :hora, :que, número :code. Puedes cambiar la fecha o la hora, o cancelar, :plazo: escríbenos y lo hacemos contigo.',
            texto_sin_plazo: 'Para tu reserva del :dia a las :hora, :que, número :code. Escríbenos y lo vemos contigo.',
            devolvemos: ', y te devolvemos la señal', mensaje: 'Hola, quiero cambiar o cancelar mi reserva :code del :dia a las :hora.',
            llamar: 'Llamar al :telefono',
        },
    },
};
const ctx = { locale: 'es', textos };

const libro = (total, extra = {}) => ({ total_cents: total, paid_cents: total, balance: { kind: 'settled', cents: 0, rest_at_park_cents: 0 }, movements: [{ kind: 'booking', label: 'Reserva hecha', amount_cents: total }], is_consistent: true, note: null, ...extra });
const entrada = (extra = {}) => ({
    reservation: {
        id: 1, product_name: 'Kids 1 hora', date: '2026-09-26', time_window: '17:00–18:00', start_time: '17:00:00', quantity: 2,
        charged_subtotal_cents: 2000, quantity_label: '2 niños', status: 'active', cancelled: false, shows_deposit_note: false,
        addons: [{ id: 2, product_name: 'Calcetines', quantity: 2, charged_subtotal_cents: 400, quantity_label: '2 unidades', note: 'Tenéis 2 pares de calcetines comprados; os los damos en la puerta.' }],
        ledger: libro(2400), today: false, product_id: 101,
        cancellation: { cutoff_hours: 24, written: `hasta 24${NB}h antes`, span: `24${NB}h`, until: '2026-09-25T17:00:00+02:00', open: true, deposit_refundable: false },
        ...extra,
    },
    order: { code: 'R-7K2P4', status: 'paid', created_label: '24/09/2026', can_be_retried: false },
});
const cumple = () => {
    const c = entrada({
        product_name: 'Pack Kids', quantity_label: '10 niños', charged_subtotal_cents: 16950, addons: [], shows_deposit_note: true,
        ledger: libro(16950, { paid_cents: 5000, balance: { kind: 'pay_at_park', cents: 11950, rest_at_park_cents: 0 } }),
        cancellation: { cutoff_hours: 72, written: `hasta 3${NB}días antes`, span: `3${NB}días`, until: '2026-09-23T17:00:00+02:00', open: true, deposit_refundable: true },
    });
    c.order.code = 'R-5F2K8';

    return c;
};

describe('la próxima, las otras y el historial', () => {
    test('la próxima es la primera de un pedido PAGADO; uno a medio pagar no cuenta', () => {
        const pendiente = { reservation: { id: 9 }, order: { status: 'pending' } };
        const a = entrada();
        const b = entrada({ id: 3 });

        assert.deepEqual(reservasDeCuenta({ proximas: [pendiente, a, b], pasadas: [] }), { proxima: a, otras: [b], historial: [] });
    });

    test('sin nada cargado, sin próxima', () => {
        assert.deepEqual(reservasDeCuenta({ proximas: null }), { proxima: null, otras: [], historial: [] });
    });
});

describe('la tarjeta (`BookingCard`)', () => {
    test('la hoja del día sin puntos, la hora, qué y cuántos, el número y su nombre accesible', () => {
        assert.deepEqual(tarjetaDe(entrada(), ctx), {
            day: { dow: 'sáb', n: '26', month: 'sep' }, time: '17:00', title: 'Kids 1 hora · 2 niños', code: 'Nº R-7K2P4',
            aria: 'Sábado 26 de septiembre a las 17:00, Kids 1 hora · 2 niños',
        });
    });

    test('WCAG 2.5.3: el nombre accesible CONTIENE lo que se lee (la fila es un botón y su nombre sustituye al contenido)', () => {
        const fila = filaHistorial({ ...entrada(), order: { code: 'R-7K2P4', status: 'refunded' } }, ctx);

        assert.equal(fila.aria, 'Sábado 26 de septiembre a las 17:00, Kids 1 hora · 2 niños, Devuelta');
        assert.ok(fila.aria.includes(fila.title) && fila.aria.includes(fila.status));
    });

    test('la hoja, en otro idioma', () => {
        assert.deepEqual(hojaDelDia('2026-09-26', 'en'), { dow: 'sat', n: '26', month: 'sep' });
        assert.deepEqual(hojaDelDia('2026-09-26', 'fr'), { dow: 'sam', n: '26', month: 'sep' });
    });

    test('el título es qué y cuántos, tal cual los compone el servidor', () => {
        assert.equal(tituloDe({ product_name: 'Jump 1 hora', quantity_label: '2 entradas' }), 'Jump 1 hora · 2 entradas');
    });
});

describe('las líneas bajo la tarjeta', () => {
    test('una entrada: el aviso del complemento que escribió el panel y el plazo, dentro', () => {
        assert.deepEqual(lineasDe(entrada(), ctx), [
            { icon: 'package', texto: 'Tenéis 2 pares de calcetines comprados; os los damos en la puerta.' },
            { icon: 'calendar-clock', texto: 'Puedes cambiar o cancelar hasta el viernes 25 a las 17:00.' },
        ]);
    });

    test('`#775`: sin aviso, el complemento se nombra con su cantidad, sin prometer nada', () => {
        const sinAviso = entrada({ addons: [{ product_name: 'Calcetines', quantity_label: '2 unidades', note: null }] });

        assert.equal(lineasDe(sinAviso, ctx)[0].texto, 'Calcetines · 2 unidades');
    });

    test('un cumpleaños con señal: lo pagado y lo del día de la fiesta, del LIBRO, y el plazo', () => {
        assert.deepEqual(lineasDe(cumple(), ctx).map((l) => l.texto), [
            `Señal pagada: 50${NB}€`, `El día de la fiesta: 119,50${NB}€`, 'Puedes cambiar o cancelar hasta el miércoles 23 a las 17:00.',
        ]);
        assert.equal(lineasDe(cumple(), ctx)[0].fuerte, true);
    });

    test('fuera de plazo no se promete lo que ya no se puede: se dice qué queda', () => {
        const fuera = entrada({ addons: [], cancellation: { cutoff_hours: 24, written: '', span: `24${NB}h`, until: '2026-09-25T17:00:00+02:00', open: false, deposit_refundable: false } });

        assert.deepEqual(lineasDe(fuera, ctx), [{ icon: 'clock-alert', texto: `Quedan menos de 24${NB}h: ya no se puede cambiar ni cancelar. Si ha pasado algo, escríbenos y lo vemos.` }]);
    });

    test('un plazo de cero horas, pasado, no nombra un tramo; sin plazo publicado, nada del plazo', () => {
        const cero = entrada({ addons: [], cancellation: { cutoff_hours: 0, written: 'hasta la hora reservada', span: null, until: '2026-09-26T17:00:00+02:00', open: false, deposit_refundable: false } });
        assert.equal(lineasDe(cero, ctx)[0].texto, 'Ya no se puede cambiar ni cancelar. Si ha pasado algo, escríbenos y lo vemos.');
        assert.deepEqual(lineasDe(entrada({ addons: [], cancellation: null }), ctx), []);
    });
});

describe('«Ver el pago»', () => {
    test('cada línea con lo cobrado, y el total del libro', () => {
        assert.deepEqual(pagoDe(entrada(), ctx), {
            total: `24${NB}€`, totalLabel: 'Total', now: null, later: null, note: '',
            lines: [{ label: 'Kids 1 hora · 2 niños', value: `20${NB}€` }, { label: 'Calcetines · 2 unidades', value: `4${NB}€` }],
        });
    });

    test('con señal: «Señal pagada» y «El día de la fiesta»', () => {
        const p = pagoDe(cumple(), ctx);

        assert.deepEqual([p.now, p.later], [{ label: 'Señal pagada', value: `50${NB}€` }, { label: 'El día de la fiesta', value: `119,50${NB}€` }]);
    });

    test('lo incluido sin cargo dice «Incluido»', () => {
        const menu = entrada({ addons: [{ product_name: 'Menú 1', quantity_label: '2 unidades', charged_subtotal_cents: 0 }], ledger: libro(2000) });

        assert.equal(pagoDe(menu, ctx).lines[1].value, 'Incluido');
    });

    test('si las líneas no suman el total del libro, cuentan los MOVIMIENTOS del libro (nunca un recibo que no cuadra)', () => {
        const cortesia = entrada({ ledger: libro(1900, { movements: [{ label: 'Reserva hecha', amount_cents: 2400 }, { label: 'Cortesía', amount_cents: -500 }] }) });

        assert.deepEqual(pagoDe(cortesia, ctx).lines, [{ label: 'Reserva hecha', value: `24${NB}€` }, { label: 'Cortesía', value: `−5${NB}€` }]);
        assert.equal(pagoDe(cortesia, ctx).total, `19${NB}€`);
    });

    test('si el libro NO cierra, ninguna línea: el total y la frase del servidor (`DECISIONES #132`)', () => {
        const roto = entrada({ ledger: libro(2400, { is_consistent: false, note: 'Estamos revisando este pedido.' }) });

        assert.deepEqual(pagoDe(roto, ctx).lines, []);
        assert.equal(pagoDe(roto, ctx).note, 'Estamos revisando este pedido.');
    });
});

describe('el historial', () => {
    test('cancelada, devuelta, sin pagar o pasada', () => {
        const con = (r, o) => ({ reservation: { cancelled: false, ...r }, order: { status: 'paid', ...o } });

        assert.equal(estadoDe(con({ cancelled: true })), 'cancelada');
        assert.equal(estadoDe(con({}, { status: 'cancelled' })), 'cancelada');
        assert.equal(estadoDe(con({}, { status: 'refunded' })), 'devuelta');
        assert.equal(estadoDe(con({}, { status: 'expired' })), 'sin_pagar');
        assert.equal(estadoDe(con({}, { status: 'pending' })), 'sin_pagar');
        assert.equal(estadoDe(con({ status: 'finished' })), 'pasada');
    });

    test('una fila: la tarjeta con su estado escrito', () => {
        assert.equal(filaHistorial(entrada({ status: 'finished' }), ctx).status, 'Pasada');
    });
});

describe('«Cambiar o cancelar»', () => {
    test('dentro de plazo: el texto del diseño, el mensaje ya escrito, WhatsApp y llamar con el teléfono del parque', () => {
        const c = cambiarDe(entrada(), { ...ctx, telefono: '+34 641 99 57 14' });

        assert.equal(c.fuera, false);
        assert.equal(c.texto, `Para tu reserva del sábado 26 a las 17:00, Kids 1 hora, 2 niños, número R-7K2P4. Puedes cambiar la fecha o la hora, o cancelar, hasta 24${NB}h antes: escríbenos y lo hacemos contigo.`);
        assert.equal(c.mensaje, 'Hola, quiero cambiar o cancelar mi reserva R-7K2P4 del sábado 26 a las 17:00.');
        assert.equal(c.whatsapp, `https://wa.me/34641995714?text=${encodeURIComponent(c.mensaje)}`);
        assert.deepEqual(c.llamar, { href: 'tel:+34641995714', texto: 'Llamar al +34 641 99 57 14' });
    });

    test('`#775`: «y te devolvemos la señal» SOLO si el producto lo promete', () => {
        assert.ok(cambiarDe(cumple(), ctx).texto.includes(`hasta 3${NB}días antes, y te devolvemos la señal: escríbenos`));

        const sinPromesa = cumple();
        sinPromesa.reservation.cancellation = { ...sinPromesa.reservation.cancellation, deposit_refundable: false };
        assert.doesNotMatch(cambiarDe(sinPromesa, ctx).texto, /señal/);
    });

    test('fuera de plazo, su aviso; sin plazo publicado, «escríbenos y lo vemos contigo»', () => {
        const fuera = entrada({ cancellation: { cutoff_hours: 24, written: '', span: `24${NB}h`, until: '', open: false, deposit_refundable: false } });
        assert.equal(cambiarDe(fuera, ctx).fuera, true);
        assert.match(cambiarDe(fuera, ctx).texto, /^Quedan menos de 24/);

        assert.equal(cambiarDe(entrada({ cancellation: null }), ctx).texto, 'Para tu reserva del sábado 26 a las 17:00, Kids 1 hora, 2 niños, número R-7K2P4. Escríbenos y lo vemos contigo.');
    });

    test('sin teléfono del parque, ni WhatsApp ni llamada', () => {
        const c = cambiarDe(entrada(), ctx);

        assert.equal(c.whatsapp, '');
        assert.equal(c.llamar, null);
    });
});
