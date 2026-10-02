/**
 * **Las casillas del código, sin estado** (`CampoCodigo.vue`, el `CodeInput` del zip (6); Z6g·1 de
 * `specs/isla-y-landing-nueva.md` §4.27): cómo se pinta cada una según si lleva el cursor, si tiene cifra y si hay un «no».
 * Solo tokens de control, como el diseño: sirve igual sobre claro y sobre tinta. Con su `node --test`.
 */

/**
 * Una casilla. El «no» manda sobre todo (las seis en rojo); si no, la del cursor lleva el anillo, y la del cursor y las
 * escritas, el borde fuerte.
 *
 * @param {{activa: boolean, llena: boolean, error: boolean}} e
 */
export function estiloCasilla({ activa, llena, error }) {
    return {
        flex: '1 1 0', minWidth: 0, maxWidth: '56px', height: '56px', boxSizing: 'border-box',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        background: 'var(--control-bg)', borderRadius: 'var(--r-md)',
        border: `1px solid ${error ? 'var(--border-danger)' : activa || llena ? 'var(--control-border-strong)' : 'var(--control-border)'}`,
        boxShadow: activa ? 'var(--ring)' : 'none', transition: 'var(--t-hover)',
        fontFamily: 'var(--font-mono)', fontSize: '1.5rem', fontWeight: 500, lineHeight: 1, fontVariantNumeric: 'tabular-nums',
        color: 'var(--control-fg)',
    };
}
