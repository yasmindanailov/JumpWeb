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
export function estiloRaiz({ gutter, top, inCheckout = false, reservado = 0, kb = null }) {
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
        // Con el TECLADO abierto (§4.16, `useTeclado`), la raíz se ciñe a la ventana VISIBLE —en iOS, abrir el teclado no
        // encoge la página, y lo fijado al fondo quedaba debajo de él—: la acción, siempre encima del teclado.
        ...(inCheckout && ! top && kb ? { top: `${kb.top}px`, bottom: 'auto', height: `${kb.h}px`, boxSizing: 'border-box', paddingTop: '8px', paddingBottom: '8px' } : {}),
    };
}

const tamanoEn = (dur, curva = 'var(--ease-out)') => ['width', 'height', 'border-radius'].map((q) => `${q} ${dur} ${curva}`).join(', ');

/**
 * La transición de la caja (Z6a, zip (6)). La capa grande (la compra, Mi cuenta) y el pago fallido van en calma, sin
 * rebote: abrir en `--dur-slow` y cerrar más corto (`--dur-close`), que la página vuelva enseguida. Todo lo demás —los
 * cambios de la píldora, abrir y cerrar un panel— es el MORPH de la isla con `--ease-island`: rápido (`--dur-island`) si lo
 * provoca quien la toca, en calma (`--dur-island-calma`) si lo trae el scroll. El fondo cambia en `--dur-slow` (cristal ↔
 * tinta). Hundida al tocarla, en `--dur-instant`; al soltar, con el muelle.
 */
export function transicionIsla({ calm, inCheckout = false, rapido = true, hundida }) {
    const tamano = calm
        ? tamanoEn(inCheckout ? 'var(--dur-slow)' : 'var(--dur-close)')
        : `${tamanoEn(rapido ? 'var(--dur-island)' : 'var(--dur-island-calma)', 'var(--ease-island)')}, transform var(--dur-base) var(--ease-out)`;

    return [
        tamano,
        'border-color var(--dur-base) var(--ease-out)',
        'background-color var(--dur-slow) var(--ease-out)',
        hundida ? 'transform var(--dur-instant) var(--ease-out)' : 'transform 260ms var(--ease-spring)',
    ].join(', ');
}

/**
 * La isla: anima hasta lo que mide su contenido (`box`); sin transición hasta la primera medida. `nombre` es su
 * `view-transition-name`: entre páginas, la isla se queda y el resto se funde (solo la de la página, que es la que
 * está en las dos; la compra y Mi cuenta no cruzan de página).
 * **Es cristal** (Z6a): `--surface-glass-ink-float` (82 %) con `--blur-island`, en reposo, abierta, con las cookies, los
 * avisos y el pago fallido; solo la capa grande (`lee`: la compra y Mi cuenta, formularios largos) va en `--ink-surface`.
 * Si lo trae el scroll (`rapido` falso), `--dur-island` pasa a la calma también para lo que se cruza dentro.
 * ⚠️ **El borde (1px por lado) va FUERA de lo medido** (el owner, 01-10, Z6b: «los márgenes no son perfectos»): la hoja
 * del sistema pone `box-sizing: border-box` a todo (`tokens/base.css` del diseño), así que una isla del tamaño exacto de
 * su medidor recortaba 2px su contenido —9px de aire arriba y a la izquierda, 7 abajo y a la derecha (medido), igual en
 * el mockup—. Se suma el borde aquí (`useMorfeo` ya reservaba su hueco con él, `h + 2`) y el medidor lleva 7px de aire
 * (`estiloMedida`): la caja de fuera mide lo mismo que antes y el aire, 8px en los cuatro lados.
 */
export function estiloIsla({ row, box, alert, grown, animate, calm, lee = false, inCheckout = false, rapido = true, hundida = false, nombre = null }) {
    return {
        pointerEvents: 'auto',
        position: 'relative',
        overflow: 'hidden',
        width: row ? (box.w ? `${box.w + 2}px` : 'max-content') : '100%',
        height: box.h ? `${box.h + 2}px` : 'auto',
        maxWidth: '100%',
        '--dur-island': rapido ? undefined : 'var(--dur-island-calma)',
        background: lee ? 'var(--ink-surface)' : 'var(--surface-glass-ink-float)',
        WebkitBackdropFilter: 'var(--blur-island)',
        backdropFilter: 'var(--blur-island)',
        border: `1px solid ${alert ? 'var(--isla-borde-alerta)' : 'var(--surface-glass-ink-border)'}`,
        borderRadius: grown ? 'var(--r-lg)' : 'var(--r-pill)',
        boxShadow: 'var(--isla-sombra)',
        transition: animate ? transicionIsla({ calm, inCheckout, rapido, hundida }) : 'none',
        // Tocar la píldora la hunde (0,97) antes de crecer: el bote empieza en el dedo (02b).
        transform: hundida ? 'scale(var(--scale-press))' : 'none',
        viewTransitionName: nombre || undefined,
        willChange: 'width, height',
    };
}

/**
 * El medidor: el bloque cuyo tamaño persigue la isla. En la compra, 600px arriba y el ancho entero abajo. Su aire, 7px: con
 * el borde de la isla (1px, fuera de lo medido: `estiloIsla`), los 8px del diseño, iguales en los cuatro lados.
 */
export function estiloMedida({ row, top, isOpen, cap, maxWidth, inCheckout = false }) {
    return {
        boxSizing: 'border-box',
        padding: '7px',
        width: inCheckout ? (top ? 'min(600px, calc(100vw - 32px))' : '100%') : row ? 'max-content' : '100%',
        // Abierta en escritorio la isla se ensancha hasta un mínimo cómodo. En píxeles, nunca en %: un % se mide
        // contra la isla, que es quien se anima, y los dos se perseguían 2px por fotograma.
        minWidth: top && isOpen ? `${Math.min(cap || maxWidth, maxWidth, 390)}px` : undefined,
        maxWidth: top ? (cap ? `${Math.min(cap, maxWidth)}px` : `${maxWidth}px`) : row ? 'calc(100vw - 2 * var(--gutter))' : '100%',
    };
}

/**
 * **El alto en reposo que la isla publica** (`--island-h`, zip del 27-09, §4.15): su fila y, si la línea va encima de
 * ella (abajo, en móvil), desde la línea; más el aire de su medidor y su borde (7 + 1 arriba y 7 + 1 abajo: el alto de la
 * caja entera). La cabecera mide con él. Las
 * cookies y los avisos son de paso y no cuentan. `0` = no hay nada que publicar (sin fila, o una medida absurda).
 */
export function altoEnReposo({ fila, linea = null, row }) {
    if (! fila) return 0;
    const primero = ! row && linea ? linea.top : fila.top;
    const h = Math.round(fila.bottom - primero + 16);

    return h >= 40 ? h : 0;
}

/**
 * Cuándo publica: en reposo. Abierta, en la compra o con el pago fallido se queda el último (ya no hay compacta, Z6a); y
 * con el aviso a isla entera (Z6b·2), que esconde la fila: es de paso, como las cookies. El diseño no lo dice y no lo
 * publicaba porque la fila escondida mide 16 (< 40); aquí, por su nombre.
 */
export function publicaAlto({ isOpen, inCheckout, extra, aviso = false }) {
    return ! isOpen && ! inCheckout && ! extra && ! aviso;
}

/** El `data-size` de la raíz, de más a menos: la compra manda sobre un panel, y un panel sobre el aviso. */
export function tamano({ inCheckout, isOpen, notice }) {
    return inCheckout ? 'compra' : isOpen ? 'abierta' : notice ? 'aviso' : 'reposo';
}
