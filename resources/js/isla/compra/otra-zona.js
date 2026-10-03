/**
 * **LA OTRA ZONA en la pantalla 0** (K2 de `docs/specs/otra-zona.md` §4.2, `DECISIONES #878`): en una compra de ENTRADAS,
 * añadir las de otra zona sin salir —el enlace «¿Alguien va a JUMP? Añádelo a la misma reserva» (D3-B) y su tarjeta—, con
 * el MISMO día y la MISMA hora de entrada para todo el pedido (D1-A).
 * ▶ Desde la K2·b (`otra-zona.md` §4.7, `#882`, `[DECIDIDO owner]`), la tarjeta es una ENTRADA COMPLETA en pequeño: para
 * quién es (sus edades), «¿Cuánto tiempo?» con los de su zona —de partida, el de la entrada del pedido (`filaParecida`):
 * nada se elige a escondidas, el precio está a la vista—, «¿Cuántos?», sus complementos (nunca marcados) y «Quitar». Y
 * si los grupos salen a horas distintas, el resumen dice a cuál sale cada uno.
 *
 * «La otra zona» son las zonas de entradas de la isla con oferta ESE día, menos la del pedido: con una, se nombra; con
 * varias, el texto genérico; sin ninguna, nada (`otra-zona.md` §2). Nada de negocio quemado: zonas, filas, duraciones,
 * edades y precios son datos del catálogo y de `availability/{id}/dates`; lo que alarga un complemento, de su ficha.
 *
 * ⚠️ Las horas: solo valen las que caben para TODAS las líneas, cada una en SU zona —el mockup sumaba la gente de las dos,
 * pero cada zona tiene sus franjas—. La oferta de la otra se pide con la cesta (`AFORO-02`: la oferta lleva lo que el
 * cliente ya retiene). Aquí solo se cruza lo que el servidor ofrece; el dinero, del presupuesto (`PAY-12`).
 * Puro, salvo el cargador de horas (`api` por parámetro, `CE-6`); se prueba con `node --test`.
 */
import { toApiItems } from '../../sidebar/cart.js';
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';
import { horaCorta, precioDelDia } from './vista.js';
import { filasDeZona, opcionesDeTiempo, rangoHorario, zonasConEntradas } from './pantalla-cuando.js';
import { complementosDe, estancia, gruposComoFilas } from './complementos.js';

/** Las OTRAS zonas de entradas (todas menos la del pedido), cada una con sus filas —sus tiempos, en el orden del catálogo—. */
export function otrasZonas(productos, zona) {
    return zonasConEntradas(productos)
        .filter((z) => z.slug !== zona)
        .map((z) => ({ slug: z.slug, name: z.name, filas: filasDeZona(productos, z.slug) }))
        .filter((z) => z.filas.length > 0);
}

/** Las filas de una zona que se VENDEN ese día (con precio ese día). Sin sus días todavía, ninguna: no se promete. */
const queSeVenden = (zona, precios, dia) => zona.filas.filter((f) => precioDelDia(precios, f.id, dia) !== null);

/** Las zonas que se venden ese día: alguna de sus filas tiene precio ese día. */
export const conOfertaElDia = (zonas, precios, dia) => (Array.isArray(zonas) ? zonas : []).filter((z) => queSeVenden(z, precios, dia).length > 0);

/** Los minutos de una fila; sin ellos (ilimitada), sin tope. */
const minutosDe = (fila) => (Number.isInteger(fila?.duration_min) && fila.duration_min > 0 ? fila.duration_min : Infinity);

/**
 * **El tiempo de partida de la otra línea** (`#882`): la fila con la MISMA duración que la del pedido; si su zona no la
 * tiene, la más larga sin pasarse (una ilimitada no tiene tope: Kids ilimitada → Jump 2 horas); si todas se pasan, la
 * primera. Las dos reglas son una: la más larga que no pasa de la del pedido —la misma, si la hay—. Es la decisión que el
 * cliente ya tomó, no una venta: la tarjeta la enseña con su precio y se cambia de un toque.
 *
 * @param {Array<object>} filas  las que se pueden elegir (las que se venden ese día), en el orden del catálogo
 * @param {number|null|undefined} duracion  los minutos de la fila del pedido (sin ellos, ilimitada)
 */
export function filaParecida(filas, duracion) {
    const lista = Array.isArray(filas) ? filas : [];
    const tope = Number.isInteger(duracion) && duracion > 0 ? duracion : Infinity;
    // La más larga sin pasarse; entre iguales, la primera del catálogo (`reduce` se queda con la que ya tenía).
    const larga = lista.filter((f) => minutosDe(f) <= tope)
        .reduce((mejor, f) => (mejor === null || minutosDe(f) > minutosDe(mejor) ? f : mejor), null);

    return larga ?? lista[0] ?? null;
}

/**
 * La línea que se AÑADE al tocar el enlace: de la primera zona que se vende ese día, su fila PARECIDA a la del pedido
 * (`filaParecida`), para UNA persona (el mockup: `{ zona: otraDe(zona), n: 1 }`), sin complementos. `null` si no hay ninguna.
 */
export function otraNueva(zonas, precios, dia, duracion) {
    const z = conOfertaElDia(zonas, precios, dia)[0];
    const fila = z ? filaParecida(queSeVenden(z, precios, dia), duracion) : null;

    return fila ? { fila: fila.id, n: 1, extras: [], elecciones: {} } : null;
}

/**
 * La otra línea tras CAMBIAR DE DÍA: si su tiempo no se vende ese día y otro de su zona sí, el parecido (la regla de
 * `otraNueva`), con su gente y sin lo elegido de la fila anterior —otro producto, otros complementos—; si no, la misma (si
 * su zona entera no se vende, la tarjeta lo dice). Sin los días de su fila todavía, la misma: no se mueve lo que quizá se venda.
 */
export function otraDelDia(otra, zonas, precios, dia, duracion) {
    if (! otra || ! dia || ! Array.isArray(precios?.[otra.fila]) || precioDelDia(precios, otra.fila, dia) !== null) return otra;
    const zona = (Array.isArray(zonas) ? zonas : []).find((z) => z.filas.some((f) => f.id === otra.fila)) ?? null;
    const fila = zona ? filaParecida(queSeVenden(zona, precios, dia), duracion) : null;

    return fila ? { ...otra, fila: fila.id, extras: [], elecciones: {} } : otra;
}

/** Para quién es una fila (`guest_age_min`/`guest_age_max`): «De 4 a 7 años», «Desde 8 años», «Hasta 7 años» o nada. */
export function edadesDe(fila, textos) {
    const min = Number.isInteger(fila?.guest_age_min) && fila.guest_age_min > 0 ? fila.guest_age_min : null;
    const max = Number.isInteger(fila?.guest_age_max) ? fila.guest_age_max : null;

    if (min !== null && max !== null) return textoCon(textos, 'compra.cuando.edades_de_a', { min, max });
    if (min !== null) return textoCon(textos, 'compra.cuando.edades_desde', { min });

    return max !== null ? textoCon(textos, 'compra.cuando.edades_hasta', { max }) : '';
}

/**
 * La hora a la que SALE quien entra a `hora` («11:00» o «11:00:00») y se queda `minutos`: «13:00». Como el aforo, sin
 * pasar de medianoche (`SlotAvailability::spanEnd` topa en las 24:00). Sin hora o sin minutos, `null`.
 */
export function finDe(hora, minutos) {
    const [h, m] = String(hora ?? '').split(':').map(Number);

    if (! Number.isInteger(h) || ! Number.isInteger(m) || ! Number.isInteger(minutos)) return null;
    const fin = Math.min(h * 60 + m + minutos, 24 * 60);

    return `${String(Math.floor(fin / 60)).padStart(2, '0')}:${String(fin % 60).padStart(2, '0')}`;
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
 * ese día (`bloquea`: no se puede continuar sin quitarla o cambiar de día), su parte del resumen («Jump · 1 hora · 1
 * entrada»; con estancias distintas, con su hora de salida: «Jump · 1 hora · 11:00–12:00 · 1 entrada»), a qué hora sale
 * entonces el grupo del pedido (`finPrincipal`) y qué horas apagar (`noCabe(hora)`), con su porqué.
 *
 * @param {object} e  `borrador` ({ zona, dia, hora, otra: { fila, n, extras, elecciones } }) · `productos` · `precios`
 *   ({ [id]: días }) · `horasOtra` (la oferta de su fila ese día, o `null`) · `fichaOtra` (la ficha de su fila: sus
 *   complementos) · `sueltosOtra` (sus complementos resueltos sin hora) · `conHoraOtra` (resueltos con su hora, o `null`) ·
 *   `gruposOtra` (sus grupos de elección, `#881`) · `principal` ({ duracion, complementos }: la fila del pedido y sus filas
 *   de complementos, para su estancia) · `excluir` (ids que la pantalla ya pregunta para todos: los calcetines) · `textos` ·
 *   `locale`
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

        return { enlace, tarjeta: null, bloquea: false, falta: null, resumen: null, finPrincipal: null, noCabe: () => false, notaHora: '' };
    }

    const zona = zonas.find((z) => z.filas.some((f) => f.id === b.otra.fila)) ?? null;
    const fila = zona?.filas.find((f) => f.id === b.otra.fila) ?? null;
    const vendidas = zona && b.dia ? queSeVenden(zona, e.precios, b.dia) : [];
    const llegaron = (f) => Array.isArray(e.precios?.[f.id]);
    // Lo que no se vende ese día, ya con sus días: su zona ENTERA (la tarjeta lo dice en rojo) o solo SU TIEMPO (su opción lo
    // dice, apagada; `otraDelDia` lo evita al cambiar de día). Con cualquiera, no se continúa: lo que falta es eso (M2).
    const zonaNo = Boolean(b.dia && zona) && zona.filas.every(llegaron) && vendidas.length === 0;
    const filaNo = Boolean(b.dia && fila) && llegaron(fila) && ! vendidas.includes(fila);
    const n = b.otra.n;
    const hora = horaCorta(b.hora);
    const complementos = [
        ...gruposComoFilas(e.gruposOtra, { elecciones: b.otra.elecciones ?? {} }),
        ...complementosDe({
            ficha: e.fichaOtra?.id === b.otra.fila ? e.fichaOtra : null, excluir: e.excluir ?? [], extras: b.otra.extras ?? [],
            sinHora: e.sueltosOtra, conHora: b.hora ? (e.conHoraOtra ?? null) : null, hora, textos,
        }),
    ];
    // ¿Salen a horas distintas? Cada estancia con lo que la alarga (la hora extra elegida y que cabe): si no coinciden, cada
    // grupo dice su hora de salida (una ilimitada no tiene).
    const suya = estancia(fila?.duration_min, complementos);
    const delPedido = estancia(e.principal?.duracion, e.principal?.complementos);
    const distintas = Boolean(b.hora) && suya !== delPedido;
    const rango = distintas && suya !== null ? rangoHorario(hora, finDe(b.hora, suya)) : null;

    return {
        enlace: '',
        tarjeta: {
            titulo: zona?.name ?? '',
            // Para quién es; si su zona no se vende ese día, eso, en rojo (`noSeVende`).
            pista: zonaNo ? tp('compra.cuando.otra_no_vende', { zona: zona?.name ?? '' }) : edadesDe(fila, textos),
            noSeVende: zonaNo,
            filas: zona ? opcionesDeTiempo(zona.filas, { precios: e.precios, dia: b.dia, textos, locale }) : [],
            fila: String(b.otra.fila),
            n,
            ...unidad,
            complementos,
        },
        bloquea: zonaNo || filaNo,
        // A dónde lleva «lo que falta» (M2): la tarjeta, si su zona no se vende ese día; si solo su tiempo, su «¿Cuánto tiempo?».
        falta: zonaNo ? 'pjc-q-otra' : (filaNo ? 'pjc-q-otra-tiempo' : null),
        zona: zona?.name ?? '',
        resumen: fila ? [fila.name, rango, `${n} ${n === 1 ? unidad.uno : unidad.varios}`].filter(Boolean).join(' · ') : null,
        finPrincipal: distintas && delPedido !== null ? finDe(b.hora, delPedido) : null,
        noCabe: horasQueNoCaben(e.horasOtra, n),
        notaHora: tp('compra.cuando.otra_no_cabe', { zona: zona?.name ?? '' }),
    };
}
