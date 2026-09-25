/**
 * **La lógica del widget del DISEÑO, transcrita** (`PrecioEntradas` de `paginas/entradas/pieza-3.jsx`), para el lado B
 * del banco de la calculadora (`scripts/banco-entradas.php`, pieza `calculadora`; T4d·2 de
 * `specs/isla-y-landing-nueva.md` §4.12): con los datos de prueba del diseño (`contenido.js`, sus días de septiembre y
 * sus sesiones inventadas) da la MISMA vista que pinta el diseño, para juzgar a 0 píxeles la VISTA del producto
 * (`resources/js/isla/calculadora/`). Vive en `scripts/`: nunca entra en el paquete. Lo que la página hará de verdad
 * (el motor, los textos, el dinero del servidor) es la T4d·3.
 * Cada función repite la del diseño, con su nombre; los literales, los del diseño. Sin oferta (`ofertas={false}`).
 */
// El espacio duro entre la cifra y su unidad, sin escribir el carácter suelto (ESLint no lo admite).
const NBSP = String.fromCharCode(0xa0);
const P3_HOY = '2026-09-23';
const P3_DIAS = ['2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26', '2026-09-27', '2026-09-28', '2026-09-29', '2026-09-30'];
const P3_SEMANA = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const P3_CIERRE = 21 * 60 + 30;
const p3Dow = (iso) => new Date(`${iso}T12:00:00`).getDay();
const p3Especial = (iso) => { const d = p3Dow(iso); return d === 5 || d === 6 || d === 0; };
const p3Largo = (iso) => `${P3_SEMANA[p3Dow(iso)]} ${Number(iso.slice(8))}`;
const p3Min = (t) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3));
const p3Hora = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
const p3Eur = (n) => { const r = Math.round(n * 100) / 100; return `${Number.isInteger(r) ? String(r) : r.toFixed(2).replace('.', ',')}${NBSP}€`; };
const P3_NUM = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez'];
const p3Calcetines = (n) => (n === 1 ? 'un par de calcetines' : `calcetines para ${P3_NUM[n] || n}`);

function p3Sesiones(iso, horas, gente) {
    const finde = p3Dow(iso) === 0 || p3Dow(iso) === 6;
    const abre = finde ? 11 * 60 : 16 * 60 + 30;
    const dura = (horas || 1) * 60;
    const n = Number(iso.slice(8));
    const out = [];
    for (let m = abre, i = 0; m + dura <= P3_CIERRE; m += 30, i += 1) {
        let left = 14 + ((n * 17 + i * 29) % 37);
        if ((n * 5 + i) % 13 === 7) left = 0;
        else if ((n * 3 + i) % 8 === 1) left = 6;
        const s = { time: p3Hora(m), left };
        out.push(left > 0 && left < gente ? Object.assign(s, { disabled: true, note: `Quedan ${left}` }) : s);
    }
    return out;
}

/** El estado de partida de la página (`inicio="vacio"`): nada elegido, ni día ni calcetines. */
export function estadoInicial(z) {
    const p = z.p3;
    return { gente: (p.inicio && p.inicio.personas) || 1, filaId: p.filas[0].id, dia: null, hora: null, calcetines: 0 };
}

const filaDe = (p, s) => p.filas.find((f) => f.id === s.filaId) || p.filas[0];
const horaValida = (p, s) => !! s.hora && (s.dia ? p3Sesiones(s.dia, filaDe(p, s).horas, s.gente) : []).some((x) => x.time === s.hora && x.left > 0 && ! x.disabled);

/** Los manejadores del diseño (`T(setX)`, `elegirFila`) y su efecto: una hora que ya no vale se vacía. */
export function cambiarDiseno(z, s, campo, valor) {
    const p = z.p3;
    if (campo === 'n') s.gente = valor;
    if (campo === 'dia') s.dia = valor;
    if (campo === 'hora') s.hora = valor;
    if (campo === 'cal') s.calcetines = valor;
    if (campo === 'fila') {
        s.filaId = valor;
        const f = p.filas.find((x) => x.id === valor);
        if (f && f.soloLJ && s.dia && p3Especial(s.dia)) { s.dia = null; s.hora = null; }
    }
    if (s.hora && ! horaValida(p, s)) s.hora = null;
}

/** La vista que pinta el diseño, en la forma de `resources/js/isla/calculadora/CalculadoraEntradas.vue`. */
export function vistaDiseno(z, s) {
    const p = z.p3;
    const fila = filaDe(p, s);
    const dias = P3_DIAS.filter((d) => ! (fila.soloLJ && p3Especial(d))).map((d) => ({ date: d, special: p3Especial(d) }));
    const col = s.dia ? (p3Especial(s.dia) ? 1 : 0) : null;
    const unidad = col == null ? null : fila.precios[col];
    const sesiones = s.dia ? p3Sesiones(s.dia, fila.horas, s.gente) : [];
    const h = horaValida(p, s) ? s.hora : null;
    const entradas = unidad != null ? s.gente * unidad : 0;
    const calc = s.calcetines * 2;
    const total = entradas + calc;
    const falta = ! s.dia ? 'Elige el día para ver el total' : ! h ? 'Elige la hora para ver el total' : '';
    const quien = (n) => `${n} ${p.persona[n === 1 ? 0 : 1]}`;
    const seleccion = [quien(s.gente), fila.label.toLowerCase(), s.dia ? p3Largo(s.dia) + (h ? ` a las ${h}` : '') : null, s.calcetines ? p3Calcetines(s.calcetines) : null].filter(Boolean).join(' · ');
    const lineas = falta ? [] : [
        { label: `Entradas · ${s.gente} × ${p3Eur(unidad)}`, value: p3Eur(entradas) },
        s.calcetines ? { label: `Calcetines · ${s.calcetines} × 2${NBSP}€`, value: p3Eur(calc) } : null,
    ].filter(Boolean);
    const precioDe = (f) => (col != null ? (f.precios[col] == null ? null : p3Eur(f.precios[col])) : `desde ${p3Eur(f.precios[0])}`);
    const zona = z.zona === 'jump' ? 'Jump' : 'Kids';
    const wa = `https://wa.me/?text=${encodeURIComponent(`Play Jump Park, zona ${zona}: ${seleccion}${falta ? '' : ` · ${p3Eur(total)}`} · https://playjumppark.es/${z.zona}${p.compartirNota ? `\n${p.compartirNota}` : ''}`)}`;

    return {
        locale: 'es',
        cuantos: { titulo: p.preguntas[0], label: p.cuantos.label, sub: p.cuantos.sub, n: s.gente, min: 1, max: 30, precio: unidad != null ? `${p3Eur(unidad)} por ${p.persona[0]}` : '', nota: p.cuantos.nota },
        tiempo: {
            titulo: p.preguntas[1], name: `p3-tiempo-${z.zona}`, fila: fila.id, columnas: String(p.filas.length),
            items: p.filas.map((f) => ({ value: f.id, title: f.label, price: precioDe(f), description: f.soloLJ ? `${f.sub}, ${f.soloLJ.toLowerCase()}.` : null })),
        },
        dia: {
            titulo: p.preguntas[2], mes: '2026-09', desde: '2026-09', hasta: '2026-09', hoy: P3_HOY, dias, valor: s.dia,
            nota: fila.soloLJ ? 'La ilimitada es solo de lunes a jueves.' : '',
            eco: s.dia && unidad != null ? { antes: `${col ? 'Tarifa especial' : 'De lunes a jueves'}: `, cifra: p3Eur(unidad), despues: ` por ${p.persona[0]}` } : null,
        },
        hora: {
            titulo: p.preguntas[3], horas: s.dia ? sesiones : null, valor: h, espera: 'Elige antes el día: cada día tiene sus horas libres.',
            eco: h ? (fila.horas ? `De ${h} a ${p3Hora(p3Min(h) + fila.horas * 60)}` : `De ${h} al cierre, a las 21:30`) : null, regla: p.tiempo,
        },
        calcetines: {
            titulo: p.preguntas[4], sub: p.calcetines.sub, label: 'Pares de calcetines', sublabel: s.calcetines ? '' : 'Traéis los vuestros', n: s.calcetines, max: 40,
            precio: s.calcetines ? p3Eur(calc) : '', cadaUno: { texto: `Un par para cada ${p.persona[0]} · ${s.gente}`, elegido: s.calcetines === s.gente, n: s.gente }, nota: p.calcetines.nota,
        },
        resumen: { seleccion, lineas, total: p3Eur(total), falta, faltaHref: ! s.dia ? '#p3-dia' : '#p3-hora', boton: p.boton, junto: p.junto, nota: p.nota },
        compartir: { value: `https://playjumppark.es/${z.zona}#precio`, items: [{ kind: 'whatsapp', label: p.compartir, href: wa }] },
    };
}
