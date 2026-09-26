import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { avisoDeAnalitica, avisoDeCuenta } from './avisos.js';

/** T5e·2 — los avisos de la cuenta en Mi cuenta (`avisos.js`), con la decisión del índice del cajón (`#331`). */
const textos = {
    mi_cuenta: {
        ajustes: { privacidad: 'Privacidad' },
        avisos: {
            verificar: 'Confirma tu correo con el enlace que te enviamos.', verificar_descargo: 'Tu descargo quedará firmado al confirmarlo.',
            reenviar: 'Reenviar el correo', reenviar_en: 'Reenviar en :s s', quedan: 'Reenvíos que quedan: :n.',
            limite: 'Has llegado al límite de reenvíos.', firmar: 'Te falta firmar el descargo.', firmar_nuevo: 'El descargo ha cambiado: vuelve a firmarlo.',
            firmar_boton: 'Firmar', hijos: 'Falta la firma del descargo de alguno de tus hijos.', hijos_boton: 'Ver a tus hijos',
        },
    },
};
const listo = { segundos: 0, quedan: 4 };
const interno = (extra = {}) => ({ mode: 'interno', required: false, outdated: false, pending: false, dependents_pending: false, ...extra });

describe('el aviso de la cuenta', () => {
    test('sin contexto o con todo al día, ninguno', () => {
        assert.equal(avisoDeCuenta(null, { textos, reenvio: listo }), null);
        assert.equal(avisoDeCuenta({ email_verified: true, waiver: interno() }, { textos, reenvio: listo }), null);
    });

    test('sin el correo confirmado, eso primero —aunque falte firmar—, con el reenvío y lo que queda', () => {
        const a = avisoDeCuenta({ email_verified: false, waiver: interno({ required: true }) }, { textos, reenvio: listo });

        assert.equal(a.tipo, 'verificar');
        assert.equal(a.texto, 'Confirma tu correo con el enlace que te enviamos.');
        assert.equal(a.detalle, 'Reenvíos que quedan: 4.');
        assert.deepEqual(a.accion, { texto: 'Reenviar el correo', hace: 'reenviar', deshabilitada: false });
    });

    test('con el descargo aceptado al darse de alta, dice que se firmará al confirmar (un solo aviso, no dos)', () => {
        const a = avisoDeCuenta({ email_verified: false, waiver: interno({ pending: true }) }, { textos, reenvio: listo });

        assert.equal(a.detalle, 'Tu descargo quedará firmado al confirmarlo. Reenvíos que quedan: 4.');
    });

    test('esperando, la cuenta atrás y apagado; sin cupo, el límite y sin botón', () => {
        const esperando = avisoDeCuenta({ email_verified: false }, { textos, reenvio: { segundos: 42, quedan: 3 } });

        assert.deepEqual(esperando.accion, { texto: 'Reenviar en 42 s', hace: 'reenviar', deshabilitada: true });
        const agotado = avisoDeCuenta({ email_verified: false }, { textos, reenvio: { segundos: 0, quedan: 0 } });

        assert.equal(agotado.accion, null);
        assert.equal(agotado.detalle, 'Has llegado al límite de reenvíos.');
    });

    test('la espera sin armar NO ofrece reenviar (falla cerrada, la puerta del cajón)', () => {
        assert.equal(avisoDeCuenta({ email_verified: false }, { textos, reenvio: { segundos: undefined, quedan: 4 } }).accion.deshabilitada, true);
    });

    test('con el correo confirmado, SU descargo: sin firmar, o de una versión anterior', () => {
        const falta = avisoDeCuenta({ email_verified: true, waiver: interno({ required: true }) }, { textos, reenvio: listo });

        assert.equal(falta.tipo, 'firmar');
        assert.equal(falta.texto, 'Te falta firmar el descargo.');
        assert.deepEqual(falta.accion, { texto: 'Firmar', hace: 'firmar', deshabilitada: false });
        assert.equal(avisoDeCuenta({ email_verified: true, waiver: interno({ outdated: true }) }, { textos, reenvio: listo }).texto, 'El descargo ha cambiado: vuelve a firmarlo.');
    });

    test('y después, el de sus hijos, que lleva a ellos (no a su propio descargo)', () => {
        const a = avisoDeCuenta({ email_verified: true, waiver: interno({ dependents_pending: true }) }, { textos, reenvio: listo });

        assert.equal(a.tipo, 'hijos');
        assert.equal(a.accion.hace, 'hijos');
    });

    test('lo gestiona el parque: nada que firmar aquí', () => {
        assert.equal(avisoDeCuenta({ email_verified: true, waiver: { mode: 'externo', required: true } }, { textos, reenvio: listo }), null);
    });
});

describe('el aviso de la analítica', () => {
    test('su texto y su «Entendido», del servidor; y el botón, con el nombre que da la frase («Privacidad y datos»)', () => {
        const contexto = { analytics_notice: { text: 'Puedes oponerte en «Privacidad y datos».', dismiss: 'Entendido' } };
        const motor = { account: { privacy: { title: 'Privacidad y datos' } } };

        assert.deepEqual(avisoDeAnalitica(contexto, { textos, motor }), { texto: 'Puedes oponerte en «Privacidad y datos».', despedir: 'Entendido', privacidad: 'Privacidad y datos' });
        assert.equal(avisoDeAnalitica(contexto, { textos }).privacidad, 'Privacidad', 'sin el rótulo del motor, el del plegable');
    });

    test('despedido o sin contexto, ninguno', () => {
        assert.equal(avisoDeAnalitica({ analytics_notice: null }, { textos }), null);
        assert.equal(avisoDeAnalitica(null, { textos }), null);
    });
});
