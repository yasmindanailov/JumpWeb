/**
 * Los estilos del BOTÓN y del ENLACE del sistema de diseño (`Button.jsx` y `Link.jsx`), como funciones planas.
 *
 * Viven aquí y no en sus componentes por la regla `CE-6` del SPA (`SidebarComponentBudgetTest`): un componente
 * PINTA, y las tablas y las cuentas van en módulos planos. Las tablas son las del diseño, sin las variantes que
 * nombraban la paleta de su marca (`volt`, `inverse`, `glass` del botón; `inverse` del enlace).
 */

const TALLAS_BOTON = {
    sm: { height: 'var(--control-sm)', padding: '0 18px', fontSize: 'var(--fs-body-sm)', gap: '8px' },
    md: { height: 'var(--control-md)', padding: '0 26px', fontSize: 'var(--fs-body)', gap: '10px' },
    lg: { height: 'var(--control-lg)', padding: '0 32px', fontSize: '1.0625rem', gap: '10px' },
    xl: { height: 'var(--control-xl)', padding: '0 40px', fontSize: '1.1875rem', gap: '12px' },
};

const VARIANTES_BOTON = {
    primary: {
        base: { background: 'var(--action-bg)', color: 'var(--action-fg)', boxShadow: 'var(--shadow-sm)' },
        hover: { background: 'var(--action-bg-hover)', boxShadow: 'var(--shadow-cta)' },
    },
    secondary: {
        base: { background: 'var(--control-selected-bg)', color: 'var(--control-selected-fg)' },
        hover: { boxShadow: 'var(--shadow-md)', filter: 'brightness(1.12)' },
    },
    outline: {
        base: { background: 'transparent', color: 'var(--text-strong)', boxShadow: 'inset 0 0 0 2px var(--control-border-strong)' },
        hover: { background: 'var(--control-selected-bg)', color: 'var(--control-selected-fg)' },
    },
    ghost: {
        base: { background: 'transparent', color: 'var(--text-strong)' },
        hover: { background: 'var(--control-bg-hover)' },
    },
    // La secundaria de la isla y de la compra: clara, con BORDE de verdad (una sombra taparía el anillo de foco).
    quiet: {
        base: { background: 'var(--action-quiet-bg)', color: 'var(--action-quiet-fg)', border: '1px solid var(--action-quiet-border)' },
        hover: { background: 'var(--control-bg-hover)' },
    },
};

/** El estilo del botón, en el orden del diseño: base, talla, variante y, encima, su `hover`. */
export function estiloBoton({ variant, size, full, bloqueado, loading, hover, press }) {
    const v = VARIANTES_BOTON[variant] || VARIANTES_BOTON.primary;
    return {
        display: full ? 'flex' : 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: full ? '100%' : undefined,
        fontFamily: 'var(--font-ui)',
        fontWeight: 'var(--fw-bold)',
        letterSpacing: '-0.01em',
        border: 'none',
        borderRadius: 'var(--r-pill)',
        cursor: bloqueado ? 'not-allowed' : 'pointer',
        textDecoration: 'none',
        whiteSpace: variant === 'quiet' ? 'normal' : 'nowrap',
        textAlign: 'center',
        gap: '9px',
        transition: 'var(--t-hover)',
        transform: press ? 'scale(var(--scale-press))' : hover && !bloqueado ? 'translateY(var(--lift-hover))' : 'none',
        opacity: bloqueado && !loading ? 0.42 : 1,
        ...(TALLAS_BOTON[size] || TALLAS_BOTON.md),
        ...v.base,
        ...(hover && !bloqueado ? v.hover : {}),
    };
}

const TONOS_ENLACE = {
    default: { color: 'var(--text-link)', hover: 'var(--text-link-hover)' },
    quiet: { color: 'var(--text-muted)', hover: 'var(--text-strong)' },
    strong: { color: 'var(--text-strong)', hover: 'var(--text-link-hover)' },
};
const TALLAS_ENLACE = { sm: 'var(--fs-caption)', md: 'var(--fs-body-sm)', lg: 'var(--fs-body)' };
const ICONOS_ENLACE = { sm: 14, md: 16, lg: 18 };

export const tallaIconoEnlace = (size) => ICONOS_ENLACE[size] || 16;

/** El estilo del enlace: cian en reposo; el color de encima solo al pasar por él. */
export function estiloEnlace({ variant, size, block, underline, disabled, encendido }) {
    const tono = TONOS_ENLACE[variant] || TONOS_ENLACE.default;
    return {
        display: block ? 'flex' : 'inline-flex',
        position: 'relative',
        alignItems: 'center',
        justifyContent: block ? 'space-between' : undefined,
        width: block ? '100%' : undefined,
        minHeight: block ? '44px' : undefined,
        padding: block ? '10px 2px' : 0,
        gap: '7px',
        border: 'none',
        background: 'none',
        textAlign: 'left',
        fontFamily: 'var(--font-ui)',
        fontSize: TALLAS_ENLACE[size] || TALLAS_ENLACE.md,
        fontWeight: 'var(--fw-semibold)',
        lineHeight: 1.4,
        color: encendido ? tono.hover : tono.color,
        textDecoration: underline === 'always' || (underline === 'hover' && encendido) ? 'underline' : 'none',
        textUnderlineOffset: '3px',
        textDecorationThickness: '1.5px',
        cursor: disabled ? 'not-allowed' : 'pointer',
        opacity: disabled ? 0.42 : 1,
        transition: 'var(--t-hover)',
    };
}

