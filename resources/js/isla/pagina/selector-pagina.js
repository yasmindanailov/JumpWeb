/**
 * **¿Trae la página el SELECTOR de planes?** (el owner, 03-10: «siempre la flecha hacia atrás para poder seleccionar otro
 * producto»). La flecha de la pantalla 0 de la compra vuelve a él desde cualquier producto si la página lo tiene; si no,
 * no hay nada detrás y solo queda la X (`pasos.js::volverDeLaPantallaCero`).
 *
 * Lo dice la configuración de la isla de la página (`#jw-isla-pagina`, `config.plans` con opciones), la misma que lee el
 * aviso del servidor (`aviso-servidor.js`): la compra y la isla de la página son entradas distintas, sin módulos en común,
 * pero con el mismo documento. Módulo plano, con el documento por parámetro (`CE-6`, `node --test`).
 *
 * @param {Document} [doc]
 */
export function haySelectorEnLaPagina(doc = globalThis.document) {
    const el = doc?.getElementById?.('jw-isla-pagina');

    if (! el) return false;
    try {
        const plans = JSON.parse(el.textContent ?? '')?.config?.plans;

        return Array.isArray(plans?.options) && plans.options.length > 0;
    } catch {
        return false;
    }
}
