/**
 * **LO QUE LA ISLA MIDE** (la Z6c, el experimento B3; la forma, acordada con el SPA el 02-10 en su buzón): la isla cuenta lo
 * que hace por `JumpWeb.track`, como el cajón con `createExperiments().expose()` (`sidebar/experiments.js`): quien pinta la
 * variante sabe cuándo la pintó de verdad. `JumpWeb.track` es el buzón del cajón en toda página con su script (el tracker
 * lo vacía al llegar y decide qué sale, con el consentimiento); se resuelve en CADA llamada, para no quedarse con el buzón.
 * La analítica es del SPA (`#735`): el contrato de eventos y el informe del experimento son suyos.
 *
 *   · `experiment_exposed` (`{ key: 'isla', variant }`): UNA vez por carga —la isla de la página se vuelve a montar al
 *     cerrarse la compra—, al montarse ABAJO (donde el B3 cambia algo) y solo si el servidor asignó una variante
 *     (`<html data-isla-experimento>`, que la lleva: `Http\Instancia\VarianteDeIsla`). Sin experimento no hay exposición.
 *   · `isla_accion`, `isla_panel` e `isla_razon`, en cada gesto, con su `variante` (la cara que se veía).
 * Módulo plano, con la ventana por parámetro (`CE-6`, `isla/medir.test.js`).
 */
export const CLAVE_EXPERIMENTO = 'isla';

let expuesta = false;

const trackDe = (win) => (typeof win?.JumpWeb?.track === 'function' ? win.JumpWeb.track : null);

/** La exposición al experimento, una vez por carga y solo con variante asignada. Devuelve si la contó. */
export function exponerExperimento(win = globalThis.window) {
    const asignada = win?.document?.documentElement?.getAttribute?.('data-isla-experimento') || null;
    const track = trackDe(win);

    if (expuesta || ! asignada || ! track) return false;
    expuesta = true;
    track('experiment_exposed', { key: CLAVE_EXPERIMENTO, variant: asignada });

    return true;
}

/** Un gesto de la isla, con sus datos; sin `JumpWeb.track` (una página sin el cajón), nada. */
export function medir(nombre, datos, win = globalThis.window) {
    trackDe(win)?.(nombre, datos);
}

/** Solo para las pruebas: otra carga de la página. */
export function otraCarga() {
    expuesta = false;
}
