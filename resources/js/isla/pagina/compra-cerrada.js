/**
 * **LO QUE DEJA LA COMPRA AL CERRARSE** (`#867`, los tres usos de los banners; `isla-y-landing-nueva.md` §4.27): la compra de
 * la isla (`compra/useSeccionCompra.js`) y la isla de la página son dos apps que solo comparten el documento. Al cerrarse, la
 * compra dice qué deja —una reserva a medias, con su línea, o una hecha— y la isla de la página lo dice en su barra
 * (`useCompraCerrada.js`).
 *
 *   · En vivo, con un evento en la ventana (`isla:compra`), ANTES de cerrar.
 *   · A través de una RECARGA: entrar o crear la cuenta dentro de la compra hace que cerrarla recargue la página
 *     (`authChanged`, `sidebar/account/session-gained.js`), y la memoria de la isla se va con ella. Si va a recargar, lo
 *     deja también en la PESTAÑA (`sessionStorage`), para UNA lectura y con 2 minutos de vida: lo bastante para la recarga,
 *     nunca para la visita de mañana. A medias lleva su `vuelta` (`?compra=reanudar`): tras la recarga, la compra se retoma
 *     por `reanudar()`, como al volver de Google.
 *
 * ⚠️ Sin datos personales: el estado, la línea de la reserva (qué, cuándo y cuántos, lo que la compra dice abajo) y la vuelta.
 * Todo va en `try`: el almacén de la pestaña LANZA con las cookies bloqueadas. Módulo plano, con el almacén y la hora por
 * parámetro (`CE-6`, `node --test`); lo importan las dos entradas, como `aviso-servidor.js`.
 */
import { esRutaPropia } from '../../sidebar/marca-compra.js';

export const EVENTO = 'isla:compra';
export const CLAVE = 'jw-isla-compra';
export const VIGENCIA_MS = 2 * 60 * 1000;

/** Los pasos en que cerrar deja la reserva A MEDIAS: hay pedido y no se ha pagado (la hora perdida es de «Pagar»). */
const A_MEDIAS = ['datos', 'pagar', 'perdida'];

/**
 * Lo que deja: a medias, con la línea de la reserva; hecha, y si fue una fiesta; o nada (la pantalla 0, el banco, un
 * desenlace sin pagar). ⚠️ Sin «tu hora queda guardada»: antes de pagar no la hay (`#688`).
 *
 * @param {{paso: string, pedido: object|null, linea?: string|null, fiesta?: boolean}} compra
 * @returns {{estado: 'a-medias', linea: string}|{estado: 'hecho', fiesta: boolean}|null}
 */
export function loQueDeja({ paso, pedido, linea = null, fiesta = false }) {
    if (paso === 'listo') return { estado: 'hecho', fiesta: Boolean(fiesta) };
    if (pedido && A_MEDIAS.includes(paso)) return { estado: 'a-medias', linea: typeof linea === 'string' ? linea : '' };

    return null;
}

/** Lo anuncia a la isla de la página, que lo oye en la ventana. */
export function anunciar(lo, win = globalThis.window) {
    if (lo && win?.dispatchEvent && win.CustomEvent) win.dispatchEvent(new win.CustomEvent(EVENTO, { detail: lo }));
}

/** Lo deja en la pestaña para después de la recarga, con la página a la que vale. Devuelve si se pudo (sin almacén, no). */
export function dejar(almacen, lo, { ruta, ahora, vuelta = null }) {
    if (! lo) return false;

    try {
        almacen?.setItem(CLAVE, JSON.stringify({ ...lo, ruta, vuelta, en: ahora }));

        return Boolean(almacen);
    } catch {
        return false;
    }
}

/**
 * Lo toma UNA vez y lo borra, valga o no: lo dejado en ESTA página hace menos de 2 minutos, o `null`. Solo lo que la compra
 * puede dejar; una `vuelta` que no sea del sitio no vale (la isla navega a ella).
 */
export function tomar(almacen, { ruta, ahora }) {
    let lo;

    try {
        lo = JSON.parse(almacen?.getItem(CLAVE) ?? 'null');
        almacen?.removeItem(CLAVE);
    } catch {
        return null;
    }

    if (lo === null || typeof lo !== 'object' || lo.ruta !== ruta || typeof lo.en !== 'number') return null;
    if (ahora < lo.en || ahora - lo.en > VIGENCIA_MS) return null;
    if (lo.estado === 'hecho') return { estado: 'hecho', fiesta: Boolean(lo.fiesta) };
    if (lo.estado === 'a-medias') {
        return { estado: 'a-medias', linea: typeof lo.linea === 'string' ? lo.linea : '', vuelta: esRutaPropia(lo.vuelta) ? lo.vuelta : null };
    }

    return null;
}
