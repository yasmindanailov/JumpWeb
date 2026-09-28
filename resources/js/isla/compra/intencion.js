/**
 * **LA INTENCIÓN CON LA QUE SE ABRE LA COMPRA DE LA ISLA → su borrador** (T3e de `docs/specs/isla-y-landing-nueva.md`
 * §4.10; la de la calculadora de la fiesta, T6b·3, `#836`).
 *
 * ⚠️ Aparte de `oferta.js` a propósito, y medido: aquel lo importan también las calculadoras de la página, y lo que
 * viviera en él viajaba con ellas en su trozo compartido aunque solo lo use la compra (la T6b·3a hizo crecer la
 * calculadora de entradas por encima de su techo, `SidebarBundleBudgetTest`, sin tocarla).
 */

/** El borrador de la pantalla 0 en blanco: sin zona, una persona y sin calcetines (`startCuando` del diseño). */
export const borradorVacio = () => ({ modo: 'nuevo', zona: null, elegirZona: false, dia: null, hora: null, fila: null, n: 1, cal: 0, otra: null });

/**
 * El de una FIESTA (T3e·5, `fiesta.js`): sin edad, sin día —una fiesta no nace «para hoy»—, los niños en el mínimo del
 * pack (se sabe con su ficha), el menú que deje elegido el servidor y nada que la alargue.
 */
export const borradorDeFiesta = (zona, fila) => ({ ...borradorVacio(), fiesta: true, zona, fila, edad: null, n: null, menu: null, extras: [] });

/**
 * ¿La intención trae la selección ENTERA de una calculadora de la página, y la compra sigue SOLA hasta «Pagar» (o «Tus
 * datos» si falta algo, `#785`)? La de entradas (`linea`, T4d) y la de la fiesta (`fiesta`, T6b·3), con `continuar`.
 */
export const sigueSola = (intencion) => ['linea', 'fiesta'].includes(intencion?.type) && intencion?.continuar === true;

/**
 * **La intención de la landing → el borrador con el que abre la pantalla 0** (`intent.js` del cajón, en isla).
 *
 *  · `{ type: 'product', id }` de una ENTRADA: su zona, con esa fila ya elegida; de un PACK, su fiesta;
 *  · `{ type: 'packs' }`: la fiesta del primer pack del catálogo;
 *  · `{ type: 'zone', slug }`: esa zona, con su primera fila; si solo vende packs, su fiesta;
 *  · `{ type: 'linea', … }` y `{ type: 'fiesta', … }`: la selección entera de la calculadora de la página (T4d, T6b·3);
 *    `linea` de un PACK (T6c·3), la de colegios: por el camino de la fiesta, que decide con la ficha;
 *  · sin intención, o con una que no casa con el catálogo: «Para hoy», eligiendo zona. Nunca inventa una fila: sale
 *    del listado del servidor. ⚠️ Que el pack sea DE FIESTA (pregunta la edad) lo dice su ficha, que aún no está: lo
 *    comprueba la pantalla al llegar (`fiesta.js::packsDeFiesta`).
 *
 * @param {{type?: string, id?: number, slug?: string}|null} intencion
 * @param {Array<object>} productos  el catálogo tal cual
 */
export function borradorDeIntencion(intencion, productos) {
    const lista = Array.isArray(productos) ? productos : [];
    const entradas = lista.filter((p) => p?.type === 'entry');
    const packs = lista.filter((p) => p?.type === 'pack');
    // «Reservar y pagar» de la calculadora de la página (T4d): la selección ENTERA —la entrada, su día y su hora, cuántos
    // y sus calcetines—. La hora llega en la forma del motor (`HH:MM:SS`); lo que ya no quepa lo vacía la pantalla 0.
    const linea = intencion?.type === 'linea' ? entradas.find((p) => p.id === intencion.id) : null;

    if (linea) {
        return {
            ...borradorVacio(), zona: linea.zone.slug, fila: linea.id, dia: intencion.date ?? null, hora: intencion.time ?? null,
            n: Math.max(1, Number(intencion.quantity) || 1), cal: Number(intencion.addons?.[0]?.quantity) || 0,
        };
    }
    // La de un PACK (la calculadora de colegios, T6c·3): su día, su hora y cuántos, por el camino de las fiestas, que
    // carga las fichas de su zona y decide al llegar (`usePantallaCero::situarFiesta`): con packs de edad, la fiesta pide
    // la edad; sin ellos (una excursión), se vende como las entradas, con esto ya elegido.
    const packDeLinea = intencion?.type === 'linea' ? packs.find((p) => p.id === intencion.id) : null;

    if (packDeLinea) {
        return {
            ...borradorDeFiesta(packDeLinea.zone.slug, packDeLinea.id), dia: intencion.date ?? null, hora: intencion.time ?? null,
            n: Number(intencion.quantity) > 0 ? Number(intencion.quantity) : null,
        };
    }
    // «Reservar y pagar la señal» de la calculadora de la FIESTA de la página (T6b·3, `#836`): el pack de la edad, la edad,
    // su día y su hora, los niños, el menú y lo que ALARGA la fiesta (`extras`, la hora extra). Lo que ya no quepa lo
    // vacía la pantalla 0, y lo que ya no se ofrezca a esa hora lo suelta el servidor al resolver.
    const deFiesta = intencion?.type === 'fiesta' ? packs.find((p) => p.id === intencion.id) : null;

    if (deFiesta) {
        return {
            ...borradorDeFiesta(deFiesta.zone.slug, deFiesta.id), edad: Number.isInteger(intencion.edad) ? intencion.edad : null,
            n: Number(intencion.quantity) > 0 ? Number(intencion.quantity) : null, dia: intencion.date ?? null, hora: intencion.time ?? null,
            menu: intencion.menu == null ? null : String(intencion.menu),
            extras: (Array.isArray(intencion.extras) ? intencion.extras : []).filter((x) => Number.isInteger(x?.product_id) && x.quantity > 0),
        };
    }
    const zonaPedida = intencion?.type === 'zone' ? intencion.slug : null;
    const pack = (intencion?.type === 'product' && packs.find((p) => p.id === intencion.id))
        || (intencion?.type === 'packs' && packs[0])
        || (zonaPedida && ! entradas.some((p) => p.zone?.slug === zonaPedida) && packs.find((p) => p.zone?.slug === zonaPedida))
        || null;

    if (pack) return borradorDeFiesta(pack.zone.slug, pack.id);

    const producto = intencion?.type === 'product' ? entradas.find((p) => p.id === intencion.id) : null;
    const zona = producto?.zone?.slug ?? zonaPedida;
    const fila = producto ?? entradas.find((p) => p.zone?.slug === zona) ?? null;

    return fila ? { ...borradorVacio(), zona: fila.zone.slug, fila: fila.id } : { ...borradorVacio(), elegirZona: true };
}
