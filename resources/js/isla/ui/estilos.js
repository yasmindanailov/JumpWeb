/**
 * Los estilos del BOTÓN y del ENLACE del sistema de diseño (`Button.jsx` y `Link.jsx`), como funciones planas.
 *
 * Viven aquí y no en sus componentes por la regla `CE-6` del SPA (`SidebarComponentBudgetTest`): un componente
 * PINTA, y las tablas y las cuentas van en módulos planos. Las tablas son las del diseño, sin las variantes que
 * nombraban la paleta de su marca (`volt` y `glass` del botón; `inverse` del enlace). La `inverse` del botón entra
 * con Mi cuenta (T5a), escrita con roles de la isla.
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
    // La inversa, clara sobre la tinta de la isla (Mi cuenta: «Enseñar mi QR», «Guardar en el móvil»). En el diseño
    // nombraba la paleta (`--snow`, `--ink-900`, `--ink-100`); aquí, sus ROLES (T5a), que cada instalación pinta.
    inverse: {
        base: { background: 'var(--isla-inverso-fondo)', color: 'var(--isla-inverso-texto)' },
        hover: { background: 'var(--isla-inverso-fondo-hover)', boxShadow: 'var(--shadow-md)' },
    },
    // La secundaria de la isla y de la compra: clara, con BORDE de verdad (una sombra taparía el anillo de foco).
    quiet: {
        base: { background: 'var(--action-quiet-bg)', color: 'var(--action-quiet-fg)', border: '1px solid var(--action-quiet-border)' },
        hover: { background: 'var(--control-bg-hover)' },
    },
};

/** El hueco entre el icono y el texto de cada talla: el contenido lo lleva en su propia caja (Z3). */
export const huecoBoton = (size) => (TALLAS_BOTON[size] || TALLAS_BOTON.md).gap;

/**
 * El margen de la caja del TEXTO de un botón (`#844`): en el de ancho completo SIN iconos, el aire lateral de su talla, en
 * negativo; en los demás, ninguno (con iconos, el texto se les montaría encima). Su ancho es el de su hueco, así que su aire es
 * un MÁXIMO y no un mínimo —el texto que no cabe con él se lo come, como con el `Button` del diseño, y solo parte si no cabe
 * en la píldora entera—, y su ancho mínimo deja de sumarlo: era ESO lo que ensanchaba la columna de la calculadora y sacaba la
 * página de lado (medido: 294 px de mínimo, 214 de texto + 80 de aire). Aquí y no en el componente (`CE-6`): así se prueba.
 */
export const margenTextoBoton = ({ full, iconos, size }) => (full && ! iconos ? '-' + (TALLAS_BOTON[size] || TALLAS_BOTON.md).padding.slice(2) : undefined);

/** La capa del botón que va ENCIMA de su contenido (la bola de carga), del mismo tamaño: así el botón no cambia de ancho. */
export const CAPA_BOTON = { position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', pointerEvents: 'none' };

/** El brillo del primario que llega: uno solo, que cruza una vez (`pj-sheen` del diseño, `isla-sheen` aquí). */
export const BRILLO_BOTON = { position: 'absolute', inset: 0, pointerEvents: 'none', background: 'linear-gradient(100deg, transparent 30%, rgba(255,255,255,0.55) 50%, transparent 70%) no-repeat', backgroundSize: '60% 100%', animation: 'isla-sheen 900ms var(--ease-in-out) 520ms 1 both' };

/**
 * El estilo del botón, en el orden del diseño: base, talla, variante y, encima, su `hover`. Desde el 26-09 (Z3) es la
 * caja de sus capas (`position`, `overflow`: el brillo no se sale) y, si `llega`, bota (`isla-bote`).
 * ❗ **El de ANCHO COMPLETO parte su texto si no cabe** (`#844`, `[DECIDIDO owner]` 2026-09-29): el `Button` del diseño es
 * de alto fijo y una línea, y «Reservar y pagar la señal» pedía 294 px de ancho mínimo donde un Android de 360 deja 270 (la
 * página se salía de lado; en francés, ya a 390). Así que su alto pasa a MÍNIMO, con aire arriba y abajo, y el texto parte
 * equilibrado SOLO si no cabe en la píldora entera (su caja de texto se come el aire lateral: `margenTextoBoton`); si
 * cabe, el mismo botón de siempre —una línea en su alto de talla—. El que no es de ancho completo sigue como en el diseño.
 */
export function estiloBoton({ variant, size, full, bloqueado, loading, hover, press, llega = false }) {
    const v = VARIANTES_BOTON[variant] || VARIANTES_BOTON.primary;
    const talla = TALLAS_BOTON[size] || TALLAS_BOTON.md;
    const parte = Boolean(full);
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
        whiteSpace: variant === 'quiet' || parte ? 'normal' : 'nowrap',
        textAlign: 'center',
        gap: '9px',
        transition: 'var(--t-hover)',
        transform: press ? 'scale(var(--scale-press))' : hover && !bloqueado ? 'translateY(var(--lift-hover))' : 'none',
        opacity: bloqueado && !loading ? 0.42 : 1,
        position: 'relative',
        overflow: 'hidden',
        animation: llega ? 'isla-bote var(--dur-bote) linear both' : undefined,
        ...talla,
        // ⚠️ SIN `lineHeight` propio: medido, un 1,2 movía las letras del botón que SÍ cabe (1.420 píxeles en su fila, con
        // el control a 0); el interlineado heredado sirve para las dos líneas y deja idéntico el de una.
        ...(parte ? { height: undefined, minHeight: talla.height, paddingBlock: '10px', textWrap: 'balance' } : {}),
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

