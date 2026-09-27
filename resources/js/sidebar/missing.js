import { missingMonths } from './calendar.js';

/**
 * **La demanda sin hueco** (`docs/specs/analitica-para-decidir.md` §4.8.ter, la T2; `DECISIONES #758`): quien abre un
 * producto y no encuentra fecha. Con la oferta de días ya en la mano, cada mes sin un solo día desde el en curso
 * (`calendar.js::missingMonths()`) sale como `availability_missing {product, month}` —una vez por producto y mes en la
 * vida del reportero, que es una visita: abrir y cerrar el mismo producto no la multiplica—.
 *
 * ⚠️ El tracker se INYECTA (`JumpWeb.track` en el cajón), como en los experimentos: sin él —el render del servidor, una
 * página sin analítica— no pasa nada. Es una medida; qué días se venden lo sigue diciendo el servidor (`AFORO-02`).
 *
 * @param {((name: string, props: object) => void)|undefined} track
 * @returns {(productId: number|string, offeredDates: Array<{date: string}>, now?: Date) => string[]}  devuelve los meses que emitió
 */
export function createMissingReporter(track) {
    const seen = new Set();

    return (productId, offeredDates, now = new Date()) => {
        const emitted = [];

        for (const month of missingMonths(offeredDates, now)) {
            const key = `${productId}|${month}`;
            if (seen.has(key)) continue;
            seen.add(key);
            emitted.push(month);
            track?.('availability_missing', { product: String(productId), month });
        }

        return emitted;
    };
}
