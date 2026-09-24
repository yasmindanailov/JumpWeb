import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    cuentaQueYaExiste, datosVacios, entradaVacia, errorDeEntrar, erroresDelAcceso, erroresDelServidor, firmaPendiente,
    formularioDeAlta, revisarDatos,
} from './datos.js';

/**
 * «Tus datos» de la compra de la isla (T3e·3 de `specs/isla-y-landing-nueva.md` §4.10): qué falta antes de preguntar,
 * a qué campo va cada «no» del servidor, y cuándo hay descargo que firmar. Los textos, los de `lang/es/isla.php`.
 */
const textos = {
    compra: {
        datos: {
            errores: {
                nombre: 'Escribe tu nombre y apellidos.', correo: 'Revisa el correo: falta algo.', telefono: 'Revisa el teléfono: son 9 cifras.',
                contrasena: 'Escribe una contraseña de 8 caracteres o más.', clave: 'Escribe tu contraseña.', descargo: 'Marca la casilla para seguir.',
                entrar: 'Revisa el correo o la contraseña.',
            },
        },
    },
};
const lleno = { ...datosVacios(), nombre: 'Ana García', correo: 'ana@correo.es', telefono: '612345214', contrasena: 'secreto-8', descargo: true };

describe('revisarDatos: lo que falta, con las frases del diseño', () => {
    test('sin cuenta, los cuatro campos y la casilla si hay descargo que firmar', () => {
        const errores = revisarDatos(datosVacios(), { firmaPendiente: true, textos });

        assert.deepEqual(Object.keys(errores), ['nombre', 'correo', 'telefono', 'contrasena', 'descargo']);
        assert.equal(errores.telefono, 'Revisa el teléfono: son 9 cifras.');
    });

    test('solo mira que ESTÉ: el formato, la política de la contraseña y el teléfono son del servidor', () => {
        const raro = { ...lleno, telefono: '+44 20 7946 0958', contrasena: 'x' };

        assert.deepEqual(revisarDatos(raro, { firmaPendiente: true, textos }), {}, 'un teléfono extranjero o una clave corta los juzga el servidor');
        assert.equal(revisarDatos({ ...lleno, correo: 'ana' }, { textos }).correo, 'Revisa el correo: falta algo.');
        assert.equal(revisarDatos({ ...lleno, nombre: '   ' }, { textos }).nombre, 'Escribe tu nombre y apellidos.', 'solo espacios es vacío');
    });

    test('sin descargo que firmar, la casilla no se pide', () => {
        assert.deepEqual(revisarDatos({ ...lleno, descargo: false }, { firmaPendiente: false, textos }), {});
    });

    test('cuenta que ya existe: correo y su contraseña, y nada más (su descargo es suyo)', () => {
        const errores = revisarDatos({ ...datosVacios(), cuenta: 'existe', correo: 'ana@correo.es' }, { firmaPendiente: true, textos });

        assert.deepEqual(errores, { contrasena: 'Escribe tu contraseña.' });
    });

    test('con sesión: solo el teléfono si falta y la casilla si nunca firmó', () => {
        const dentro = { ...datosVacios(), cuenta: 'dentro' };

        assert.deepEqual(revisarDatos(dentro, { textos }), {}, 'con todo, nada que pedir');
        assert.deepEqual(Object.keys(revisarDatos(dentro, { pedirTelefono: true, firmaPendiente: true, textos })), ['telefono', 'descargo']);
    });
});

describe('los «no» del servidor', () => {
    test('cada campo del alta, al suyo, con SU mensaje; lo que no es de ninguno, aparte', () => {
        const { errores, resto } = erroresDelServidor({
            name: 'El nombre es obligatorio.', email: 'Ya tienes una cuenta.', phone: 'Teléfono no válido.',
            password: 'Mínimo 8.', waiver_document_id: 'Vuelve a leerlo.', website: 'raro',
        });

        assert.deepEqual(errores, { nombre: 'El nombre es obligatorio.', correo: 'Ya tienes una cuenta.', telefono: 'Teléfono no válido.', contrasena: 'Mínimo 8.', descargo: 'Vuelve a leerlo.' });
        assert.deepEqual(resto, ['raro']);
    });

    test('la cuenta que ya existe se reconoce por su CÓDIGO, verificada o no; el anti-bot, no', () => {
        assert.equal(cuentaQueYaExiste('already_registered'), true);
        assert.equal(cuentaQueYaExiste('pending_verification'), true);
        assert.equal(cuentaQueYaExiste('bot_check_failed'), false);
        assert.equal(cuentaQueYaExiste(undefined), false);
    });

    test('entrar: las credenciales, por su código, con la frase del diseño bajo la contraseña', () => {
        const credenciales = { ok: false, response: { error: { code: 'invalid_credentials' } }, errors: { global: '', fields: { email: 'Estas credenciales no coinciden.' } } };

        assert.deepEqual(erroresDelAcceso(credenciales, textos), { errores: { contrasena: 'Revisa el correo o la contraseña.' }, aviso: '' });
    });

    test('entrar: el limitador va arriba y un campo, a su sitio', () => {
        const limite = { ok: false, response: { error: { code: 'too_many_requests' } }, errors: { global: 'Espera 30 segundos.', fields: {} } };
        const campo = { ok: false, response: { error: { code: 'validation_failed' } }, errors: { global: '', fields: { email: 'Correo no válido.' } } };

        assert.deepEqual(erroresDelAcceso(limite, textos), { errores: {}, aviso: 'Espera 30 segundos.' });
        assert.deepEqual(erroresDelAcceso(campo, textos), { errores: { correo: 'Correo no válido.' }, aviso: '' });
    });

    test('«Entra» (T3e·4) tiene UNA línea de error: la de las credenciales, la del campo o la del limitador', () => {
        const credenciales = { ok: false, response: { error: { code: 'invalid_credentials' } }, errors: { global: '', fields: {} } };
        const limite = { ok: false, response: { error: { code: 'too_many_requests' } }, errors: { global: 'Espera 30 segundos.', fields: {} } };
        const campo = { ok: false, response: { error: { code: 'validation_failed' } }, errors: { global: '', fields: { email: 'Correo no válido.' } } };

        assert.equal(errorDeEntrar(credenciales, textos), 'Revisa el correo o la contraseña.');
        assert.equal(errorDeEntrar(limite, textos), 'Espera 30 segundos.');
        assert.equal(errorDeEntrar(campo, textos), 'Correo no válido.');
        assert.equal(errorDeEntrar({ ok: false, skipped: true }, textos), '', 'un doble clic no inventa un error');
        assert.deepEqual(entradaVacia('ana@correo.es'), { paso: 'id', valor: 'ana@correo.es', clave: '', error: '', solo: false });
    });
});

describe('el descargo que firmar', () => {
    const documento = { id: 7 };

    test('sin texto firmable (modo externo, o sin versión) no hay casilla, con o sin sesión', () => {
        assert.equal(firmaPendiente({ cuenta: 'nueva', documento: null }), false);
        assert.equal(firmaPendiente({ cuenta: 'dentro', contexto: { waiver: { required: true, pending: false } }, documento: null }), false);
    });

    test('sin sesión, siempre que haya texto: el alta lo acepta', () => {
        assert.equal(firmaPendiente({ cuenta: 'nueva', documento }), true);
    });

    test('con sesión, solo quien nunca firmó y no dejó ya su aceptación esperando al correo', () => {
        const con = (waiver) => firmaPendiente({ cuenta: 'dentro', contexto: { waiver }, documento });

        assert.equal(con({ required: true, pending: false }), true);
        assert.equal(con({ required: true, pending: true }), false, 'aceptada en el alta, espera al correo (`#181`)');
        assert.equal(con({ required: false, pending: false, outdated: true }), false, 'una versión anterior la deja pasar la puerta');
    });
});

test('el formulario del alta del motor, sin espacios de más (la contraseña, tal cual)', () => {
    assert.deepEqual(formularioDeAlta({ ...lleno, nombre: ' Ana García ', correo: ' ana@correo.es ', contrasena: ' clave con espacios ' }), {
        name: 'Ana García', email: 'ana@correo.es', phone: '612345214', password: ' clave con espacios ', accept_waiver: true,
    });
});
