/**
 * **LA VISTA DE LA CALCULADORA DEL PRODUCTO CONTRA LA DEL DISEÑO** (parte 3 de `scripts/modelo-entradas.php`; T4d·3 de
 * `specs/isla-y-landing-nueva.md` §4.12). Lo llama el PHP con un JSON: lo que la página le da al producto (`pagina`,
 * del modelo REAL de la instancia con los hechos del brief), los textos del producto (`textos`, el grupo `isla`), la
 * zona del diseño (`z`, de `contenido.js`) y la ficha de su primera entrada (`ficha`, donde está el complemento de los
 * calcetines).
 *
 * Por cada estado —una secuencia de cambios, los mismos a los dos lados— compara campo a campo:
 *   · la del DISEÑO, `vistaDiseno()` (su lógica transcrita, `banco-calculadora/diseno.js`, la que el banco juzga a 0);
 *   · la del PRODUCTO, `vistaCalculadora()` con esos mismos datos de prueba en la FORMA del motor (`motorDiseno()`).
 * Así se prueba lo que el banco no ve: que la página y el producto, con sus textos y su dinero del servidor, escriben
 * lo que el diseño. Imprime un JSON: por estado, sus diferencias (`ruta`, `diseno`, `producto`).
 *
 *   node scripts/calculadora-contra-diseno.mjs <entrada.json>
 */
import { readFileSync } from 'node:fs';
import process from 'node:process';
import { calcetinDe } from '../resources/js/isla/compra/oferta.js';
import { cierreDelDia, vistaCalculadora } from '../resources/js/isla/calculadora/vista.js';
import { cambiarDiseno, estadoInicial, motorDiseno, vistaDiseno } from './banco-calculadora/diseno.js';

const { pagina, textos, z, ficha } = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const base = calcetinDe(ficha);
const calcetin = base ? { ...base, name: (ficha.addons ?? []).find((a) => a.id === base.id)?.name ?? '' } : null;
const ids = pagina.filas.map((f) => f.id);
const idDe = (idDiseno) => ids[z.p3.filas.findIndex((f) => f.id === idDiseno)];

/**
 * Lo que difiere por DECISIÓN, con su porqué (se enseña y no cuenta). La ruta, con `*` por posición.
 */
const DECIDIDAS = {
    'resumen.lineas.*.label': '`#763`: el complemento del recibo se llama como en el PANEL («Calcetines antideslizantes»), el mismo nombre que dirá el paso de pagar',
};

/**
 * Lo que se compara es lo que SE VE: una hora completa sale «Completo» con `left: 0` (el diseño) o además apagada (la
 * pantalla 0 del producto, `horasDelSelector`), y el total no se pinta mientras falta el día o la hora.
 */
function visible(v) {
    const w = JSON.parse(JSON.stringify(v));
    if (w.hora.horas) w.hora.horas = w.hora.horas.map((x) => ({ time: x.time, left: x.left, note: x.note ?? null, completa: x.left === 0 || Boolean(x.disabled) }));
    if (w.resumen.falta) delete w.resumen.total;

    return w;
}

const casa = (ruta) => Object.keys(DECIDIDAS).find((p) => new RegExp(`^${p.replaceAll('.', '\\.').replaceAll('*', '[^.]+')}$`).test(ruta));

const aplanar = (x, ruta = '', plano = {}) => {
    if (x === null || typeof x !== 'object') { plano[ruta] = x ?? null; return plano; }
    for (const [k, v] of Object.entries(x)) aplanar(v, ruta === '' ? k : `${ruta}.${k}`, plano);
    return plano;
};

const ESTADOS = {
    reposo: [],
    dia: [['dia', '2026-09-26']],
    hoy: [['dia', '2026-09-23']],
    hora: [['dia', '2026-09-26'], ['hora', '17:00']],
    todo: [['dia', '2026-09-26'], ['hora', '17:00'], ['n', 3], ['cal', 3]],
    'un-par': [['dia', '2026-09-26'], ['hora', '17:00'], ['cal', 1]],
    'dos-pares': [['dia', '2026-09-26'], ['hora', '17:00'], ['cal', 2]],
    'dos-horas': [['dia', '2026-09-24'], ['fila', '2h'], ['hora', '19:00']],
    'no-cabe': [['dia', '2026-09-26'], ['hora', '12:30'], ['n', 8]],
    ilimitada: [['dia', '2026-09-26'], ['fila', 'ilim']],
    'ilimitada-hora': [['fila', 'ilim'], ['dia', '2026-09-28'], ['hora', '18:30']],
};

const resultado = {};
for (const [nombre, pasos] of Object.entries(ESTADOS)) {
    if (pasos.some(([campo, valor]) => campo === 'fila' && ! z.p3.filas.some((f) => f.id === valor))) continue;
    const s = estadoInicial(z);
    for (const [campo, valor] of pasos) cambiarDiseno(z, s, campo, valor);

    const diseno = vistaDiseno(z, s);
    // Los valores de las tarjetas del diseño son sus ids («1h»); los de la página, los productos.
    diseno.tiempo.fila = String(idDe(diseno.tiempo.fila));
    diseno.tiempo.items = diseno.tiempo.items.map((it) => ({ ...it, value: String(idDe(it.value)) }));

    const motor = motorDiseno(z, s, ids, calcetin);
    const producto = vistaCalculadora({
        pagina, textos, locale: 'es', calcetin, minimo: 1, maximo: null, cierre: cierreDelDia(pagina.cierres, s.dia), ...motor,
    });

    const [a, b] = [aplanar(visible(diseno)), aplanar(visible(producto))];
    const rutas = [...new Set([...Object.keys(a), ...Object.keys(b)])].filter((r) => a[r] !== b[r]);
    resultado[nombre] = {
        diferencias: rutas.filter((r) => ! casa(r)).map((r) => ({ ruta: r, diseno: a[r] ?? '(falta)', producto: b[r] ?? '(falta)' })),
        decididas: [...new Set(rutas.map(casa).filter(Boolean))].map((p) => DECIDIDAS[p]),
    };
}

process.stdout.write(JSON.stringify(resultado));
