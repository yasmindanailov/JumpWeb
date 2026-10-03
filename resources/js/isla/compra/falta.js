/**
 * **LO QUE FALTA PARA CONTINUAR, DICHO** (M2 de `docs/specs/isla-y-landing-nueva.md` §4.29; `[DECIDIDO owner]` en
 * `DECISIONES #881`): ningún botón de la compra de la isla se queda apagado sin decir por qué. Mientras falte algo, la nota
 * del pie lo dice encima del botón —como la calculadora de la página: «Elige la hora para ver el total»— y, al pulsar, la
 * caja lleva a esa pregunta (`ir-a.js`) y la MARCA en rojo con la misma frase hasta que se contesta.
 *
 * La marca es `{ id, texto }`: el `id` de la pregunta (el de su título en `PreguntaCompra`, o `pjc-perdida`) y su frase. La
 * da la compra (`FALTA`, con `provide`), y la pinta quien tiene ese `id`: así ninguna pantalla cambia de props.
 * Puro y sin Vue: se prueba con `node --test`.
 */
import { CODE_LENGTH, codeDigits } from '../../sidebar/code-input.js';
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';

/** La clave con que la compra da su marca (`provide`) a lo que la pinta (`inject`). */
export const FALTA = Symbol('falta');

/** La pregunta de la hora llena (`PantallaPerdida`): no es una `PreguntaCompra`, pero se marca igual. */
export const PERDIDA = 'pjc-perdida';

// De la pregunta de la pantalla 0 (`pantalla-cuando.js` y `fiesta.js::falta`) a su frase.
// `pjc-q-tiempo`: «Añadir otra entrada» sin un tiempo que se venda ese día (K3 de `otra-zona.md`).
const FRASES = { 'pjc-q-zona': 'zona', 'pjc-q-dia': 'dia', 'pjc-q-hora': 'hora', 'pjc-q-edad': 'edad', 'pjc-q-tiempo': 'tiempo' };
// Y de las tarjetas de las líneas añadidas, por su PREFIJO (`otra-zona.js`, una por línea desde la K3, con su índice):
// `pjc-q-otra-<i>`, la tarjeta cuando su zona no se vende ese día (K2); `pjc-q-otra-tiempo-<i>`, su «¿Cuánto tiempo?»
// cuando solo su tiempo no se vende (K2·b, `#882`). Las dos frases llevan `:zona`. La más larga, primero.
const PREFIJOS = [['pjc-q-otra-tiempo-', 'otra_tiempo'], ['pjc-q-otra-', 'otra']];
const claveDe = (falta) => FRASES[falta] ?? PREFIJOS.find(([p]) => typeof falta === 'string' && falta.startsWith(p) && /^\d+$/.test(falta.slice(p.length)))?.[1];

/**
 * Lo que se MARCA por lo que falta: un dato de la reserva de un pack (`pjc-dato-<clave>`, `#839`) marca su bloque
 * —«Datos de la reserva»—, y la caja va al campo, que se enfoca; lo demás, su pregunta.
 */
export const preguntaDeFalta = (falta) => (typeof falta === 'string' && falta.startsWith('pjc-dato-') ? 'pjc-q-datos' : falta ?? null);

/**
 * La frase de lo que falta en la pantalla 0, o `''` si no se sabe decir (y entonces el pie no dice nada). `params`, lo que
 * nombra su frase (la de la otra zona, `:zona`).
 */
export function textoDeFalta(falta, textos = {}, params = {}) {
    const clave = preguntaDeFalta(falta) === 'pjc-q-datos' ? 'datos' : claveDe(falta);

    return clave ? textoCon(textos, `compra.falta.${clave}`, params) : '';
}

/** La marca de lo que falta en la pantalla 0, o `null`. */
export function marcaDe(falta, textos = {}, params = {}) {
    const frase = textoDeFalta(falta, textos, params);

    return frase ? { id: preguntaDeFalta(falta), texto: frase } : null;
}

/**
 * Lo que falta en «Entra» (A3, `#849`): con el correo, escribirlo; con el código, sus seis cifras —la sexta ya entra sola
 * (`#867`), pero quien pulsa antes sabe por qué no pasa nada—. `{ id, texto }` del campo, o `null` si está listo.
 */
export function faltaDeEntrada(ent, textos = {}) {
    if (ent?.paso === 'codigo') {
        return codeDigits(ent.codigo).length < CODE_LENGTH
            ? { id: 'pjc-ent-codigo', texto: textoCon(textos, 'compra.falta.codigo', { n: CODE_LENGTH }) }
            : null;
    }

    return String(ent?.valor ?? '').trim() ? null : { id: 'pjc-ent', texto: texto(textos, 'compra.falta.correo') };
}

/** Lo que falta en la hora llena (`PjcPerdida`): elegir una de las cercanas. */
export const faltaDePerdida = (horaNueva, textos = {}) => (horaNueva ? null : { id: PERDIDA, texto: texto(textos, 'compra.falta.hora_libre') });

/**
 * ¿Sigue en pie la marca? Se va cuando su pregunta deja de ser lo que falta (se contestó, o falta otra cosa antes): la
 * nueva no se marca sola, solo al pulsar.
 */
export const sigueLaMarca = (marca, falta) => Boolean(marca && falta && marca.id === preguntaDeFalta(falta));

/**
 * El PIE de un paso (`ck`) con lo que falta: la nota encima del botón —tocarla hace lo mismo que el botón— y el botón,
 * nunca apagado (quien lo pulsa sin estar listo recibe la marca, no un botón muerto). Sin nada que falte, el paso tal cual.
 */
export function conFalta(ck, falta, accion) {
    if (! falta) return ck;

    return { ...ck, note: falta.texto, noteIcon: 'circle-alert', onNote: accion, action: ck.action ? { ...ck.action, disabled: false } : ck.action };
}
