/**
 * **LA PANTALLA 0 DE LAS ENTRADAS, pura** (T3e de `docs/specs/isla-y-landing-nueva.md` §4.10; desde la T6c·3, §4.19, también
 * la de un PACK sin edad —una excursión— con lo que pide al reservar, `#839`).
 *
 * ⚠️ Aparte de `vista.js` a propósito, y medido (como `intencion.js`, `#836`): aquél lo importan también las calculadoras de
 * la página y Mi cuenta, y lo que viviera en él viajaba con ellas en su trozo compartido aunque solo lo use la compra (la
 * T6c·3 hizo crecer las dos calculadoras por encima de su techo, `SidebarBundleBudgetTest`, sin tocarlas).
 * ⚠️⚠️ **Aquí no se calcula dinero** (`PAY-12`): el precio del día, de `availability/{id}/dates`; el total, la señal y el
 * precio de ESA cantidad, de la línea que resuelve el servidor. Solo se FORMATEA.
 */
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';
import { calendarioDeTira, diaCorto, euros, horaCorta, horasDelSelector, precioDelDia, tiraDias } from './vista.js';

/**
 * Las filas de una zona, en el orden del catálogo: sus entradas; y si no vende ninguna, sus PACKS (T6c·3 de §4.19: una
 * zona de packs SIN edad —las excursiones— se vende como las entradas, con sus packs por «¿Cuánto tiempo?»). Que un pack
 * es de FIESTA lo decide su ficha, y esa pantalla es otra (`fiesta.js`): aquí solo llegan los que no lo son.
 */
export function filasDeZona(productos, zona) {
    const deZona = (Array.isArray(productos) ? productos : []).filter((p) => p?.zone?.slug === zona);
    const entradas = deZona.filter((p) => p.type === 'entry');

    return entradas.length ? entradas : deZona.filter((p) => p.type === 'pack');
}

/** Las zonas que venden entradas, en el orden en que aparecen: lo que ofrece «¿Qué zona?». */
export function zonasConEntradas(productos) {
    const vistas = new Map();

    for (const p of Array.isArray(productos) ? productos : []) {
        if (p?.type === 'entry' && p?.zone?.slug && ! vistas.has(p.zone.slug)) vistas.set(p.zone.slug, p.zone);
    }

    return [...vistas.values()];
}

/**
 * Lo que un PACK pide AL RESERVAR (`event_fields` de su ficha: la API publica solo los de la fase de reserva), como el
 * bloque «Datos de la reserva» de la pantalla 0 (`#839`, `[DECIDIDO owner]`: el centro y su responsable de una
 * excursión, que el servidor exige para admitir la línea). Menos la EDAD de quien cumple, que pregunta la fiesta. Las
 * etiquetas llegan ya en el idioma de la visita, del panel.
 */
export function datosDeReserva(ficha, respuestas = {}) {
    if (ficha?.type !== 'pack') return [];

    return (Array.isArray(ficha.event_fields) ? ficha.event_fields : [])
        .filter((c) => c?.key && c.type !== 'celebrant_age')
        .map((c) => ({ key: c.key, label: String(c.label ?? c.key), numero: c.type === 'number' || c.type === 'adults', required: c.required === true, valor: String(respuestas?.[c.key] ?? '') }));
}

/** ¿Están contestados los obligatorios? Sin espacios, como los sanea el servidor al validar la línea. */
export const datosCompletos = (datos) => datos.every((d) => ! d.required || d.valor.trim() !== '');

/** Las respuestas que viajan en la línea (`event_data`): las contestadas de esos campos, y nada más. */
export const respuestasDe = (datos) => Object.fromEntries(datos.filter((d) => d.valor.trim() !== '').map((d) => [d.key, d.valor.trim()]));

/**
 * La pregunta de los CALCETINES (el complemento por cantidad, `oferta.js::calcetinDe`): de una entrada y, desde `#880`,
 * de una fiesta. Sin él, `null`, y la pantalla no pregunta. El precio del par, el de su ficha.
 */
export function preguntaCalcetines(calcetin, n, { textos = {}, locale = 'es' } = {}) {
    if (! calcetin) return null;

    return {
        titulo: texto(textos, 'compra.cuando.pregunta_calcetines'),
        n: n ?? 0,
        uno: texto(textos, 'compra.cuando.par'),
        varios: texto(textos, 'compra.cuando.pares'),
        pista: textoCon(textos, 'compra.cuando.pista_calcetines', { precio: euros(calcetin.price_cents, locale) }),
        max: calcetin.max_quantity ?? 40,
    };
}

/**
 * La PANTALLA 0 de las entradas, «Cuándo y cuántos» (`PjcCuando`): sus props y la descripción del paso.
 *
 * @param {object} e  el estado, todo plano:
 *   `borrador` ({ zona, elegirZona, dia, hora, fila, n, cal, extras, otra, evento }) · `productos` (el catálogo tal cual) ·
 *   `precios` ({ [id]: días ofrecidos }) · `horas` (las ofrecidas de la fila y el día) · `cargandoHoras` ·
 *   `maximo` (lo que cabe a esa hora, o `null`) · `minimo` · `umbral` (el «casi llena» del panel) ·
 *   `calcetin` (el complemento por cantidad de la fila, o `null`) · `complementos` (las filas de los demás, hechas:
 *   `complementos.js`) · `ficha` (la de la fila: su tope y lo que pide al reservar) · `linea` (la que resolvió el
 *   servidor) · `textos` · `locale` · `hoy`.
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
    // Un PACK sin edad (una excursión, T6c·3): «personas», el tope de su ficha y lo que pide al reservar (`#839`).
    const esPack = fila?.type === 'pack';
    const unidad = esPack
        ? { uno: t('compra.cuando.persona'), varios: t('compra.cuando.personas') }
        : { uno: t('compra.cuando.entrada'), varios: t('compra.cuando.entradas') };
    const datos = esPack ? datosDeReserva(e.ficha, b.evento) : [];
    const listo = Boolean(fila && b.dia && b.hora) && datosCompletos(datos);

    const props = {
        // Sin entradas, la zona no está en «¿Qué zona?»: su nombre, tal cual (el de una excursión no es «Entrada …»).
        titulo: zona ? tp('compra.cuando.titulo_zona', { zona: zona.name }) : (fila?.zone?.name ?? t('compra.cuando.titulo_hoy')),
        zonas: b.elegirZona ? zonas.map((z) => ({ value: z.slug, title: z.name })) : null,
        zona: b.zona,
        preguntas: fila ? {
            dia: t('compra.cuando.pregunta_dia'),
            hora: t('compra.cuando.pregunta_hora'),
            tiempo: t('compra.cuando.pregunta_tiempo'),
            cuantos: t('compra.cuando.pregunta_cuantos'),
            calcetines: t('compra.cuando.pregunta_calcetines'),
            // Los datos de la reserva de un pack, con su título y sus campos; sin ellos, no hay pregunta.
            datos: datos.length ? { titulo: t('compra.cuando.pregunta_datos'), campos: datos } : null,
        } : null,
        dias: fila ? tiraDias(e.precios?.[fila.id], { hoy: e.hoy, locale, textos, elegido: b.dia }) : [],
        calendario: fila ? calendarioDeTira(e.precios?.[fila.id], { dia: b.dia, hoy: e.hoy, locale }) : null,
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
            // El precio del día de un pack es el de su tramo más barato (el de más gente): «desde», salvo el elegido con su
            // línea, que dice el de ESTA cantidad (`unit_price_cents`, del servidor).
            const dePack = p.type === 'pack' && cents !== null;
            const suyo = dePack && p.id === fila?.id && b.dia && b.hora && e.linea ? e.linea.unit_price_cents : null;

            return {
                value: String(p.id),
                title: p.name,
                description: noSeVende ? t('compra.cuando.no_disponible') : '',
                disabled: noSeVende,
                price: cents === null ? '' : (suyo !== null ? euros(suyo, locale) : (dePack ? tp('compra.cuando.desde_precio', { precio: euros(cents, locale) }) : euros(cents, locale))),
                was: '',
                highlight: ahorro > 0 ? tp('compra.cuando.ahorro', { importe: euros(ahorro, locale) }) : '',
            };
        }),
        fila: fila ? String(fila.id) : null,
        cuantos: { n: b.n, ...unidad, min: e.minimo ?? 1, max: e.maximo ?? (esPack ? e.ficha?.max_quantity : null) ?? 20 },
        calcetines: preguntaCalcetines(e.calcetin, b.cal, { textos, locale }),
        // Los demás complementos que se venden al reservar (`#880`; la hora extra, en el hueco que el diseño le dejó):
        // llegan hechos de `complementos.js`.
        complementos: e.complementos ?? [],
        otra: null,
        umbral: e.umbral || 6,
        otraZona: false,
    };

    const resumen = fila && b.dia
        ? [fila.name, `${diaCorto(b.dia, locale)}${b.hora ? `, ${horaCorta(b.hora)}` : ''}`, `${b.n} ${b.n === 1 ? unidad.uno : unidad.varios}`].join(' · ')
        : null;
    const sinContestar = datos.find((d) => d.required && d.valor.trim() === '');

    return {
        props,
        listo,
        // Lo PRIMERO que falta para continuar, por su `id` en la pantalla: a donde la capa lleva la vista (`ir-a.js`, el owner
        // 28-09) al llegar con la selección hecha y al pulsar «Continuar» sin estar lista. De un dato, su CAMPO.
        falta: listo ? null : (b.elegirZona && ! b.zona ? 'pjc-q-zona' : ! fila ? null : ! b.dia ? 'pjc-q-dia' : ! b.hora ? 'pjc-q-hora'
            : (sinContestar ? `pjc-dato-${sinContestar.key}` : null)),
        ck: {
            key: 'cuando',
            stepStrong: '',
            step: t('compra.cuando.banda'),
            progress: null,
            summary: resumen,
            total: listo && e.linea ? euros(e.linea.total_cents, locale) : null,
            // Con señal (un pack), lo que se paga hoy, como la fiesta: del presupuesto del servidor.
            today: listo && e.linea?.has_deposit ? textoCon(textos, 'compra.pagar.hoy_pagas', { importe: euros(e.linea.deposit_cents, locale) }) : null,
            note: null,
            action: { label: t('compra.cuando.continuar'), disabled: ! listo },
        },
    };
}
