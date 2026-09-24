/**
 * **LA VISTA DE LA COMPRA DE LA ISLA, pura** (T3e de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #692`).
 *
 * Del estado del MOTOR (el catálogo, la oferta de días y horas, la línea que resuelve el servidor) y del borrador
 * de la isla, a lo que pintan las pantallas de la T3c: las props de cada pantalla y la descripción del paso (`ck`)
 * para `CompraIsla`. Es el adaptador del banco (`scripts/banco-compra/entrada.js`) con el motor en lugar de los
 * datos de prueba del diseño, y por eso vive en un módulo PLANO con su `node --test` (`CE-6`).
 *
 * ⚠️⚠️ **Aquí no se calcula dinero** (`PAY-12`): cada importe llega hecho —el precio del día de cada producto, de
 * `availability/{id}/dates`; el total de la línea, de los complementos resueltos—. Solo se FORMATEA. La única
 * resta es el «X menos que dos de 1 hora» del diseño, que compara dos precios publicados y no cobra nada.
 * ⚠️ Los textos de las PREGUNTAS de cada zona son de la página (T4); hasta entonces, los del producto (`lang/*`),
 * con las cifras sacadas de los datos.
 */
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';

/** Un importe como lo escribe el diseño: sin decimales si es redondo («8 €»), con dos si no («6,40 €»). */
export function euros(cents, locale = 'es') {
    const valor = Number(cents) / 100;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: Number(cents) % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(valor);
}

const fecha = (iso) => new Date(`${iso}T12:00:00`);

const partes = (iso, locale, opciones) => new Intl.DateTimeFormat(locale, opciones).formatToParts(fecha(iso));

/** El día de la tira: el nombre corto del día sin punto («sáb») y su número. */
export function diaCorto(iso, locale = 'es') {
    const p = partes(iso, locale, { weekday: 'short', day: 'numeric' });
    const dia = (p.find((x) => x.type === 'weekday')?.value ?? '').replace(/\.$/, '');

    return `${dia} ${p.find((x) => x.type === 'day')?.value ?? ''}`.trim();
}

/** «Sábado 26 de septiembre»: el día largo, con mayúscula y sin la coma que pone el navegador tras el nombre. */
export function diaLargo(iso, locale = 'es') {
    const escrito = partes(iso, locale, { weekday: 'long', day: 'numeric', month: 'long' })
        .map((x, i) => (i === 1 && x.type === 'literal' ? x.value.replace(/^,\s*/, ' ') : x.value))
        .join('');

    return escrito.charAt(0).toUpperCase() + escrito.slice(1);
}

/**
 * «viernes 25»: el día de un plazo, como lo escribe el diseño («hasta el domingo 24»). De un instante del servidor
 * (ISO con su desfase, en la zona del parque): se toma SU fecha, no la del reloj de quien mira.
 */
export function diaDelPlazo(iso, locale = 'es') {
    const fechaDelParque = typeof iso === 'string' ? iso.slice(0, 10) : '';

    if (! /^\d{4}-\d{2}-\d{2}$/.test(fechaDelParque)) return '';
    const p = partes(fechaDelParque, locale, { weekday: 'long', day: 'numeric' });

    return `${p.find((x) => x.type === 'weekday')?.value ?? ''} ${p.find((x) => x.type === 'day')?.value ?? ''}`.trim();
}

/** Las entradas de una zona, en el orden del catálogo. */
export function filasDeZona(productos, zona) {
    return (Array.isArray(productos) ? productos : []).filter((p) => p?.type === 'entry' && p?.zone?.slug === zona);
}

/** Las zonas que venden entradas, en el orden en que aparecen: lo que ofrece «¿Qué zona?». */
export function zonasConEntradas(productos) {
    const vistas = new Map();

    for (const p of Array.isArray(productos) ? productos : []) {
        if (p?.type === 'entry' && p?.zone?.slug && ! vistas.has(p.zone.slug)) vistas.set(p.zone.slug, p.zone);
    }

    return [...vistas.values()];
}

/** El precio de un producto un día, o `null` si ese día no se vende. */
export function precioDelDia(precios, id, dia) {
    const d = (precios?.[id] ?? []).find((x) => x.date === dia);

    return d ? d.price_cents : null;
}

/** La tira de días: los siete primeros que se venden de la fila elegida, con «hoy» y la tarifa especial. */
export function tiraDias(dias, { hoy, locale, textos, cuantos = 7 }) {
    return (Array.isArray(dias) ? dias : []).slice(0, cuantos).map((d) => {
        const especial = d.rate_key === 'special';

        return {
            id: d.date,
            n: Number(d.date.slice(8, 10)),
            label: d.date === hoy ? texto(textos, 'compra.cuando.hoy') : diaCorto(d.date, locale).split(' ')[0],
            special: especial,
            aria: diaLargo(d.date, locale) + (especial ? `, ${texto(textos, 'compra.cuando.tarifa_especial')}` : ''),
        };
    });
}

/** `17:00:00` → `17:00`: la hora como se pinta y como se elige en la isla. */
export const horaCorta = (hora) => (typeof hora === 'string' ? hora.slice(0, 5) : null);

/**
 * Las horas del selector. Una hora que no cabe —sin plazas, o con menos de las que se piden— sale apagada, con
 * «Quedan N» si aún le queda alguna: el precio y la hora nunca cambian a escondidas.
 */
export function horasDelSelector(ofrecidas, { gente, textos }) {
    return (Array.isArray(ofrecidas) ? ofrecidas : []).map((h) => {
        const libres = Number(h.available ?? 0);
        const noCabe = h.sellable === false || libres < gente;

        return {
            time: horaCorta(h.time),
            left: libres,
            ...(noCabe ? { disabled: true } : {}),
            ...(noCabe && libres > 0 ? { note: textoCon(textos, 'pieza.quedan', { n: libres }) } : {}),
        };
    });
}

/**
 * Las horas CERCANAS del mismo día, por si la elegida se llena al pagar (T3e·6; `cercanas()` de
 * `paginas/compra/datos.js` del diseño): las cuatro con sitio más próximas a la perdida, en orden de reloj.
 */
export function horasCercanas(slots, hora) {
    const lista = Array.isArray(slots) ? slots : [];
    const k = lista.findIndex((s) => s.time === hora);
    const minutos = (h) => Number(h.slice(0, 2)) * 60 + Number(h.slice(3));

    return lista.filter((s) => s.time !== hora && s.left > 0 && ! s.disabled)
        .sort((a, b) => Math.abs(lista.indexOf(a) - k) - Math.abs(lista.indexOf(b) - k))
        .slice(0, 4)
        .sort((a, b) => minutos(a.time) - minutos(b.time));
}

/**
 * La PANTALLA 0 de las entradas, «Cuándo y cuántos» (`PjcCuando`): sus props y la descripción del paso.
 *
 * @param {object} e  el estado, todo plano:
 *   `borrador` ({ zona, elegirZona, dia, hora, fila, n, cal, otra }) · `productos` (el catálogo tal cual) ·
 *   `precios` ({ [id]: días ofrecidos }) · `horas` (las ofrecidas de la fila y el día) · `cargandoHoras` ·
 *   `maximo` (lo que cabe a esa hora, o `null`) · `minimo` · `umbral` (el «casi llena» del panel) ·
 *   `calcetin` (el complemento por cantidad de la fila, o `null`) · `linea` (la que resolvió el servidor) ·
 *   `textos` · `locale` · `hoy`.
 */
export function pantallaCuando(e) {
    const { borrador: b, textos, locale } = e;
    const t = (clave) => texto(textos, clave);
    const tp = (clave, p) => textoCon(textos, clave, p);
    const filas = b.zona ? filasDeZona(e.productos, b.zona) : [];
    const fila = filas.find((p) => p.id === b.fila) ?? null;
    const zonas = zonasConEntradas(e.productos);
    const zona = zonas.find((z) => z.slug === b.zona) ?? null;
    const precio = (id) => precioDelDia(e.precios, id, b.dia);
    const base = filas[0] ?? null;
    const unidad = { uno: t('compra.cuando.entrada'), varios: t('compra.cuando.entradas') };
    const listo = Boolean(fila && b.dia && b.hora);

    const props = {
        titulo: zona ? tp('compra.cuando.titulo_zona', { zona: zona.name }) : t('compra.cuando.titulo_hoy'),
        zonas: b.elegirZona ? zonas.map((z) => ({ value: z.slug, title: z.name })) : null,
        zona: b.zona,
        preguntas: fila ? {
            dia: t('compra.cuando.pregunta_dia'),
            hora: t('compra.cuando.pregunta_hora'),
            tiempo: t('compra.cuando.pregunta_tiempo'),
            cuantos: t('compra.cuando.pregunta_cuantos'),
            calcetines: t('compra.cuando.pregunta_calcetines'),
        } : null,
        dias: fila ? tiraDias(e.precios?.[fila.id], { hoy: e.hoy, locale, textos }) : [],
        dia: b.dia,
        horas: horasDelSelector(e.horas, { gente: b.n, textos }),
        hora: horaCorta(b.hora),
        cargando: Boolean(e.cargandoHoras),
        filas: filas.map((p) => {
            const cents = precio(p.id);
            // ⚠️ Sin sus días TODAVÍA (llegando) no se sabe si se vende: decir «no se vende» sería mentir un segundo.
            const noSeVende = Array.isArray(e.precios?.[p.id]) && cents === null;
            const doble = base && p.id !== base.id && p.duration_min && base.duration_min && p.duration_min === 2 * base.duration_min;
            const ahorro = doble && cents !== null && precio(base.id) !== null ? 2 * precio(base.id) - cents : 0;

            return {
                value: String(p.id),
                title: p.name,
                description: noSeVende ? t('compra.cuando.no_disponible') : '',
                disabled: noSeVende,
                price: cents === null ? '' : euros(cents, locale),
                was: '',
                highlight: ahorro > 0 ? tp('compra.cuando.ahorro', { importe: euros(ahorro, locale) }) : '',
            };
        }),
        fila: fila ? String(fila.id) : null,
        cuantos: { n: b.n, ...unidad, min: e.minimo ?? 1, max: e.maximo ?? 20 },
        calcetines: e.calcetin ? {
            n: b.cal,
            uno: t('compra.cuando.par'),
            varios: t('compra.cuando.pares'),
            pista: tp('compra.cuando.pista_calcetines', { precio: euros(e.calcetin.price_cents, locale) }),
            max: e.calcetin.max_quantity ?? 40,
        } : null,
        horaExtra: false,
        otra: null,
        umbral: e.umbral || 6,
        otraZona: false,
    };

    const resumen = fila && b.dia
        ? [fila.name, `${diaCorto(b.dia, locale)}${b.hora ? `, ${horaCorta(b.hora)}` : ''}`, `${b.n} ${b.n === 1 ? unidad.uno : unidad.varios}`].join(' · ')
        : null;

    return {
        props,
        listo,
        ck: {
            key: 'cuando',
            stepStrong: '',
            step: t('compra.cuando.banda'),
            progress: null,
            summary: resumen,
            total: listo && e.linea ? euros(e.linea.total_cents, locale) : null,
            today: null,
            note: null,
            action: { label: t('compra.cuando.continuar'), disabled: ! listo },
        },
    };
}
