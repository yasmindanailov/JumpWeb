import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { VISTA, ckDeCuenta, lineaProxima, vistaDeApertura } from './vista.js';

/** Mi cuenta en la isla, lo que decide (T5a, `DECISIONES #773`, spec §4.13). */

const textos = {
    mi_cuenta: { titulo: 'Mi cuenta', qr: { titulo: 'Tu QR' } },
    mi_cuenta_alta: { crear_titulo: 'Crea tu cuenta', crear_boton: 'Crear mi cuenta', creando: 'Creando tu cuenta' },
    compra: { entrar: { titular: 'Entra', continuar: 'Continuar', cargando: 'Entrando' }, datos: { olvido: '¿Has olvidado tu contraseña?' } },
};
const acciones = {
    cerrar: () => 'cerrar', alMenu: () => 'menu', aInicio: () => 'inicio', aEntrar: () => 'entrar',
    entrar: () => 'entrar!', crear: () => 'crear!', completarGoogle: () => 'google!', volverDelDescargo: () => 'formulario',
    aReserva: () => 'reserva', escribir: () => 'whatsapp!',
};
const ck = (e) => ckDeCuenta({ textos, acciones, ...e });

describe('con qué vista se abre Mi cuenta', () => {
    test('con sesión: el inicio; el carné, Tu QR; «Mis reservas» de los correos, el inicio en la próxima', () => {
        assert.deepEqual(vistaDeApertura('home', { sesion: true }), { vista: VISTA.INICIO, bloque: '' });
        assert.deepEqual(vistaDeApertura('card', { sesion: true }), { vista: VISTA.QR, bloque: '' });
        assert.deepEqual(vistaDeApertura('orders', { sesion: true }), { vista: VISTA.INICIO, bloque: 'proxima' });
    });

    test('el bloque de un enlace (`#mi-cuenta/antes`) manda sobre el de la zona', () => {
        assert.deepEqual(vistaDeApertura('home', { sesion: true, bloque: 'antes' }), { vista: VISTA.INICIO, bloque: 'antes' });
        assert.deepEqual(vistaDeApertura('orders', { sesion: true, bloque: 'ajustes' }), { vista: VISTA.INICIO, bloque: 'ajustes' });
    });

    test('sin sesión, Mi cuenta abre Entrar; el alta, Crea tu cuenta; el olvido, Entrar (sale de ahí con el correo)', () => {
        for (const zona of ['home', 'orders', 'card', 'login', 'forgot']) {
            assert.equal(vistaDeApertura(zona, { sesion: false }).vista, VISTA.ENTRAR, zona);
        }
        assert.equal(vistaDeApertura('register', { sesion: false }).vista, VISTA.CREAR);
    });

    test('completar el alta de Google va antes que la sesión: a esa puerta se llega sin ella', () => {
        assert.equal(vistaDeApertura('google-signup', { sesion: false }).vista, VISTA.ALTA_GOOGLE);
        assert.equal(vistaDeApertura('google-signup', { sesion: true }).vista, VISTA.ALTA_GOOGLE);
    });

    test('con sesión, las puertas de invitado llevan al inicio (quien ya entró no vuelve a Entrar)', () => {
        assert.equal(vistaDeApertura('login', { sesion: true }).vista, VISTA.INICIO);
        assert.equal(vistaDeApertura('register', { sesion: true }).vista, VISTA.INICIO);
    });
});

describe('la próxima reserva en una línea', () => {
    test('el día corto con mayúscula, la hora de inicio y qué', () => {
        assert.equal(
            lineaProxima({ date: '2026-09-26', time_window: '17:00–18:00', product_name: 'Kids 1 hora' }, { locale: 'es' }),
            'Sáb 26 · 17:00 · Kids 1 hora',
        );
    });

    test('en otro idioma, su día', () => {
        assert.equal(lineaProxima({ date: '2026-09-26', time_window: '17:00-18:00', product_name: 'Kids 1 hour' }, { locale: 'en' }), 'Sat 26 · 17:00 · Kids 1 hour');
    });

    test('sin reserva, nada', () => {
        assert.equal(lineaProxima(null), '');
        assert.equal(lineaProxima({}), '');
    });

    test('con las reservas ya cargadas, qué Y cuántos (T5b)', () => {
        assert.equal(
            lineaProxima({ date: '2026-09-26', time_window: '17:00–18:00', product_name: 'Kids 1 hora' }, { locale: 'es', titulo: 'Kids 1 hora · 2 niños' }),
            'Sáb 26 · 17:00 · Kids 1 hora · 2 niños',
        );
    });
});

describe('las vistas de las reservas (T5b)', () => {
    const t5b = { ...textos, mi_cuenta: { ...textos.mi_cuenta, reserva: { titulo: 'Tu reserva' }, cambiar: { banda: 'Cambiar o cancelar', boton: 'Escribirnos por WhatsApp' } } };
    const ck5 = (e) => ckDeCuenta({ textos: t5b, acciones, ...e });

    test('Tu reserva: su banda, sin acción, y la flecha vuelve a Mi cuenta', () => {
        const r = ck5({ vista: VISTA.RESERVA, desde: 'menu' });

        assert.equal(r.step, 'Tu reserva');
        assert.equal(r.onBack(), 'inicio');
        assert.equal(r.action, null);
    });

    test('Cambiar o cancelar: desde la reserva abierta vuelve a ella; desde la próxima, a Mi cuenta', () => {
        assert.equal(ck5({ vista: VISTA.CAMBIAR, cambiar: { desdeReserva: true, whatsapp: true } }).onBack(), 'reserva');
        assert.equal(ck5({ vista: VISTA.CAMBIAR, cambiar: { desdeReserva: false, whatsapp: true } }).onBack(), 'inicio');
    });

    test('su acción es escribir por WhatsApp; sin teléfono del parque, ninguna', () => {
        const con = ck5({ vista: VISTA.CAMBIAR, cambiar: { desdeReserva: false, whatsapp: true } });

        assert.equal(con.step, 'Cambiar o cancelar');
        assert.equal(con.action.label, 'Escribirnos por WhatsApp');
        assert.equal(con.action.onClick(), 'whatsapp!');
        assert.equal(ck5({ vista: VISTA.CAMBIAR, cambiar: { desdeReserva: false, whatsapp: false } }).action, null);
    });
});

describe('la banda de cada vista', () => {
    test('el inicio: «Mi cuenta», sin acción; la flecha solo si se abrió desde el menú, y vuelve a él', () => {
        const desdeFuera = ck({ vista: VISTA.INICIO });
        assert.equal(desdeFuera.step, 'Mi cuenta');
        assert.equal(desdeFuera.onBack, null, 'desde un correo o /mi-cuenta: solo la X');
        assert.equal(desdeFuera.action, null);
        assert.equal(desdeFuera.onClose(), 'cerrar');

        assert.equal(ck({ vista: VISTA.INICIO, desde: 'menu' }).onBack(), 'menu');
    });

    test('Tu QR: desde Mi cuenta vuelve a Mi cuenta; desde el menú, al menú; si no, solo la X', () => {
        assert.equal(ck({ vista: VISTA.QR, qrDesde: 'cuenta', desde: 'menu' }).onBack(), 'inicio');
        assert.equal(ck({ vista: VISTA.QR, qrDesde: 'menu', desde: 'menu' }).onBack(), 'menu');
        assert.equal(ck({ vista: VISTA.QR, qrDesde: 'fuera' }).onBack, null);
        assert.equal(ck({ vista: VISTA.QR }).step, 'Tu QR');
    });

    test('Entrar: «Continuar» apagado hasta tener correo y contraseña, y «Entrando» mientras', () => {
        assert.equal(ck({ vista: VISTA.ENTRAR, entrada: { valor: '', clave: '' } }).action.disabled, true);
        assert.equal(ck({ vista: VISTA.ENTRAR, entrada: { valor: 'a@b.es', clave: '' } }).action.disabled, true);
        assert.equal(ck({ vista: VISTA.ENTRAR, entrada: { valor: '  ', clave: 'x' } }).action.disabled, true);

        const lista = ck({ vista: VISTA.ENTRAR, entrada: { valor: 'a@b.es', clave: 'secreta1' } });
        assert.equal(lista.action.disabled, false);
        assert.equal(lista.action.label, 'Continuar');
        assert.equal(lista.action.loading, false);
        assert.equal(lista.action.onClick(), 'entrar!');
        assert.equal(ck({ vista: VISTA.ENTRAR, entrada: { valor: 'a@b.es', clave: 'x' }, ocupado: 'entrar' }).action.loading, 'Entrando');
    });

    test('el olvido es un paso de Entrar: su flecha vuelve a Entrar y no lleva acción', () => {
        const olvido = ck({ vista: VISTA.ENTRAR, subpaso: 'olvido', desde: 'menu' });
        assert.equal(olvido.step, '¿Has olvidado tu contraseña?');
        assert.equal(olvido.onBack(), 'entrar');
        assert.equal(olvido.action, null);
        assert.notEqual(olvido.key, ck({ vista: VISTA.ENTRAR }).key, 'otra clave: la isla anima el cambio');
    });

    test('Crea tu cuenta: vuelve a Entrar y su acción es «Crear mi cuenta», con «Creando tu cuenta»', () => {
        const crear = ck({ vista: VISTA.CREAR });
        assert.equal(crear.step, 'Crea tu cuenta');
        assert.equal(crear.onBack(), 'entrar');
        assert.equal(crear.action.onClick(), 'crear!');
        assert.equal(ck({ vista: VISTA.CREAR, ocupado: 'crear' }).action.loading, 'Creando tu cuenta');
    });

    test('completar el alta de Google: sin flecha; la acción solo si hay algo que completar', () => {
        const rotulos = { altaGoogle: 'Completa tu cuenta', altaGoogleBoton: 'Crear mi cuenta', altaGoogleEnviando: 'Creando…' };
        const pendiente = ck({ vista: VISTA.ALTA_GOOGLE, rotulos, altaGoogle: { pendiente: true } });
        assert.equal(pendiente.onBack, null);
        assert.equal(pendiente.step, 'Completa tu cuenta');
        assert.equal(pendiente.action.onClick(), 'google!');
        assert.equal(ck({ vista: VISTA.ALTA_GOOGLE, rotulos, altaGoogle: { pendiente: true }, ocupado: 'google' }).action.loading, 'Creando…');
        assert.equal(ck({ vista: VISTA.ALTA_GOOGLE, rotulos, altaGoogle: { pendiente: false } }).action, null, 'caducada: su pantalla lleva a empezar otra vez');
    });

    test('el descargo se lee dentro del alta: su flecha vuelve al formulario y no lleva acción', () => {
        const t2 = { ...textos, compra: { ...textos.compra, datos: { ...textos.compra.datos, descargo: 'El descargo' } } };

        for (const vista of [VISTA.CREAR, VISTA.ALTA_GOOGLE]) {
            const descargo = ckDeCuenta({ textos: t2, acciones, vista, subpaso: 'descargo', altaGoogle: { pendiente: true } });
            assert.equal(descargo.step, 'El descargo', vista);
            assert.equal(descargo.onBack(), 'formulario', vista);
            assert.equal(descargo.action, null, vista);
        }
    });

    test('cada vista trae la dirección con la que entra (la animación de la isla)', () => {
        assert.equal(ck({ vista: VISTA.QR, dir: 'fwd' }).dir, 'fwd');
        assert.equal(ck({ vista: VISTA.INICIO, dir: 'back' }).dir, 'back');
    });
});
