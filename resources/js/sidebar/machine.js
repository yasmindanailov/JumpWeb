/**
 * La MÁQUINA DE ESTADOS del cajón de reservas (Fase 4 · paso 4.1,
 * `docs/specs/sidebar-spa.md` §4.8).
 *
 * **Sin un solo `import` de Vue, y es la decisión que la hace verificable.** El sidebar Livewire
 * tiene hoy su máquina cubierta por seis ficheros de test; transcribirla a componentes sin red sería
 * una pérdida neta de cobertura, y montar un runner de componentes es una dependencia y un modo de
 * fallo más. Como módulo plano se prueba con el runner integrado de Node (`node --test`), que ya
 * está disponible porque Vite 8 exige Node 20+.
 *
 * Los componentes quedan sin test unitario **a propósito**: son marcado, y de eso responde el diff
 * de árbol de `SidebarDomContractTest`.
 *
 * ⚠️ **Aquí no se decide nada de negocio.** Qué se vende, qué cabe, cuánto cuesta y si una línea
 * entra en la cesta lo dicen los endpoints de `/api/v1` (`CE-4`). Esto solo sabe **en qué pantalla
 * está el cliente y a cuál puede ir**, que es justo lo que ningún endpoint puede saber.
 */

/**
 * Los pasos del cajón, con los MISMOS números que el sidebar Livewire.
 *
 * Se conservan los números en vez de renombrarlos a estados con nombre porque durante la
 * convivencia los dos motores tienen que ser comparables paso a paso, y porque `$wire.step` es hoy
 * el vocabulario de la paridad. Renombrarlos ahora obligaría a mantener una tabla de equivalencias
 * en la cabeza durante toda la transcripción.
 */
export const STEPS = {
    CATALOG: 1,
    DATE: 2,
    TIME: 3,
    CART: 4,
    IDENTIFY: 5,
    CONFIRMED: 6,
    PAY: 8,
    REDIRECTING: 9,
    DECLINED: 10,
    VERIFYING: 11,
};

/**
 * El «modo» que el cajón PUBLICA hacia fuera, derivado del paso.
 *
 * ⚠️ No es cosmético y no vive dentro del cajón: `layout.blade.php` pinta `is-{modo}` en el panel
 * —minimiza el bloque de cuenta y recoloca el pie— y `account-context` bloquea sus botones de login
 * con `identifying`. Un motor que no publique estas dos señales deja el panel en `is-catalog` para
 * siempre y los botones de invitado activos en plena identificación: **dos regresiones silenciosas**,
 * porque ninguna de las dos clases aparece en el marcado del cajón.
 */
export function modeOf(step) {
    if (step === STEPS.CATALOG) return 'catalog';
    if (step === STEPS.DATE || step === STEPS.TIME) return 'booking';
    if (step === STEPS.CART || step === STEPS.IDENTIFY) return 'cart';

    return 'result';
}

/** `true` SOLO en el paso de identificación: es lo que bloquea los botones de login de fuera. */
export function isIdentifying(step) {
    return step === STEPS.IDENTIFY;
}

/**
 * Las transiciones permitidas. Un mapa explícito y no una cadena de `if`, para que «¿se puede ir de
 * aquí a allí?» sea un dato que un test puede recorrer entero en vez de una conducta que hay que
 * adivinar caso a caso.
 *
 * Lo que NO está aquí es tan informativo como lo que está: de `CONFIRMED` solo se sale volviendo al
 * catálogo (empezar otra compra), y de `REDIRECTING` no se sale — el navegador se va a la pasarela.
 */
const TRANSITIONS = {
    [STEPS.CATALOG]: [STEPS.DATE],
    [STEPS.DATE]: [STEPS.CATALOG, STEPS.TIME],
    [STEPS.TIME]: [STEPS.DATE, STEPS.CART],
    [STEPS.CART]: [STEPS.CATALOG, STEPS.IDENTIFY, STEPS.PAY],
    [STEPS.IDENTIFY]: [STEPS.CART, STEPS.PAY],
    [STEPS.PAY]: [STEPS.CART, STEPS.REDIRECTING],
    [STEPS.REDIRECTING]: [],
    [STEPS.CONFIRMED]: [STEPS.CATALOG],
    [STEPS.DECLINED]: [STEPS.CATALOG, STEPS.PAY],
    [STEPS.VERIFYING]: [STEPS.CATALOG, STEPS.CONFIRMED, STEPS.DECLINED],
};

export function canGo(from, to) {
    return (TRANSITIONS[from] ?? []).includes(to);
}

/**
 * El desenlace del pago que el servidor dejó en la sesión → el paso en el que abre el cajón.
 *
 * Los tres valores los escribe `Http\Sidebar\SidebarEntry` (Fase 4 · paso 4.0a), que es su único
 * dueño. `verifying` existe porque hay terminales que vuelven SIN los datos firmados: no se puede
 * confirmar en este lado y hay que sondear.
 */
export function stepForOutcome(outcome) {
    if (outcome === 'confirmed') return STEPS.CONFIRMED;
    if (outcome === 'failed') return STEPS.DECLINED;
    if (outcome === 'verifying') return STEPS.VERIFYING;

    return null;
}

/**
 * Crea la máquina. Estado plano y transiciones explícitas; sin reactividad, que la pone el store.
 *
 * @param {{step?: number, onChange?: (step: number) => void}} options
 */
export function createMachine({ step = STEPS.CATALOG, onChange = null } = {}) {
    let current = step;

    /** Intención de entrada pendiente (`{type:'packs'}` · `{type:'zone', slug}`), si llegó antes de estar listos. */
    let pendingIntent = null;

    const notify = () => {
        if (onChange) onChange(current);
    };

    return {
        get step() {
            return current;
        },

        get mode() {
            return modeOf(current);
        },

        get identifying() {
            return isIdentifying(current);
        },

        /**
         * Va a un paso **si la transición existe**. Devuelve si se movió.
         *
         * Rechazar en silencio y no lanzar es deliberado: el llamante es la interfaz, y una
         * transición imposible casi siempre viene de un doble clic o de un evento que llegó tarde
         * —no de un error de programa—. Lo que no puede pasar es que el cajón acabe en un paso al
         * que no se llega desde donde estaba.
         */
        go(to) {
            if (! canGo(current, to)) return false;

            current = to;
            notify();

            return true;
        },

        /**
         * Salta a un paso SIN comprobar la transición. Es para lo que viene de FUERA del cajón: la
         * vuelta de la pasarela, que aterriza en el paso que diga el servidor sin haber pasado por
         * los intermedios porque el navegador se fue a otro dominio y volvió.
         */
        enter(to) {
            current = to;
            notify();
        },

        /** El desenlace del pago, si lo hay, decide dónde abre el cajón. */
        enterOutcome(outcome) {
            const target = stepForOutcome(outcome);
            if (target === null) return false;

            this.enter(target);

            return true;
        },

        /** Empezar otra compra: vuelve al catálogo desde cualquier desenlace. */
        restart() {
            this.enter(STEPS.CATALOG);
        },

        /**
         * Guarda una intención de entrada para aplicarla cuando el cajón esté listo.
         *
         * La landing puede pedir «ábreme los packs» antes de que el motor haya montado —el entry se
         * carga con `import()` en la primera apertura—, y esa petición no se puede perder: es la
         * regresión que la costura de 4.0a existe para evitar.
         */
        queueIntent(intent) {
            pendingIntent = intent ?? null;
        },

        /** Consume la intención pendiente (una sola vez) y la devuelve, o `null`. */
        takeIntent() {
            const intent = pendingIntent;
            pendingIntent = null;

            return intent;
        },
    };
}
