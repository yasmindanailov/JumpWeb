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
import { isoDeFecha } from '../ui/fecha.js';

/**
 * El formulario en blanco. `cuenta`: `nueva` (alta), `existe` (su código) o `dentro` (con sesión). `nacimiento`, la
 * fecha del titular como se teclea («07/03/1988»): entera y OPCIONAL (`DECISIONES #792`).
 * ▶ Sin CONTRASEÑA desde la A3 del acceso con código (`specs/acceso-con-codigo.md`, `#848`/`#849`): la cuenta nueva
 * nace sin ella, y la que ya existe entra con el `codigo` que le llega al correo.
 */
export const datosVacios = () => ({ nombre: '', correo: '', telefono: '', nacimiento: '', codigo: '', descargo: false, cuenta: 'nueva' });

/**
 * **La fecha de nacimiento, para el servidor**: vacía, `''` (no se manda: `register.js` y `google.js` solo la llevan si
 * hay una); completa y de un día que existe, en `Y-m-d`; a medias, `null` —`revisarDatos` la para antes—. Qué fechas
 * valen (no futura, mayor de edad) lo decide el SERVIDOR (`BirthDatePolicy`), y su «no» sale bajo el campo.
 */
export function nacimientoDeAlta(valor) {
    return String(valor ?? '').trim() === '' ? '' : isoDeFecha(String(valor).trim());
}

/**
 * «Entra» en blanco (`PjcEntrar`, T3e·4): `paso` `id` (el correo) o `codigo` (el que le acaba de llegar), la puerta del
 * acceso con código (A3, `#849`): el correo decide —con cuenta, el código; nuevo, «Tus datos»—.
 * ⚠️ Solo CORREO (`#695`, `[DECIDIDO owner]`): el acceso del producto no admite teléfono (`LoginRequest`).
 */
export const entradaVacia = (valor = '') => ({ paso: 'id', valor, codigo: '', error: '', reenvios: 0 });

const vacio = (valor) => String(valor ?? '').trim() === '';

/**
 * Lo que falta, antes de preguntar a nadie.
 *
 * @param {ReturnType<typeof datosVacios>} f
 * @param {{pedirTelefono: boolean, firmaPendiente: boolean, textos: object}} deps  `pedirTelefono`, si ESTE pedido lo
 *   exige (`#787`: una fiesta —en el alta, o con sesión y sin él, `phone_missing`— o el servidor al pagar);
 *   `firmaPendiente`, si hay descargo que firmar y nunca se firmó.
 * @returns {Record<string, string>}  vacío si no falta nada
 */
export function revisarDatos(f, { pedirTelefono = false, firmaPendiente = false, textos = {} }) {
    const t = (clave) => texto(textos, `compra.datos.errores.${clave}`);
    const errores = {};

    if (f.cuenta === 'dentro') {
        if (pedirTelefono && vacio(f.telefono)) errores.telefono = t('telefono');
    } else if (f.cuenta === 'google') {
        // El alta que vuelve de Google (`#785`): el correo es el suyo y no se teclea; queda el nombre (y la casilla), y el
        // teléfono en una fiesta.
        if (vacio(f.nombre)) errores.nombre = t('nombre');
        if (pedirTelefono && vacio(f.telefono)) errores.telefono = t('telefono');
        if (nacimientoDeAlta(f.nacimiento) === null) errores.nacimiento = t('nacimiento');
    } else if (f.cuenta === 'existe') {
        if (! String(f.correo ?? '').includes('@')) errores.correo = t('correo');
        if (vacio(f.codigo)) errores.codigo = t('codigo');

        return errores;
    } else {
        if (vacio(f.nombre)) errores.nombre = t('nombre');
        if (! String(f.correo ?? '').includes('@')) errores.correo = t('correo');
        // El teléfono, solo en una fiesta (`#787`): en una entrada el alta va sin él.
        if (pedirTelefono && vacio(f.telefono)) errores.telefono = t('telefono');
        // Opcional: solo se para la que está A MEDIAS o no existe («31/02/1990»); vacía, pasa.
        if (nacimientoDeAlta(f.nacimiento) === null) errores.nacimiento = t('nacimiento');
    }

    if (firmaPendiente && f.descargo !== true) errores.descargo = t('descargo');

    return errores;
}

/**
 * **¿«Tus datos» tiene algo que pedir?** (`#785`, el owner: «si no vamos a poner algo REALMENTE relevante que merezca
 * una pantalla, directamente la quitamos»). Sin sesión, sí: entrar o crear la cuenta. Con ella, solo lo que de verdad
 * falta —el teléfono que ese pedido exige, la firma que esa cuenta nunca hizo—; si no falta nada, la compra va a
 * «Pagar». ⚠️ No es la autoridad: si al pagar el servidor pide algo, la compra vuelve aquí con su campo (`alPagarMal`).
 *
 * @param {{identificado: boolean, pedirTelefono: boolean, firma: boolean}} e
 */
export function hayQuePedir({ identificado, pedirTelefono = false, firma = false }) {
    return ! identificado || pedirTelefono || firma;
}

/** El campo de la isla que corresponde a cada campo del alta. Lo que no está aquí va arriba, al resumen. */
const CAMPOS = { name: 'nombre', email: 'correo', phone: 'telefono', born_on: 'nacimiento', code: 'codigo', accept_waiver: 'descargo', waiver_document_id: 'descargo' };

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

/**
 * ¿El «no» del alta es que la cuenta YA EXISTE (verificada o no)? Entonces se pide su código, allí mismo. La puerta, entrar
 * con el código y sus «no» viven en `acceso.js`, en el trozo diferido de los pasos (A3, `#849`).
 */
export const cuentaQueYaExiste = (signup) => signup === 'already_registered' || signup === 'pending_verification';

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

/**
 * El formulario de la isla, en el del alta del motor (`stores/auth.js`). La fecha, en `Y-m-d` o vacía (`#792`). ⚠️ La
 * contraseña va VACÍA (A3, `#849`): el alta ya no la pide (`POST /auth/register` 1.55.0) y el servidor la guarda `NULL`.
 */
export function formularioDeAlta(f) {
    return {
        name: f.nombre.trim(), email: f.correo.trim(), phone: f.telefono.trim(), born_on: nacimientoDeAlta(f.nacimiento) ?? '',
        password: '', accept_waiver: f.descargo === true,
    };
}
