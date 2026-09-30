/**
 * **Entrar con un código al correo, en la compra de la isla** (A3 de `docs/specs/acceso-con-codigo.md`, `#848`/`#849`): la
 * PUERTA (`POST /auth/code`), ENTRAR con el código (`POST /auth/login`) y sus «no», sin estado.
 *
 * ⚠️ Viaja en el trozo DIFERIDO de los pasos (`pasos-diferidos.js`), que la compra pide al montarse, y no en el de la compra:
 * cuando alguien pulsa «Continuar» ya está aquí, y la compra no paga su peso (medido: dentro, 166,46 de un techo de 166;
 * el precedente, `datos-reserva.js` en `#839`). `useDatosCompra` lo pide con `import()` del mismo trozo.
 */
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';
import { INVALID_CREDENTIALS } from '../../sidebar/login.js';

/**
 * LA PUERTA: ¿tiene cuenta este correo? Con cuenta, el servidor le manda ya el código (`next: code`); nuevo, `register`.
 * El límite del CORREO dice `next: code` también: hay uno recién enviado.
 *
 * @returns {Promise<{siguiente: ?string, r: object}>}
 */
export async function puerta(api, correo) {
    const r = await api.post('/auth/code', { email: String(correo ?? '').trim() });

    return { siguiente: r.ok ? r.data?.next ?? null : r.error?.params?.next ?? null, r };
}

/** ENTRAR con el código: el servidor abre la sesión (recordada 90 días, `#848`) y devuelve el perfil. */
export function entrar(api, correo, codigo) {
    return api.post('/auth/login', { email: String(correo ?? '').trim(), code: String(codigo ?? '').trim() });
}

/** El primer mensaje de un campo del sobre de error de la API (`fields.x` es una lista). */
const primero = (v) => (Array.isArray(v) ? v[0] : v) ?? '';

/**
 * El «no» de la PUERTA o de ENTRAR CON EL CÓDIGO, sobre el `ApiResult` tal cual. El código malo, con la frase de siempre
 * bajo el código —no dice si caducó, se gastó o no casa: a quien lo escribe le basta «pide otro»—, por su CÓDIGO de error
 * y no por el texto; el limitador, con cuánto esperar; un campo, con su mensaje; el resto (el corte de red), arriba con
 * `generico`.
 *
 * @param {{ok: boolean, error: ?{code: string, message: string, fields?: object, params?: object}}} r
 * @returns {{errores: Record<string, string>, aviso: string}}
 */
export function erroresDelCodigo(r, textos = {}, generico = '') {
    const e = r?.error ?? null;

    if (e?.code === INVALID_CREDENTIALS) return { errores: { codigo: texto(textos, 'compra.datos.errores.codigo_mal') }, aviso: '' };
    if (e?.code === 'too_many_requests') {
        return { errores: {}, aviso: textoCon(textos, 'compra.datos.errores.espera', { n: e.params?.retry_after ?? 60 }) };
    }

    const errores = {};
    if (e?.fields?.email) errores.correo = primero(e.fields.email);
    if (e?.fields?.code) errores.codigo = primero(e.fields.code);

    return { errores, aviso: Object.keys(errores).length ? '' : (e?.message || generico) };
}

/** El mismo «no», en la ÚNICA línea de error de «Entra» (bajo su campo, como la pinta el diseño). */
export function errorDeEntrar(r, textos = {}, generico = '') {
    const { errores, aviso } = erroresDelCodigo(r, textos, generico);

    return errores.codigo || errores.correo || aviso || '';
}
