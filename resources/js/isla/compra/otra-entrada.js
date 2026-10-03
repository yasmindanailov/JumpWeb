/**
 * **«AÑADIR OTRA ENTRADA», pura** (K3 de `docs/specs/otra-zona.md` §4.3, `#878`; `anadirOtra` y `continuarCuando` del mockup):
 * desde «Pagar», la pantalla 0 en modo «otra». El día y la hora, FIJOS: los del pedido (D1-A). «¿Qué zona?» con dos zonas o
 * más —lo dicho al owner en `#882`: las mezclas dentro de una zona, un Jump de 1 h y otro de 2 h, se añaden aquí—, de partida
 * la OTRA (el mockup); «¿Cuánto tiempo?», de partida el parecido al de la línea del pedido (`otra-zona.js::filaParecida`);
 * «¿Cuántos?» y sus complementos (la regla de la M1, nunca marcados). Un tiempo que YA está en la reserva se puede elegir: suma
 * gente a su línea (`linea.js::conNuevaLinea`), y la opción lo dice.
 *
 * ⚠️⚠️ Aquí no se calcula dinero (`PAY-12`): el precio de cada tiempo, de `availability/{id}/dates`; el de sus complementos, la
 * nota del servidor a esa hora; el total, el del presupuesto de la cesta en «Pagar». Puro; se prueba con `node --test`.
 */
import { t as texto } from '../../sidebar/i18n.js';
import { diaCorto, horaCorta, precioDelDia } from './vista.js';
import { opcionesDeTiempo } from './pantalla-cuando.js';
import { complementosDe, gruposComoFilas } from './complementos.js';
import { conOfertaElDia, edadesDe, filaParecida, otrasZonas, queSeVenden, zonasConFilas } from './otra-zona.js';

/**
 * El tiempo de partida en una zona: el parecido al del pedido entre los que se venden ese día y AÚN NO están en la reserva
 * —quien pulsa «Añadir otra entrada» quiere algo nuevo—; si ya están todos, el parecido entre los que se venden.
 */
const tiempoDePartida = (zona, { precios, dia, duracion, enLaReserva }) => {
    const vendidas = queSeVenden(zona, precios, dia);
    const nuevas = vendidas.filter((f) => ! enLaReserva.has(f.id));

    return filaParecida(nuevas.length ? nuevas : vendidas, duracion);
};

/**
 * La línea nueva de partida: de la primera OTRA zona que se vende ese día y tiene algún tiempo que aún no está en la reserva
 * (si ninguna, la primera que se venda; y si no, la del pedido), su tiempo de partida (`tiempoDePartida`), para UNA persona y
 * sin complementos. `null` si ese día no se vende nada.
 *
 * @param {{productos: Array, precios: object, dia: string, zona: string, duracion: number|null, enLaReserva?: Iterable<number>}} e
 */
export function nuevaEntrada({ productos, precios, dia, zona, duracion, enLaReserva = [] }) {
    const ya = new Set(enLaReserva);
    const otras = conOfertaElDia(otrasZonas(productos, zona), precios, dia);
    const todas = conOfertaElDia(zonasConFilas(productos), precios, dia);
    const conAlgoNuevo = (x) => queSeVenden(x, precios, dia).some((f) => ! ya.has(f.id));
    const z = otras.find(conAlgoNuevo) ?? todas.find(conAlgoNuevo) ?? otras[0] ?? todas[0] ?? null;
    const fila = z ? tiempoDePartida(z, { precios, dia, duracion, enLaReserva: ya }) : null;

    return fila ? { zona: z.slug, fila: fila.id, n: 1, extras: [], elecciones: {} } : null;
}

/** Otra ZONA para la línea nueva: su tiempo de partida allí, y lo elegido de la fila anterior, fuera. */
export function nuevaEnZona(nueva, { productos, precios, dia, zona, duracion, enLaReserva = [] }) {
    const z = zonasConFilas(productos).find((x) => x.slug === zona) ?? null;
    const fila = z ? (tiempoDePartida(z, { precios, dia, duracion, enLaReserva: new Set(enLaReserva) }) ?? z.filas[0]) : null;

    return fila ? { ...nueva, zona, fila: fila.id, extras: [], elecciones: {} } : nueva;
}

/**
 * **La pantalla «Añadir otra entrada»** (`PjcCuando` en modo «otra», con las props de `PantallaCuando.vue`): sus props, si
 * está lista (su tiempo se vende ese día) y lo que falta si no (`pjc-q-zona` sin zona que se venda; si no, `pjc-q-tiempo`).
 *
 * @param {object} e  `nueva` ({ zona, fila, n, extras, elecciones }) · `pedido` (el de ahora: su `fila`, `dia`, `hora` y sus
 *   `otras`) · `productos` · `precios` ({ [id]: días }) · y, POR FILA: `fichas`, `sueltos` (sus complementos sin hora) y
 *   `grupos` · `oferta` (lo que el servidor resolvió de la nueva a esa hora: `{ singles, groups }`, o `null`) · `excluir` (lo
 *   que se pregunta para todo el pedido: los calcetines) · `textos` · `locale`
 */
export function pantallaOtraEntrada(e) {
    const { nueva, pedido, textos, locale } = e;
    const t = (clave) => texto(textos, clave);
    const todas = zonasConFilas(e.productos);
    const zona = todas.find((z) => z.slug === nueva?.zona) ?? null;
    const fila = zona?.filas.find((f) => f.id === nueva?.fila) ?? null;
    const enLaReserva = new Set([pedido?.fila, ...(pedido?.otras ?? []).map((o) => o.fila)].map(String));
    const vende = Boolean(fila) && precioDelDia(e.precios, fila.id, pedido?.dia) !== null;
    const ficha = e.fichas?.[nueva?.fila] ?? null;
    const dia = pedido?.dia ? diaCorto(pedido.dia, locale) : '';
    const unidad = { uno: t('compra.cuando.entrada'), varios: t('compra.cuando.entradas') };

    return {
        props: {
            titulo: t('compra.cuando.otra_titulo'),
            otraEntrada: true,
            fijo: [dia.charAt(0).toUpperCase() + dia.slice(1), horaCorta(pedido?.hora)].filter(Boolean).join(' · '),
            // Con una sola zona de entradas no hay nada que elegir: la nueva es de ella (otro tiempo, o más gente).
            zonas: todas.length > 1 ? todas.map((z) => ({
                value: z.slug, title: z.name, description: edadesDe(z.filas[0], textos), disabled: queSeVenden(z, e.precios, pedido?.dia).length === 0,
            })) : null,
            zona: nueva?.zona ?? null,
            preguntas: { dia: '', hora: '', tiempo: t('compra.cuando.pregunta_tiempo'), cuantos: t('compra.cuando.pregunta_cuantos'), calcetines: '', datos: null },
            filas: zona ? opcionesDeTiempo(zona.filas, { precios: e.precios, dia: pedido?.dia, textos, locale })
                .map((f) => (! f.disabled && enLaReserva.has(f.value) ? { ...f, description: t('compra.cuando.se_suma') } : f)) : [],
            fila: nueva?.fila == null ? null : String(nueva.fila),
            cuantos: { n: nueva?.n ?? 1, ...unidad, min: 1, max: 20 },
            calcetines: null,
            complementos: nueva ? [
                ...gruposComoFilas(e.oferta?.groups?.length ? e.oferta.groups : e.grupos?.[nueva.fila], { elecciones: nueva.elecciones ?? {} }),
                ...complementosDe({
                    ficha: ficha?.id === nueva.fila ? ficha : null, excluir: e.excluir ?? [], extras: nueva.extras ?? [],
                    sinHora: e.sueltos?.[nueva.fila], conHora: e.oferta ? e.oferta.singles : null, hora: horaCorta(pedido?.hora), textos,
                }),
            ] : [],
            otras: [],
            otraZona: '',
        },
        listo: vende,
        falta: vende ? null : (zona && queSeVenden(zona, e.precios, pedido?.dia).length > 0 ? 'pjc-q-tiempo' : 'pjc-q-zona'),
        // Lo que se dice si se pulsa sin estar lista: la zona elegida, o ninguna (ese día no se vende).
        faltaZona: zona?.name ?? '',
    };
}
