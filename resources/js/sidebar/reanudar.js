/**
 * **LA COMPRA QUE SALE A GOOGLE Y VUELVE** (T3e·4 de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #695`).
 *
 * «Continuar con Google» es una redirección del SERVIDOR (`GoogleAuthController`): la página se va a Google y
 * vuelve a `next` —validado contra el mismo sitio, `SEC-08`— con sesión, o a `/registro/google` si la cuenta es
 * nueva. La compra no sobrevive sola a ese viaje: la cesta sí (`localStorage`), pero no DÓNDE se estaba. Por eso:
 *  · la VUELTA es la misma página con `?compra=reanudar` (`vueltaDe`), y con ese parámetro el SERVIDOR la sirve con
 *    la compra abierta, como `/entradas` (`Http\Sidebar\PurchaseResume`): ni un byte en la entrada de las páginas;
 *  · la marca de DÓNDE se estaba va en la PESTAÑA (`sessionStorage`: muere con ella, no la ve otra) y la toma la
 *    compra de la isla al montarse en la vuelta (`vuelveAqui`, `tomarMarca`), para seguir en «Tus datos»;
 *  · la cuenta nueva completa su alta en `/registro/google` y vuelve a esa misma vuelta (`account/after-auth.js`).
 *
 * ⚠️⚠️ **Sin datos personales**: la zona, el día, la hora, cuántos y el complemento por cantidad —lo que dice la
 * pantalla 0— y la página de vuelta. Y **caduca** (30 min): quien fue a Google y no volvió no se encuentra la compra
 * a medias al día siguiente. Todo va en `try`: el almacén de la pestaña LANZA con las cookies bloqueadas.
 *
 * Módulo PLANO con el almacén y la hora por parámetro (`CE-6`), con su `node --test`. LEER la marca vive en
 * `marca-compra.js`, que el motor carga sin esta otra mitad (solo de la isla).
 */
import { CLAVE_REANUDAR, VIGENCIA_MS, almacenDeLaPestana, esRutaPropia, marcaViva } from './marca-compra.js';

export { CLAVE_REANUDAR, VIGENCIA_MS, almacenDeLaPestana, esRutaPropia, marcaViva };

/** El parámetro de la vuelta. ⚠️ Es el mismo que lee el servidor (`PurchaseResume::PARAM`/`VALUE`; lo vigila su test). */
export const PARAMETRO = 'compra';
export const VALOR = 'reanudar';

/** La vuelta de ESTA página: su ruta y su búsqueda, con `?compra=reanudar`. `''` si la ruta no es del sitio. */
export function vueltaDe(ruta, busqueda = '') {
    if (! esRutaPropia(ruta)) return '';
    const params = new URLSearchParams(busqueda);

    params.set(PARAMETRO, VALOR);

    return `${ruta}?${params.toString()}`;
}

/** ¿La búsqueda de esta página es la de una vuelta? */
export const esVuelta = (busqueda) => new URLSearchParams(busqueda ?? '').get(PARAMETRO) === VALOR;

/** La búsqueda SIN la marca de vuelta, para dejar limpia la barra de direcciones (`''` o `?…`). */
export function sinVuelta(busqueda) {
    const params = new URLSearchParams(busqueda ?? '');

    params.delete(PARAMETRO);
    const resto = params.toString();

    return resto === '' ? '' : `?${resto}`;
}

/** La ida a Google con su vuelta: la URL la compone el servidor (`urls.google`); aquí solo se le añade `next`. */
export function conVuelta(url, vuelta) {
    if (typeof url !== 'string' || url === '') return '';
    if (! esRutaPropia(vuelta)) return url;

    return `${url}${url.includes('?') ? '&' : '?'}next=${encodeURIComponent(vuelta)}`;
}

/** Guarda la marca antes de salir. Devuelve si se pudo (sin almacén, se va igual: la cesta sigue guardada). */
export function marcarSalida(almacen, { vuelta, compra = null, ahora }) {
    if (! esRutaPropia(vuelta)) return false;

    try {
        almacen?.setItem(CLAVE_REANUDAR, JSON.stringify({ vuelta, compra, en: ahora }));

        return Boolean(almacen);
    } catch {
        return false;
    }
}

/**
 * ¿Esta página ES la vuelta de una compra que salió a Google? Su búsqueda lleva la marca de vuelta y hay una marca
 * viva de ESTA ruta (la búsqueda se compara por el parámetro y no letra a letra: el navegador puede reescribirla).
 */
export function vuelveAqui(almacen, { ruta, busqueda }, ahora) {
    const marca = esVuelta(busqueda) ? marcaViva(almacen, ahora) : null;

    return marca !== null && marca.vuelta.split('?')[0] === ruta;
}

/** La toma UNA vez: la devuelve (o `null`) y la borra, esté viva o no. */
export function tomarMarca(almacen, ahora) {
    const marca = marcaViva(almacen, ahora);

    try {
        almacen?.removeItem(CLAVE_REANUDAR);
    } catch {
        // Sin almacén no había marca que borrar.
    }

    return marca;
}
