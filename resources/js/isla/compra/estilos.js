/**
 * La escala de texto de un paso de la compra (`paginas/compra/ui.jsx` del diseño, su `PJC_UI`): cuatro tamaños y
 * ninguno más —título (26px, display), pregunta (16px, negrita), cuerpo (15px) y pista (13px)—; el espaciado va
 * de 8 en 8. Todo con tokens: sobre tinta cambia solo. `hueco` es el recuadro de lo que aún no tiene contenido.
 *
 * ⚠️ `width: '100%'` en el paso, con su tope de 520: dentro de una REJILLA (Mi cuenta envuelve «Entra»), una casilla con
 * `margin: 0 auto` se encoge a su contenido, y «Entra» salía en una columna de ~260px con los campos estrechos mientras
 * «Crea tu cuenta», fuera de rejilla, ocupaba la columna entera (el owner, 26-09). Fuera de rejilla no cambia nada.
 */
export const PASO = {
    paso: { display: 'grid', gap: '24px', width: '100%', maxWidth: '520px', margin: '0 auto' },
    titulo: { margin: 0, outline: 'none', fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '1.625rem', lineHeight: 1.05, letterSpacing: '-0.02em', color: 'var(--text-strong)', textWrap: 'balance' },
    pregunta: { margin: 0, fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body)', lineHeight: 1.3, color: 'var(--text-strong)' },
    cuerpo: { margin: 0, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-body)', textWrap: 'pretty' },
    pista: { margin: 0, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.5, color: 'var(--text-muted)', textWrap: 'pretty' },
    hueco: { padding: '12px 14px', border: '1px dashed var(--control-border)', borderRadius: 'var(--r-sm)', font: 'var(--type-mono)', color: 'var(--text-muted)' },
};

/** La entrada de lo que llega en Listo: sube con muelle, escalonado (`pj-outcome-rise`). */
export const subir = (ms) => ({ animation: `isla-outcome-rise var(--dur-island) var(--ease-spring) ${ms}ms both` });
