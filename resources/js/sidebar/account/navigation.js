/**
 * **El modelo de navegación del ÁREA DE CLIENTE** (`docs/specs/area-cliente.md` §3.1 y §4.2).
 *
 * ⚠️⚠️ **Un área de cliente NO es un embudo, y por eso esto no es `machine.js`.** El embudo tiene un
 * grafo cerrado —`FUNNEL_TRANSITIONS`, con guarda— porque sus pasos van en orden y cada uno sabe de
 * cuál viene. Aquí las pantallas se navegan **libremente**: de cualquier zona a cualquier zona, sin
 * orden y sin vuelta atrás obligatoria. Colgar estas pantallas de aquel grafo era el error caro que
 * `DECISIONES #119` dejó por escrito.
 *
 * ▶ Lo que sí necesita un área de cliente es **historia**: por dónde ha pasado el cliente, para que
 * «volver» signifique algo. Eso es una PILA, y es todo lo que hay aquí.
 *
 * Módulo PLANO, sin Vue (`CE-6`): se prueba entero con `node --test`.
 */

/**
 * Las zonas de la tanda 1, y **las dos tienen original que copiar**, que es lo que las hace
 * verificables (`specs/area-cliente.md` §6).
 *
 * ⚠️ **`ORDERS` se llama «Mis reservas» de cara al cliente, no «Mis pedidos»** — medido en `lang/`:
 * `account.orders.title` es literalmente «Mis reservas». El vocabulario del cliente **no distingue**
 * pedido de reserva, y el del código sí. Se conserva el nombre técnico aquí dentro y el del cliente
 * en `lang/`, que es donde vive: renombrar la zona a `reservations` la confundiría con
 * `GET /me/reservations`, que es otra cosa —las PRÓXIMAS— y alimenta el índice.
 *
 * ⚠️ **Se añaden cuando existe su pantalla, no antes.** `PASSWORD` y `SESSIONS` entraron con el paso
 * 6b, `PROFILE` con el 7b y `PRIVACY` con el 8, cada una **después** de que su endpoint existiera.
 * Declarar una zona vacía «para dejarlo preparado» es el error que `#119` evitó a propósito.
 * ▶ Y el modelo no se ha tocado para añadirlas: entran en `ZONES` con su rótulo, que es exactamente
 * lo que §4.2 prometía. Con `PRIVACY` la tanda 2 queda cerrada y son **cinco zonas**.
 */
export const ZONES = {
    /** El índice: quién eres, tu próxima reserva y los accesos. Espeja `/mi-cuenta` + el bloque `.acct`. */
    HOME: 'home',
    /** «Mis reservas»: el historial con su detalle y el reintento. Espeja `/mi-cuenta/pedidos`. */
    ORDERS: 'orders',

    /** Cambiar la contraseña (tanda 2 · paso 6b). Espeja el bloque de `/mi-cuenta`. */
    PASSWORD: 'password',

    /** Cerrar sesión en los demás dispositivos. */
    SESSIONS: 'sessions',

    /** Los datos del titular y el ciclo del cambio de correo (tanda 2 · paso 7b). */
    PROFILE: 'profile',

    /** Los dos derechos RGPD: descargar los datos y borrar la cuenta (tanda 2 · paso 8). */
    PRIVACY: 'privacy',

    // ── Las zonas de INVITADO (`specs/auth-en-cajon.md` §4.1) ─────────────────────────────────
    //
    // ⚠️⚠️ **Con ellas la sección de cuenta deja de ser «solo con sesión»**, que era un supuesto
    // implícito de las seis de arriba. Son las tres pantallas de auth que hasta el 2026-08-23 vivían
    // en el modal de la cabecera, y su presencia aquí es lo que permite retirarlo: `DECISIONES #66`
    // pedía que la gestión del cliente viviera en UN sitio, y entrar es parte de gestionarse.

    /** Identificarse. Es donde aterriza un invitado que entra al área. */
    LOGIN: 'login',

    /** Crear cuenta. Alta **suelta**: manda correo de verificación y no abre sesión (§4.3). */
    REGISTER: 'register',

    /** Pedir el enlace para restablecer la contraseña. */
    FORGOT: 'forgot',
};

/** Donde aterriza quien entra al área sin pedir nada concreto. */
export const DEFAULT_ZONE = ZONES.HOME;

/**
 * El **rótulo** de cada zona, por su camino dentro del grupo `account` PODADO que el servidor inyecta
 * (§4.5 de `sidebar-spa.md`). `i18n.js` lee por camino, así que aquí solo vive la correspondencia.
 *
 * ⚠️ **Vive en el módulo plano y no en el componente por un motivo medido**: `t()` devuelve `''`
 * cuando una clave falta —en producción un texto ausente no puede tumbar el cajón—, así que una zona
 * sin rótulo se pintaría **con el título en blanco y nada avisaría**. Aquí sí puede vigilarlo un
 * test que recorra `ZONES` entero, y lo hace.
 */
export const ZONE_TITLE_KEYS = {
    [ZONES.HOME]: 'account.title',
    [ZONES.ORDERS]: 'orders.title',
    [ZONES.PASSWORD]: 'account.password.title',
    [ZONES.SESSIONS]: 'account.sessions.title',
    [ZONES.PROFILE]: 'account.profile.title',
    [ZONES.PRIVACY]: 'account.privacy.title',
    [ZONES.LOGIN]: 'login.title',
    [ZONES.REGISTER]: 'register.title',
    [ZONES.FORGOT]: 'forgot.title',
};

/**
 * **Las zonas a las que se llega SIN sesión** — y la razón de que esta lista exista.
 *
 * ⚠️⚠️ Hasta el 2026-08-23 la guarda de alcanzabilidad decía que **toda** zona tenía que estar en
 * `HOME_ENTRIES`, porque «dentro del cajón no hay URL, así que el índice es la única puerta». Con las
 * tres de auth **eso deja de ser cierto**: se llega a ellas por RUTA —las puertas de
 * `Http\Sidebar\AccountDoor`— y entre sí, y **ninguna puede estar en el índice**, que solo lo ve
 * quien ya tiene sesión.
 *
 * ▶ La guarda no se relaja: cambia de forma. Toda zona sigue teniendo que ser alcanzable, y ahora hay
 * **dos** puertas declaradas en vez de una. Sin esta lista, «alcanzable» se habría convertido en una
 * excepción escrita a mano en el test, que es donde se acaba metiendo cualquier cosa.
 */
export const GUEST_ZONES = [ZONES.LOGIN, ZONES.REGISTER, ZONES.FORGOT];

/** ¿Esta zona la ve alguien SIN sesión? Lo pregunta la sección para decidir por dónde entrar. */
export function isGuestZone(value) {
    return GUEST_ZONES.includes(value);
}

/**
 * **La zona que debe quedar DEBAJO al entrar de fuera, o `null` si no hay ninguna.**
 *
 * ⚠️⚠️ Existe porque «volver» tiene que significar lo correcto desde los **tres** sitios por los que
 * se llega a recuperar contraseña, y no son el mismo caso:
 *
 *  · **desde la pantalla de entrar** — hay historia de verdad: `go()` la apila y «volver» la deshace;
 *  · **desde una PUERTA por URL** (`/recuperar-contrasena`) — el cliente llega en frío, sin historia
 *    dentro del cajón. Sin nada debajo, «volver a iniciar sesión» le sacaría al catálogo de compra, y
 *    ése no es el sitio que el rótulo promete. Se siembra `LOGIN`, que es la portada del invitado
 *    igual que `HOME` lo es de quien tiene sesión;
 *  · **desde el PASO 5 del embudo** — aquí lo correcto es justo lo contrario: no hay zona a la que
 *    volver, porque de donde viene **no es una zona**. Con la pila vacía, «volver» sale de la sección
 *    y devuelve la compra donde estaba, con su cesta. Por eso quien entra así **no siembra nada**, y
 *    por eso esta decisión es del que llama y no de `enter()`.
 */
export function parentZoneFor(zone) {
    return isGuestZone(zone) && zone !== ZONES.LOGIN ? ZONES.LOGIN : null;
}

/**
 * **Las entradas del índice, en su orden.**
 *
 * ⚠️ **Es un DATO y no marcado**, y ese es el punto: añadir una zona al área de cliente pasa a ser
 * una línea aquí y su rótulo arriba, no copiar dieciséis líneas de `<button>` con su `<svg>` dentro.
 * El índice las recorre. `HOME` no está porque el índice no se enlaza a sí mismo.
 */
export const HOME_ENTRIES = [ZONES.ORDERS, ZONES.PROFILE, ZONES.PASSWORD, ZONES.SESSIONS, ZONES.PRIVACY];

/**
 * **Las zonas que traen su PROPIO encabezado**, y por tanto no llevan el del armazón.
 *
 * ⚠️⚠️ **Existe porque el título salía DOS VECES.** Las tres pantallas de auth reutilizan
 * `steps/LoginForm.vue` y `steps/RegisterForm.vue` del paso 5 del embudo —donde no hay armazón que
 * ponga título, así que el formulario trae el suyo— y `AccountSection` ponía además el de la zona,
 * con el MISMO literal: «Inicia sesión» encima de «Inicia sesión».
 *
 * ▶ **Se declara aquí y no como un `v-if` en la plantilla** por lo mismo que `ZONE_TITLE_KEYS`: es un
 * dato de la navegación, un test puede recorrerlo entero, y el día que nazca una cuarta pantalla con
 * encabezado propio se añade una línea en vez de descubrirse mirando.
 *
 * ⚠️ `titleKeyOf()` **sigue devolviendo su clave** para estas zonas, y a propósito: el rótulo se usa
 * en más sitios que el encabezado —el índice pinta las entradas con él— y devolver `null` habría
 * atado dos cosas que no son la misma.
 *
 * @var {string[]}
 */
const ZONES_WITH_OWN_HEADING = [ZONES.LOGIN, ZONES.REGISTER, ZONES.FORGOT];

/** ¿Esta zona pinta ya su encabezado, y el armazón debe callarse? */
export function bringsOwnHeading(zone) {
    return ZONES_WITH_OWN_HEADING.includes(zone);
}

/** El camino del rótulo de una zona. Una zona desconocida cae en el del índice, nunca en `''`. */
export function titleKeyOf(zone) {
    return ZONE_TITLE_KEYS[zone] ?? ZONE_TITLE_KEYS[DEFAULT_ZONE];
}

/** ¿Es una zona que existe? Lo pregunta el store antes de aterrizar en una pantalla que no hay. */
export function isZone(value) {
    return Object.values(ZONES).includes(value);
}

/**
 * Crea la navegación del área, con su pila.
 *
 * @param {{zone?: string}} options  la zona de entrada (por defecto, el índice)
 */
export function createNavigation({ zone = DEFAULT_ZONE } = {}) {
    /** La historia. El ÚLTIMO elemento es la zona actual: la pila nunca está vacía mientras se navega. */
    let trail = [isZone(zone) ? zone : DEFAULT_ZONE];

    return {
        /** La zona que se está viendo. */
        get zone() {
            return trail[trail.length - 1];
        },

        /** La historia completa, en copia: quien la lea no puede mutarla por accidente. */
        get trail() {
            return [...trail];
        },

        /** ¿Hay a dónde volver DENTRO del área? Si no, «volver» significa salir de la sección. */
        get canBack() {
            return trail.length > 1;
        },

        /**
         * Va a una zona. Devuelve si se movió.
         *
         * ⚠️⚠️ **La pila NO admite repeticiones, y no es una optimización: es lo que impide que
         * «volver» deje de funcionar.** Con una pila ingenua, alternar entre el índice y las reservas
         * quince veces deja quince entradas, y el cliente tendría que pulsar «volver» quince veces
         * para salir de un área que solo tiene dos pantallas. Al ir a una zona que YA está en la
         * historia, se recorta hasta ella —comportamiento de miga de pan— y la pila queda acotada por
         * el número de zonas, no por la paciencia del cliente.
         *
         * Una zona desconocida se rechaza en silencio, igual que hace la máquina del embudo con una
         * transición imposible: quien llama es la interfaz, y lo que no puede pasar es que el área
         * acabe enseñando una pantalla que no existe.
         */
        go(next) {
            if (! isZone(next) || next === this.zone) return false;

            const seen = trail.indexOf(next);

            trail = seen === -1 ? [...trail, next] : trail.slice(0, seen + 1);

            return true;
        },

        /**
         * Vuelve a la zona anterior. Devuelve si se movió.
         *
         * ⚠️ **Cuando devuelve `false` NO es un error: significa «aquí ya no hay atrás»**, y quien
         * llama lo traduce en salir del área hacia la compra. La decisión de SALIR no vive aquí a
         * propósito: este módulo sabe de zonas, no de secciones, y mezclarlo sería volver a tener dos
         * niveles de navegación en un mismo mapa — lo que `#119` desmontó.
         */
        back() {
            if (! this.canBack) return false;

            trail = trail.slice(0, -1);

            return true;
        },

        /**
         * Reinicia la historia en una zona.
         *
         * Lo llama quien ENTRA al área: la pila se vacía al salir (`specs/area-cliente.md` §4.2), de
         * modo que quien vuelve a entrar no arrastra el recorrido de la visita anterior — que ya no
         * describe nada de lo que tiene delante.
         */
        reset(next = DEFAULT_ZONE, under = null) {
            const target = isZone(next) ? next : DEFAULT_ZONE;

            // ⚠️ `under` deja UNA zona debajo, para que «volver» signifique algo dentro del área en
            // vez de salir de ella. Quién lo pide y por qué, en `parentZoneFor()`. Se ignora si no es
            // una zona o si es la misma —una pila `[x, x]` haría que «volver» no se moviera—.
            trail = isZone(under) && under !== target ? [under, target] : [target];
        },
    };
}
