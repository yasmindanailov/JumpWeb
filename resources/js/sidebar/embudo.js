/**
 * **LOS PASOS DEL EMBUDO QUE NADIE CONTABA** (`docs/specs/analitica.md` §4.2; el owner, 03-10: «debe ser profesional y robusto»).
 *
 * El cuadro (`FunnelReport::STEPS`) cuenta por sesión «eligió fecha» (`date_chosen`), «añadió» (`line_added`), «se
 * identificó» (`identified`) e «inició el pago» (`pay_started`), y NADIE los emitía: ni la isla ni el cajón (medido el 03-10:
 * en local, 2.916 compras abiertas y 79 pagadas contra cero de los cuatro; en staging, lo mismo con la isla). Los cuenta el
 * MOTOR al cambiar de paso —por ahí pasan las dos carcasas, la isla y el cajón—, con lo que la cesta sabe en ese momento:
 *   · al CARRITO (4): por cada línea, su producto, su fecha, su hora y su cantidad (`product_chosen`, `date_chosen`,
 *     `time_chosen`, `line_added`): llegar al carrito es tener la línea entera;
 *   · a IDENTIFICARSE (5): `identify_started`; a VERIFICAR el correo (7): `email_verification_pending`;
 *   · a PAGAR (8): `identified` —no se llega sin titular—, `checkout` si acaba de identificarse y `session` si ya lo estaba;
 *   · a la PASARELA (9): `pay_started` con el último total que presupuestó el servidor (la cesta se vacía, con su
 *     presupuesto, justo antes de saltar: por eso el motor le va diciendo cada total con {@see precio}).
 * El cuadro cuenta SESIONES que llegan a cada paso: un paso repetido (ir y volver) no infla nada. Ningún dato personal: ids
 * de producto, fechas, cantidades e importes. Módulo plano, todo por parámetro (`CE-6`, `embudo.test.js`).
 */
import { STEPS } from './machine.js';

/**
 * @param {{track: (name: string, props: object) => void, lineas?: () => Array<{product_id?: number|string|null, date?: string, time?: string, quantity?: number}>}} deps
 * @returns {{paso: (from: number, to: number) => void, precio: (cents: number) => void}}
 */
export function createFunnelFacts({ track, lineas = () => [] }) {
    let total = 0;

    return {
        /** El último total presupuestado; uno vacío (la cesta vaciada) no borra el anterior. */
        precio(cents) {
            const n = Number(cents);

            if (Number.isFinite(n) && n > 0) total = Math.round(n);
        },

        /** Un cambio de paso de la máquina, ya filtrado (`from !== to`). */
        paso(from, to) {
            if (to === STEPS.CART) {
                for (const linea of lineas() ?? []) {
                    const product = linea?.product_id ?? null;

                    if (product === null || product === '') continue;
                    track('product_chosen', { product });
                    if (linea.date) track('date_chosen', { product, date: linea.date });
                    if (linea.time) track('time_chosen', { product });
                    track('line_added', { product, qty: Math.max(1, Number(linea.quantity) || 1) });
                }
            } else if (to === STEPS.IDENTIFY) {
                track('identify_started', { method: 'checkout' });
            } else if (to === STEPS.VERIFY_EMAIL) {
                track('email_verification_pending', {});
            } else if (to === STEPS.PAY) {
                track('identified', { method: from === STEPS.IDENTIFY || from === STEPS.VERIFY_EMAIL ? 'checkout' : 'session' });
            } else if (to === STEPS.REDIRECTING) {
                track('pay_started', { amount_cents: total });
            }
        },
    };
}
