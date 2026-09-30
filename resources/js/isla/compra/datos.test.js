import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    cuentaQueYaExiste, datosVacios, entradaVacia, erroresDelServidor, firmaPendiente, formularioDeAlta, hayQuePedir,
    nacimientoDeAlta, revisarDatos,
} from './datos.js';
import { errorDeEntrar, erroresDelCodigo } from './acceso.js';

/**
 * «Tus datos» de la compra de la isla (T3e·3 de `specs/isla-y-landing-nueva.md` §4.10): qué falta antes de preguntar,
 * a qué campo va cada «no» del servidor, y cuándo hay descargo que firmar. Los textos, los de `lang/es/isla.php`.
 * ▶ Desde la A3 del acceso con código (`#848`/`#849`): sin contraseña; la cuenta que ya existe entra con su código.
 */
const textos = {
    compra: {
        datos: {
            errores: {
                nombre: 'Escribe tu nombre y apellidos.', correo: 'Revisa el correo: falta algo.', telefono: 'Revisa el teléfono: son 9 cifras.',
                descargo: 'Marca la casilla para seguir.', codigo: 'Escribe el código que te hemos enviado.',
                codigo_mal: 'El código no es correcto o ha caducado. Pide otro.', espera: 'Espera :n segundos para pedir otro código.',
            },
        },
    },
};
const lleno = { ...datosVacios(), nombre: 'Ana García', correo: 'ana@correo.es', telefono: '612345214', descargo: true };

describe('revisarDatos: lo que falta, con las frases del diseño', () => {
    test('sin cuenta, sus campos y la casilla si hay descargo que firmar; el teléfono, SOLO en una fiesta (`#787`); sin contraseña (A3)', () => {
        const entrada = revisarDatos(datosVacios(), { firmaPendiente: true, textos });
        const fiesta = revisarDatos(datosVacios(), { pedirTelefono: true, firmaPendiente: true, textos });

        assert.deepEqual(Object.keys(entrada), ['nombre', 'correo', 'descargo'], 'una entrada no pide teléfono, y nadie pide contraseña');
        assert.deepEqual(Object.keys(fiesta), ['nombre', 'correo', 'telefono', 'descargo']);
        assert.equal(fiesta.telefono, 'Revisa el teléfono: son 9 cifras.');
    });

    test('solo mira que ESTÉ: el formato del correo y el teléfono son del servidor', () => {
        const raro = { ...lleno, telefono: '+44 20 7946 0958' };

        assert.deepEqual(revisarDatos(raro, { firmaPendiente: true, textos }), {}, 'un teléfono extranjero lo juzga el servidor');
        assert.equal(revisarDatos({ ...lleno, correo: 'ana' }, { textos }).correo, 'Revisa el correo: falta algo.');
        assert.equal(revisarDatos({ ...lleno, nombre: '   ' }, { textos }).nombre, 'Escribe tu nombre y apellidos.', 'solo espacios es vacío');
    });

    test('sin descargo que firmar, la casilla no se pide', () => {
        assert.deepEqual(revisarDatos({ ...lleno, descargo: false }, { firmaPendiente: false, textos }), {});
    });

    test('cuenta que ya existe: correo y su CÓDIGO, y nada más (su descargo es suyo)', () => {
        const errores = revisarDatos({ ...datosVacios(), cuenta: 'existe', correo: 'ana@correo.es' }, { firmaPendiente: true, textos });

        assert.deepEqual(errores, { codigo: 'Escribe el código que te hemos enviado.' });
        assert.deepEqual(revisarDatos({ ...datosVacios(), cuenta: 'existe', correo: 'ana@correo.es', codigo: '482 913' }, { textos }), {});
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
            code: 'Código mal.', waiver_document_id: 'Vuelve a leerlo.', website: 'raro', password: 'Mínimo 8.',
        });

        assert.deepEqual(errores, { nombre: 'El nombre es obligatorio.', correo: 'Ya tienes una cuenta.', telefono: 'Teléfono no válido.', codigo: 'Código mal.', descargo: 'Vuelve a leerlo.' });
        assert.deepEqual(resto, ['raro', 'Mínimo 8.'], 'la contraseña ya no es de ningún campo (A3): si llegara, arriba');
    });

    test('la cuenta que ya existe se reconoce por su CÓDIGO, verificada o no; el anti-bot, no', () => {
        assert.equal(cuentaQueYaExiste('already_registered'), true);
        assert.equal(cuentaQueYaExiste('pending_verification'), true);
        assert.equal(cuentaQueYaExiste('bot_check_failed'), false);
        assert.equal(cuentaQueYaExiste(undefined), false);
    });

    // El sobre de error de la API tal cual (`ApiResult`): `error.code`, `error.fields.x` (una lista) y `error.params`.
    const credenciales = { ok: false, status: 401, error: { code: 'invalid_credentials', message: 'Estas credenciales no coinciden.' } };
    const limite = { ok: false, status: 429, error: { code: 'too_many_requests', message: 'Demasiados intentos.', params: { retry_after: 30 } } };
    const campo = { ok: false, status: 422, error: { code: 'validation_failed', message: 'Revisa los datos.', fields: { email: ['Correo no válido.'] } } };

    test('el código malo, por su CÓDIGO de error y no por el texto, con la frase de siempre bajo el código', () => {
        assert.deepEqual(erroresDelCodigo(credenciales, textos), { errores: { codigo: 'El código no es correcto o ha caducado. Pide otro.' }, aviso: '' });
    });

    test('el limitador dice cuánto esperar; un campo va a su sitio; sin respuesta, el aviso genérico', () => {
        assert.deepEqual(erroresDelCodigo(limite, textos), { errores: {}, aviso: 'Espera 30 segundos para pedir otro código.' });
        assert.deepEqual(erroresDelCodigo(campo, textos), { errores: { correo: 'Correo no válido.' }, aviso: '' });
        assert.deepEqual(erroresDelCodigo({ ok: false, error: null, offline: true }, textos, 'Inténtalo de nuevo.'), { errores: {}, aviso: 'Inténtalo de nuevo.' });
    });

    test('«Entra» (T3e·4) tiene UNA línea de error: la del código, la del campo o la del limitador', () => {
        assert.equal(errorDeEntrar(credenciales, textos), 'El código no es correcto o ha caducado. Pide otro.');
        assert.equal(errorDeEntrar(limite, textos), 'Espera 30 segundos para pedir otro código.');
        assert.equal(errorDeEntrar(campo, textos), 'Correo no válido.');
        assert.equal(errorDeEntrar({ ok: false, skipped: true }, textos), '', 'un doble clic no inventa un error');
        assert.deepEqual(entradaVacia('ana@correo.es'), { paso: 'id', valor: 'ana@correo.es', codigo: '', error: '', reenvios: 0 });
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

describe('«Tus datos», solo si falta algo (`#785`)', () => {
    test('sin sesión, siempre: entrar o crear la cuenta', () => {
        assert.equal(hayQuePedir({ identificado: false }), true);
    });

    test('con sesión, solo el teléfono que ese pedido exige o la firma que nunca hizo; sin nada, a «Pagar»', () => {
        assert.equal(hayQuePedir({ identificado: true }), false, 'un «Hola, Ana» sin nada que pedir no es una pantalla');
        assert.equal(hayQuePedir({ identificado: true, pedirTelefono: true }), true);
        assert.equal(hayQuePedir({ identificado: true, firma: true }), true);
    });

    test('la cuenta nueva que vuelve de Google: su nombre y la casilla; el correo es el de Google y no se teclea', () => {
        const google = { ...datosVacios(), cuenta: 'google' };

        assert.deepEqual(Object.keys(revisarDatos(google, { firmaPendiente: true, textos })), ['nombre', 'descargo']);
        assert.deepEqual(revisarDatos({ ...google, nombre: 'Ana García', descargo: true }, { firmaPendiente: true, textos }), {});
        assert.deepEqual(revisarDatos({ ...google, nombre: 'Ana García' }, { textos }), {}, 'sin descargo que firmar, basta el nombre');
        assert.deepEqual(Object.keys(revisarDatos({ ...google, nombre: 'Ana García' }, { pedirTelefono: true, textos })), ['telefono'], 'en una fiesta, su teléfono (`#787`)');
    });
});

test('el formulario del alta del motor, sin espacios de más y con la contraseña VACÍA (A3: el alta ya no la pide)', () => {
    assert.deepEqual(formularioDeAlta({ ...lleno, nombre: ' Ana García ', correo: ' ana@correo.es ' }), {
        name: 'Ana García', email: 'ana@correo.es', phone: '612345214', born_on: '', password: '', accept_waiver: true,
    });
});

describe('la fecha de nacimiento del titular, entera y opcional (`#792`)', () => {
    test('vacía no viaja; completa, en Y-m-d; a medias o inexistente, `null`', () => {
        assert.equal(nacimientoDeAlta(''), '');
        assert.equal(nacimientoDeAlta('   '), '');
        assert.equal(nacimientoDeAlta('07/03/1988'), '1988-03-07');
        assert.equal(nacimientoDeAlta('07/03'), null);
        assert.equal(nacimientoDeAlta('31/02/1990'), null, 'el 31 de febrero no se desborda a marzo');
        assert.equal(formularioDeAlta({ ...lleno, nacimiento: '07/03/1988' }).born_on, '1988-03-07');
    });

    test('revisarDatos solo para la que está a medias: vacía pasa (opcional), en el alta y en la de Google', () => {
        const conTextos = { compra: { datos: { errores: { ...textos.compra.datos.errores, nacimiento: 'Revisa la fecha: día, mes y año.' } } } };

        assert.deepEqual(revisarDatos(lleno, { textos: conTextos }), {});
        assert.deepEqual(revisarDatos({ ...lleno, nacimiento: '07/03/1988' }, { textos: conTextos }), {});
        assert.deepEqual(revisarDatos({ ...lleno, nacimiento: '07/03' }, { textos: conTextos }), { nacimiento: 'Revisa la fecha: día, mes y año.' });
        assert.deepEqual(revisarDatos({ ...datosVacios(), cuenta: 'google', nombre: 'Ana', nacimiento: '99/99/1988' }, { textos: conTextos }), { nacimiento: 'Revisa la fecha: día, mes y año.' });
        assert.deepEqual(revisarDatos({ ...datosVacios(), cuenta: 'dentro', nacimiento: '07/03' }, { textos: conTextos }), {}, 'con sesión no se pide aquí: es de Mi cuenta');
    });

    test('el «no» del servidor a la fecha va bajo su campo', () => {
        assert.deepEqual(erroresDelServidor({ born_on: 'Tienes que ser mayor de edad.' }).errores, { nacimiento: 'Tienes que ser mayor de edad.' });
    });
});
