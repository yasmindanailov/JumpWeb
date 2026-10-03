/**
 * **LA VISTA DE LA COMPRA DE LA ISLA, pura** (T3e de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #692`).
 *
 * Del estado del MOTOR (el catálogo, la oferta de días y horas, la línea que resuelve el servidor) y del borrador
 * de la isla, a lo que pintan las pantallas de la T3c: las props de cada pantalla y la descripción del paso (`ck`)
 * para `CompraIsla`. Es el adaptador del banco (`scripts/banco-compra/entrada.js`) con el motor en lugar de los
 * datos de prueba del diseño, y por eso vive en un módulo PLANO con su `node --test` (`CE-6`). La pantalla 0 de las
 * entradas, que solo usa la compra, vive aparte (`pantalla-cuando.js`): esto lo importan también las calculadoras.
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

/** El precio de un producto un día, o `null` si ese día no se vende. */
export function precioDelDia(precios, id, dia) {
    const d = (precios?.[id] ?? []).find((x) => x.date === dia);

    return d ? d.price_cents : null;
}

/**
 * Cuántos días enseña la tira: DOS SEMANAS (el owner, 27-09, `#830`: eran siete y el motor vende cinco meses). Lo que
 * queda más allá se elige en el calendario de «Más fechas» (`calendarioDeTira`).
 */
export const DIAS_TIRA = 14;

/**
 * La tira de días: los catorce primeros que se venden de la fila elegida, con «hoy» y la tarifa especial. El día
 * ELEGIDO en el calendario, si cae más allá, entra al final: elegido y a la vista, nunca escondido; y con su MES encima
 * en vez del día de la semana («nov / 20»): tras «dom 11», un «vie 20» se leía como del mismo mes.
 */
export function tiraDias(dias, { hoy, locale, textos, cuantos = DIAS_TIRA, elegido = null }) {
    const lista = Array.isArray(dias) ? dias : [];
    const primeros = lista.slice(0, cuantos);
    const fuera = elegido && ! primeros.some((d) => d.date === elegido) ? lista.find((d) => d.date === elegido) : null;
    const mesCorto = (iso) => new Intl.DateTimeFormat(locale, { month: 'short', timeZone: 'UTC' }).format(new Date(`${iso}T12:00:00Z`)).replace('.', '');

    return (fuera ? [...primeros, fuera] : primeros).map((d) => {
        const especial = d.rate_key === 'special';

        return {
            id: d.date,
            n: Number(d.date.slice(8, 10)),
            label: d === fuera ? mesCorto(d.date) : d.date === hoy ? texto(textos, 'compra.cuando.hoy') : diaCorto(d.date, locale).split(' ')[0],
            special: especial,
            aria: diaLargo(d.date, locale) + (especial ? `, ${texto(textos, 'compra.cuando.tarifa_especial')}` : ''),
        };
    });
}

/**
 * «Más fechas» (`#830`): el calendario de meses de la página para lo que la tira no enseña, o `null` si la tira ya los
 * tiene todos. Abre en el mes del día elegido si cae fuera de la tira y, si no, en el del primer día que la tira deja
 * fuera: lo que se busca al tocarlo. Sus topes, el mes del primer día y el del último que se vende; y lo que el
 * calendario necesita para decir «hoy» y los meses en su idioma.
 *
 * @returns {{days: Array<{date: string, special: boolean}>, month: string, minMonth: string, maxMonth: string, today: ?string, locale: string}|null}
 */
export function calendarioDeTira(dias, { dia = null, hoy = null, locale = 'es', cuantos = DIAS_TIRA } = {}) {
    const lista = Array.isArray(dias) ? dias : [];

    if (lista.length <= cuantos) return null;
    const fuera = dia && lista.findIndex((d) => d.date === dia) >= cuantos;

    return {
        days: lista.map((d) => ({ date: d.date, special: d.rate_key === 'special' })),
        month: (fuera ? dia : lista[cuantos].date).slice(0, 7),
        minMonth: lista[0].date.slice(0, 7),
        maxMonth: lista[lista.length - 1].date.slice(0, 7),
        today: hoy,
        locale,
    };
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
 * ⚠️ La perdida llega como la guarda el motor («17:00:00», `oferta.js::horaDelMotor`) y las horas del selector, cortas
 * («17:00»): se comparan en «HH:MM». Comparadas tal cual no casaban nunca, y salían las PRIMERAS del día con sitio, no las
 * de alrededor (medido el 03-10, en la K4 de `otra-zona.md`).
 */
export function horasCercanas(slots, hora) {
    const lista = Array.isArray(slots) ? slots : [];
    const corta = String(hora ?? '').slice(0, 5);
    const k = lista.findIndex((s) => s.time === corta);
    const minutos = (h) => Number(h.slice(0, 2)) * 60 + Number(h.slice(3));

    return lista.filter((s) => s.time !== corta && s.left > 0 && ! s.disabled)
        .sort((a, b) => Math.abs(lista.indexOf(a) - k) - Math.abs(lista.indexOf(b) - k))
        .slice(0, 4)
        .sort((a, b) => minutos(a.time) - minutos(b.time));
}
