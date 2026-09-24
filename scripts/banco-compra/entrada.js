/**
 * Entrada del BANCO DE LA COMPRA (`scripts/banco-compra.php`, T3c de `specs/isla-y-landing-nueva.md` §4.10): monta
 * `IslaFlotante.vue` en su tamaño «Compra» con la pantalla del producto que toca. Es el lado «B» del juez de
 * píxeles; vive en `scripts/` y se compila aparte, nunca en el paquete.
 *
 * El estado llega en términos del DISEÑO (su pedido, su formulario, su paso), normalizado por `estado.js` igual
 * que en el lado A. Este adaptador hace lo que en producción hará quien lleve la compra con el motor (T3e): sacar
 * de él la descripción del paso (`ck`) y los datos de cada pantalla. Los textos fijos salen del `lang` del producto
 * (así el banco comprueba también que son el literal del diseño); lo que depende de datos —zonas, precios,
 * sesiones, las tareas del parque— sale de las funciones del propio diseño (`window.PJC`), que es lo que el lado A
 * pinta.
 */
import { createApp, h } from 'vue';
import { t as texto, tp as textoCon } from '../../resources/js/sidebar/i18n.js';
import '../../resources/js/isla/isla.css';
import IslaFlotante from '../../resources/js/isla/IslaFlotante.vue';
import BotonSistema from '../../resources/js/isla/ui/BotonSistema.vue';
import PantallaCuando from '../../resources/js/isla/compra/PantallaCuando.vue';
import PantallaCuandoFiesta from '../../resources/js/isla/compra/PantallaCuandoFiesta.vue';
import PantallaDatos from '../../resources/js/isla/compra/PantallaDatos.vue';
import PantallaEntrar from '../../resources/js/isla/compra/PantallaEntrar.vue';
import PantallaDescargo from '../../resources/js/isla/compra/PantallaDescargo.vue';
import PantallaPagar from '../../resources/js/isla/compra/PantallaPagar.vue';
import JuntoPagar from '../../resources/js/isla/compra/JuntoPagar.vue';
import PantallaSaliendo from '../../resources/js/isla/compra/PantallaSaliendo.vue';
import PantallaFallido from '../../resources/js/isla/compra/PantallaFallido.vue';
import JuntoFallido from '../../resources/js/isla/compra/JuntoFallido.vue';
import PantallaVerificando from '../../resources/js/isla/compra/PantallaVerificando.vue';
import PantallaPerdida from '../../resources/js/isla/compra/PantallaPerdida.vue';
import PantallaListo from '../../resources/js/isla/compra/PantallaListo.vue';
import { PASO } from '../../resources/js/isla/compra/estilos.js';

const { estado, isla, textos } = globalThis.BANCO;
const D = globalThis.PJC;
const T = D.T;
const P = T.propuesta;
const t = (clave) => texto(textos, clave);
const tp = (clave, p) => textoCon(textos, clave, p);
const nada = () => {};

/** Los días de la tira (`pjcDias` de `pantalla-0.jsx`). */
const diasDe = (sinHoy) => D.DIAS.filter((x) => ! (sinHoy && x.hoy)).map((x) => ({
    id: x.id, n: x.n, label: x.hoy ? 'hoy' : x.id.split(' ')[0], special: x.especial,
    aria: x.largo + (x.especial ? ', tarifa especial' : ''),
}));

/** La pantalla 0 de las entradas (lo que `PjcCuando` calcula antes de pintar). */
function cuando(c, ofertas) {
    const otra = c.modo === 'otra';
    const Z = c.zona ? D.zona(c.zona) : null;
    const d = D.dia(c.dia);
    const col = d.especial ? 1 : 0;
    const fila = Z ? (Z.filas.find((x) => x.id === c.fila) || Z.filas[0]) : null;
    const gente = c.n + (c.otra ? c.otra.n : 0);
    const precio = (x) => (ofertas ? x.oferta[col] : x.precios[col]);
    const Z2 = c.otra ? D.zona(c.otra.zona) : null;
    const vm = {
        titulo: otra ? t('compra.cuando.otra_titulo') : (c.zona ? T.cuando.titulo[c.zona] : t('compra.cuando.titulo_hoy')),
        otraEntrada: otra,
        fijo: otra ? `${D.cap(c.dia)} · ${c.hora}` : '',
        zonas: otra || c.elegirZona ? ['kids', 'jump'].map((id) => ({ value: id, title: T.cuando.zonas[id] })) : null,
        zona: c.zona,
    };
    if (! Z) return { vm, extraZona: false };

    return {
        vm: {
            ...vm,
            preguntas: { cuantos: Z.preguntas[0], tiempo: Z.preguntas[1], dia: Z.preguntas[2], hora: Z.preguntas[3], calcetines: Z.preguntas[4] },
            dias: diasDe(false),
            dia: c.dia,
            horas: otra ? [] : D.sesiones(d, fila.horas, gente, false),
            hora: c.hora,
            pistaHora: Z.tiempo,
            filas: Z.filas.map((x) => {
                const no = x.precios[col] == null;
                const ahorro = x.horas === 2 && ! no ? 2 * precio(Z.filas[0]) - precio(x) : 0;
                return {
                    value: x.id, title: x.label, description: no ? x.soloLJ : x.sub || '', disabled: no,
                    price: no ? '' : D.eur(precio(x)), was: ofertas && ! no ? D.eur(x.precios[col]) : '',
                    highlight: ahorro > 0 ? tp('compra.cuando.ahorro', { importe: D.eur(ahorro) }) : '',
                };
            }),
            fila: c.fila,
            cuantos: { n: c.n, uno: Z.uno, varios: Z.varios },
            calcetines: { n: c.cal, uno: P.par, varios: P.pares, pista: Z.calcetines },
            horaExtra: fila.horas === 2,
            otra: Z2 ? { titulo: `${Z2.nombre} · ${Z2.filas[0].label}`, precio: `${D.eur(precio(Z2.filas[0]))} por ${Z2.uno}`, n: c.otra.n, uno: Z2.uno, varios: Z2.varios } : null,
        },
        extraZona: fila.horas === 2,
    };
}

/** La pantalla 0 de una fiesta (lo que `PjcCuandoCumple` calcula antes de pintar). */
function cuandoFiesta(c) {
    const C = T.cuando.cumple;
    const d = c.dia ? D.dia(c.dia) : null;
    const pack = c.edad == null ? null : c.edad >= 8 ? 'Jump' : 'Kids';
    return {
        titulo: T.cuando.titulo.cumple,
        preguntas: C.preguntas,
        edades: [4, 5, 6, 7, 8, 9, 10, 11, 12, 13].map((a) => ({ time: String(a) })),
        edad: c.edad == null ? null : String(c.edad),
        pack: pack ? C.packs[pack] : '',
        ninos: { n: c.n, min: 8, max: 40, uno: 'niño', varios: 'niños', pista: `${C.minimo} ${C.ajusta}` },
        dias: diasDe(true),
        dia: c.dia,
        horas: d ? D.sesiones(d, 2, 1, true) : null,
        hora: c.hora,
        menus: C.menus.map((m) => ({ value: m.id, title: m.label, description: m.sub })),
        menu: c.menu,
    };
}

/** El recibo de «Pagar» (lo que `PjcPagar` calcula antes de pintar). */
function pagar(pd, q) {
    const c = D.cuenta(pd, q);
    const entradas = pd.tipo === 'entradas';
    const lineas = c.filas.map((r) => ({
        id: r.id, label: r.titulo, sub: r.unidad, value: D.eur(r.importe),
        control: r.n != null ? { n: r.n, min: r.min, max: r.max, uno: r.uno, varios: r.varios } : null,
    }));
    if (entradas && q.calcetines > 0) {
        lineas.push({ id: 'calcetines', label: T.calcetinesFila, sub: T.calcetinesUnidad, value: D.eur(c.calcetines), control: { n: q.calcetines, min: 0, max: 20, uno: P.par, varios: P.pares } });
    }
    if (c.descuento) lineas.push({ id: 'oferta', label: T.oferta, value: `−${D.eur(c.descuento)}`, tone: 'positive' });
    return {
        lineas,
        total: D.eur(c.total),
        nota: entradas ? '' : tp('compra.pagar.senal', { senal: D.eur(pd.senal), resto: D.eur(c.resto) }),
        otraEntrada: entradas,
        calcetines: entradas && q.calcetines === 0 ? { texto: T.pagar.calcetines, uno: P.par, varios: P.pares } : null,
    };
}

/** Las tareas de «Listo» (`pjcTareas`): las del producto con su `lang`; los calcetines, del parque. */
function tareas(pd, q) {
    if (pd.tipo === 'cumple') {
        return [{ id: 'cumple', icon: 'party-popper', title: t('compra.listo.fiesta_intro'), steps: [tp('compra.listo.fiesta_invitados', { fecha: pd.plazo }), t('compra.listo.fiesta_invitacion')], botones: [t('compra.listo.fiesta_formulario'), t('compra.listo.fiesta_compartir')] }];
    }
    const fuera = [];
    if (pd.lineas.some((l) => l.zona === 'Kids')) fuera.push({ id: 'menores', icon: 'user-round-plus', texto: t('compra.listo.menores'), botones: [t('compra.listo.menores_boton')] });
    const jump = pd.lineas.find((x) => x.zona === 'Jump');
    const mas = jump ? (q.lineas[jump.id] || 0) - 1 : 0;
    if (mas > 0) fuera.push({ id: 'adultos', icon: 'users', texto: mas === 1 ? t('compra.listo.adulto') : tp('compra.listo.adultos', { n: mas }) });
    fuera.push({ id: 'calcetines', icon: 'footprints', texto: T.listo.calcetines });
    return fuera;
}

/** La línea de «Listo» (`lineaListo`), con el «Nº de pedido» del `lang`. */
function lineaListo(pd, q, hora, codigo) {
    const cuantos = (l, n) => `${n} ${n === 1 ? l.uno : l.varios}`;
    const partes = pd.tipo === 'cumple' ? [pd.pack.nombre, `${q.ninos} niños`] : pd.lineas.map((l) => `${l.zona} ${l.tiempo} · ${cuantos(l, q.lineas[l.id] || 0)}`);
    return [pd.fecha, hora].concat(partes, [tp('compra.listo.pedido', { codigo })]).join(' · ');
}

/** El paso entero: la descripción para `CompraIsla` (`ck`), la pantalla y lo que va junto a la acción. */
function paso(S) {
    const { borrador, pd, q, hora, f, err, ent } = S;
    const enCuando = S.paso === 'cuando';
    const pdCuando = enCuando && borrador && borrador.zona && borrador.modo === 'nuevo' && (borrador.zona !== 'cumple' || (borrador.edad != null && borrador.dia)) ? D.pedidoDe(borrador, S.ofertas) : null;
    const qCuando = pdCuando ? { lineas: pdCuando.lineas ? Object.fromEntries(pdCuando.lineas.map((l) => [l.id, l.n])) : {}, calcetines: borrador.cal || 0, ninos: borrador.n } : null;
    const Pd = pdCuando || pd;
    const Q = qCuando || q;
    const c = Pd ? D.cuenta(Pd, Q) : null;
    const importe = c ? D.eur(c.importe) : '';
    const segundo = S.principal === 'bizum' ? 'tarjeta' : 'bizum';
    const ck = { key: S.clave, dir: S.dir, stepStrong: '', step: '', progress: null, onBack: null, onClose: nada, action: null, note: null };
    const pasoN = (n, nombre) => Object.assign(ck, { stepStrong: tp('compra.paso', { n, total: 2 }), step: ` · ${nombre}`, progress: [n, 2] });
    let cuerpo = null;
    let junto = null;
    let conResumen = true;

    if (enCuando) {
        ck.step = t('compra.cuando.banda');
        const listo = borrador.modo === 'otra' ? Boolean(borrador.zona) : borrador.zona === 'cumple' ? Boolean(borrador.edad != null && borrador.dia && borrador.hora) : Boolean(borrador.zona && borrador.hora);
        ck.action = { label: t('compra.cuando.continuar'), onClick: nada, disabled: ! listo };
        if (borrador.modo === 'otra' || borrador.desde === 'selector') ck.onBack = nada;
        if (borrador.zona === 'cumple') {
            cuerpo = h(PantallaCuandoFiesta, cuandoFiesta(borrador));
        } else {
            const { vm, extraZona } = cuando(borrador, S.ofertas);
            cuerpo = h(PantallaCuando, vm, extraZona ? { 'hora-extra': () => [h('div', { style: PASO.hueco }, P.horaExtra)] } : {});
        }
        conResumen = Boolean(Pd);
    } else if (S.paso === 'datos') {
        pasoN(1, t('compra.datos.banda'));
        ck.action = S.llena
            ? { label: t('compra.datos.elegir'), onClick: nada, disabled: ! S.horaNueva }
            : { label: t('compra.datos.continuar'), onClick: nada, loading: S.ocupado === 'datos' ? t('compra.datos.cargando') : false };
        if (S.vista === 'descargo') {
            cuerpo = h(PantallaDescargo, null, { default: () => [h('div', { style: PASO.hueco }, 'Texto del descargo pendiente: lo entrega el parque. Va entero aquí, y se lee sin salir de la compra.')] });
            ck.action = null;
            ck.onBack = nada;
        } else if (S.vista === 'entrar') {
            cuerpo = h(PantallaEntrar, { paso: ent.paso, valor: ent.valor, clave: ent.pass, error: ent.error, enApp: S.insta });
            ck.action = ent.paso === 'id' ? { label: t('compra.entrar.continuar'), onClick: nada, disabled: ! ent.valor.trim() || ! ent.pass, loading: S.ocupado === 'entrar' ? t('compra.entrar.cargando') : false } : null;
            ck.onBack = nada;
        } else {
            cuerpo = h(PantallaDatos, {
                cuenta: f.cuenta,
                nombrePila: f.nombre.trim().split(' ')[0],
                valores: { nombre: f.nombre, correo: f.correo, telefono: f.telefono, contrasena: f.contrasena, descargo: f.descargo },
                firmado: f.firmado,
                pedirTelefono: pd.tipo === 'cumple' && f.pedirTel,
                errores: err,
                lineaMenores: pd.tipo === 'entradas' && (D.personas(pd, q) > 1 || pd.lineas.some((l) => l.zona === 'Kids')),
                llena: S.llena,
                cercanas: pd.cercanas || [],
                horaNueva: S.horaNueva,
                enApp: S.insta,
            });
            if (S.desdeCuando) ck.onBack = nada;
        }
    } else if (S.paso === 'pagar') {
        pasoN(2, t('compra.pagar.banda'));
        ck.note = tp('compra.pagar.retencion', { hora: S.hold });
        ck.action = { label: tp(`compra.pagar.${S.principal}`, { importe }), onClick: nada };
        junto = h(JuntoPagar, {
            secundario: { etiqueta: tp(`compra.pagar.${segundo}`, { importe }), metodo: segundo },
            marcas: T.pagar.marcas.concat(S.wallets ? T.pagar.wallets : []),
            condicionesHref: '#condiciones',
            condiciones: T.pagar.condiciones.despues,
        });
        cuerpo = h(PantallaPagar, pagar(pd, q));
        ck.onBack = nada;
    } else if (S.paso === 'banco') {
        pasoN(2, t('compra.pagar.banda'));
        ck.note = tp('compra.pagar.retencion', { hora: S.hold });
        ck.action = { label: t('compra.pagar.saliendo_boton'), onClick: nada };
        cuerpo = h(PantallaSaliendo);
    } else if (S.paso === 'fallido') {
        pasoN(2, t('compra.pagar.banda'));
        ck.action = { label: t('compra.fallido.bizum'), onClick: nada };
        junto = h(JuntoFallido, { ayuda: S.manual });
        cuerpo = h(PantallaFallido, { hora: S.hold, motivo: S.motivo });
    } else if (S.paso === 'verificando') {
        pasoN(2, t('compra.pagar.banda'));
        cuerpo = h(PantallaVerificando);
    } else if (S.paso === 'perdida') {
        pasoN(2, t('compra.pagar.banda'));
        ck.action = { label: t('compra.perdida.boton'), onClick: nada, disabled: ! S.horaNueva };
        cuerpo = h(PantallaPerdida, { cercanas: pd.cercanas || [], horaNueva: S.horaNueva });
    } else if (S.paso === 'listo') {
        conResumen = false;
        ck.action = { label: t('compra.listo.mi_qr'), onClick: nada };
        junto = h(BotonSistema, { variant: 'quiet', full: true }, { default: () => t('compra.listo.otra') });
        cuerpo = h(PantallaListo, {
            fiesta: pd.tipo === 'cumple',
            linea: lineaListo(pd, q, hora, S.hecho.codigo),
            codigo: S.hecho.codigo,
            correo: S.hecho.correo,
            whatsapp: S.whatsapp,
            tareas: tareas(pd, q),
            cuentaNueva: S.hecho.nueva,
        });
    }

    ck.summary = conResumen && Pd ? D.linea(Pd, Q, enCuando ? borrador.hora : hora) : null;
    ck.total = conResumen && c ? D.eur(c.total) : null;
    ck.today = conResumen && Pd && Pd.tipo === 'cumple' ? tp('compra.pagar.hoy_pagas', { importe: D.eur(Pd.senal) }) : null;

    return { ck, cuerpo, junto };
}

const { ck, cuerpo, junto } = paso(globalThis.bancoCompraEstado(estado));
createApp({
    render: () => h(IslaFlotante, { ...isla, textos, checkout: ck }, { default: () => [cuerpo], junto: () => (junto ? [junto] : []) }),
}).mount('#isla');
