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
    /**
     * «Revisa tu correo», tras un alta que NO abrió sesión (Fase 4 · paso 4.4b·1).
     *
     * ⚠️ No confundir con `VERIFYING` (11), que es verificar un PAGO. Este paso faltaba en la máquina
     * y su ausencia estaba anotada en `paused.js`: es el único paso del embudo que el aviso de pausa
     * **no** tapa, porque una verificación de correo en curso debe poder completarse.
     */
    VERIFY_EMAIL: 7,
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
 *
 * ⚠️ **El paso de PAGO es modo `cart`, no `result`**, y aquí decía `result` hasta 4.3·1. Se midió
 * contra `Purchase::stepModeMap()`, que es la fuente del otro motor: `8 => 'cart'`. El cajón SPA
 * recolocaba el pie y el bloque de cuenta de otra forma que Livewire justo en la pantalla de pagar, y
 * **ningún test de árbol podía verlo** porque la clase se pinta FUERA del cajón. La paridad de esta
 * función la fija ahora `SidebarProgressParityTest`, recorriendo el mapa entero del servidor.
 */
export function modeOf(step) {
    if (step === STEPS.CATALOG) return 'catalog';
    if (step === STEPS.DATE || step === STEPS.TIME) return 'booking';
    if (step === STEPS.CART || step === STEPS.IDENTIFY || step === STEPS.PAY) return 'cart';

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
/**
 * **Los pasos que forman el EMBUDO DE COMPRA.** Es la lista cerrada que `FUNNEL_TRANSITIONS` gobierna.
 *
 * ⚠️⚠️ **Se exporta para que se pueda VIGILAR que sigue cerrada** (`machine.test.js`), y el motivo es
 * una decisión de producto ya tomada: `DECISIONES #66` dice que el cajón será también el ÁREA DE
 * CLIENTE —sus pedidos, sus reservas, sus ajustes—, y **un área de cliente NO es un embudo**: sus
 * pantallas se navegan libremente, sin orden ni vuelta atrás obligatoria.
 *
 * ▶ **Colgar esas pantallas de este grafo sería el error caro**: mezclaría dos modelos de navegación
 * en un solo mapa, y a partir de ahí cada cambio de cualquiera de los dos tendría que razonar sobre
 * el otro. Cuando lleguen, van en su propia SECCIÓN con su propio modelo. Este mapa se queda con lo
 * que ya tiene.
 */
export const FUNNEL_STEPS = [
    STEPS.CATALOG, STEPS.DATE, STEPS.TIME, STEPS.CART, STEPS.IDENTIFY,
    STEPS.CONFIRMED, STEPS.VERIFY_EMAIL, STEPS.PAY, STEPS.REDIRECTING, STEPS.DECLINED, STEPS.VERIFYING,
];

/**
 * Las transiciones permitidas **DENTRO DEL EMBUDO**.
 *
 * ⚠️ Antes se llamaba `TRANSITIONS` a secas, y el nombre importaba: leído así parecía «las
 * transiciones del cajón», que es lo que invita a añadir aquí la primera pantalla de cuenta. Es del
 * EMBUDO, y solo suyo.
 */
export const FUNNEL_TRANSITIONS = {
    [STEPS.CATALOG]: [STEPS.DATE],
    [STEPS.DATE]: [STEPS.CATALOG, STEPS.TIME],
    [STEPS.TIME]: [STEPS.DATE, STEPS.CART],
    [STEPS.CART]: [STEPS.CATALOG, STEPS.IDENTIFY, STEPS.PAY],
    // Desde la identificación se sale por tres puertas: atrás al carrito, adelante al pago (alta o
    // login que abrieron sesión) o a «revisa tu correo» **cuando el alta no identificó a nadie** — el
    // caso del señuelo, que el servidor y la web tratan igual que un alta buena para no delatarlo.
    [STEPS.IDENTIFY]: [STEPS.CART, STEPS.PAY, STEPS.VERIFY_EMAIL],
    // De «revisa tu correo» no se sale dentro del cajón: el Blade tampoco ofrece salida.
    [STEPS.VERIFY_EMAIL]: [],
    [STEPS.PAY]: [STEPS.CART, STEPS.REDIRECTING],
    [STEPS.REDIRECTING]: [],
    [STEPS.CONFIRMED]: [STEPS.CATALOG],
    /**
     * ⚠️ **Las tres salidas del pago denegado, MEDIDAS contra `Purchase::retryPayment()`** en 4.6·2.
     * Hasta entonces esta lista decía `[CATALOG, PAY]` y las dos mitades estaban mal:
     *  · el reintento **no vuelve a la pantalla de pago**, va DIRECTO a la pasarela (`$this->step = 9`)
     *    — reabre el cobro sobre el pedido que ya existe, así que no hay nada que volver a confirmar;
     *  · y sí hay una salida a IDENTIFICARSE: `$this->step = $user ? 1 : 5`, para la sesión que se
     *    perdió entre la vuelta de la pasarela y el clic. Un cliente de API se entera por el 401.
     * Era una suposición de 4.1, cuando el paso no estaba transcrito y nadie podía medirlo.
     */
    [STEPS.DECLINED]: [STEPS.CATALOG, STEPS.REDIRECTING, STEPS.IDENTIFY],
    [STEPS.VERIFYING]: [STEPS.CATALOG, STEPS.CONFIRMED, STEPS.DECLINED],
};

export function canGo(from, to) {
    return (FUNNEL_TRANSITIONS[from] ?? []).includes(to);
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
 * ¿El cajón está en un paso al que se ha llegado **desde FUERA**? (Fase 4 · paso 4.6·1)
 *
 * ⚠️ **No es cosmético y decide una precedencia**: al montar, el cajón restaura su cesta y —como la
 * web— abre en el carrito si hay algo. Pero `Purchase::mount()` coloca ese paso 4 **antes** de mirar
 * el desenlace de la pasarela, así que el desenlace lo pisa. En la SPA el orden natural es el
 * contrario, y la cesta vive en `localStorage`: **otra pestaña puede haberla llenado mientras se
 * pagaba en ésta**, y quien vuelve de pagar aterrizaría en un carrito en vez de en su reserva.
 *
 * Vive aquí y no dentro del componente por lo de siempre (`CE-6`): en un `.vue` esta precedencia no
 * tendría ninguna red, y es exactamente la clase de fallo que ningún árbol enseña.
 */
export function isOutcome(step) {
    return step === STEPS.CONFIRMED || step === STEPS.DECLINED || step === STEPS.VERIFYING;
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
