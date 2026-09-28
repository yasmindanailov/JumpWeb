/**
 * Los estilos de `FilaMejora.vue` (`marketing/UpgradeRow.jsx` del diseño, en su tono claro), fuera del componente
 * (`CE-6`): la caja según esté marcada, apagada o con el ratón encima, y su casilla. ⚠️ Con los ROLES de los controles
 * (`--control-*`, los de `TarjetasOpcion` y `CasillaSistema`) y no con los primitivos del diseño (`--ink-*`, `--snow`):
 * son de PlayJump, y en otra instalación la fila no cambiaría con su tema (`IslaPaletaNeutraTest`).
 */

/** La caja: marcada, con el borde fuerte de 2px y su fondo; apagada, a media opacidad y sin sombra. */
export function cajaMejora({ marcada, apagada, encima }) {
    return {
        position: 'relative', display: 'flex', alignItems: 'flex-start', gap: '12px', padding: '15px 16px',
        background: marcada ? 'var(--control-bg-hover)' : 'var(--control-bg)',
        border: marcada ? '2px solid var(--control-border-strong)' : '1px solid var(--control-border)', margin: marcada ? 0 : '1px',
        borderRadius: 'var(--r-lg)', boxShadow: encima && ! marcada && ! apagada ? 'var(--shadow-sm)' : 'none',
        cursor: apagada ? 'not-allowed' : 'pointer', opacity: apagada ? 0.5 : 1, transition: 'var(--t-card)',
    };
}

/** La casilla: tinta llena con el visto al marcarla; si no, el hueco con su icono. */
export function casillaMejora(marcada) {
    return {
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '24px', height: '24px', flexShrink: 0, marginTop: '1px',
        borderRadius: 'var(--r-xs)', background: marcada ? 'var(--control-selected-bg)' : 'transparent',
        boxShadow: marcada ? 'none' : 'inset 0 0 0 2px var(--control-border)', color: marcada ? 'var(--control-selected-fg)' : 'var(--text-muted)', transition: 'var(--t-hover)',
    };
}

export const TITULO_MEJORA = { fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body)', color: 'var(--text-strong)', whiteSpace: 'nowrap' };

export const CIFRA_MEJORA = {
    flexShrink: 1, minWidth: 0, fontFamily: 'var(--font-mono)', fontWeight: 'var(--fw-medium)', fontSize: 'var(--fs-body-sm)', color: 'var(--text-strong)',
    fontVariantNumeric: 'tabular-nums', fontFeatureSettings: '"tnum" 1',
};

export const TEXTO_MEJORA = { fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.45, color: 'var(--text-body)' };

/** La nota: la de encendida en verde con su cartera; la de apagada (por qué no se puede), gris con el reloj. */
export const NOTA_MEJORA = (encendida) => ({
    display: 'inline-flex', alignItems: 'center', gap: '6px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)',
    fontWeight: encendida ? 'var(--fw-semibold)' : 'normal', color: encendida ? 'var(--text-positive)' : 'var(--text-muted)',
});
