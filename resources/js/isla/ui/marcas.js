/**
 * Las formas de pago, en una fila DISCRETA (`#786`, el owner: «que no robe la atención, pero lo suficiente para que
 * convierta»): los logotipos oficiales sin chapa ni borde, pequeños y a la misma altura ÓPTICA —un logotipo ancho (Bizum,
 * Visa) y uno compacto (los círculos de Mastercard) se ven iguales con alturas distintas—. Sobre tinta va la versión
 * oficial para fondo oscuro, no una chapa blanca. ⚠️ Sin opacidad ni filtros: son marcas ajenas y se pintan tal cual.
 * ⚠️ En su módulo y no en `estilos.js`: ése lo comparte la isla de CADA página, que no pinta marcas (medido: +0,37 KiB).
 */
const ALTO = { bizum: 15, visa: 12, mastercard: 20 };

export const MARCA = {
    fila: { display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '8px 16px', margin: 0, padding: 0, listStyle: 'none' },
    // Una marca que el producto aún no conoce, a una altura media.
    logo: (id) => ({ display: 'block', height: `${ALTO[id] ?? 14}px`, width: 'auto' }),
};
