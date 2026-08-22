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
 * ⚠️ **Las zonas de la tanda 2 —perfil, contraseña, sesiones, privacidad— NO se declaran aquí.**
 * Declararlas vacías «para dejarlo preparado» es exactamente el error que `#119` evitó a propósito:
 * sin pantallas, su forma es especulación. El modelo admite añadirlas sin tocarse.
 */
export const ZONES = {
    /** El índice: quién eres, tu próxima reserva y los accesos. Espeja `/mi-cuenta` + el bloque `.acct`. */
    HOME: 'home',
    /** «Mis reservas»: el historial con su detalle y el reintento. Espeja `/mi-cuenta/pedidos`. */
    ORDERS: 'orders',
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
};

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
        reset(next = DEFAULT_ZONE) {
            trail = [isZone(next) ? next : DEFAULT_ZONE];
        },
    };
}
