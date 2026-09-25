/**
 * **LA VISTA DE LA CALCULADORA DE LA PÁGINA, pura** (T4d·3 de `docs/specs/isla-y-landing-nueva.md` §4.12): del estado
 * del MOTOR (los días de cada fila con su precio, las horas con sus plazas, la línea y el cargo de los calcetines que
 * resuelve el servidor), del borrador y de lo que la PÁGINA declara (sus filas y sus textos, `data-jw-calculadora`), a
 * lo que pinta `CalculadoraEntradas.vue`. Es la misma forma que `scripts/banco-calculadora/diseno.js` saca de los datos
 * de prueba del diseño, y por eso se compara con ella (`scripts/calculadora-contra-diseno.php`).
 *
 * ⚠️⚠️ **Aquí no se calcula dinero** (`PAY-12`): el precio del día sale de `availability/{id}/dates`; las líneas y el
 * total, de la `line` del servidor (`unit_price_cents`, `subtotal_cents`, cada complemento y `total_cents`); lo que
 * cuestan los calcetines antes de tener hora, del `charged_cents` de su complemento resuelto. Solo se FORMATEA.
 * ⚠️ Lo que nombra lo del parque (las preguntas, «niño», los calcetines, el mensaje) lo trae la página; lo genérico
 * (qué falta, el eco de la hora, las líneas del recibo) es del grupo `calculadora` de `lang/<idioma>/isla.php`.
 */
import { tp as textoCon } from '../../sidebar/i18n.js';
import { diaCorto, diaDelPlazo, euros, horaCorta, horasDelSelector, precioDelDia } from '../compra/vista.js';

const mayuscula = (s) => s.charAt(0).toUpperCase() + s.slice(1);

const minutos = (hhmm) => Number(hhmm.slice(0, 2)) * 60 + Number(hhmm.slice(3, 5));
const reloj = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

/**
 * La hora de cierre de un día, de lo que la página declara (`cierres`): la del día especial si lo es (un festivo), si no
 * la de su día de la semana; `null` si ese día no abre.
 */
export function cierreDelDia(cierres, dia) {
    if (! dia) return null;

    return cierres?.dias?.[dia] ?? cierres?.semana?.[new Date(`${dia}T12:00:00`).getDay()] ?? null;
}

/** Las cantidades del resumen como las escribe la página: en letra hasta donde llegue su lista («para dos»). */
function enLetra(n, numeros) {
    return (Array.isArray(numeros) && numeros[n]) || String(n);
}

/**
 * @param {object} e  el estado, plano:
 *   `pagina` ({ zona, nombre, url, filas: [{ id, label, horas, descripcion?, aviso?, desde_cents }], columnas: [normal,
 *   especial], textos }) · `borrador` ({ fila, dia, hora, n, cal }) · `precios` ({ [id]: días ofrecidos }) · `horas`
 *   (las ofrecidas de la fila y el día) · `linea` (la del servidor, o `null`) · `cargoCalcetines` (céntimos, o `null`)
 *   · `calcetin` (el complemento por cantidad, o `null`) · `minimo` · `maximo` · `cierre` (`HH:MM` del día elegido) ·
 *   `pendiente` (una petición en camino) · `textos` (el grupo `isla`) · `locale` · `hoy`.
 */
export function vistaCalculadora(e) {
    const { pagina: pg, borrador: b, textos, locale } = e;
    const p = pg.textos;
    const c = p.calculadora;
    const tp = (clave, params) => textoCon(textos, `calculadora.${clave}`, params);
    // Los de la página que llevan cifras (sus calcetines, su mensaje), por el mismo camino.
    const pp = (clave, params) => textoCon(p, `calculadora.${clave}`, params);
    const fila = pg.filas.find((f) => f.id === b.fila) ?? pg.filas[0];
    const dias = e.precios?.[fila.id] ?? [];
    const delDia = dias.find((d) => d.date === b.dia) ?? null;
    const unidad = delDia ? delDia.price_cents : null;
    const horas = b.dia ? horasDelSelector(e.horas, { gente: b.n, textos }) : null;
    const h = b.dia && b.hora && (horas ?? []).some((x) => x.time === horaCorta(b.hora) && ! x.disabled) ? horaCorta(b.hora) : null;
    const linea = h ? e.linea : null;
    const falta = ! b.dia ? tp('falta_dia') : ! h ? tp('falta_hora') : '';
    const quien = (n) => `${n} ${p.persona[n === 1 ? 0 : 1]}`;
    const cal = b.cal > 0 ? pp(b.cal === 1 ? 'calcetines_uno' : 'calcetines_varios', { n: enLetra(b.cal, c.numeros) }) : null;
    const seleccion = [quien(b.n), fila.label.toLowerCase(), b.dia ? (h ? tp('a_las', { dia: diaDelPlazo(b.dia, locale), hora: h }) : diaDelPlazo(b.dia, locale)) : null, cal].filter(Boolean).join(' · ');
    const complementos = (linea?.addons ?? []).filter((a) => a.quantity > 0).map((a) => ({
        label: tp('linea_complemento', { nombre: a.product_name, n: a.quantity, precio: e.calcetin && a.product_id === e.calcetin.id ? euros(e.calcetin.price_cents, locale) : '' }),
        value: euros(a.subtotal_cents, locale),
    }));
    const lineas = linea ? [{ label: tp('linea_entradas', { n: b.n, precio: euros(linea.unit_price_cents, locale) }), value: euros(linea.subtotal_cents, locale) }, ...complementos] : [];
    const total = linea ? euros(linea.total_cents, locale) : '';
    const precioDe = (f) => {
        if (! b.dia) return tp('desde', { precio: euros(f.desde_cents, locale) });
        const cents = precioDelDia(e.precios, f.id, b.dia);

        return cents === null ? null : euros(cents, locale);
    };
    const mensaje = `${pp('mensaje', { nombre: pg.nombre, zona: pg.zonaNombre })}${seleccion}${total ? ` · ${total}` : ''} · ${pg.url}${p.compartirNota ? `\n${p.compartirNota}` : ''}`;
    const ultimo = dias.length ? dias[dias.length - 1].date.slice(0, 7) : null;

    return {
        locale,
        cuantos: { titulo: p.preguntas[0], label: p.cuantos.label, sub: p.cuantos.sub, n: b.n, min: e.minimo ?? 1, max: e.maximo ?? 30, precio: unidad !== null ? tp('por', { precio: euros(unidad, locale), persona: p.persona[0] }) : '', nota: p.cuantos.nota },
        tiempo: {
            titulo: p.preguntas[1], name: `p3-tiempo-${pg.zona}`, fila: String(fila.id), columnas: String(pg.filas.length),
            items: pg.filas.map((f) => ({ value: String(f.id), title: f.label, price: precioDe(f), description: f.descripcion ?? null })),
        },
        dia: {
            titulo: p.preguntas[2], mes: (b.dia ?? e.hoy).slice(0, 7), desde: e.hoy.slice(0, 7), hasta: ultimo && ultimo > e.hoy.slice(0, 7) ? ultimo : e.hoy.slice(0, 7),
            hoy: e.hoy, dias: dias.map((d) => ({ date: d.date, special: d.rate_key === 'special' })), valor: b.dia, nota: fila.aviso ?? '',
            eco: unidad !== null ? { antes: tp('eco_dia_antes', { tarifa: pg.columnas[delDia.rate_key === 'special' ? 1 : 0] }), cifra: euros(unidad, locale), despues: tp('eco_dia_despues', { persona: p.persona[0] }) } : null,
        },
        hora: {
            titulo: p.preguntas[3], horas, valor: h, espera: tp('espera_hora'),
            eco: h ? (fila.horas ? tp('eco_hora', { desde: h, hasta: reloj(minutos(h) + fila.horas * 60) }) : tp('eco_hora_cierre', { desde: h, cierre: e.cierre ?? '' })) : null, regla: p.tiempo,
        },
        calcetines: e.calcetin ? {
            titulo: p.preguntas[4], sub: p.calcetines.sub, label: c.calcetines, sublabel: b.cal ? '' : c.sin_calcetines, n: b.cal, max: e.calcetin.max_quantity ?? 40,
            precio: b.cal && e.cargoCalcetines != null ? euros(e.cargoCalcetines, locale) : '', nota: p.calcetines.nota,
            cadaUno: { texto: pp('cada_uno', { persona: p.persona[0], n: b.n }), elegido: b.cal === b.n, n: b.n },
        } : null,
        // `pendiente`: hay una petición en camino (más gente, otro par): el botón espera a la última respuesta.
        resumen: { seleccion, lineas, total, falta, listo: Boolean(linea) && ! e.pendiente, faltaHref: ! b.dia ? '#p3-dia' : '#p3-hora', boton: p.boton, junto: p.junto, nota: p.nota },
        // Lo que la calculadora le cuenta a la ISLA de la página (T4e, el `onCalculo` de `pagina.jsx` del diseño): qué
        // pregunta falta y, con todo elegido, lo elegido en corto con el total DEL SERVIDOR («Sáb 26 · 17:00 · 3 niños ·
        // 24 €»). Solo cuenta si alguien la ha tocado (`tocada`): los valores de partida no son un cálculo.
        isla: e.tocada ? { falta: ! b.dia ? 'dia' : ! h ? 'hora' : '', elegido: linea ? `${mayuscula(diaCorto(b.dia, locale))} · ${h} · ${quien(b.n)} · ${total}` : null, boton: p.boton } : null,
        compartir: { value: `${pg.url}#precio`, items: [{ kind: 'whatsapp', label: p.compartir, href: `https://wa.me/?text=${encodeURIComponent(mensaje)}` }] },
    };
}
