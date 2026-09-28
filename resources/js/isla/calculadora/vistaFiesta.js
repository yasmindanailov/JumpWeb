/**
 * **LA VISTA DE LA CALCULADORA DE LA FIESTA, pura** (T6b·3 de `docs/specs/isla-y-landing-nueva.md` §4.18, `#836`; el
 * `Pieza6` de `paginas/piezas-5-6.jsx` del diseño): del estado del MOTOR (los días de cada pack con su precio, las horas,
 * los menús y los complementos resueltos, la línea del servidor), del borrador y de lo que la PÁGINA declara (sus packs
 * con su ficha, lo que alarga cada uno y sus textos, `data-jw-calculadora-fiesta`), a lo que pinta `CalculadoraFiesta.vue`.
 *
 * ⚠️⚠️ **Aquí no se calcula dinero** (`PAY-12`): el precio por niño del día sale de `availability/{id}/dates`; las
 * líneas, el total, la señal y el resto, de la `line` del servidor (`total_cents`, `deposit_cents`,
 * `gate_remainder_cents`); el precio de la hora extra y de cada menú, de su complemento resuelto. Solo se FORMATEA.
 * ⚠️ La EDAD elige el pack (`compra/fiesta.js::packPorEdad`, la regla de la isla): sin edad no hay pack ni total, y
 * mientras tanto el primero de la página presta sus días y su mínimo. Lo que ALARGA la fiesta se reconoce por su FORMA
 * (`extensiones`, de `/prices.stay_extensions`, `#834`), y si a esa hora cabe lo dice el servidor: si no la ofrece, no cabe.
 */
import { tp as textoCon } from '../../sidebar/i18n.js';
import { diaCorto, diaDelPlazo, euros, horaCorta, horasDelSelector, precioDelDia } from '../compra/vista.js';
import { edadesDe, packPorEdad, rotuloDelPack } from '../compra/fiesta.js';
import { mesesDelCalendario } from './vista.js';

const mayuscula = (s) => s.charAt(0).toUpperCase() + s.slice(1);

/** El pack de la edad del borrador, o `null` sin edad (o sin un pack que la cubra). */
export const packDeLaEdad = (packs, edad) => (edad == null ? null : packPorEdad(packs, edad));

/**
 * Los MENÚS como tarjetas (`OptionCards` del diseño: «Menú 1 · Incluido», «Menú 2 · +2 € por niño», con sus platos): los
 * del grupo de elección que resolvió el servidor, o —antes de tener hora— los de la ficha del pack (su `choice_group`).
 */
export function menusDeLaFiesta(grupos, ficha, { tp, locale, persona }) {
    const resueltos = Array.isArray(grupos) ? (grupos[0]?.options ?? null) : null;
    const opciones = resueltos ?? (ficha?.addons ?? []).filter((a) => a.choice_group).map((a) => ({
        product_id: a.id, product_name: a.name, price_cents: a.price_cents, is_included: a.included, features: a.features, gifts: a.gifts,
    }));

    return opciones.map((o) => ({
        value: String(o.product_id),
        title: o.product_name,
        price: o.is_included || ! o.price_cents ? tp('menu_incluido') : tp('menu_mas', { precio: euros(o.price_cents, locale), persona }),
        includes: [...(o.features ?? []), ...(o.gifts ?? [])],
    }));
}

/** El menú elegido: el del borrador si el pack lo ofrece; si no, el que el servidor deja elegido (o el incluido). */
export function menuDeLaFiesta(items, grupos, ficha, menu) {
    if (items.some((i) => i.value === menu)) return menu;
    const elegido = (Array.isArray(grupos) ? grupos[0]?.options ?? [] : []).find((o) => o.selected)
        ?? (ficha?.addons ?? []).find((a) => a.choice_group && a.included);

    return String(elegido?.product_id ?? elegido?.id ?? items[0]?.value ?? '') || null;
}

/**
 * @param {object} e  el estado, plano: `pagina` ({ zona, nombre, url, packs: [fichas], extensiones: { [pack]: { id,
 *   descripcion, desde } }, textos }) · `borrador` ({ edad, n, dia, hora, menu, horaExtra }) · `precios` ({ [id]: días }) ·
 *   `horas` · `grupos` (los menús resueltos, o `null`) · `sueltos` (los complementos sueltos resueltos) · `linea` (la del
 *   servidor, o `null`) · `maximo` (lo que cabe a esa hora) · `pendiente` · `tocada` · `abriendo` · `textos` · `locale` · `hoy`.
 */
export function vistaCalculadoraFiesta(e) {
    const { pagina: pg, borrador: b, textos, locale } = e;
    const p = pg.textos;
    const tp = (clave, params) => textoCon(textos, `calculadora.${clave}`, params);
    const packs = pg.packs ?? [];
    const pack = packDeLaEdad(packs, b.edad);
    const base = pack ?? packs[0] ?? null;
    const dias = base ? (e.precios?.[base.id] ?? []) : [];
    const unidad = b.dia && base ? precioDelDia(e.precios, base.id, b.dia) : null;
    const minimo = base?.min_quantity ?? 1;
    const maximo = Math.max(minimo, Math.min(base?.max_quantity ?? 40, e.maximo ?? Infinity));
    const horas = b.dia ? horasDelSelector(e.horas, { gente: b.n, textos }) : null;
    const h = pack && b.dia && b.hora && (horas ?? []).some((x) => x.time === horaCorta(b.hora) && ! x.disabled) ? horaCorta(b.hora) : null;
    const linea = h ? e.linea : null;
    const quien = (n) => `${n} ${p.persona[n === 1 ? 0 : 1]}`;
    const menus = menusDeLaFiesta(e.grupos, base, { tp, locale, persona: p.persona[0] });
    const vistaMes = mesesDelCalendario(dias, { hoy: e.hoy, dia: b.dia });
    const ultimo = dias.length ? dias[dias.length - 1].date.slice(0, 7) : null;

    // Lo que ALARGA la fiesta de ESTE pack: su complemento (por su forma) y, si a esa hora cabe, el resuelto del servidor.
    const ext = base ? (pg.extensiones?.[base.id] ?? null) : null;
    const suelto = ext && h ? (e.sueltos ?? []).find((s) => s.product_id === ext.id) ?? null : null;
    const cobrado = ext && linea ? (linea.addons ?? []).find((a) => a.product_id === ext.id && a.quantity > 0) ?? null : null;
    const horaExtra = ext ? {
        titulo: p.hora_extra.titulo, descripcion: ext.descripcion ?? '', nota: p.hora_extra.nota,
        precio: suelto ? tp(suelto.per_guest ? 'extra_por' : 'extra_mas', { precio: euros(suelto.price_cents, locale), persona: p.persona[0] }) : (ext.desde ?? ''),
        total: cobrado && suelto?.per_guest ? tp('extra_total', { precio: euros(cobrado.subtotal_cents, locale) }) : '',
        disponible: Boolean(suelto), marcada: Boolean(b.horaExtra && suelto),
        notaNo: ! h ? tp('extra_sin_hora') : (suelto ? '' : tp('extra_no_cabe', { hora: h })),
    } : null;

    const falta = ! pack ? tp('falta_edad') : ! b.dia ? tp('falta_dia') : ! h ? tp('falta_hora') : '';
    const cobrados = (linea?.addons ?? []).filter((a) => a.quantity > 0 && a.subtotal_cents > 0);
    // Con algo cobrado aparte (el menú de pago, la hora extra), el desglose; sin nada, solo el total (el diseño).
    const lineas = linea && cobrados.length ? [
        { label: tp('linea_pack', { pack: pack.name, n: b.n, precio: euros(linea.unit_price_cents, locale) }), value: euros(linea.subtotal_cents, locale) },
        ...cobrados.map((a) => ({ label: a.product_name, value: euros(a.subtotal_cents, locale) })),
    ] : [];
    const total = linea ? euros(linea.total_cents, locale) : '';
    const seleccion = [pack?.name, quien(b.n), b.dia ? (h ? tp('a_las', { dia: diaDelPlazo(b.dia, locale), hora: h }) : diaDelPlazo(b.dia, locale)) : null].filter(Boolean).join(' · ');
    const mensaje = `${p.mensaje}${seleccion}${total ? ` · ${total}` : ''} · ${pg.url}`;

    return {
        locale,
        edad: { titulo: p.preguntas[0], edades: edadesDe(packs).map((a) => ({ time: String(a) })), valor: b.edad == null ? null : String(b.edad), pack: rotuloDelPack(pack, textos) },
        ninos: {
            // `:n` y no `:minimo`: los textos de la página ya llegan con SUS cifras puestas, y el mínimo es el del pack elegido.
            titulo: p.preguntas[1], label: p.ninos.label, sub: textoCon(p, 'ninos.sub', { n: minimo }), n: b.n, min: minimo, max: maximo,
            precio: unidad !== null ? tp('por', { precio: euros(unidad, locale), persona: p.persona[0] }) : (base?.from_price_cents ? tp('desde', { precio: euros(base.from_price_cents, locale) }) : ''),
        },
        dia: {
            titulo: p.preguntas[2], mes: vistaMes.mes, meses: vistaMes.meses, desde: e.hoy.slice(0, 7), hasta: ultimo && ultimo > e.hoy.slice(0, 7) ? ultimo : e.hoy.slice(0, 7),
            hoy: e.hoy, dias: dias.map((d) => ({ date: d.date, special: d.rate_key === 'special' })), valor: b.dia,
        },
        hora: { titulo: p.preguntas[3], horas, valor: h, espera: tp('espera_hora') },
        menu: { titulo: p.preguntas[4], items: menus, valor: menuDeLaFiesta(menus, e.grupos, base, b.menu) },
        horaExtra,
        resumen: {
            seleccion, lineas, total, falta, faltaHref: ! pack ? '#p6-edad' : ! b.dia ? '#p6-dia' : '#p6-hora', listo: Boolean(linea) && ! e.pendiente,
            ahora: linea?.has_deposit ? { label: p.ahora, value: euros(linea.deposit_cents, locale) } : (p.senal ? { label: p.ahora, value: p.senal } : null),
            luego: linea?.has_deposit ? { label: p.luego, value: euros(linea.gate_remainder_cents, locale) } : null,
            nota: p.nota, boton: p.boton, junto: p.junto, abriendo: Boolean(e.abriendo),
        },
        // Lo que la calculadora le cuenta a la ISLA de la página (el `onCalculo` del diseño), solo si alguien la ha tocado.
        // Con el ancla de lo que falta (`faltaHref`): las de esta pieza no son las de la de entradas.
        isla: e.tocada ? {
            falta: ! pack ? 'edad' : ! b.dia ? 'dia' : ! h ? 'hora' : '', faltaHref: falta ? (! pack ? '#p6-edad' : ! b.dia ? '#p6-dia' : '#p6-hora') : '',
            elegido: linea ? `${mayuscula(diaCorto(b.dia, locale))} · ${h} · ${quien(b.n)} · ${total}` : null, boton: p.boton,
        } : null,
        compartir: { value: `${pg.url}#calcula`, items: [{ kind: 'whatsapp', label: p.compartir, href: `https://wa.me/?text=${encodeURIComponent(mensaje)}` }] },
    };
}
