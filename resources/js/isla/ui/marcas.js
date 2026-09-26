/**
 * Las chapas de las formas de pago (`#784`; las «Formas de pago» de `PjcPagar`): todas del MISMO tamaño, para que un
 * logotipo ancho (Bizum, Visa) y uno redondo (Mastercard) se lean como una fila. 76×40 con su aire: la marca más ancha
 * (Visa) queda en ~13px de alto, legible, y el hueco alrededor es su espacio de respeto. ⚠️ El fondo es BLANCO a
 * propósito y no un token: los logotipos oficiales van en su color sobre claro, y sobre tinta no se leería ninguno.
 * ⚠️ En su módulo y no en `estilos.js`: ése lo comparte la isla de CADA página, que no pinta marcas (medido: +0,37 KiB).
 */
export const MARCA = {
    fila: { display: 'flex', flexWrap: 'wrap', gap: '6px', margin: 0, padding: 0, listStyle: 'none' },
    chapa: { display: 'flex', alignItems: 'center', justifyContent: 'center', boxSizing: 'border-box', width: '76px', height: '40px', padding: '8px 10px', background: '#fff', border: '1px solid var(--border-subtle)', borderRadius: '6px' },
    logo: { display: 'block', width: '100%', height: '100%', objectFit: 'contain' },
};
