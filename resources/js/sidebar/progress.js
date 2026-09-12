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
import { STEPS } from './machine.js';

/**
 * **LAS CINCO FASES, y una por PANTALLA** (`#555`, `[DECIDIDO owner]`).
 *
 * El camino del embudo tras el catálogo son cinco pantallas —día, hora, cesta, quién eres, pagar— y la
 * banda tiene una fase para cada una. ⚠️ **El 1:1 es lo que hace honesto el contador**: hasta `#555` la
 * tercera fase («Extras») se encendía DENTRO de la pantalla de la hora, así que un mismo «Paso 3 de N»
 * salía en dos pantallas distintas — y una banda que existe para decir cuánto queda no puede repetir
 * su número.
 *
 * ⚠️ **El catálogo NO es una fase**: ahí todavía no se ha empezado a reservar nada, y contarlo haría
 * que el cliente empezara el embudo en «Paso 1 de 6» con la cesta vacía.
 *
 * ❗❗ **El destino del «Volver» y su rótulo salen de LA MISMA FILA, y eso no es orden: es la única
 * forma de que no se separen.** Con el rótulo aquí y el destino en el embudo, cambiar uno sin el otro
 * deja un botón que dice «Volver al carrito» y lleva a otro sitio — **sin que nada falle**.
 *
 * `clear` es lo que hay que DESHACER al volver, y también pertenece al destino: volver de la hora con
 * la hora puesta dejaría el paso anterior mostrando un progreso que ya no aplica, y volver de la cesta
 * al catálogo sin limpiar lo abriría con el producto anterior elegido.
 */
const FASES = [
    { step: STEPS.DATE, label: 'phase_date', back: 'back', backTo: STEPS.CATALOG, clear: null },
    { step: STEPS.TIME, label: 'phase_time', back: 'back', backTo: STEPS.DATE, clear: 'time' },
    { step: STEPS.CART, label: 'phase_cart', back: 'back', backTo: STEPS.CATALOG, clear: 'selection' },
    { step: STEPS.IDENTIFY, label: 'phase_identify', back: 'back_to_cart', backTo: STEPS.CART, clear: null },
    { step: STEPS.PAY, label: 'phase_pay', back: 'back_to_cart', backTo: STEPS.CART, clear: null },
];

/**
 * A dónde vuelve el «Volver» desde este paso, y qué hay que deshacer por el camino.
 *
 * Devuelve `null` en los pasos sin banda — que son los que no tienen «Volver», así que preguntar por
 * ellos es un error de quien llama y no un caso a contemplar.
 *
 * @param {number} step
 * @returns {{to: number, clear: string|null}|null}
 */
export function backPlan(step) {
    const fase = FASES.find((f) => f.step === step);

    return fase ? { to: fase.backTo, clear: fase.clear } : null;
}

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
 * La banda vive en las **cinco pantallas del camino**, del día al pago (`#555`). Antes solo existía en
 * los pasos 2 y 3, así que desde la cesta hasta pagar —justo el tramo donde se abandona una compra— el
 * cliente no tenía ni idea de cuánto le quedaba, ni por dónde volver dentro de la propia banda.
 *
 * ⚠️ **Los desenlaces no la llevan** (confirmado, denegado, verificando, revisa-tu-correo, redirigiendo):
 * de tres de ellos no se sale, y contar una fase para una pantalla sin vuelta atrás prometería un
 * camino que no existe.
 *
 * ⚠️ **El CONTEXTO solo en las dos primeras**, y no es un olvido: dice «producto · día · hora» de UNA
 * línea, y de la cesta en adelante puede haber varias — una cesta con dos reservas rotularía la del
 * producto que quedó seleccionado, que no es «la reserva» de nadie. El artboard del paso 08 también la
 * dibuja sin contexto.
 *
 * @param {{step: number, productName: string, date: string|null, time: string|null, messages: object, locale: string}} state
 * @returns {{active: number, total: number, steps: Array<{label: string, state: string}>, context: string, backLabel: string}|null}
 */
export function buildProgress({ step, productName = '', date = null, time = null, messages = {}, locale = 'es' }) {
    const actual = FASES.findIndex((f) => f.step === step);

    if (actual === -1) {
        return null;
    }

    const hasTime = time !== null && time !== '';

    // El contexto se va llenando conforme el cliente elige. `filter(Boolean)` descarta los tramos
    // vacíos igual que el `array_filter` del servidor — sin él quedarían separadores sueltos.
    const context = step === STEPS.DATE || step === STEPS.TIME
        ? [
            productName,
            date ? ucfirst(shortDate(date, locale)) : null,
            hasTime ? String(time).slice(0, 5) : null,
        ].filter(Boolean).join(' · ')
        : '';

    return {
        active: actual + 1,
        total: FASES.length,
        steps: FASES.map((fase, i) => ({
            label: t(messages, fase.label),
            state: i < actual ? 'done' : (i === actual ? 'current' : 'todo'),
        })),
        context,
        backLabel: t(messages, FASES[actual].back),
    };
}
