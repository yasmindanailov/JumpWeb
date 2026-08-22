import { createApp } from 'vue';
import { createPinia } from 'pinia';
import Sidebar from './Sidebar.vue';
import { createMachine, STEPS } from './machine.js';
import { applyIntent as applyIntentToCatalog } from './intent.js';
import { usePurchaseStore } from './stores/purchase.js';

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

/**
 * Espera a que el ancla de una sección exista en el DOM, y se rinde.
 *
 * ⚠️ **Por CONDICIÓN y acotada, no por reloj.** El catálogo llega por red y el componente lo pinta
 * después, así que mirar el DOM en el instante del clic encontraría la nada; pero esperar sin techo
 * dejaría un bucle vivo en una landing que el cliente ya abandonó. Si no aparece en ~4 s, quien llama
 * recibe `null` y lo dice — el cajón se queda en el catálogo, que es el mejor destino posible.
 */
function waitForAnchor(id, { attempts = 40, every = 100 } = {}) {
    return new Promise((resolve) => {
        let left = attempts;

        const look = () => {
            const element = id ? document.getElementById(id) : null;

            if (element || left-- <= 0) return resolve(element ?? null);

            setTimeout(look, every);
        };

        look();
    });
}

let app = null;

/**
 * Monta el cajón dentro de su hueco, una sola vez.
 *
 * Devuelve la API que la costura de intención necesita, para que `app.js` no tenga que conocer ni
 * Vue ni Pinia: la landing habla con el store de Alpine, y el store con esto.
 *
 * @param {HTMLElement} el  el hueco del layout donde vive el cajón
 * @param {{outcome?: string|null, orderCode?: string|null, messages?: object, ui?: object}} boot  lo que el servidor dejó en el montaje
 */
export function mount(el, boot = {}) {
    if (app) return app._jumpweb;

    const machine = createMachine();
    const pinia = createPinia();

    app = createApp(Sidebar, {
        messages: boot.messages ?? {},
        ui: boot.ui ?? {},
        // Los dos grupos que el paso de identificación necesita y que NO están en `tickets` (§4.5).
        account: boot.account ?? {},
        auth: boot.auth ?? {},
        userId: boot.userId ?? null,
        // ⚠️ **El pedido del que habla el desenlace.** Entre el clic de pagar y la vuelta hubo una
        // navegación completa a otro dominio, así que la memoria del cajón NO sobrevive: este código es
        // lo único con lo que las tres pantallas de desenlace pueden preguntar de qué reserva se trata.
        // Lo posee el mismo dueño que el `outcome` —`Http\Sidebar\SidebarEntry`— y viaja con él.
        orderCode: boot.orderCode ?? '',
        // Las rutas que pintan las pantallas de desenlace, compuestas con `route()` en el servidor.
        urls: boot.urls ?? {},
    });
    app.use(pinia);

    // El desenlace de la pasarela decide en qué paso ABRE el cajón. Lo posee `Http\Sidebar\SidebarEntry`
    // en servidor (paso 4.0a) y llega ya consumido: mirarlo dos veces reabriría el cajón en cada
    // página hasta que caducara la sesión, que es el fallo que aquel paso cerró.
    //
    // ⚠️ **Va ANTES de `store.boot()`, y el orden es el fallo que rompía la Fase 4 entera.** El store
    // copia `machine.step` en `boot()`, `go()` y `enter()`; una llamada DIRECTA a la máquina después de
    // arrancar lo deja desincronizado —la máquina en el paso 6 y el store en el 1— y Vue pinta desde el
    // store: quien volvía de pagar veía **el catálogo**. Ningún test podía verlo (la máquina se prueba
    // sola, los componentes se montan con props y nadie ejecuta esta secuencia); lo encontró el extremo
    // a extremo con navegador.
    if (boot.outcome) machine.enterOutcome(boot.outcome);

    const store = usePurchaseStore(pinia);
    store.boot(machine);

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

        /**
         * Aplica una intención de entrada de la landing (`{type:'packs'}` · `{type:'zone', slug}`).
         *
         * ⚠️⚠️ **Encolar NO es aplicar, y esa confusión costó la funcionalidad entera** (`#117`). Hasta
         * el 2026-08-21 esto solo hacía `queueIntent(...)` y nadie llamaba nunca a `takeIntent()`: la
         * intención se guardaba para siempre y los tres enlaces profundos abrían el catálogo raíz.
         * **No fallaban: no hacían nada.** Se conserva el paso por la máquina —es la dueña única de
         * «hay una intención pendiente», y así `queueIntent`/`takeIntent` siguen siendo un par— pero
         * se DRENA acto seguido, que es lo que faltaba.
         *
         * La decisión de a dónde llevar el cajón vive en `intent.js` (módulo plano, `CE-6`); aquí solo
         * se le da acceso al mundo: el paso, el DOM y el desplazamiento.
         */
        applyIntent(intent) {
            machine.queueIntent(intent);

            return applyIntentToCatalog(machine.takeIntent(), {
                goToCatalog: () => {
                    if (store.step !== STEPS.CATALOG) store.go(STEPS.CATALOG);
                },
                waitForAnchor: (id) => waitForAnchor(id),
                // `block: 'start'` y no `center`: la sección tiene que quedar arriba del panel, que es
                // donde el cliente espera encontrarla tras pedirla desde la landing.
                scrollTo: (element) => element.scrollIntoView({ behavior: 'smooth', block: 'start' }),
            });
        },
        store,
        machine,
    };

    app._jumpweb = handle;

    return handle;
}
