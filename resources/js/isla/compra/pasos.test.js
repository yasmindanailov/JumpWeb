import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { STEPS } from '../../sidebar/machine.js';
import { ckDelPaso, direccion, empiezaOtra, pantallaListo, pasoDelMotor, rango } from './pasos.js';

/**
 * Los pasos de la compra de la isla tras la pantalla 0 (T3e·3 de `specs/isla-y-landing-nueva.md` §4.10): la banda, la
 * acción y «volver» de cada uno, como el guion del diseño (`paginas/compra/compra.jsx`), y quién manda en cada paso.
 */
const textos = {
    paso: 'Paso :n de :total',
    pieza: { cargando: 'Cargando' },
    compra: {
        paso: 'Paso :n de :total',
        datos: { banda: 'Tus datos', continuar: 'Continuar al pago', cargando: 'Comprobando tus datos y guardando tu hora' },
        entrar: { continuar: 'Continuar', cargando: 'Entrando' },
        pagar: { banda: 'Pagar', tarjeta: 'Pagar :importe con tarjeta', saliendo_boton: 'Continuar al pago' },
        fallido: { tarjeta: 'Volver a intentar con tarjeta' },
        listo: { mi_qr: 'Ir a Mi QR', menores: 'Añade a tus hijos…', menores_boton: 'Añadir a mis hijos' },
    },
};
const acciones = { volver: () => 'volver', continuar: () => 'continuar', entrar: () => 'entrar', pagar: () => 'pagar', salir: () => 'salir', reintentar: () => 'reintentar', miQr: () => 'miQr', cerrar: () => 'cerrar' };
const resumen = { summary: 'Kids · 1 hora · sáb 26, 17:00 · 2 entradas', total: '16 €' };
const ck = (paso, extra = {}) => ckDelPaso({ paso, textos, acciones, resumen, importe: '16 €', ...extra });

test('los pasos del cobro los manda la MÁQUINA (llegan también de la vuelta del banco); los de antes, la isla', () => {
    assert.equal(pasoDelMotor(STEPS.REDIRECTING), 'banco');
    assert.equal(pasoDelMotor(STEPS.CONFIRMED), 'listo');
    assert.equal(pasoDelMotor(STEPS.DECLINED), 'fallido');
    assert.equal(pasoDelMotor(STEPS.VERIFYING), 'verificando');
    for (const step of [STEPS.CATALOG, STEPS.CART, STEPS.IDENTIFY, STEPS.PAY, STEPS.VERIFY_EMAIL]) assert.equal(pasoDelMotor(step), null);
});

test('la vuelta del banco se queda en su desenlace; solo una intención de la landing empieza otra compra', () => {
    for (const step of [STEPS.CONFIRMED, STEPS.DECLINED, STEPS.VERIFYING]) {
        assert.equal(empiezaOtra(null, step), false, `sin intención, el desenlace ${step} no se borra`);
        assert.equal(empiezaOtra({ type: 'zone', slug: 'kids' }, step), true, 'con intención, la compra nueva manda');
    }
    assert.equal(empiezaOtra(null, STEPS.CATALOG), true, 'sin desenlace, «Para hoy»');
    assert.equal(empiezaOtra(null, STEPS.CART), true, 'una cesta guardada no es un desenlace');
});

test('la dirección: avanzar entra por la derecha, volver por la izquierda, y la primera vez sin ella', () => {
    assert.equal(direccion(null, rango('cuando')), null);
    assert.equal(direccion(rango('cuando'), rango('datos')), 'fwd');
    assert.equal(direccion(rango('datos'), rango('datos', 'descargo')), 'fwd');
    assert.equal(direccion(rango('datos', 'entrar', 'id'), rango('datos', 'entrar', 'olvido')), 'fwd', 'el olvido, detrás de «Entra»');
    assert.equal(direccion(rango('datos', 'entrar', 'olvido'), rango('datos', 'entrar', 'id')), 'back');
    assert.equal(direccion(rango('pagar'), rango('datos')), 'back');
    assert.equal(direccion(rango('pagar'), rango('banco')), 'fwd');
    assert.equal(direccion(rango('fallido'), rango('listo')), 'fwd');
});

describe('la descripción de cada paso', () => {
    test('«Tus datos»: paso 1 de 2, volver, y la acción con su espera mientras el servidor contesta', () => {
        const c = ck('datos');

        assert.equal(`${c.stepStrong}${c.step}`, 'Paso 1 de 2 · Tus datos');
        assert.deepEqual(c.progress, [1, 2]);
        assert.equal(c.onBack(), 'volver');
        assert.equal(c.action.label, 'Continuar al pago');
        assert.equal(c.action.onClick(), 'continuar');
        assert.equal(c.action.loading, false);
        assert.equal(ck('datos', { ocupado: 'datos' }).action.loading, 'Comprobando tus datos y guardando tu hora');
        assert.equal(c.summary, resumen.summary);
    });

    test('dentro de «Tus datos» (el descargo, el olvido) no hay acción: se vuelve con la flecha', () => {
        assert.equal(ck('datos', { vista: 'descargo' }).action, null);
        assert.equal(ck('datos', { vista: 'descargo' }).key, 'datosdescargo');
        assert.equal(ck('datos', { vista: 'entrar', entrada: { paso: 'olvido' } }).action, null);
        assert.equal(ck('datos', { vista: 'entrar', entrada: { paso: 'olvido' } }).key, 'datosentrarolvido');
    });

    test('«Entra» (T3e·4): «Continuar», apagado hasta tener correo y contraseña, con su espera', () => {
        const vacia = ck('datos', { vista: 'entrar', entrada: { paso: 'id', valor: '  ', clave: '' } });

        assert.equal(vacia.action.label, 'Continuar');
        assert.equal(vacia.action.disabled, true);
        assert.equal(vacia.key, 'datosentrarid');
        assert.equal(ck('datos', { vista: 'entrar', entrada: { paso: 'id', valor: 'ana@correo.es', clave: '' } }).action.disabled, true);

        const lista = ck('datos', { vista: 'entrar', entrada: { paso: 'id', valor: 'ana@correo.es', clave: 'x' }, ocupado: 'entrar' });

        assert.equal(lista.action.disabled, false);
        assert.equal(lista.action.onClick(), 'entrar');
        assert.equal(lista.action.loading, 'Entrando');
        assert.equal(lista.onBack(), 'volver');
    });

    test('«Pagar»: paso 2 de 2, sin «tu hora queda guardada» (`#688`) y el importe que cobra la pasarela', () => {
        const c = ck('pagar');

        assert.equal(`${c.stepStrong}${c.step}`, 'Paso 2 de 2 · Pagar');
        assert.equal(c.note, null);
        assert.equal(c.action.label, 'Pagar 16 € con tarjeta');
        assert.equal(c.action.onClick(), 'pagar');
        assert.equal(ck('pagar', { ocupado: 'pagar' }).action.loading, 'Cargando');
    });

    test('saliendo al banco, el botón por si no salta; el pago no completado, reintentar con tarjeta (sin Bizum, `#683`)', () => {
        assert.equal(ck('banco').action.onClick(), 'salir');
        assert.equal(ck('banco').onBack, null);
        assert.equal(ck('fallido').action.label, 'Volver a intentar con tarjeta');
        assert.equal(ck('fallido').action.onClick(), 'reintentar');
        assert.equal(ck('verificando').action, null);
    });

    test('«Listo»: sin banda ni resumen, y «Ir a Mi QR»', () => {
        const c = ck('listo');

        assert.equal(c.stepStrong, '');
        assert.equal(c.summary, null);
        assert.equal(c.total, null);
        assert.equal(c.action.onClick(), 'miQr');
        assert.equal(c.onClose(), 'cerrar');
    });
});

test('«Listo»: la tarea de los hijos solo si la instalación firma dentro; el QR es el del carné', () => {
    const base = { linea: 'Sábado 26 · 17:00', confirmacion: { code: 'R-7K2P4' }, correo: 'ana@correo.es', qrSrc: '/api/v1/me/card/png?v=1', textos };

    assert.deepEqual(pantallaListo(base).tareas, []);
    assert.deepEqual(pantallaListo({ ...base, firmaDentro: true }).tareas.map((x) => x.id), ['menores']);
    assert.equal(pantallaListo(base).codigo, 'R-7K2P4');
    assert.equal(pantallaListo(base).whatsapp, false, 'el producto no manda WhatsApp');
});
