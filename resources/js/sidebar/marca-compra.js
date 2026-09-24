/**
 * **LEER la marca de la compra que salió a Google** (T3e·4, `DECISIONES #695`): la mitad de `reanudar.js` que necesita
 * el MOTOR —el alta con Google (`account/after-auth.js`) mira si hay una compra a la que volver—, aparte para que
 * el motor no cargue la otra mitad, que solo usa la compra de la isla (escribir la marca, la vuelta, la limpieza):
 * el motor lo descarga todo el que abre la compra (`SidebarBundleBudgetTest`). El porqué entero, en `reanudar.js`.
 */
export const CLAVE_REANUDAR = 'jw-compra-reanudar';

/** Lo que vale la marca: un viaje a Google y vuelta, con margen para completar un alta. */
export const VIGENCIA_MS = 30 * 60 * 1000;

/** El almacén de la pestaña, o `null` si el navegador no lo deja tocar (LANZA con las cookies bloqueadas). */
export function almacenDeLaPestana(win = globalThis.window) {
    try {
        return win?.sessionStorage ?? null;
    } catch {
        return null;
    }
}

/**
 * ¿Es una ruta del PROPIO sitio? Empieza por `/`, y no por `//` ni con `\` (el navegador los trata como otro host):
 * la misma regla que el servidor aplica a `next` (`safeDestination`).
 */
export function esRutaPropia(ruta) {
    return typeof ruta === 'string' && ruta.startsWith('/') && ! ruta.startsWith('//') && ! ruta.includes('\\');
}

/** La marca, si existe, está bien formada y no ha caducado. No la consume. */
export function marcaViva(almacen, ahora) {
    let marca;

    try {
        marca = JSON.parse(almacen?.getItem(CLAVE_REANUDAR) ?? 'null');
    } catch {
        return null;
    }

    if (marca === null || typeof marca !== 'object' || ! esRutaPropia(marca.vuelta) || typeof marca.en !== 'number') return null;
    if (ahora < marca.en || ahora - marca.en > VIGENCIA_MS) return null;

    return marca;
}
