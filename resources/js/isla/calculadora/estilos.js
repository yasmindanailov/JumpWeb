/**
 * Los estilos de la calculadora de la página (`paginas/entradas/pieza-3.jsx`, su `<style>` de `.p3-*`), como objetos
 * para el `style` de Vue: las piezas de la isla llevan el estilo en línea, como el diseño (`CE-6`: fuera del componente).
 */

/** Las pistas bajo cada pregunta (`.p3-hint`). */
export const PISTA = {
    margin: 0, maxWidth: '62ch', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-body)', textWrap: 'pretty',
};

/** El eco de lo elegido —«Tarifa especial: 8 € por niño», «De 17:00 a 18:00»— (`.p3-eco`). */
export const ECO = {
    display: 'inline-flex', alignItems: 'center', gap: '9px', justifySelf: 'start', maxWidth: '100%', padding: '9px 14px', background: 'var(--bg-subtle)',
    borderRadius: 'var(--r-pill)', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-strong)',
};

/** Una línea del resumen con su icono (`.p3-linea`): la cuenta y el descargo, dichos antes del botón. */
export const LINEA = {
    display: 'flex', alignItems: 'flex-start', gap: '9px', margin: 0, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5,
    color: 'var(--text-body)', textWrap: 'pretty',
};

/**
 * Las cifras en columna (`.pj-num` de `tokens/base.css` del diseño): la calculadora las lleva en línea para no depender
 * de una clase de la página que la aloja.
 */
export const CIFRA = { fontVariantNumeric: 'tabular-nums', fontFeatureSettings: '"tnum" 1' };

/** Su icono (`.p3-linea > span:first-child`). */
export const LINEA_ICONO = { display: 'inline-flex', flexShrink: 0, paddingTop: '2px', color: 'var(--aqua-600)' };
