import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { runRetry } from '../outcome.js';
import { STEPS } from '../machine.js';
import { usePurchaseStore } from './purchase.js';
import { useOutcomeStore } from './outcome.js';
import { useSectionStore } from './section.js';
import { useAccountStore } from './account.js';
import { ZONES } from '../account/navigation.js';

/**
 * **El estado de «Mis reservas»**: qué página se ha pedido, qué respondió y qué falló
 * (`docs/specs/area-cliente.md` §4.3).
 *
 * ⚠️⚠️ **Guarda la respuesta CRUDA, no las filas ya compuestas**, y es deliberado: componerlas exige
 * los diccionarios del montaje, que viven en las props del componente. Con el payload crudo aquí y
 * `account/orders.js` ejecutándose donde están los textos, el store se prueba sin diccionarios y el
 * módulo se prueba sin store — que es lo que hace que las dos mitades tengan red propia.
 *
 * ⚠️ **Y aquí vive la regla que sustituyó a `<KeepAlive>`** (`DECISIONES #120(g)`): `ensure()` pide
 * **solo si no hay datos**. Eso es lo que hace que volver a la zona no repita la petición, sin pagar
 * los 2,3 KiB de una caché del framework y sin romper las template refs.
 *
 * ⚠️ **RGPD**: nada de esto se persiste. La cesta ya excluye a propósito las respuestas del evento
 * —nombres y alergias de menores, art. 9— y un historial de pedidos en `localStorage` sería la misma
 * fuga por otra puerta (`specs/area-cliente.md` §4.4). Vive en memoria y se repide al abrir.
 */
export const useOrdersStore = defineStore('orders', {
    state: () => ({
        /**
         * Las respuestas de `GET /me/reservations/{scope}`, crudas y **por ámbito**. `null` mientras
         * ese ámbito no se haya pedido nunca.
         *
         * ⚠️⚠️ **Los dos ámbitos viven en UN store, y no es comodidad** (2026-08-23,
         * `specs/mis-reservas-por-reserva.md` §4.4). «Mis reservas» y «Historial» son dos pantallas
         * de la MISMA lista partida por un predicado del servidor; con un store cada una, las dos
         * podrían tener versiones distintas del mismo pedido —una reserva reintentada en una y
         * caducada en la otra— y nadie lo notaría hasta ver los dos números discrepar. Aquí una
         * recarga invalida lo que tenga que invalidar en los dos.
         */
        pages: { upcoming: null, past: null },

        /**
         * La respuesta de `GET /me/orders`: **«Mis pedidos»**, la pantalla del DINERO
         * (`specs/desglose-dinero-cliente.md` §19). `null` mientras no se haya pedido nunca.
         *
         * ⚠️⚠️ **Es otra lista, no otra vista de la de arriba.** Aquélla lista RESERVAS y ésta
         * PEDIDOS, y son unidades distintas: un pedido puede llevar tres reservas de tres fechas.
         * Aplanar una en la otra en el navegador daría un orden que solo es cierto dentro de la
         * página —el motivo por el que `#126` descartó exactamente eso—, así que cada pantalla pide
         * la suya y el servidor ordena y pagina las dos.
         */
        purchases: null,

        /**
         * **El pedido que hay que abrir DESPLEGADO al entrar en «Mis pedidos»**, o `''`.
         *
         * ⚠️ Lo siembra «Ver pedido» de una reserva (decisión del owner, spec §5·2) y lo consume la
         * zona **una sola vez**: es una intención de navegación, no un estado de la pantalla. Si no
         * se vaciara, volver a entrar por el índice reabriría el pedido de la visita anterior, que
         * ya no describe nada de lo que el cliente tiene delante.
         */
        focus: '',

        /** ¿Hay una petición en vuelo? */
        loading: false,

        /** Lo que salió mal, ya en el idioma del cliente. Cadena vacía si no hay nada que decir. */
        error: '',

        /**
         * ⚠️ **La sesión caducó mientras el cajón estaba abierto** (§4.6). Se distingue del error
         * genérico porque la salida es otra: no se reintenta, se entra. Sin este caso, un 401 dejaría
         * la zona en blanco sin decir por qué.
         */
        unauthenticated: false,

        /**
         * Las respuestas del pack, **por código de pedido y solo si el cliente las ha pedido**
         * (tanda 3, `DECISIONES #120(u)`).
         *
         * ⚠️⚠️ **No llegan con la lista, y esa es toda la razón de que esto exista.** Son datos de un
         * MENOR —nombre, edad y alergias, art. 9— y `GET /me/orders` los excluye a propósito para que
         * listar el historial no los arrastre. Pedirlos es un acto explícito del titular, igual que en
         * el resumen de la compra.
         *
         * ⚠️ Y como todo lo demás de esta zona, **vive en memoria**: nada de `localStorage`.
         */
        eventData: {},
        // Los JUSTIFICANTES de menores invitados por código de pedido (`#337`). Como `eventData`:
        // se pide al desplegar y se cachea, porque el enlace que trae es una credencial portadora y
        // no puede viajar en el contexto sembrado.
        guestMinors: {},
    }),

    getters: {
        /** ¿Ya se pidió ese ámbito? Lo mira `ensure()` para no repetir la petición al volver. */
        loaded: (state) => (scope) => state.pages[scope] !== null,
    },

    actions: {
        /** Pide la primera página de un ámbito **solo si hace falta**. Se llama al entrar en la zona. */
        async ensure(scope, deps) {
            if (this.loaded(scope) || this.loading) return;

            await this.load(scope, 1, deps);
        },

        /**
         * **Abre «Mis pedidos» en un pedido concreto.** Lo llama «Ver pedido» de una reserva.
         *
         * ⚠️⚠️ **Las TRES cosas pasan aquí y no repartidas entre el store y el componente**: sembrar
         * el pedido, invalidar la página y NAVEGAR. La primera versión dejaba la navegación en
         * `OrdersZone.vue`, y eso partía una regla en dos mitades de las que **una no tiene red** —un
         * componente pinta y no se prueba con `node --test` (`CE-6`)—. El precedente es `retry()`,
         * que también conmuta de sección desde aquí.
         *
         * ⚠️⚠️ **Invalida la página que hubiera**, y no es una optimización: la página cargada puede
         * ser cualquiera, y el pedido buscado estar en otra. Sin este borrado, `ensurePurchases()`
         * —que pide «solo si no hay datos»— se quedaría con la que ya tiene y la pantalla se abriría
         * **sin el pedido dentro**, sin fallar nada. Es la forma silenciosa de incumplir la decisión
         * del owner, que es justo la que este trabajo lleva persiguiendo.
         */
        openPurchase(code) {
            this.focus = String(code ?? '');
            this.purchases = null;
            useAccountStore().go(ZONES.PURCHASES);
        },

        /** Consume la intención: la zona la lee UNA vez y la vacía. Ver {@see focus}. */
        takeFocus() {
            const code = this.focus;

            this.focus = '';

            return code;
        },

        /**
         * Pide la página de «Mis pedidos» **solo si hace falta**. Se llama al entrar en la zona.
         *
         * ⚠️ Si hay un pedido que enfocar, la página la elige el SERVIDOR con `containing`: el orden
         * y el tamaño de página son suyos, así que es el único que sabe en cuál cae (`#129`).
         */
        async ensurePurchases(deps) {
            if (this.purchases !== null || this.loading) return;

            // ⚠️ La intención se consume AQUÍ y no en la zona: un componente pinta, no orquesta
            // (`CE-6`). Y es seguro consumirla dentro del `if` porque `openPurchase()` deja la
            // página en `null`, así que sembrar el foco garantiza que esta carga ocurre.
            await this.loadPurchases(1, { ...deps, containing: this.takeFocus() });
        },

        async loadPurchases(page = 1, { api = httpClient, containing = '' } = {}) {
            this.loading = true;
            this.error = '';
            this.unauthenticated = false;

            try {
                const query = containing
                    ? 'containing=' + encodeURIComponent(containing)
                    : 'page=' + encodeURIComponent(page);
                const response = await api.get('/me/orders?per_page=5&' + query);

                if (response.ok) {
                    this.purchases = response.data;

                    return;
                }

                if (response.status === 401) {
                    this.unauthenticated = true;

                    return;
                }

                this.error = response.error?.message ?? '';
            } finally {
                this.loading = false;
            }
        },

        /**
         * Pide una página del historial.
         *
         * ⚠️ **La página anterior NO se borra al empezar**: si la petición falla, el cliente se queda
         * con lo que ya tenía y un aviso, en vez de con una pantalla vacía que parece decir «no tienes
         * reservas» — que es justo lo contrario de lo que ha pasado.
         */
        /**
         * Pide las respuestas de un pedido **una sola vez**.
         *
         * ⚠️ **Un fallo no se anuncia con el error de la zona**: esto es un despliegue que el cliente
         * ha pedido, no el contenido de la pantalla. Pintar «algo ha ido mal» sobre la lista entera
         * porque no se pudo leer un bloque diría que el problema es otro.
         */
        async ensureEventData(code, { api = httpClient } = {}) {
            if (! code || this.eventData[code]) return;

            const response = await api.get('/orders/' + encodeURIComponent(code) + '/event-data');

            if (response.ok) this.eventData = { ...this.eventData, [code]: response.data };
        },

        /**
         * Los JUSTIFICANTES de menores invitados de un pedido (`specs/waiver-por-reserva.md` §4.10).
         *
         * ⚠️ **Vive AQUÍ y no en el componente** (`CE-6`): una zona pinta y no habla con la API, y
         * `SidebarComponentBudgetTest` lo impone. La primera versión de `GuestMinorsPanel.vue`
         * llamaba a `api.get` directamente y la guarda la cazó — con la regla citada en el docblock
         * de la tarjeta de al lado.
         *
         * ⚠️ Un fallo NO se anuncia: es un despliegue que el cliente ha pedido, no el contenido de la
         * pantalla. Que no se pueda leer este bloque no puede teñir de error la lista de pedidos.
         *
         * ⚠️⚠️ **`response.data` es el SOBRE, no su contenido, y ahí estuvo el defecto REAL** (`#402`):
         * `api.js` devuelve el cuerpo entero —`{data: {...}}`— y este store guardaba eso tal cual,
         * así que el componente leía `guestMinors[code].reservations` sobre un objeto que solo tiene
         * `data`. **Resultado: la petición salía con 200 y la pantalla no pintaba NADA**, en silencio
         * y durante dos tandas. El owner lo dijo dos veces —*«no me sale nada del enlace»*— y las dos
         * veces se le achacó a otra cosa.
         *
         * ▶ *Un 200 en la pestaña de red no dice que el dato haya llegado a donde se lee.* El resto
         * del store desenvuelve al COMPONER (`cardRows(payload)` hace `payload?.data ?? []`); aquí no
         * hay compositor, así que se desenvuelve al guardar y el componente recibe lo que espera.
         */
        async ensureGuestMinors(code, { api = httpClient } = {}) {
            if (! code || this.guestMinors[code]) return;

            const response = await api.get('/orders/' + encodeURIComponent(code) + '/guest-minors');

            if (response.ok) {
                this.guestMinors = { ...this.guestMinors, [code]: response.data?.data ?? { reservations: [] } };
            }
        },

        async load(scope, page = 1, { api = httpClient } = {}) {
            this.loading = true;
            this.error = '';
            this.unauthenticated = false;

            try {
                const response = await api.get(`/me/reservations/${encodeURIComponent(scope)}?page=${encodeURIComponent(page)}`);

                if (response.ok) {
                    this.pages = { ...this.pages, [scope]: response.data };

                    return;
                }

                if (response.status === 401) {
                    this.unauthenticated = true;

                    return;
                }

                this.error = response.error?.message ?? '';
            } finally {
                this.loading = false;
            }
        },

        /** Vuelve a pedir la página que se está viendo de un ámbito (tras un reintento, o un fallo). */
        async reload(scope, deps) {
            await this.load(scope, Number(this.pages[scope]?.meta?.current_page ?? 1), deps);
        },

        /**
         * **Reintentar el pago de un pedido desde su ficha.**
         *
         * ⚠️⚠️ **No reimplementa la secuencia: llama a `runRetry`, el MISMO módulo que usa el paso 10
         * del embudo.** Reabrir un cobro tiene reglas de dominio —extiende el hold, no suelta el
         * pedido si la pasarela falla, y devuelve 401 si la sesión se perdió por el camino— y
         * transcribirlas otra vez aquí sería la segunda superficie que las interpreta.
         *
         * ⚠️ Y sale por la pantalla de redirección **del embudo**, que ya existe y está probada. Se
         * usa `enter()` y no `go()` a propósito: `go()` exige que la transición esté declarada en
         * `FUNNEL_TRANSITIONS` y aquí se llega **desde fuera del embudo**, que es exactamente para lo
         * que `enter()` existe.
         *
         * ⚠️ **`api` es inyectable pero tiene el cliente REAL por defecto**, y las dos mitades tienen
         * motivo: inyectable para que `node --test` pueda doblarlo —sin eso este store no tendría
         * red—, y con defecto para que **el componente no necesite conocer la API**. Un componente
         * que importa `api` para pasárselo a un store ya está orquestando peticiones, que es lo que
         * `SidebarComponentBudgetTest` existe para impedir.
         *
         * @param {string} code  el código del pedido
         * @param {{api: object, messages: object}} deps
         */
        async retry(code, { api = httpClient, messages = {} } = {}) {
            this.loading = true;
            this.error = '';

            try {
                const result = await runRetry({ orderCode: code, api, messages });

                if (result.ok) {
                    const outcome = useOutcomeStore();
                    const purchase = usePurchaseStore();

                    outcome.declinedReason = '';
                    outcome.gateway = result.form;
                    useSectionStore().showPurchase();
                    purchase.enter(STEPS.REDIRECTING);

                    return true;
                }

                // ⚠️ Aquí el aviso SÍ se ve, al revés que en el paso 10 del embudo —cuyo bloque no
                // pinta errores y está anotado como deuda de PRODUCTO—. No es una divergencia
                // heredada: es que esta pantalla sí tiene dónde decirlo.
                this.error = result.error;

                if (result.goTo === 'identify') this.unauthenticated = true;

                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});
