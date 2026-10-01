/**
 * EL AVISO A ISLA ENTERA, su lógica y sus estilos (`AvisoIsla.vue` solo pinta: la regla `CE-6`). Es el `avisoEl` de
 * `ParkIsland.jsx` (la Z6b·2, zip (6), `isla-y-landing-nueva.md` §4.27): sin panel abierto ni cookies, el aviso ocupa la isla
 * entera —la fila se esconde— con el check lima que salta, el HECHO (la primera frase, en negrita) y el MATIZ (el resto), y
 * abajo la barra lima de lo que le queda. Se toca para quitarlo, y el ratón o el foco encima lo paran (WCAG 2.2.1); el
 * reloj es de la isla (`useAviso.js`).
 */
import { computed } from 'vue';
import { AVISO_MS } from '../useAviso.js';
import { quieto } from '../movimiento.js';

export const PROPS_AVISO = {
    texto: { type: String, required: true },
    /** Lo que se lee con el foco en él: el aviso y que se quita al tocarlo. */
    etiqueta: { type: String, default: '' },
    top: { type: Boolean, default: false },
    /** El ratón o el foco encima: la barra se para con el reloj. */
    pausado: { type: Boolean, default: false },
    /** Cuánto lleva corrido el reloj (`useAviso().transcurrido`): se lee al montarse. */
    transcurrido: { type: Function, default: () => 0 },
};

/**
 * El hecho y el matiz: se parte tras la PRIMERA frase y el resto va unido por un espacio, como el diseño
 * (`split(/(?<=\.)\s+/)`). Dos diferencias, a propósito: la frase también acaba en «!» o «?» —«¡Email confirmado! Tu
 * cuenta ya está activa.» salía entera en negrita— y no hay `lookbehind`, que Safari entiende solo desde 16.4: la web
 * sirve a 16.0 (el objetivo de Vite por defecto) y un `RegExp` que no entiende rompe el módulo entero al cargarlo.
 *
 * @param {string} texto
 * @returns {{hecho: string, matiz: string}}
 */
export function partesDelAviso(texto) {
    const s = String(texto ?? '').trim();
    const corte = /[.!?]\s+/.exec(s);

    if (! corte) return { hecho: s, matiz: '' };

    return { hecho: s.slice(0, corte.index + 1), matiz: s.slice(corte.index + corte[0].length).replace(/([.!?])\s+/g, '$1 ') };
}

/** El aviso entero es un botón: entra con el relevo de la isla, un poco después de que ella empiece a crecer. */
export function estiloAviso({ top }) {
    return {
        display: 'grid', gap: '10px', width: '100%', minWidth: top ? '380px' : 0, padding: '6px 6px 2px', border: 0,
        background: 'none', color: 'var(--text-strong)', textAlign: 'left', font: 'inherit', cursor: 'pointer',
        animation: 'isla-swap calc(var(--dur-island) * 0.8) var(--ease-out) calc(var(--dur-island) * 0.12) both',
    };
}

export const CHECK = {
    flex: 'none', width: '40px', height: '40px', borderRadius: '50%', display: 'grid', placeItems: 'center',
    background: 'var(--isla-vivo)', color: 'var(--isla-tinta)', animation: 'isla-pop var(--dur-slow) var(--ease-spring) both',
};
export const HECHO = { fontSize: '15px', fontWeight: 700, lineHeight: 1.3, textWrap: 'pretty' };
export const MATIZ = { fontSize: '13.5px', lineHeight: 1.35, color: 'var(--text-muted)', textWrap: 'pretty' };
export const CARRIL = {
    display: 'block', height: '3px', margin: '0 6px 4px', borderRadius: '3px', background: 'var(--control-bg-hover)', overflow: 'hidden',
};

/**
 * La barra de lo que le queda: se vacía en `AVISO_MS` desde lo ya corrido (un retraso negativo la empieza a medias) y se
 * para con el reloj. `null` con «reducir movimiento»: quieta mentiría y vacía no diría nada —el aviso se va igual, y el
 * ratón o el foco lo siguen parando—.
 */
export function estiloBarra({ transcurrido = 0, pausado = false, sinMovimiento = quieto() } = {}) {
    if (sinMovimiento) return null;

    return {
        display: 'block', height: '100%', borderRadius: '3px', background: 'var(--isla-vivo)', transformOrigin: 'left center',
        animation: `isla-aviso-resto ${AVISO_MS}ms linear ${-Math.round(Math.max(0, transcurrido))}ms both`,
        animationPlayState: pausado ? 'paused' : 'running',
    };
}

export function useAvisoIsla(props) {
    // Lo corrido se lee UNA vez, al montarse (el aviso vuelve tras cerrar un panel o al irse las cookies): después la barra
    // sigue sola y se para con la animación, que va con el reloj. Releerlo cambiaría su retraso y la haría saltar.
    const corrido = props.transcurrido();

    return {
        partes: computed(() => partesDelAviso(props.texto)),
        estilo: computed(() => estiloAviso({ top: props.top })),
        barra: computed(() => estiloBarra({ transcurrido: corrido, pausado: props.pausado })),
    };
}
