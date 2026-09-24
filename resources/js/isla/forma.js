/**
 * La FORMA de la isla: dónde se pega, cuánto mide y cómo se anima (los estilos en línea de `ParkIsland.jsx`).
 * Funciones puras —entran medidas y banderas, sale un objeto de estilo— para que `IslaFlotante.vue` solo pinte
 * (`CE-6`) y para poder probar las reglas que a simple vista no se ven. Los parámetros llevan los nombres del
 * diseño (`row`, `box`, `grown`…), como el resto del port.
 */

/** La raíz: una banda propia, pegada abajo (al alcance del pulgar) o arriba (desde 900px). */
export function estiloRaiz({ gutter, top }) {
    return {
        // La isla es su propia banda: ocupa el ancho del contenedor de scroll y se pega al borde. Si la metes en un
        // div de su alto, sticky no tiene recorrido y se va con el scroll: por eso el hueco lateral es suyo.
        position: 'sticky', zIndex: 80, display: 'flex', justifyContent: 'center',
        width: '100%', boxSizing: 'border-box', paddingLeft: gutter, paddingRight: gutter,
        pointerEvents: 'none',
        ...(top ? { top: 0, paddingTop: 'max(14px, env(safe-area-inset-top))' } : { bottom: 0, paddingBottom: 'max(14px, env(safe-area-inset-bottom))' }),
    };
}

// Los cambios grandes (abrir o cerrar un panel) van sin rebote; los pequeños, con el muelle de la isla.
const TRANSICION_CALMA = 'width var(--dur-slow) var(--ease-out), height var(--dur-slow) var(--ease-out), border-radius var(--dur-slow) var(--ease-out), border-color var(--dur-base) var(--ease-out)';
const TRANSICION_VIVA = 'var(--t-island), border-color var(--dur-base) var(--ease-out)';

/** La isla: anima hasta lo que mide su contenido (`box`); sin transición hasta la primera medida. */
export function estiloIsla({ row, box, alert, grown, animate, calm }) {
    return {
        pointerEvents: 'auto',
        position: 'relative',
        overflow: 'hidden',
        width: row ? (box.w ? `${box.w}px` : 'max-content') : '100%',
        height: box.h ? `${box.h}px` : 'auto',
        maxWidth: '100%',
        background: 'var(--ink-surface)',
        WebkitBackdropFilter: 'var(--blur-island)',
        backdropFilter: 'var(--blur-island)',
        border: `1px solid ${alert ? 'var(--isla-borde-alerta)' : 'var(--surface-glass-ink-border)'}`,
        borderRadius: grown ? 'var(--r-lg)' : 'var(--r-pill)',
        boxShadow: 'var(--isla-sombra)',
        transition: animate ? (calm ? TRANSICION_CALMA : TRANSICION_VIVA) : 'none',
        willChange: 'width, height',
    };
}

/** El medidor: el bloque cuyo tamaño persigue la isla. */
export function estiloMedida({ row, top, isOpen, cap, maxWidth }) {
    return {
        boxSizing: 'border-box',
        padding: '8px',
        width: row ? 'max-content' : '100%',
        // Abierta en escritorio la isla se ensancha hasta un mínimo cómodo. En píxeles, nunca en %: un % se mide
        // contra la isla, que es quien se anima, y los dos se perseguían 2px por fotograma.
        minWidth: top && isOpen ? `${Math.min(cap || maxWidth, maxWidth, 390)}px` : undefined,
        maxWidth: top ? (cap ? `${Math.min(cap, maxWidth)}px` : `${maxWidth}px`) : row ? 'calc(100vw - 32px)' : '100%',
    };
}

/** El `data-size` de la raíz, de más a menos: la compra manda sobre un panel, y un panel sobre el aviso. */
export function tamano({ inCheckout, isOpen, notice, isCompact }) {
    return inCheckout ? 'compra' : isOpen ? 'abierta' : notice ? 'aviso' : isCompact ? 'compacta' : 'reposo';
}
