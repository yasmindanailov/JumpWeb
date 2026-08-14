import { createApp } from 'vue';
import { createPinia } from 'pinia';
import Sidebar from './Sidebar.vue';
import { createMachine } from './machine.js';
import { usePurchaseStore } from './store.js';

/**
 * El ENTRY del cajón SPA (Fase 4 · paso 4.1, `sidebar-spa.md` §4.7).
 *
 * ⚠️ **Este fichero no se carga con la página.** Lo trae un `import()` dinámico en la PRIMERA
 * apertura del cajón (ver `app.js`), y por eso Vue y Pinia acaban en un chunk propio: montarlos en
 * el bundle de todas las páginas públicas multiplicaría por un orden de magnitud los 15 kB que la
 * landing sirve hoy, **y durante la convivencia del flag se enviarían los dos motores**.
 *
 * El precedente correcto ya existía en el repo con `html2canvas`.
 *
 * ⚠️ Y hay un riesgo de rendimiento distinto que sí toca `PERF-02`: si la raíz montara con avidez y
 * pidiera catálogo en cada carga de landing, añadiría una petición por visita en la ruta de más
 * tráfico. Por eso se monta **al abrir**, no al cargar.
 */

let app = null;

/**
 * Monta el cajón dentro de su hueco, una sola vez.
 *
 * Devuelve la API que la costura de intención necesita, para que `app.js` no tenga que conocer ni
 * Vue ni Pinia: la landing habla con el store de Alpine, y el store con esto.
 *
 * @param {HTMLElement} el  el hueco del layout donde vive el cajón
 * @param {{outcome?: string|null, messages?: object, ui?: object}} boot  lo que el servidor dejó en el montaje
 */
export function mount(el, boot = {}) {
    if (app) return app._jumpweb;

    const machine = createMachine();
    const pinia = createPinia();

    app = createApp(Sidebar, { messages: boot.messages ?? {}, ui: boot.ui ?? {}, userId: boot.userId ?? null });
    app.use(pinia);

    const store = usePurchaseStore(pinia);
    store.boot(machine);

    // El desenlace de la pasarela decide en qué paso ABRE el cajón. Lo posee `Http\Sidebar\SidebarEntry`
    // en servidor (paso 4.0a) y llega ya consumido: mirarlo dos veces reabriría el cajón en cada
    // página hasta que caducara la sesión, que es el fallo que aquel paso cerró.
    if (boot.outcome) machine.enterOutcome(boot.outcome);

    const root = app.mount(el);

    const handle = {
        /**
         * Relee el estado de las reservas (la pausa).
         *
         * ⚠️ Lo llama la landing en CADA apertura del cajón. El motor se monta una sola vez por carga
         * de página y no se desmonta, así que sin esto la pausa solo entraría al recargar — que es el
         * snapshot que `GET /booking/status` existe para evitar, y la diferencia con Livewire, que
         * reevalúa su guarda en cada render.
         */
        refreshStatus() {
            root.refreshBookingStatus?.();
        },

        /**
         * Vuelve a resolver QUIÉN es el titular, y purga la cesta si ha cambiado.
         *
         * ⚠️ La identidad sale SIEMPRE del servidor (`GET /me` la toma del guard, nunca de un
         * parámetro). El evento `logged-in` de Livewire solo DISPARA esta llamada: no trae el id
         * —`dispatch('logged-in')` va sin payload— y colgarse de un id que viaje por el bus de eventos
         * del navegador sería confiar en el cliente para una defensa de seguridad.
         */
        refreshIdentity() {
            return root.refreshIdentity?.();
        },

        /** Aplica una intención de entrada de la landing (`{type:'packs'}` · `{type:'zone', slug}`). */
        applyIntent(intent) {
            machine.queueIntent(intent);
        },
        store,
        machine,
    };

    app._jumpweb = handle;

    return handle;
}
