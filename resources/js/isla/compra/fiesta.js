/**
 * **LA PANTALLA 0 DE UNA FIESTA, sin estado** (T3e·5 de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #696`).
 *
 * `PjcCuandoCumple` del diseño con los datos del motor: la EDAD elige el pack de su tramo (`guest_age_min`–
 * `guest_age_max` de cada ficha; sin máximo, abierto), después los niños (el mínimo y el máximo del pack), el día, la
 * hora y el MENÚ —el grupo de elección que resuelve el servidor, con sus platos—. Del resto de complementos, nada
 * aquí: van al formulario de invitados (`#692`·4, cero fricción antes de pagar).
 *
 * ⚠️⚠️ **Aquí no se calcula dinero** (`PAY-12`): el total, la señal y el precio de cada menú llegan hechos (la línea
 * que resuelve el servidor, el precio publicado de la opción) y solo se escriben.
 * ⚠️ Lo que se lee de cada ficha son sus DATOS (el campo de tipo `celebrant_age`, los tramos, los mínimos); los textos,
 * del `lang` con sus cifras, hasta que la página traiga los suyos (T4).
 */
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';
import { diaCorto, euros, horaCorta, horasDelSelector, tiraDias } from './vista.js';

/** El campo de la EDAD de quien cumple (`type: 'celebrant_age'`), o `null`: sin él, el pack no es de fiesta. */
export const campoDeEdad = (ficha) => (Array.isArray(ficha?.event_fields) ? ficha.event_fields : []).find((c) => c?.type === 'celebrant_age') ?? null;

/** Los packs de FIESTA de una zona, en el orden del catálogo, con su ficha (los que preguntan la edad). */
export function packsDeFiesta(productos, fichas, zona) {
    return (Array.isArray(productos) ? productos : [])
        .filter((p) => p?.type === 'pack' && p?.zone?.slug === zona)
        .map((p) => fichas?.[p.id])
        .filter((f) => f && campoDeEdad(f));
}

/** El pack de una edad: el de su tramo. Sin máximo, el tramo es abierto. `null` si ninguno la cubre. */
export function packPorEdad(packs, edad) {
    const e = Number(edad);

    return (packs ?? []).find((p) => e >= (p.guest_age_min ?? 0) && (p.guest_age_max == null || e <= p.guest_age_max)) ?? null;
}

/**
 * Las edades que se ofrecen: de la menor de los tramos a la mayor. ⚠️ Un tramo ABIERTO («desde 8») no tiene final, y
 * el diseño ofrece cinco años más que su mínimo (su rejilla, de 4 a 13): es lo único que aquí no sale de un dato.
 */
export const MAS_ALLA_DEL_ABIERTO = 5;

export function edadesDe(packs) {
    const lista = packs ?? [];

    if (lista.length === 0) return [];
    const desde = Math.min(...lista.map((p) => p.guest_age_min ?? 0));
    const hasta = Math.max(...lista.map((p) => (p.guest_age_max == null ? (p.guest_age_min ?? 0) + MAS_ALLA_DEL_ABIERTO : p.guest_age_max)));

    return Array.from({ length: Math.max(0, hasta - desde + 1) }, (_, i) => desde + i);
}

/** «Pack Kids, de 4 a 7 años» · «Pack Jump, desde 8 años»: el pack elegido, con su tramo. */
export function rotuloDelPack(pack, textos = {}) {
    if (! pack) return '';
    if (pack.guest_age_max == null) return textoCon(textos, 'compra.cuando.pack_desde', { pack: pack.name, min: pack.guest_age_min });

    return textoCon(textos, 'compra.cuando.pack_de_a', { pack: pack.name, min: pack.guest_age_min, max: pack.guest_age_max });
}

/** Un elemento de la lista en mitad de la frase: solo el primero conserva su mayúscula. */
const enFrase = (s, i) => (i === 0 ? s : s.charAt(0).toLowerCase() + s.slice(1));

/**
 * Los MENÚS: el grupo de elección que resuelve el servidor (`POST /catalog/products/{id}/addons`). El rótulo, como el
 * diseño («Menú 1, incluido» · «Menú 2, 2 € más por niño») con el precio PUBLICADO de la opción; debajo, sus platos y
 * regalos, en una frase.
 *
 * @param {Array<{key: string, options: Array<object>}>} grupos
 */
export function menusDe(grupos, { textos = {}, locale = 'es' } = {}) {
    const grupo = Array.isArray(grupos) ? grupos[0] : null;
    const lista = new Intl.ListFormat(locale, { style: 'long', type: 'conjunction' });

    return (grupo?.options ?? []).map((o) => {
        const platos = [...(o.features ?? []), ...(o.gifts ?? [])].map(enFrase);

        return {
            value: String(o.product_id),
            title: o.is_included || ! o.price_cents
                ? textoCon(textos, 'compra.cuando.menu_incluido', { menu: o.product_name })
                : textoCon(textos, 'compra.cuando.menu_mas', { menu: o.product_name, precio: euros(o.price_cents, locale) }),
            description: platos.length ? `${lista.format(platos)}.` : '',
        };
    });
}

/** La opción que el servidor dejó elegida (la incluida, por defecto), o la primera. */
export function menuElegido(grupos) {
    const opciones = Array.isArray(grupos) ? (grupos[0]?.options ?? []) : [];

    return String((opciones.find((o) => o.selected) ?? opciones[0])?.product_id ?? '') || null;
}

/** La elección que viaja a la línea: el menú, en su grupo. */
export function eleccionesDe(grupos, menu) {
    const clave = Array.isArray(grupos) ? grupos[0]?.key : null;

    return clave && menu ? [{ group: clave, product_id: Number(menu) }] : [];
}

/**
 * La PANTALLA 0 de una fiesta (`PjcCuandoCumple`): sus props y la descripción del paso.
 *
 * @param {object} e  `borrador` ({ edad, n, dia, hora, menu, fila }) · `packs` (fichas de fiesta de la zona) ·
 *   `precios` ({ [id]: días }) · `horas` · `cargandoHoras` · `maximo` (lo que cabe a esa hora) · `grupos` (del menú) ·
 *   `linea` (la que resolvió el servidor) · `corte` (horas de ajuste, `GET /config`) · `textos` · `locale` · `hoy`.
 */
export function pantallaCuandoFiesta(e) {
    const { borrador: b, textos, locale } = e;
    const t = (clave) => texto(textos, `compra.cuando.${clave}`);
    const tp = (clave, p) => textoCon(textos, `compra.cuando.${clave}`, p);
    const packs = e.packs ?? [];
    const pack = b.edad == null ? null : packPorEdad(packs, b.edad);
    const base = pack ?? packs.find((p) => p.id === b.fila) ?? packs[0] ?? null;
    const minimo = base?.min_quantity ?? 1;
    const maximo = Math.max(minimo, Math.min(base?.max_quantity ?? 40, e.maximo ?? Infinity));
    const ninos = { uno: t('nino'), varios: t('ninos') };
    const listo = Boolean(pack && b.dia && b.hora);
    const pista = [tp('minimo', { n: minimo }), Number.isInteger(e.corte) ? tp('ajusta', { n: minimo, horas: e.corte }) : ''].filter(Boolean).join(' ');

    const props = {
        titulo: t('titulo_fiesta'),
        preguntas: [t('pregunta_edad'), t('pregunta_ninos'), t('pregunta_dia_fiesta'), t('pregunta_hora'), t('pregunta_menu')],
        edades: edadesDe(packs).map((a) => ({ time: String(a) })),
        edad: b.edad == null ? null : String(b.edad),
        pack: rotuloDelPack(pack, textos),
        ninos: { n: b.n ?? minimo, min: minimo, max: maximo, ...ninos, pista },
        dias: base ? tiraDias(e.precios?.[base.id], { hoy: e.hoy, locale, textos }) : [],
        dia: b.dia,
        horas: b.dia && ! e.cargandoHoras ? horasDelSelector(e.horas, { gente: b.n ?? minimo, textos }) : null,
        hora: horaCorta(b.hora),
        menus: menusDe(e.grupos, { textos, locale }),
        menu: b.menu,
    };

    const resumen = pack && b.dia
        ? [pack.name, `${diaCorto(b.dia, locale)}${b.hora ? `, ${horaCorta(b.hora)}` : ''}`, `${b.n} ${b.n === 1 ? ninos.uno : ninos.varios}`].join(' · ')
        : null;

    return {
        props,
        listo,
        ck: {
            key: 'cuando',
            stepStrong: '',
            step: t('banda'),
            progress: null,
            summary: resumen,
            total: listo && e.linea ? euros(e.linea.total_cents, locale) : null,
            today: listo && e.linea?.has_deposit ? textoCon(textos, 'compra.pagar.hoy_pagas', { importe: euros(e.linea.deposit_cents, locale) }) : null,
            note: null,
            action: { label: t('continuar'), disabled: ! listo },
        },
    };
}
