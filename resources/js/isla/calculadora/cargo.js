/**
 * Lo que CARGA el servidor por un complemento con esa selección, aun sin día ni hora (T4d de
 * `specs/isla-y-landing-nueva.md` §4.12: en la calculadora, los calcetines tienen precio antes de tener hora). Es su
 * `charged_cents` resuelto —el endpoint da los complementos sin día ni hora, pero rechaza `null` en ellos
 * (`compra/oferta.js::cargarGrupos`)—; `null` si no contesta o no lo trae. Nunca se cuenta aquí (`PAY-12`).
 * ⚠️ Aparte de `compra/oferta.js` a propósito: la compra de la isla importa ese módulo, y lo que viviera en él viajaría
 * con ella aunque solo lo use la calculadora (medido en la T4d·4).
 *
 * @returns {Promise<number|null>}
 */
export async function cargarCargo({ api, productId, quantity, addons, id }) {
    const r = await api.post(`/catalog/products/${productId}/addons`, { quantity, addons, choices: [] });
    const cargo = r.ok ? (r.data?.singles ?? []).find((a) => a.product_id === id)?.charged_cents : undefined;

    return Number.isInteger(cargo) ? cargo : null;
}
