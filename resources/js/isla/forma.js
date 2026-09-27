/**
 * La FORMA de la isla: dónde se pega, cuánto mide y cómo se anima (los estilos en línea de `ParkIsland.jsx`).
 * Funciones puras —entran medidas y banderas, sale un objeto de estilo— para que `IslaFlotante.vue` solo pinte
 * (`CE-6`) y para poder probar las reglas que a simple vista no se ven. Los parámetros llevan los nombres del
 * diseño (`row`, `box`, `grown`…), como el resto del port.
 */

/**
 * La raíz: una banda propia, pegada abajo (al alcance del pulgar) o arriba (desde 900px). En la compra se separa
 * de la página: fija sobre el velo, con aire arriba y abajo en escritorio y a pantalla completa en móvil, donde
 * tocar fuera no la cierra.
 * **La primera pantalla** (zip del 27-09, `isla-y-landing-nueva.md` §4.15): arriba, `--island-inset` sobre la isla y la
 * banda FIJA en ese aire más `--island-h` (el alto en reposo que la isla publica): lo que crece —las cookies, un aviso,
 * un panel— se abre por debajo, ENCIMA de la cabecera, sin empujarla. Abajo, el mismo aire que a los lados (`--gutter`).
 */
export function estiloRaiz({ gutter, top, inCheckout = false, reservado = 0 }) {
    const borde = top ? 'max(var(--island-inset), env(safe-area-inset-top))' : 'max(var(--gutter), env(safe-area-inset-bottom))';

    return {
        // La isla es su propia banda: ocupa el ancho del contenedor de scroll y se pega al borde. Si la metes en un
        // div de su alto, sticky no tiene recorrido y se va con el scroll: por eso el hueco lateral es suyo.
        position: 'sticky', zIndex: 80, display: 'flex', justifyContent: 'center',
        width: '100%', boxSizing: 'border-box', paddingLeft: gutter, paddingRight: gutter,
        pointerEvents: 'none',
        ...(top ? { top: 0, paddingTop: borde, height: `calc(${borde} + var(--island-h))`, alignItems: 'flex-start' } : { bottom: 0, paddingBottom: borde }),
        // Abajo, su hueco en la página es fijo (`#783`): la isla lo desborda hacia arriba y la página no se reordena en
        // cada fotograma mientras crece o encoge (`useMorfeo`, `reservado`). Arriba ya no hace falta: la banda es fija.
        ...(reservado && ! top && ! inCheckout ? { height: `calc(${reservado}px + ${borde})`, alignItems: 'flex-end' } : {}),
        ...(inCheckout && top ? { position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, height: 'auto', zIndex: 90, alignItems: 'flex-start', paddingTop: 'max(16px, 4vh)', paddingBottom: 'max(16px, 4vh)', pointerEvents: 'auto' } : {}),
        ...(inCheckout && ! top ? { position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, zIndex: 90, alignItems: 'flex-end', paddingTop: 'max(8px, env(safe-area-inset-top))', paddingBottom: 'max(8px, env(safe-area-inset-bottom))', paddingLeft: '8px', paddingRight: '8px' } : {}),
    };
}

const tamanoEn = (dur) => ['width', 'height', 'border-radius'].map((q) => `${q} ${dur} var(--ease-out)`).join(', ');

/**
 * **Qué ha cambiado en la píldora** (02c del laboratorio de movimiento, 26-09; `cambioRef` de `ParkIsland.jsx`): si solo
 * cambia la frase (lo normal al leer), `frase`; si llega la acción (la isla la recupera), `llega`; si se va, `sale`; si
 * cambia a otra, `oferta`. Recibe lo de antes y lo de ahora (`{ id, line, acc }`, `acc` = el rótulo de la acción o '').
 * Devuelve `null` si no ha cambiado nada: quien la llama conserva el último cambio (el del diseño, en su `ref`).
 */
export function tipoDeCambio(antes, ahora) {
    if (antes.id === ahora.id && antes.line === ahora.line && antes.acc === ahora.acc) return null;
    if (antes.acc === ahora.acc) return 'frase';

    return ! antes.acc ? 'llega' : ! ahora.acc ? 'sale' : 'oferta';
}

/**
 * La transición de la caja, la del diseño (26-09). Abrir, en calma (`--dur-slow`); cerrar, más corto (`--dur-close`): la
 * página vuelve enseguida. En la píldora: la frase, en calma (`--dur-base`); la acción que se va, en calma (`--dur-slow`);
 * lo que cambia la oferta, con el rebote (`--t-island`). Hundida al tocarla, en `--dur-instant`; al soltar, con el muelle.
 */
export function transicionIsla({ calm, isOpen, cambio, hundida }) {
    const tamano = calm ? tamanoEn(isOpen ? 'var(--dur-slow)' : 'var(--dur-close)')
        : cambio === 'frase' ? tamanoEn('var(--dur-base)')
            : cambio === 'sale' ? tamanoEn('var(--dur-slow)')
                : 'var(--t-island)';

    return [
        tamano,
        'border-color var(--dur-base) var(--ease-out)',
        hundida ? 'transform var(--dur-instant) var(--ease-out)' : 'transform 260ms var(--ease-spring)',
    ].join(', ');
}

/**
 * La isla: anima hasta lo que mide su contenido (`box`); sin transición hasta la primera medida. `nombre` es su
 * `view-transition-name`: entre páginas, la isla se queda y el resto se funde (solo la de la página, que es la que
 * está en las dos; la compra y Mi cuenta no cruzan de página).
 */
export function estiloIsla({ row, box, alert, grown, animate, calm, isOpen = false, cambio = null, hundida = false, nombre = null }) {
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
        transition: animate ? transicionIsla({ calm, isOpen, cambio, hundida }) : 'none',
        // Tocar la píldora la hunde (0,97) antes de crecer: el bote empieza en el dedo (02b).
        transform: hundida ? 'scale(var(--scale-press))' : 'none',
        viewTransitionName: nombre || undefined,
        willChange: 'width, height',
    };
}

/** El medidor: el bloque cuyo tamaño persigue la isla. En la compra, 600px arriba y el ancho entero abajo. */
export function estiloMedida({ row, top, isOpen, cap, maxWidth, inCheckout = false }) {
    return {
        boxSizing: 'border-box',
        padding: '8px',
        width: inCheckout ? (top ? 'min(600px, calc(100vw - 32px))' : '100%') : row ? 'max-content' : '100%',
        // Abierta en escritorio la isla se ensancha hasta un mínimo cómodo. En píxeles, nunca en %: un % se mide
        // contra la isla, que es quien se anima, y los dos se perseguían 2px por fotograma.
        minWidth: top && isOpen ? `${Math.min(cap || maxWidth, maxWidth, 390)}px` : undefined,
        maxWidth: top ? (cap ? `${Math.min(cap, maxWidth)}px` : `${maxWidth}px`) : row ? 'calc(100vw - 2 * var(--gutter))' : '100%',
    };
}

/**
 * **El alto en reposo que la isla publica** (`--island-h`, zip del 27-09, §4.15): su fila y, si la línea va encima de
 * ella (abajo, en móvil), desde la línea; más el aire de su medidor (8px arriba y 8 abajo). La cabecera mide con él. Las
 * cookies y los avisos son de paso y no cuentan. `0` = no hay nada que publicar (sin fila, o una medida absurda).
 */
export function altoEnReposo({ fila, linea = null, row }) {
    if (! fila) return 0;
    const primero = ! row && linea ? linea.top : fila.top;
    const h = Math.round(fila.bottom - primero + 16);

    return h >= 40 ? h : 0;
}

/** Cuándo publica: en reposo o cedida (píldora). Abierta, en la compra, compacta o con el pago fallido se queda el último. */
export function publicaAlto({ isOpen, inCheckout, isCompact, extra }) {
    return ! isOpen && ! inCheckout && ! isCompact && ! extra;
}

/** El `data-size` de la raíz, de más a menos: la compra manda sobre un panel, y un panel sobre el aviso. */
export function tamano({ inCheckout, isOpen, notice, isCompact }) {
    return inCheckout ? 'compra' : isOpen ? 'abierta' : notice ? 'aviso' : isCompact ? 'compacta' : 'reposo';
}
