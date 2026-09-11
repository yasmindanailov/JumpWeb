import { createApp } from 'vue';
import { createPinia } from 'pinia';
import Sidebar from './Sidebar.vue';
import { createMachine, STEPS } from './machine.js';
import { applyIntent as applyIntentToCatalog } from './intent.js';
import { usePurchaseStore } from './stores/purchase.js';
import { useSectionStore } from './stores/section.js';
import { useAccountStore } from './stores/account.js';
import { createNavigation } from './account/navigation.js';
import { useAccountContextStore } from './stores/accountContext.js';
import { watchTabReturn } from './account/tab-return.js';
import { useCartStore } from './stores/cart.js';
import { takeOver } from '../ui/account-host.js';

/**
 * El ENTRY del cajón SPA (Fase 4 · paso 4.1, `sidebar-spa.md` §4.7).
 *
 * ⚠️ **Este fichero no se carga con la página.** Lo trae un `import()` dinámico en la PRIMERA
 * apertura del cajón (ver `app.js`), y por eso Vue y Pinia acaban en un chunk propio: montarlos en
 * el bundle de todas las páginas públicas multiplicaría por un orden de magnitud los 15 kB que la
 * landing sirve hoy, **y durante la convivencia del flag se enviarían los dos motores**.
 *
 * Es el patrón que ya usaba `html2canvas` en el editor de invitaciones (retirado con él en `#528`).
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
        // Los idiomas del selector del perfil, que el servidor publica solo con sesión.
        locales: boot.locales ?? [],
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

    // La sección activa (`specs/area-cliente.md` §4.1). Se resuelve aquí y no dentro de la raíz para
    // que el handle pueda exponerla hacia fuera sin pasar por el componente.
    const sectionStore = useSectionStore(pinia);

    // La navegación del área de cliente (`specs/area-cliente.md` §4.2). Se arranca aquí, junto al
    // resto del estado, para que `showAccount(zona)` pueda entrar directo a una zona sin que el
    // componente tenga que existir todavía: la sección se monta con `v-if` en la primera entrada.
    const accountStore = useAccountStore(pinia);
    accountStore.boot(createNavigation());

    // El contexto que pinta el bloque de cuenta del panel. Llega SEMBRADO por el servidor
    // (`Http\Sidebar\AccountContextSeed`), así que el bloque se pinta sin pedirle nada a nadie; solo
    // se refresca cuando el cajón consigue sesión SIN recargar (`specs/account-context-vue.md` §4.3).
    const accountContextStore = useAccountContextStore(pinia);
    accountContextStore.seed(boot.accountContext ?? null);

    // …y al VOLVER A LA PESTAÑA (`#340`). El contexto se siembra una vez por carga de página, así que
    // quien verifica su correo —o firma— en otra pestaña volvía a ésta y seguía leyendo el estado de
    // antes: se lo encontró el owner el día del lanzamiento, con el servidor contestando ya lo
    // correcto. El disparador vive fuera (`account/tab-return.js`), con su decisión pura probada por
    // `node --test`; aquí solo se le da el store y el `window`.
    //
    // ⚠️ No se guarda la función de desconectar: el motor se monta UNA vez por carga de página y no
    // se desmonta —lo dice el propio `handle` de abajo—, así que los oyentes viven lo que la página.
    watchTabReturn({ context: accountContextStore });

    // ⚠️⚠️ **El DUEÑO de la cesta se siembra desde el HTML, aquí y ANTES de montar** — y esta línea
    // faltó desde que la identidad se mudó al store (2026-08-22) hasta el 2026-08-27, cuando una sonda
    // en headless la echó de menos (`specs/menores-a-cargo.md` §9.9.6): `userId` viajaba en el boot,
    // la raíz lo declaraba y **nadie lo leía**. El cajón que NACE ABIERTO (`/entradas`, `/mi-cuenta`,
    // `/login`, `/registro`, `/recuperar-contrasena`) arranca por `bootSpaEngine()` sin pasar por
    // `open()` —el único sitio que pregunta `GET /me`—, así que `restoreCart()` corría con el dueño a
    // `null` y `decideOwnership(N, null)` PURGABA la cesta del propio titular (medido: 2/2; desde la
    // home, 4/4 conservada porque ahí `open()` sí pregunta).
    //
    // Es el dato del SERVIDOR al pintar la página (`auth()->id()`), no un id que venga del cliente:
    // por eso vale como siembra, y por eso `GET /me` sigue re-resolviéndolo en cada apertura. Va
    // ANTES de `app.mount(el)` porque la sección restaura en su `onMounted`. Lo fija
    // `SidebarMountTest::test_the_engine_seeds_the_cart_owner_from_the_boot_before_mounting`.
    useCartStore(pinia).setOwner(boot.userId ?? null);

    // ⚠️⚠️ **El hueco del bloque se vacía ANTES de montar, y el orden no es negociable**: `<Teleport>`
    // **anexa y no vacía** —al revés que `app.mount()`, que sí limpia su contenedor—, así que sin esto
    // el suelo servido y el bloque de Vue convivirían y el cliente vería DOS botones de cerrar sesión.
    // El dueño único del hueco es `ui/account-host.js`; aquí solo se le da el elemento.
    takeOver(document.getElementById('sidecart-account'));

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
         * parámetro). Quien llama a esto solo DISPARA la pregunta: nunca le pasa un identificador,
         * porque colgar de un id que viaje por el cliente sería confiar en él para una defensa de
         * seguridad. (Hasta el 2026-08-23 quien disparaba era el evento `logged-in` de Livewire, que
         * ya no existe: ahora es `app.js` al abrir el cajón.)
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
        /**
         * **Pedir una SECCIÓN del cajón desde fuera** (`specs/area-cliente.md` §4.6).
         *
         * ⚠️ Va en el handle y no en el store de Alpine por lo mismo que la costura de intención: la
         * landing no tiene por qué saber que dentro hay Pinia. Habla con el motor, y el motor con su
         * estado — así el día que cambie el estado no hay que tocar ni un `@click` del Blade.
         *
         * ⚠️⚠️ **El botón de `account-context` NO se cablea aquí todavía, y es deliberado**: la
         * sección de cuenta es hoy su armazón, sin zonas. Cablear la puerta antes de que haya
         * habitación llevaría al cliente a una pantalla vacía si esto se despliega. Se cablea en el
         * paso 5 de la tanda, cuando las zonas existan.
         */
        // ⚠️ Esta es la entrada **desde fuera del cajón** —las puertas por URL y los botones de la
        // cabecera—. Delega en `accountStore.openZone()` y no repite la secuencia: desde el
        // 2026-08-23 el **bloque de cuenta del panel** necesita exactamente lo mismo desde DENTRO
        // (`specs/account-context-vue.md` §4.9), y dos copias serían dos sitios donde recordar que
        // hay que sembrar la vuelta.
        showAccount: (zone) => accountStore.openZone(zone),
        showPurchase: () => sectionStore.showPurchase(),
        store,
        section: sectionStore,
        account: accountStore,
        machine,
    };

    app._jumpweb = handle;

    return handle;
}
