/**
 * El TROZO de los pasos de la compra de la isla que no son la pantalla 0 (T3e·3): una sola importación diferida para
 * los dos componentes que lo pintan —el paso y lo que va junto a su acción—, así que viajan juntos.
 */
export { default as PasosCompra } from './PasosCompra.vue';
export { default as JuntoCompra } from './JuntoCompra.vue';
// Los datos de la reserva de un pack (T6c·3, `#839`): van con el campo del sistema, que ya viaja aquí (`datos-reserva.js`).
export { default as DatosReserva } from './DatosReserva.vue';
// Entrar con un código al correo (A3 del acceso con código, `#849`): la puerta, entrar y sus «no», fuera del trozo de la
// compra, que lo pide con `import()` de éste (ya descargado al montarse).
export * as acceso from './acceso.js';
