/**
 * La BANDA DE PROGRESO del flujo de reserva, compuesta en el cliente (Fase 4 · paso 4.3·1).
 *
 * ⚠️ **Existe porque el motor SPA no la pintaba.** `BookingProgress.vue` está escrito desde 4.2 y el
 * gate lo compara en verde, pero en ejecución real `Sidebar.vue` le pasaba `progress: null` y
 * `TimeStep` ni lo montaba: el cajón SPA vivo iba sin botón «Volver» y sin contador de fases. Es el
 * límite del diff de árbol dicho una vez más —**alimenta a Vue con el view-model del SERVIDOR**—, y
 * por eso esto se compone en un módulo plano con su propia paridad de datos
 * (`SidebarProgressParityTest`), no dentro de un componente.
 *
 * Lo que se compone aquí es PRESENTACIÓN: en qué fase está el cliente y qué texto la acompaña. Qué se
 * vende, qué días hay y cuánto cuesta lo siguen diciendo los endpoints (`CE-4`).
 *
 * ⚠️ **La fecha del contexto es el único punto donde los dos motores NO comparten la fuente del
 * texto** (§4.5). El servidor la compone con Carbon (`isoFormat('ddd D MMM')`) y aquí con `Intl`.
 * Lo importante es que **el patrón lo fijamos nosotros y no un preajuste de `Intl`**: eso es lo que
 * evita el fallo grande, porque `{weekday, day, month}` como preajuste INVIERTE día y mes en inglés
 * («Sat, Sep 5» frente a «Sat 5 Sep»). Medido el 2026-08-14 con el patrón fijado:
 *
 *   | idioma | servidor (Carbon) | cliente (Intl) | |
 *   |---|---|---|---|
 *   | en | `Sat 5 Sep`    | `Sat 5 Sep`    | **idéntico** |
 *   | fr | `Sam. 5 sept.` | `Sam. 5 sept.` | **idéntico** |
 *   | es | `Sáb. 5 sep.`  | `Sáb 5 sept`   | difiere: ICU no abrevia con punto y usa `sept` |
 *
 * Es decir: la divergencia que §4.5 declaraba para los tres idiomas queda **acotada al español y a
 * la ortografía de la abreviatura**. Y por eso los puntos de `Intl` NO se recortan, aunque a primera
 * vista lo pidan: recortarlos rompería el francés, que hoy coincide.
 * Queda en `DEUDA.md` con la forma de cerrarlo —que la oferta de días publique su etiqueta ya
 * formateada—, porque cerrarlo es un cambio de contrato y no de transcripción.
 */

import { t } from './i18n.js';

/** Mayúscula inicial, como el `Str::ucfirst` que el servidor aplica al contexto. */
function ucfirst(text) {
    return text === '' ? '' : text.charAt(0).toUpperCase() + text.slice(1);
}

/**
 * `2026-09-05` → `Sáb 5 sept`, con el PATRÓN fijado aquí (día de la semana · día · mes).
 *
 * ⚠️ La fecha se construye con los tres números, **nunca con `new Date('2026-09-05')`**: esa forma se
 * interpreta como medianoche UTC y en un huso al oeste devuelve la víspera. Es el mismo agujero que
 * el calendario ya cerró y que su test de husos vigila.
 */
export function shortDate(ymd, locale) {
    const [year, month, day] = String(ymd).split('-').map(Number);

    if (! year || ! month || ! day) {
        return '';
    }

    const date = new Date(year, month - 1, day);
    const weekday = new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(date);
    const monthName = new Intl.DateTimeFormat(locale, { month: 'short' }).format(date);

    return `${weekday} ${day} ${monthName}`;
}

/**
 * El view-model de la banda, o `null` en los pasos que no la llevan.
 *
 * Espejo de `Purchase::bookingProgress()`: la banda es EXCLUSIVA del modo «booking» (pasos 2 y 3), y
 * dentro del paso 3 el progreso avanza según se haya elegido hora o no.
 *
 * @param {{step: number, isPack: boolean, productName: string, date: string|null, time: string|null, messages: object, locale: string}} state
 * @returns {{active: number, total: number, steps: Array<{label: string, state: string}>, context: string}|null}
 */
export function buildProgress({ step, isPack = false, productName = '', date = null, time = null, messages = {}, locale = 'es' }) {
    if (step !== 2 && step !== 3) {
        return null;
    }

    const hasTime = time !== null && time !== '';

    // La tercera fase cambia de NOMBRE según el tipo de producto: en una entrada son «Extras», en un
    // pack son «Datos» (los del cumpleaños más los extras). No es cosmético: es lo que el cliente
    // espera encontrar al llegar.
    const third = isPack ? t(messages, 'phase_details') : t(messages, 'phase_extras');

    // El contexto se va llenando conforme el cliente elige. `filter(Boolean)` descarta los tramos
    // vacíos igual que el `array_filter` del servidor — sin él quedarían separadores sueltos.
    const context = [
        productName,
        date ? ucfirst(shortDate(date, locale)) : null,
        hasTime ? String(time).slice(0, 5) : null,
    ].filter(Boolean).join(' · ');

    return {
        active: step === 2 ? 1 : (hasTime ? 3 : 2),
        total: 3,
        steps: [
            { label: t(messages, 'phase_date'), state: step === 2 ? 'current' : 'done' },
            { label: t(messages, 'phase_time'), state: step === 2 ? 'todo' : (hasTime ? 'done' : 'current') },
            { label: third, state: (step === 3 && hasTime) ? 'current' : 'todo' },
        ],
        context,
    };
}
