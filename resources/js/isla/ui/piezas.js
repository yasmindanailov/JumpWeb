/**
 * Las reglas de las piezas del sistema de diseño que usa la compra (`specs/isla-y-landing-nueva.md` §4.10, T3b),
 * fuera de sus componentes por la regla `CE-6`: un componente pinta, y lo que decide va en un módulo plano con
 * sus pruebas (`piezas.test.js`). Cada función repite la de su pieza del diseño, que se cita.
 */
import { Text } from 'vue';

/** Las columnas de la rejilla de horas por defecto; a `size="sm"` pasan a casillas de 76px (`TimeSlotPicker.jsx`). */
export const COLUMNAS_HORAS = 'repeat(auto-fill, minmax(112px, 1fr))';

/**
 * Lo que dice una hora de la rejilla (`TimeSlotPicker.jsx`). Con `counts: 'low'` y aforo grande, «31 libres» en
 * cada casilla es ruido: la cifra solo sale cuando cambia algo (pocas, completa, o no cabe el grupo).
 */
export function estadoHora(hora, { value = null, lowThreshold = 6, counts = 'all' }, t) {
    const agotada = hora.left === 0 || Boolean(hora.disabled);
    const pocas = ! agotada && hora.left != null && hora.left <= lowThreshold;
    const texto = hora.note
        || (agotada ? t('pieza.completo')
            : pocas ? t('pieza.quedan', { n: hora.left })
                : counts === 'low' ? '' : t('pieza.libres', { n: hora.left }));

    return { agotada, activa: value === hora.time, pocas, texto };
}

/** La cantidad, dentro de sus topes (`QuantityStepper.jsx`). */
export function acotar(n, min, max) {
    return Math.min(max, Math.max(min, n));
}

/** El `id` del campo cuando no se da (`Field.jsx`): de la etiqueta o, sin ella, del tipo. */
export function idDeCampo(id, label, type) {
    return id || `f-${(label || type).toLowerCase().replace(/\s+/g, '-')}`;
}

/** El `id` de la casilla cuando no se da (`Checkbox.jsx`). */
export function idDeCasilla(id, label) {
    return id || `cb-${(label || 'x').toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 24)}`;
}

/**
 * Las columnas de las tarjetas de opción (`OptionCards.jsx`). Con un número: N o una, nunca un paso intermedio
 * (dos menús a 160px en un móvil parten cada renglón en tres y sacan el precio fuera de la tarjeta).
 */
export function columnasOpciones(columns) {
    if (columns === 'auto') return 'repeat(auto-fit, minmax(210px, 1fr))';
    const n = Number(columns) || 2;

    return `repeat(auto-fit, minmax(clamp(calc((100% - ${n - 1} * 12px) / ${n}), calc((${n * 17}rem - 100%) * 999), 100%), 1fr))`;
}

/** El navegador de Instagram, Facebook o TikTok (`SocialSignIn.jsx`): ahí Google no deja entrar. */
export function enNavegadorDeApp(agente) {
    return /Instagram|FBAN|FBAV|TikTok|musical_ly|Bytedance/i.test(agente || '');
}

/**
 * Las barras de un hueco de carga (`Skeleton.jsx`), por forma. `fila` las pone una al lado de otra (el precio);
 * `bloque` es la forma suelta, una sola pieza sin retardo; `extra` va a la envoltura (el círculo, su ancho).
 */
export function barrasEsqueleto({ kind = 'text', lines = 3, width = '100%', height, radius, aspect }) {
    const barra = (w, h, r, delay, ar) => ({ w, h, r, delay, ar });

    if (kind === 'text') {
        return { barras: Array.from({ length: lines }, (_, i) => barra(i === lines - 1 && lines > 1 ? '62%' : '100%', height || '12px', radius || 'var(--r-xs)', `${i * 90}ms`)) };
    }
    if (kind === 'title') return { barras: [barra(width, height || '26px', radius || 'var(--r-sm)')] };
    if (kind === 'pill') return { barras: [barra(width, height || 'var(--control-md)', radius || 'var(--r-pill)')] };
    if (kind === 'circle') {
        const d = height || '48px';

        return { barras: [barra(d, d, 'var(--r-pill)')], extra: { width: d } };
    }
    if (kind === 'price') {
        return { fila: true, barras: [barra('112px', height || '34px', radius || 'var(--r-sm)'), barra('64px', '13px', 'var(--r-xs)', '120ms')] };
    }
    if (kind === 'card') {
        return {
            barras: [
                barra('100%', height, radius || 'var(--r-lg)', undefined, height ? undefined : aspect || 'var(--ar-card)'),
                barra('72%', '18px', 'var(--r-xs)', '90ms'),
                barra('100%', '12px', 'var(--r-xs)', '180ms'),
                barra('48%', '12px', 'var(--r-xs)', '270ms'),
            ],
        };
    }

    return { bloque: { h: height || (aspect ? undefined : '120px'), ar: aspect, r: radius || 'var(--r-lg)' }, barras: [] };
}

/** `2026-09` movido `delta` meses (`shift()` de `AvailabilityCalendar.jsx`). */
export function mesDesplazado(mes, delta) {
    const y = Number(mes.slice(0, 4));
    const t = Number(mes.slice(5, 7)) - 1 + delta;

    return `${y + Math.floor(t / 12)}-${String((((t % 12) + 12) % 12) + 1).padStart(2, '0')}`;
}

/**
 * Las casillas del mes (`AvailabilityCalendar.jsx`): `null` hasta el primer día —la semana empieza en LUNES— y
 * después cada día con su fecha. Se cuenta en UTC: la fecha no depende de la hora ni del huso de quien mira.
 */
export function celdasDelMes(mes) {
    const y = Number(mes.slice(0, 4));
    const m = Number(mes.slice(5, 7));
    const primero = (new Date(Date.UTC(y, m - 1, 1)).getUTCDay() + 6) % 7;
    const dias = new Date(Date.UTC(y, m, 0)).getUTCDate();

    return [
        ...Array.from({ length: primero }, () => null),
        ...Array.from({ length: dias }, (_, i) => ({ n: i + 1, date: `${mes}-${String(i + 1).padStart(2, '0')}` })),
    ];
}

/**
 * El estado de un día (`AvailabilityCalendar.jsx`): sin fila en `days`, CERRADO; con ella, su `state` o libre. Un día
 * completo no se esconde: se tacha, y solo se pulsa si hay aviso (`onNotify`).
 */
export function estadoDia(info, { value = null, today = null, date, avisa = false }) {
    const estado = info ? (info.state || 'free') : 'closed';
    const libre = estado === 'free';
    const lleno = estado === 'full';

    return {
        libre,
        lleno,
        activo: value === date,
        hoy: today === date,
        especial: Boolean(info?.special) && ! lleno,
        pulsable: libre || (lleno && avisa),
    };
}

/**
 * Lo que pinta el calendario de un mes (`AvailabilityCalendar.jsx`): su nombre y el año, las iniciales de la semana,
 * los topes y cada casilla con su estado y su nombre accesible. Los nombres, de `Intl` en el idioma (en español, los
 * del diseño); los rótulos, `pieza.calendario.*`. `sobre` es el día bajo el puntero.
 */
export function vistaCalendario({ mes, days = [], value = null, today = null, minMonth = null, maxMonth = null, especial = '', locale = 'es', sobre = null }, { t, tp }) {
    const porFecha = Object.fromEntries(days.map((d) => [d.date, d]));
    const y = Number(mes.slice(0, 4));
    const nombre = new Intl.DateTimeFormat(locale, { month: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(y, Number(mes.slice(5, 7)) - 1, 1)));
    const inicial = new Intl.DateTimeFormat(locale, { weekday: 'narrow', timeZone: 'UTC' });
    const estado = (e) => (e.lleno ? t('pieza.calendario.aria_completo')
        : e.libre ? (e.especial ? tp('pieza.calendario.aria_especial', { tarifa: especial.toLowerCase() }) : t('pieza.calendario.aria_libre'))
            : t('pieza.calendario.aria_cerrado'));

    return {
        nombre,
        anio: y,
        // Del lunes 7 al domingo 13 de septiembre de 2026: una semana cualquiera que empieza en lunes.
        iniciales: Array.from({ length: 7 }, (_, i) => inicial.format(new Date(Date.UTC(2026, 8, 7 + i)))),
        puedeAtras: ! minMonth || mes > minMonth,
        puedeAlante: ! maxMonth || mes < maxMonth,
        celdas: celdasDelMes(mes).map((c) => {
            if (! c) return null;
            const e = estadoDia(porFecha[c.date], { value, today, date: c.date });
            const aria = tp('pieza.calendario.dia', { n: c.n, mes: nombre }) + (e.hoy ? t('pieza.calendario.aria_hoy') : '') + estado(e);

            return { ...c, e, aria, sobre: sobre === c.date };
        }),
    };
}

/**
 * El texto de un botón cuando su contenido es SOLO texto (`Button.jsx`: `typeof children === "string"`): es lo
 * que lee la bola de carga mientras el botón espera. Con un icono o varios nodos dentro, cadena vacía.
 */
export function textoDeRanura(nodos) {
    if (! Array.isArray(nodos) || nodos.length !== 1) return '';
    const [nodo] = nodos;

    return nodo && nodo.type === Text && typeof nodo.children === 'string' ? nodo.children : '';
}
