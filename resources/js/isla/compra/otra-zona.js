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

/** Las zonas de entradas, TODAS, cada una con sus filas —sus tiempos, en el orden del catálogo—. */
export function zonasConFilas(productos) {
    return zonasConEntradas(productos)
        .map((z) => ({ slug: z.slug, name: z.name, filas: filasDeZona(productos, z.slug) }))
        .filter((z) => z.filas.length > 0);
}

/** Las OTRAS zonas de entradas: todas menos la del pedido (las del enlace «¿Alguien va a…?»). */
export const otrasZonas = (productos, zona) => zonasConFilas(productos).filter((z) => z.slug !== zona);

/** Las filas de una zona que se VENDEN ese día (con precio ese día). Sin sus días todavía, ninguna: no se promete. */
export const queSeVenden = (zona, precios, dia) => zona.filas.filter((f) => precioDelDia(precios, f.id, dia) !== null);

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
 * Una línea añadida tras CAMBIAR DE DÍA: si su tiempo no se vende ese día y otro de su zona sí, el parecido (la regla de
 * `otraNueva`), con su gente y sin lo elegido de la fila anterior —otro producto, otros complementos—; si no, la misma (si
 * su zona entera no se vende, la tarjeta lo dice). Sin los días de su fila todavía, la misma: no se mueve lo que quizá se venda.
 * `zonas`: TODAS las de entradas (`zonasConFilas`): desde la K3 una línea puede ser de la zona del pedido.
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
 * Una LÍNEA AÑADIDA del borrador, vista: su zona y su fila (entre TODAS las de entradas: desde la K3 puede ser la del pedido,
 * con otro tiempo), si no se vende ese día —su zona entera (`zonaNo`) o solo su tiempo (`filaNo`), ya con sus días—, sus
 * complementos (los de SU ficha, menos los que la pantalla pregunta para todos) y su estancia.
 */
function lineaVista(o, e, todas) {
    const b = e.borrador;
    const zona = todas.find((z) => z.filas.some((f) => f.id === o.fila)) ?? null;
    const fila = zona?.filas.find((f) => f.id === o.fila) ?? null;
    const vendidas = zona && b.dia ? queSeVenden(zona, e.precios, b.dia) : [];
    const llegaron = (f) => Array.isArray(e.precios?.[f.id]);
    const ficha = e.fichasOtras?.[o.fila] ?? null;
    const complementos = [
        ...gruposComoFilas(e.gruposOtras?.[o.fila], { elecciones: o.elecciones ?? {} }),
        ...complementosDe({
            ficha: ficha?.id === o.fila ? ficha : null, excluir: e.excluir ?? [], extras: o.extras ?? [],
            sinHora: e.sueltosOtras?.[o.fila], conHora: b.hora ? (e.conHoraOtras?.[o.fila] ?? null) : null, hora: horaCorta(b.hora), textos: e.textos,
        }),
    ];

    return {
        o, zona, fila, complementos,
        zonaNo: Boolean(b.dia && zona) && zona.filas.every(llegaron) && vendidas.length === 0,
        filaNo: Boolean(b.dia && fila) && llegaron(fila) && ! vendidas.includes(fila),
        estancia: estancia(fila?.duration_min, complementos),
        noCabe: horasQueNoCaben(e.horasOtras?.[o.fila], o.n),
    };
}

/**
 * **Lo de las líneas añadidas en la pantalla 0**, hecho (K2 de `otra-zona.md` §4.2; su tarjeta completa, K2·b, §4.7;
 * VARIAS, K3, §4.3): el enlace a la otra zona (o `''`: en cuanto hay una línea, más se añaden desde «Pagar»), una tarjeta
 * por línea (`tarjetas`), si alguna no se vende ese día (`bloquea`) y lo que falta entonces (`falta` y `faltaZona`: la
 * PRIMERA; su tarjeta si no se vende su zona, su «¿Cuánto tiempo?» si solo su tiempo), su parte del resumen («Jump · 1 hora ·
 * 1 entrada»; si las estancias no coinciden, cada una con su tramo), a qué hora sale el grupo del pedido (`finPrincipal`) y,
 * de una hora, por qué no cabe (`porQueNoCabe(hora)`: la zona de la primera que no cabe; `''`, caben todas).
 * ⚠️ En una tarjeta, el tiempo que YA está en la reserva —el del pedido o el de otra tarjeta— se ve apagado: la misma fila
 * no es otra línea, es más gente en la suya (`linea.js::otrasDe`).
 *
 * @param {object} e  `borrador` ({ zona, fila, dia, hora, otras: [{ fila, n, extras, elecciones }] }) · `productos` ·
 *   `precios` ({ [id]: días }) · y, POR FILA: `horasOtras` (su oferta ese día), `fichasOtras` (su ficha), `sueltosOtras` (sus
 *   complementos sin hora), `conHoraOtras` (a esa hora) y `gruposOtras` (sus grupos, `#881`) · `principal` ({ duracion,
 *   complementos }: los del pedido, para su estancia) · `excluir` (lo que se pregunta para todos: los calcetines) · `textos`
 *   · `locale`
 */
export function otraDeLaPantalla(e) {
    const { borrador: b, textos, locale } = e;
    const t = (clave) => texto(textos, clave);
    const tp = (clave, p) => textoCon(textos, clave, p);
    const unidad = { uno: t('compra.cuando.entrada'), varios: t('compra.cuando.entradas') };
    const otras = Array.isArray(b.otras) ? b.otras : [];

    if (otras.length === 0) {
        const conDia = b.dia ? conOfertaElDia(otrasZonas(e.productos, b.zona), e.precios, b.dia) : [];
        const enlace = conDia.length === 1 ? tp('compra.cuando.otra_zona_de', { zona: conDia[0].name }) : (conDia.length > 1 ? t('compra.cuando.otra_zona') : '');

        return { enlace, tarjetas: [], bloquea: false, falta: null, faltaZona: '', resumen: null, finPrincipal: null, porQueNoCabe: () => '' };
    }

    const todas = zonasConFilas(e.productos);
    const lineas = otras.map((o) => lineaVista(o, e, todas));
    // ¿Salen a horas distintas? Cada estancia con lo que la alarga (la hora extra elegida y que cabe): si alguna no coincide,
    // cada grupo dice su tramo (una ilimitada no tiene hora de salida).
    const delPedido = estancia(e.principal?.duracion, e.principal?.complementos);
    const distintas = Boolean(b.hora) && new Set([delPedido, ...lineas.map((l) => l.estancia)]).size > 1;
    const hora = horaCorta(b.hora);
    const usadas = new Set([b.fila, ...otras.map((o) => o.fila)].map(String));
    const primera = lineas.findIndex((l) => l.zonaNo || l.filaNo);

    return {
        enlace: '',
        tarjetas: lineas.map((l, i) => ({
            indice: i,
            titulo: l.zona?.name ?? '',
            // Para quién es; si su zona no se vende ese día, eso, en rojo (`noSeVende`).
            pista: l.zonaNo ? tp('compra.cuando.otra_no_vende', { zona: l.zona?.name ?? '' }) : edadesDe(l.fila, textos),
            noSeVende: l.zonaNo,
            filas: l.zona ? opcionesDeTiempo(l.zona.filas, { precios: e.precios, dia: b.dia, textos, locale })
                .map((f) => (f.value !== String(l.o.fila) && usadas.has(f.value) ? { ...f, disabled: true, description: t('compra.cuando.ya_en_la_reserva') } : f)) : [],
            fila: String(l.o.fila),
            n: l.o.n,
            ...unidad,
            complementos: l.complementos,
        })),
        bloquea: primera >= 0,
        // A dónde lleva «lo que falta» (M2): la primera que no se vende ese día; su tarjeta, o su «¿Cuánto tiempo?».
        falta: primera < 0 ? null : (lineas[primera].zonaNo ? `pjc-q-otra-${primera}` : `pjc-q-otra-tiempo-${primera}`),
        faltaZona: primera < 0 ? '' : (lineas[primera].zona?.name ?? ''),
        resumen: lineas.filter((l) => l.fila).map((l) => [
            l.fila.name,
            distintas && l.estancia !== null ? rangoHorario(hora, finDe(b.hora, l.estancia)) : null,
            `${l.o.n} ${l.o.n === 1 ? unidad.uno : unidad.varios}`,
        ].filter(Boolean).join(' · ')).join(' + ') || null,
        finPrincipal: distintas && delPedido !== null ? finDe(b.hora, delPedido) : null,
        porQueNoCabe: (h) => {
            const l = lineas.find((x) => x.noCabe(h));

            return l ? tp('compra.cuando.otra_no_cabe', { zona: l.zona?.name ?? '' }) : '';
        },
    };
}
