/**
 * **«TUS DATOS» DE LA COMPRA DE LA ISLA, sin estado** (T3e·3 de `docs/specs/isla-y-landing-nueva.md` §4.10,
 * `DECISIONES #692`).
 *
 * ⚠️⚠️ **Aquí solo se mira que cada campo ESTÉ**, con las frases del diseño: el formato del correo, la política de
 * la contraseña o lo que valga como teléfono lo decide el SERVIDOR, y su «no» se enseña bajo el campo con su propio
 * mensaje (ya traducido). Un navegador más estricto que el servidor bloquearía datos buenos —el «son 9 cifras» del
 * diseño es de un teléfono español, y el producto no es de un país—, y uno más laxo solo gasta una petición.
 */
import { t as texto } from '../../sidebar/i18n.js';
import { INVALID_CREDENTIALS } from '../../sidebar/login.js';

/** El formulario en blanco. `cuenta`: `nueva` (alta), `existe` (su contraseña) o `dentro` (con sesión). */
export const datosVacios = () => ({ nombre: '', correo: '', telefono: '', contrasena: '', descargo: false, cuenta: 'nueva' });

const vacio = (valor) => String(valor ?? '').trim() === '';

/**
 * Lo que falta, antes de preguntar a nadie.
 *
 * @param {ReturnType<typeof datosVacios>} f
 * @param {{pedirTelefono: boolean, firmaPendiente: boolean, textos: object}} deps  `pedirTelefono` con sesión y sin
 *   teléfono (`phone_missing`, `#692`·3); `firmaPendiente`, si hay descargo que firmar y nunca se firmó.
 * @returns {Record<string, string>}  vacío si no falta nada
 */
export function revisarDatos(f, { pedirTelefono = false, firmaPendiente = false, textos = {} }) {
    const t = (clave) => texto(textos, `compra.datos.errores.${clave}`);
    const errores = {};

    if (f.cuenta === 'dentro') {
        if (pedirTelefono && vacio(f.telefono)) errores.telefono = t('telefono');
    } else if (f.cuenta === 'existe') {
        if (! String(f.correo ?? '').includes('@')) errores.correo = t('correo');
        if (vacio(f.contrasena)) errores.contrasena = t('clave');

        return errores;
    } else {
        if (vacio(f.nombre)) errores.nombre = t('nombre');
        if (! String(f.correo ?? '').includes('@')) errores.correo = t('correo');
        if (vacio(f.telefono)) errores.telefono = t('telefono');
        if (vacio(f.contrasena)) errores.contrasena = t('contrasena');
    }

    if (firmaPendiente && f.descargo !== true) errores.descargo = t('descargo');

    return errores;
}

/** El campo de la isla que corresponde a cada campo del alta. Lo que no está aquí va arriba, al resumen. */
const CAMPOS = { name: 'nombre', email: 'correo', phone: 'telefono', password: 'contrasena', accept_waiver: 'descargo', waiver_document_id: 'descargo' };

/**
 * Los «no» del servidor, a su campo, con SU mensaje.
 *
 * @param {Record<string, string>} campos  `registerErrors().fields`
 * @returns {{errores: Record<string, string>, resto: string[]}}
 */
export function erroresDelServidor(campos) {
    const errores = {};
    const resto = [];

    for (const [clave, mensaje] of Object.entries(campos ?? {})) {
        if (CAMPOS[clave]) errores[CAMPOS[clave]] ??= mensaje;
        else resto.push(mensaje);
    }

    return { errores, resto };
}

/** ¿El «no» del alta es que la cuenta YA EXISTE (verificada o no)? Entonces se pide su contraseña, allí mismo. */
export const cuentaQueYaExiste = (signup) => signup === 'already_registered' || signup === 'pending_verification';

/**
 * El «no» de ENTRAR con la contraseña de una cuenta que ya existe (`runLogin()` tal cual). Las credenciales, con la
 * frase del diseño bajo la contraseña —no dice cuál de las dos falla, como el acceso de siempre (`SEC-06`)—, por su
 * CÓDIGO y no por el texto; un campo, con su mensaje; el limitador y el corte de red, arriba.
 *
 * @returns {{errores: Record<string, string>, aviso: string}}
 */
export function erroresDelAcceso(resultado, textos = {}) {
    if (resultado?.response?.error?.code === INVALID_CREDENTIALS) {
        return { errores: { contrasena: texto(textos, 'compra.datos.errores.entrar') }, aviso: '' };
    }

    const campos = resultado?.errors?.fields ?? {};
    const errores = {};

    if (campos.email) errores.correo = campos.email;
    if (campos.password) errores.contrasena = campos.password;

    return { errores, aviso: resultado?.errors?.global ?? '' };
}

/**
 * ¿Hay descargo que FIRMAR aquí? Sin sesión, si la instalación sirve un texto firmable (`GET /legal/waiver`: solo en
 * el modo interno y con versión publicada). Con sesión, si esa cuenta nunca firmó (`waiver.required`) y no dejó ya
 * su aceptación esperando al correo (`waiver.pending`, `#181`), y hay texto que enseñar. Una firma de una versión
 * anterior (`outdated`) no se pide aquí: la puerta la deja pasar, y el diseño solo pide la casilla a quien nunca firmó.
 */
export function firmaPendiente({ cuenta, contexto = null, documento = null }) {
    if (documento === null) return false;
    if (cuenta !== 'dentro') return true;

    return contexto?.waiver?.required === true && contexto?.waiver?.pending !== true;
}

/** El formulario de la isla, en el del alta del motor (`stores/auth.js`). */
export function formularioDeAlta(f) {
    return { name: f.nombre.trim(), email: f.correo.trim(), phone: f.telefono.trim(), password: f.contrasena, accept_waiver: f.descargo === true };
}
