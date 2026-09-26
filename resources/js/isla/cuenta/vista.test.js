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
        assert.deepEqual(vistaDeApertura('home', { sesion: true }), { vista: VISTA.INICIO, bloque: '', plegable: '' });
        assert.deepEqual(vistaDeApertura('card', { sesion: true }), { vista: VISTA.QR, bloque: '', plegable: '' });
        assert.deepEqual(vistaDeApertura('orders', { sesion: true }), { vista: VISTA.INICIO, bloque: 'proxima', plegable: '' });
    });

    test('el bloque de un enlace (`#mi-cuenta/antes`) manda sobre el de la zona', () => {
        assert.deepEqual(vistaDeApertura('home', { sesion: true, bloque: 'antes' }), { vista: VISTA.INICIO, bloque: 'antes', plegable: '' });
        assert.deepEqual(vistaDeApertura('orders', { sesion: true, bloque: 'ajustes' }), { vista: VISTA.INICIO, bloque: 'ajustes', plegable: '' });
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

describe('los hijos (T5d)', () => {
    const t5d = { ...textos, mi_cuenta: { ...textos.mi_cuenta, hijos: { titulo: 'Añade a tus hijos', boton: 'Guardar', guardando: 'Guardando' }, hijo: { firmar: 'Firmar en su nombre', firmando: 'Firmando' } } };
    const acc = { ...acciones, guardarHijos: () => 'guardar!', firmarHijo: () => 'firmar!' };
    const ckd = (e) => ckDeCuenta({ textos: t5d, acciones: acc, ...e });

    test('la zona de menores del motor (la puerta `/mi-cuenta/hijos`) abre «Añade a tus hijos»; sin sesión, Entrar', () => {
        assert.deepEqual(vistaDeApertura('dependents', { sesion: true }), { vista: VISTA.HIJOS, bloque: '', plegable: '' });
        assert.equal(vistaDeApertura('dependents', { sesion: false }).vista, VISTA.ENTRAR);
    });

    test('Añade a tus hijos: vuelve a Mi cuenta y su acción es «Guardar», con «Guardando» mientras', () => {
        const r = ckd({ vista: VISTA.HIJOS });

        assert.equal(r.step, 'Añade a tus hijos');
        assert.equal(r.onBack(), 'inicio');
        assert.equal(r.action.onClick(), 'guardar!');
        assert.equal(r.action.loading, false);
        assert.equal(ckd({ vista: VISTA.HIJOS, ocupado: 'hijos' }).action.loading, 'Guardando');
    });

    test('su «Guardado», sin acción', () => {
        assert.equal(ckd({ vista: VISTA.HIJOS_LISTO }).action, null);
        assert.equal(ckd({ vista: VISTA.HIJOS_LISTO }).onBack(), 'inicio');
    });

    test('la ficha de un hijo: su nombre en la banda; «Firmar en su nombre» solo si hace falta y se puede', () => {
        const r = ckd({ vista: VISTA.HIJO, hijo: { nombre: 'Vera', firmar: true } });

        assert.equal(r.step, 'Vera');
        assert.equal(r.action.onClick(), 'firmar!');
        assert.equal(ckd({ vista: VISTA.HIJO, hijo: { nombre: 'Vera', firmar: true }, ocupado: 'firmar' }).action.loading, 'Firmando');
        assert.equal(ckd({ vista: VISTA.HIJO, hijo: { nombre: 'Pol', firmar: false } }).action, null);
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

describe('los Ajustes (T5e)', () => {
    const t5e = {
        ...textos,
        mi_cuenta: {
            ...textos.mi_cuenta,
            ajustes: { guardando: 'Guardando' },
            clave: { titulo: 'Cambiar la contraseña', guardar: 'Guardar la contraseña' },
            correo: { titulo: 'Correo', enviar: 'Enviar el enlace', enviando: 'Enviando el enlace' },
            otras_sesiones: { titulo: 'Cerrar sesión en otros dispositivos', boton: 'Cerrar las otras sesiones', cerrando: 'Cerrando' },
            desvincular: { titulo: 'Desvincular Google', boton: 'Desvincular', cargando: 'Desvinculando' },
            descargo: { titulo: 'Tu descargo', firmar: 'Firmar' },
            hijo: { firmando: 'Firmando' },
            borrar: { titulo: 'Borrar tu cuenta' },
        },
    };
    const acc = { ...acciones, guardarClave: () => 'clave!', enviarCorreo: () => 'correo!', cerrarOtras: () => 'otras!', desvincular: () => 'desvincular!', firmar: () => 'firmar!' };
    const cke = (e) => ckDeCuenta({ textos: t5e, acciones: acc, ...e });

    test('las zonas del cajón que son un plegable abren Mi cuenta en Ajustes, con ese plegable abierto', () => {
        assert.deepEqual(vistaDeApertura('profile', { sesion: true }), { vista: VISTA.INICIO, bloque: 'ajustes', plegable: 'datos' });
        assert.deepEqual(vistaDeApertura('password', { sesion: true }), { vista: VISTA.INICIO, bloque: 'ajustes', plegable: 'acceso' });
        assert.deepEqual(vistaDeApertura('sessions', { sesion: true }), { vista: VISTA.INICIO, bloque: 'ajustes', plegable: 'acceso' });
        assert.deepEqual(vistaDeApertura('privacy', { sesion: true }), { vista: VISTA.INICIO, bloque: 'ajustes', plegable: 'privacidad' });
        assert.equal(vistaDeApertura('privacy', { sesion: false }).vista, VISTA.ENTRAR);
    });

    test('`#mi-cuenta/<plegable>` también (la vuelta de vincular Google es `#mi-cuenta/acceso`); un bloque que no lo es, no', () => {
        assert.deepEqual(vistaDeApertura('home', { sesion: true, bloque: 'acceso' }), { vista: VISTA.INICIO, bloque: 'ajustes', plegable: 'acceso' });
        assert.deepEqual(vistaDeApertura('home', { sesion: true, bloque: 'recibos' }), { vista: VISTA.INICIO, bloque: 'ajustes', plegable: 'recibos' });
        assert.equal(vistaDeApertura('home', { sesion: true, bloque: 'quien' }).plegable, '');
    });

    test('cada paso: su banda, la flecha a Mi cuenta y su acción, con lo que dice mientras espera', () => {
        const casos = [
            [VISTA.CLAVE, 'Cambiar la contraseña', 'Guardar la contraseña', 'clave!', 'Guardando'],
            [VISTA.CORREO, 'Correo', 'Enviar el enlace', 'correo!', 'Enviando el enlace'],
            [VISTA.OTRAS, 'Cerrar sesión en otros dispositivos', 'Cerrar las otras sesiones', 'otras!', 'Cerrando'],
            [VISTA.DESVINCULAR, 'Desvincular Google', 'Desvincular', 'desvincular!', 'Desvinculando'],
            [VISTA.FIRMA, 'Tu descargo', 'Firmar', 'firmar!', 'Firmando'],
        ];

        for (const [vista, step, label, hace, cargando] of casos) {
            const r = cke({ vista, ajuste: { enviado: false, firmar: true } });

            assert.equal(r.step, step, vista);
            assert.equal(r.onBack(), 'inicio', vista);
            assert.equal(r.action.label, label, vista);
            assert.equal(r.action.onClick(), hace, vista);
            assert.equal(r.action.loading, false, vista);
            assert.equal(cke({ vista, ajuste: { enviado: false, firmar: true }, ocupado: vista }).action.loading, cargando, vista);
        }
    });

    test('borrar la cuenta NO lleva acción en la isla: lo destructivo no va en el naranja', () => {
        const r = cke({ vista: VISTA.BORRAR });

        assert.equal(r.step, 'Borrar tu cuenta');
        assert.equal(r.action, null);
        assert.equal(r.onBack(), 'inicio');
    });

    test('el correo ya enviado deja el desenlace sin acción; el descargo sin nada que firmar, también', () => {
        assert.equal(cke({ vista: VISTA.CORREO, ajuste: { enviado: true } }).action, null);
        assert.equal(cke({ vista: VISTA.FIRMA, ajuste: { firmar: false } }).action, null);
    });

    test('leer el descargo desde «Tu descargo» es un paso: su flecha vuelve a la firma', () => {
        const t2 = { ...t5e, compra: { ...t5e.compra, datos: { ...t5e.compra.datos, descargo: 'El descargo' } } };
        const r = ckDeCuenta({ textos: t2, acciones: acc, vista: VISTA.FIRMA, subpaso: 'descargo', ajuste: { firmar: true } });

        assert.equal(r.step, 'El descargo');
        assert.equal(r.onBack(), 'formulario');
    });
});
