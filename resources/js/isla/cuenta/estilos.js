/**
 * La escala de Mi cuenta dentro de la isla (`paginas/mi-cuenta/bloques.jsx` del diseño, su `PMC_UI`): título 26px, el
 * título de cada bloque como una pregunta (16px), cuerpo 15px y pista 13px. Todo con tokens, sobre tinta. Es la de los
 * pasos de la compra (`compra/estilos.js`) salvo el título, que aquí no se equilibra (el diseño no lo hace).
 */
export const CUENTA = {
    h1: { margin: 0, outline: 'none', fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '1.625rem', lineHeight: 1.05, letterSpacing: '-0.02em', color: 'var(--text-strong)' },
    h2: { margin: 0, fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body)', lineHeight: 1.3, color: 'var(--text-strong)' },
    cuerpo: { margin: 0, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-body)', textWrap: 'pretty' },
    pista: { margin: 0, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.5, color: 'var(--text-muted)', textWrap: 'pretty' },
    bloque: { display: 'grid', gap: '12px', scrollMarginTop: '16px' },
    // Mi cuenta ocupa la columna de la capa: 520px, centrada.
    columna: { display: 'grid', gap: '28px', maxWidth: '520px', margin: '0 auto' },
    oculto: { position: 'absolute', width: '1px', height: '1px', overflow: 'hidden', clip: 'rect(0 0 0 0)' },
};
