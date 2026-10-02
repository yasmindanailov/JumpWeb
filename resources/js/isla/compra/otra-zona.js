/**
 * **LA OTRA ZONA en la pantalla 0** (K2 de `docs/specs/otra-zona.md` §4.2, `DECISIONES #878`): en una compra de ENTRADAS,
 * añadir las de otra zona sin salir —el enlace «¿Alguien va a JUMP? Añádelo a la misma reserva» (D3-B) y su tarjeta: la
 * primera fila de esa zona, su precio de ese día, su gente y «Quitar» (el mockup, `pantalla-0.jsx`)—, con el MISMO día y la
 * MISMA hora para todo el pedido (D1-A).
 *
 * «La otra zona» son las zonas de entradas de la isla con oferta ESE día, menos la del pedido: con una, se nombra; con
 * varias, el texto genérico; sin ninguna, nada (`otra-zona.md` §2). Nada de negocio quemado: zonas, filas, nombres y
 * precios son datos del catálogo y de `availability/{id}/dates`.
 *
 * ⚠️ Las horas: solo valen las que caben para TODAS las líneas, cada una en SU zona —el mockup sumaba la gente de las dos,
 * pero cada zona tiene sus franjas—. La oferta de la otra se pide con la cesta (`AFORO-02`: la oferta lleva lo que el
 * cliente ya retiene). Aquí solo se cruza lo que el servidor ofrece; el dinero, del presupuesto (`PAY-12`).
 * Puro, salvo el cargador de horas (`api` por parámetro, `CE-6`); se prueba con `node --test`.
 */
import { toApiItems } from '../../sidebar/cart.js';
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';
import { euros, horaCorta, precioDelDia } from './vista.js';
import { filasDeZona, zonasConEntradas } from './pantalla-cuando.js';

/** Las OTRAS zonas de entradas (todas menos la del pedido), cada una con su PRIMERA fila (el mockup: «1 hora»). */
export function otrasZonas(productos, zona) {
    return zonasConEntradas(productos)
        .filter((z) => z.slug !== zona)
        .map((z) => ({ slug: z.slug, name: z.name, fila: filasDeZona(productos, z.slug)[0] ?? null }))
        .filter((z) => z.fila !== null);
}

/** Las que se VENDEN ese día (su primera fila tiene precio ese día). Sin sus días todavía, no se ofrecen: no se promete. */
export const conOfertaElDia = (zonas, precios, dia) => (Array.isArray(zonas) ? zonas : []).filter((z) => precioDelDia(precios, z.fila.id, dia) !== null);

/**
 * La línea que se AÑADE al tocar el enlace: la primera fila de la primera zona que se vende ese día, para UNA persona (el
 * mockup: `{ zona: otraDe(zona), n: 1 }`). `null` si no hay ninguna.
 */
export function otraNueva(zonas, precios, dia) {
    const z = conOfertaElDia(zonas, precios, dia)[0];

    return z ? { fila: z.fila.id, n: 1 } : null;
}

/** Las horas que ofrece la otra fila ese día, con la cesta de contexto (`AFORO-02`). Sin respuesta, `null`: no se sabe. */
export async function cargarHorasDe({ api, fila, dia, lineas = [] }) {
    const r = await api.post(`/availability/${fila}/times`, { date: dia, items: toApiItems(lineas) });

    return r.ok ? (r.data?.data ?? []) : null;
}

/**
 * ¿La otra línea NO cabe a esa hora (`HH:MM`)? Su zona no la ofrece, no se vende o no tiene sitio para su gente. Sin su
 * oferta todavía (`null`), ninguna: mientras llega, no se apaga lo que quizá quepa.
 *
 * @returns {(hora: string) => boolean}
 */
export function horasQueNoCaben(ofrecidasOtra, n) {
    if (! Array.isArray(ofrecidasOtra)) return () => false;
    const caben = new Set(ofrecidasOtra.filter((h) => h.sellable !== false && Number(h.available ?? 0) >= n).map((h) => horaCorta(h.time)));

    return (hora) => ! caben.has(hora);
}

/**
 * **Lo de la otra zona en la pantalla 0**, hecho: el enlace (o `''`), la tarjeta (o `null`), si la de la tarjeta no se vende
 * ese día (`bloquea`: no se puede continuar sin quitarla o cambiar de día), su parte del resumen (« + Jump · 1 hora · 1
 * entrada») y qué horas apagar (`noCabe(hora)`), con su porqué.
 *
 * @param {object} e  `borrador` ({ zona, dia, otra }) · `productos` · `precios` ({ [id]: días }) · `horasOtra` (la oferta de
 *   la fila de la tarjeta ese día, o `null`) · `textos` · `locale`
 */
export function otraDeLaPantalla(e) {
    const { borrador: b, textos, locale } = e;
    const t = (clave) => texto(textos, clave);
    const tp = (clave, p) => textoCon(textos, clave, p);
    const zonas = otrasZonas(e.productos, b.zona);
    const unidad = { uno: t('compra.cuando.entrada'), varios: t('compra.cuando.entradas') };

    if (! b.otra) {
        const conDia = b.dia ? conOfertaElDia(zonas, e.precios, b.dia) : [];
        const enlace = conDia.length === 1 ? tp('compra.cuando.otra_zona_de', { zona: conDia[0].name }) : (conDia.length > 1 ? t('compra.cuando.otra_zona') : '');

        return { enlace, tarjeta: null, bloquea: false, resumen: null, noCabe: () => false, notaHora: '' };
    }

    const fila = (Array.isArray(e.productos) ? e.productos : []).find((p) => p.id === b.otra.fila) ?? null;
    const zona = zonas.find((z) => z.slug === fila?.zone?.slug) ?? null;
    const cents = b.dia ? precioDelDia(e.precios, b.otra.fila, b.dia) : null;
    // Sin sus días todavía no se dice que no se vende: sería mentir un segundo (como las filas de la pantalla, `#830`).
    const bloquea = Boolean(b.dia) && Array.isArray(e.precios?.[b.otra.fila]) && cents === null;
    const n = b.otra.n;

    return {
        enlace: '',
        tarjeta: {
            titulo: fila?.name ?? '',
            precio: bloquea ? tp('compra.cuando.otra_no_vende', { zona: zona?.name ?? '' })
                : (cents === null ? '' : tp('compra.pagar.precio_por', { precio: euros(cents, locale), unidad: unidad.uno })),
            n,
            ...unidad,
        },
        bloquea,
        zona: zona?.name ?? '',
        resumen: fila ? `${fila.name} · ${n} ${n === 1 ? unidad.uno : unidad.varios}` : null,
        noCabe: horasQueNoCaben(e.horasOtra, n),
        notaHora: tp('compra.cuando.otra_no_cabe', { zona: zona?.name ?? '' }),
    };
}
