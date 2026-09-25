/**
 * Compartir el cálculo (`ShareRow.jsx`; `FilaCompartir.vue`) y el chip (`Tag.jsx`; `EtiquetaSistema.vue`): sus reglas y
 * sus estilos, en un módulo plano (`CE-6`) y APARTE de `piezas.js` y `estilos.js`, que importa la compra de la isla: lo
 * que viviera allí viajaría con ella aunque solo lo use la calculadora de la página (T4d·4, medido).
 */

/**
 * El icono de cada forma de compartir. ⚠️ Solo `message-circle` está en `iconos.js`: los demás entran con su primer
 * consumidor (un icono que falta pinta su hueco vacío, como el diseño).
 */
export const ICONOS_COMPARTIR = { whatsapp: 'message-circle', email: 'mail', copy: 'link', link: 'arrow-up-right' };

/** Los atributos de una píldora de compartir: un enlace (el de fuera, en otra pestaña y sin `opener`) o un botón. */
export function atributosCompartir(item) {
    if (! item?.href) return { type: 'button' };
    const fuera = item.href.indexOf('http') === 0;

    return { href: item.href, target: fuera ? '_blank' : undefined, rel: fuera ? 'noopener noreferrer' : undefined };
}

/** Una píldora de compartir: tranquila, nunca naranja; sobre tinta, su borde y su texto claros. */
export function estiloCompartir({ tinta, sobre }) {
    return {
        display: 'inline-flex', alignItems: 'center', gap: '9px', minHeight: '44px', padding: '0 18px', border: 'none', borderRadius: 'var(--r-pill)',
        background: sobre ? (tinta ? 'rgba(255,255,255,0.14)' : 'var(--ink-100)') : 'transparent',
        boxShadow: `inset 0 0 0 1px ${tinta ? 'var(--border-inverse)' : 'var(--border-subtle)'}`,
        color: tinta ? 'var(--snow)' : 'var(--ink-900)', fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-semibold)', fontSize: 'var(--fs-body-sm)',
        textDecoration: 'none', cursor: 'pointer', transition: 'var(--t-hover)',
    };
}

/** El chip: elegido en el color del control; al pasar, solo si se pulsa. */
export function estiloEtiqueta({ selected, disabled, pulsable, sobre }) {
    return {
        display: 'inline-flex', alignItems: 'center', gap: '8px', height: '40px', padding: '0 16px', borderRadius: 'var(--r-pill)', border: 'none',
        background: selected ? 'var(--control-selected-bg)' : sobre && pulsable ? 'var(--control-bg-hover)' : 'var(--bg-subtle)',
        color: selected ? 'var(--control-selected-fg)' : 'var(--text-strong)',
        boxShadow: selected ? 'none' : 'inset 0 0 0 1px var(--border-subtle)',
        fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)',
        cursor: pulsable ? (disabled ? 'not-allowed' : 'pointer') : 'default', opacity: disabled ? 0.45 : 1, transition: 'var(--t-hover)',
    };
}
