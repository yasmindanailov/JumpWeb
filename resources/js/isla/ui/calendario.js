/**
 * El calendario de mes del sistema de diseño (`AvailabilityCalendar.jsx`; `CalendarioMes.vue`): lo que decide y sus
 * estilos, en un módulo plano (`CE-6`) y APARTE de `piezas.js` y `estilos.js` a propósito: la compra de la isla
 * importa esos dos, y lo que viviera en ellos viajaría con ella aunque solo lo use la calculadora de la página (medido
 * en la T4d·4: +5,6 KiB en la descarga de la compra). Sus pruebas, en `calendario.test.js`.
 */

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

/** Las flechas del mes (su `Nav`): apagadas en el tope. */
export function estiloFlechaMes(ok) {
    return {
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '44px', height: '44px', flexShrink: 0, border: 'none',
        background: 'transparent', borderRadius: 'var(--r-pill)', color: 'var(--ink-900)', cursor: ok ? 'pointer' : 'not-allowed', opacity: ok ? 1 : 0.3,
        transition: 'var(--t-hover)',
    };
}

/**
 * Un día, por su estado (`estadoDia()`): elegido en tinta, libre en blanco con borde, cerrado y completo sin fondo.
 * `sobre`, el borde de tinta al pasar sobre un día libre (el `onMouseEnter` del diseño).
 */
export function estiloDiaCalendario(e, sobre) {
    return {
        position: 'relative', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '3px',
        minHeight: '46px', padding: '6px 2px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)',
        fontWeight: e.activo ? 'var(--fw-bold)' : 'var(--fw-semibold)', fontVariantNumeric: 'tabular-nums',
        background: e.activo ? 'var(--ink-900)' : e.libre ? 'var(--snow)' : 'transparent',
        color: e.activo ? 'var(--snow)' : e.libre ? 'var(--ink-900)' : 'var(--ink-400)',
        border: e.activo ? 'none' : e.libre ? '1px solid var(--border-subtle)' : '1px solid transparent',
        ...(e.libre && ! e.activo && sobre ? { borderColor: 'var(--ink-900)' } : {}),
        borderRadius: 'var(--r-sm)', textDecoration: e.lleno ? 'line-through' : 'none', cursor: e.pulsable ? 'pointer' : 'default',
        transition: 'var(--t-hover)',
    };
}

/** El rótulo «hoy» dentro de su día: lima si está elegido, tinta si está libre, apagado si no. */
export function estiloHoyCalendario(e) {
    return {
        display: 'inline-flex', alignItems: 'center', gap: '3px', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)', lineHeight: 1,
        color: e.activo ? 'var(--volt-500)' : e.libre ? 'var(--ink-900)' : 'var(--ink-400)',
    };
}

/** El punto de la tarifa especial (lima sobre el día elegido, sol en los demás); sin ella, un punto transparente. */
export function estiloPuntoCalendario(e) {
    const color = e.activo ? 'var(--volt-500)' : 'var(--sun-500)';

    return { width: '5px', height: '5px', borderRadius: 'var(--r-pill)', background: e.especial ? color : 'transparent' };
}
