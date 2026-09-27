/**
 * **¿La página trae selector de planes?** (T6a de `docs/specs/isla-y-landing-nueva.md` §4.17): lo mira quien no es la isla
 * de la página —«Reserva tu primera visita» de Mi cuenta (`#824`)— en la configuración que la página publica
 * (`#jw-isla-pagina`, la misma que lee la isla): con él, lo abre; sin él, va a la compra.
 *
 * ⚠️ En su propio módulo y sin dependencias: importada de `pagina.js`, Mi cuenta se llevaba ese módulo entero (+2,6 KiB,
 * medido con `SidebarBundleBudgetTest`). La regla de «trae opciones» es la misma que `pagina.js::planesDe`.
 */
export function paginaConSelector(doc) {
    try {
        const plans = JSON.parse(doc?.getElementById?.('jw-isla-pagina')?.textContent ?? 'null')?.config?.plans;

        return Array.isArray(plans?.options) && plans.options.length > 0;
    } catch {
        return false;
    }
}
