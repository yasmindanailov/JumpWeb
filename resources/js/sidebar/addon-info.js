/**
 * El «Más info» de un complemento (`#589`, `[DECIDIDO owner]`): qué se enseña al abrirlo.
 *
 * Los REGALOS de un complemento solo se ven AHÍ —la fila no los enseña—, así que el botón tiene que
 * salir también con regalos y sin ventajas: sin eso, un complemento que solo regala algo no lo
 * enseñaría nunca.
 *
 * ⚠️ Módulo plano y no lógica en el componente (CE-6, `SidebarComponentBudgetTest`).
 */

/**
 * Los regalos del complemento, siempre como lista. ⚠️ `gifts` puede no venir —un cliente de la API
 * anterior a `#589` no lo manda— y el cajón no puede romperse por eso.
 *
 * @param {{gifts?: unknown}|null|undefined} addon
 * @returns {string[]}
 */
export function addonGifts(addon) {
    return Array.isArray(addon?.gifts) ? addon.gifts : [];
}

/**
 * ¿Hay algo que abrir en el «Más info»? Ventajas o regalos.
 *
 * @param {{features?: unknown, gifts?: unknown}|null|undefined} addon
 * @returns {boolean}
 */
export function hasAddonInfo(addon) {
    return (Array.isArray(addon?.features) && addon.features.length > 0) || addonGifts(addon).length > 0;
}
