<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { usePurchaseStore } from '../stores/purchase.js';
import { useDateStore } from '../stores/date.js';
import { useTimeStore } from '../stores/time.js';
import { useAuthStore } from '../stores/auth.js';
import { useCartStore } from '../stores/cart.js';
import { useOutcomeStore } from '../stores/outcome.js';
import { useCatalogStore } from '../stores/catalog.js';
import { useBookingStore } from '../stores/booking.js';
import { useSelectionStore } from '../stores/selection.js';
import { useDependentsStore } from '../stores/dependents.js';
import { needsAssignment } from '../assignment.js';
import { STEPS, isOutcome } from '../machine.js';
import { api } from '../api.js';
import { searchIsEnabled, sectionsFrom } from '../catalog.js';
import { initialQuantity, minQuantityFor } from '../offer.js';
import { nextQuantity, unitPriceToShow } from '../quantity.js';
import { backPlan, buildProgress } from '../progress.js';
import { t as translate, tp as translateWith } from '../i18n.js';
import { buildFooter } from '../foot.js';
import { buildNotice } from '../paused.js';
import { continueAfterIdentification, runCheckout } from '../admission.js';
import { runConfirm } from '../pay.js';
import { buyerNeeds, emptyBuyerDue } from '../buyer-due.js';
import { lineProblems } from '../line-problems.js';
import { loadPaymentStatus, pollVerdict, runRetry } from '../outcome.js';
import { signupRequiresCaptcha, CONTEXT_PURCHASE } from '../register.js';
import { addLine, hasPendingEventFields, toCheckoutItems, todayIso } from '../cart.js';
import { sessionGained } from '../account/session-gained.js';
import { useAccountContextStore } from '../stores/accountContext.js';
import Shell from '../Shell.vue';
import CatalogStep from '../steps/CatalogStep.vue';
import DateStep from '../steps/DateStep.vue';
import TimeStep from '../steps/TimeStep.vue';
import CartStep from '../steps/CartStep.vue';
import IdentifyStep from '../steps/IdentifyStep.vue';
import VerifyStep from '../steps/VerifyStep.vue';
import PayStep from '../steps/PayStep.vue';
import RedirectStep from '../steps/RedirectStep.vue';
import ConfirmedStep from '../steps/ConfirmedStep.vue';
import DeclinedStep from '../steps/DeclinedStep.vue';
import VerifyingStep from '../steps/VerifyingStep.vue';

/**
 * La raíz del cajón SPA.
 *
 * **El nodo raíz emite `class="purchase"` y eso no es decorativo**: el contrato visual es el ÁRBOL
 * (§4.2), y 90 de los 292 selectores que estilan el cajón son estructurales o dependen del tipo de
 * elemento. Lo verifica `SidebarDomContractTest` paso a paso.
 *
 * **Aquí se PIDEN los datos; los pasos solo pintan.** Esa separación es lo que hace verificable la
 * paridad: los componentes de paso reciben todo por props, así que se pueden renderizar en Node —sin
 * red ni navegador— y comparar con lo que emite Livewire.
 */
const props = defineProps({
    /** El grupo `tickets` del locale activo, inyectado por el servidor en el montaje (§4.5). */
    messages: { type: Object, default: () => ({}) },

    /** El grupo `ui` (hoy, solo el rótulo del velo de carga). Va aparte: son dos grupos de `lang/`. */
    ui: { type: Object, default: () => ({}) },

    /**
     * El grupo `account`, PODADO a lo que el paso de identificación pinta (§4.5).
     *
     * ⚠️ Va aparte y con su camino real (`account.login.email`) por lo mismo que `ui`: son grupos
     * distintos de `lang/`, y fundirlos aquí crearía una tercera forma del diccionario. Medido: el
     * grupo entero son 9,6 kB en español —tanto como `tickets`— para pintar diez rótulos.
     */
    account: { type: Object, default: () => ({}) },

    /** El grupo `auth`: los dos avisos del login (`failed`, `throttle`). Tres claves. */
    auth: { type: Object, default: () => ({}) },

    /**
     * Quién es el titular al cargar la página, inyectado por el servidor. `null` = visitante anónimo.
     *
     * ⚠️ Llega con el HTML a propósito: el LOGOUT es una navegación completa, y es justo el caso que
     * la sesión resolvía sola con `invalidate()` y que `localStorage` no tiene. Esperar a un `fetch`
     * dejaría una ventana en la que la cesta de quien acaba de salir sigue en pantalla.
     *
     * ⚠️⚠️ **Aquí NO se lee: la siembra vive en `index.js`, antes de montar.** Desde el 2026-08-22
     * hasta el 2026-08-27 este docblock prometía una siembra que no existía en ninguna parte —la
     * prop estaba declarada y muerta— y el cajón que nace abierto purgaba la cesta del propio titular
     * (`specs/menores-a-cargo.md` §9.9.6). La prop se conserva declarada porque la raíz hace
     * `v-bind="props"`: sin declararla, Vue la volcaría como atributo sobre el DOM.
     */
    userId: { type: [Number, String], default: null },

    /**
     * El código del pedido del que habla el desenlace de la pasarela. Cadena vacía si no hay ninguno.
     *
     * ⚠️ **Es lo ÚNICO que sobrevive a la ida a la pasarela.** Volver de Redsys es una navegación
     * completa desde otro dominio: el motor se remonta de cero y nada de lo que el cajón sabía sigue
     * ahí, ni siquiera el pedido que acaba de crear. Lo posee `Http\Sidebar\SidebarEntry` —el mismo
     * dueño que el `outcome`, y por el mismo motivo— y viaja ya CONSUMIDO.
     */
    orderCode: { type: String, default: '' },

    /**
     * Las rutas que el cajón pinta y no puede componer: `contact` y `my_orders`.
     *
     * ⚠️ Vienen del servidor con `route()` a propósito. Quemarlas aquí sería una segunda fuente de una
     * URL que decide `routes/web.php`, y el fallo sería INVISIBLE: `href` no es atributo de contrato del
     * diff de árbol, así que un enlace a un 404 pasaría el gate en verde.
     */
    urls: { type: Object, default: () => ({}) },
});

const store = usePurchaseStore();

const catalogStore = useCatalogStore();
const selectionStore = useSelectionStore();

/**
 * El estado de las reservas (`GET /booking/status`).
 *
 * ⚠️ Es ESTADO y se relee; el objeto `notice` que trae viaja SIEMPRE, también con las reservas
 * abiertas, así que **el único bit de pausa es `reservations_paused`**.
 */
const bookingStore = useBookingStore();

/**
 * Relee el estado de las reservas.
 *
 * ⚠️ **Se llama en CADA apertura del cajón, no solo al montar**, y el matiz es el paso entero: el
 * motor SPA se monta **una vez por carga de página** y no se desmonta nunca, así que una lectura solo
 * en `onMounted` sería exactamente el snapshot que este endpoint existe para evitar — una pestaña
 * abierta antes del interruptor seguiría vendiendo hasta que alguien recargara. El motor Livewire no
 * tiene ese problema porque reevalúa la guarda en cada render.
 *
 * Un fallo de red NO inventa una pausa: se conserva lo último que se supo, que es la conducta segura
 * (el servidor rechaza igual al crear el pedido).
 */
function refreshBookingStatus() {
    return bookingStore.refresh({ api });
}

/**
 * Peticiones en vuelo. Es lo que enciende el velo de carga del armazón, y es un CONTADOR y no un
 * booleano a propósito: el cajón lanza pares de peticiones en paralelo (días + ficha del producto), y
 * con un booleano la primera en volver apagaría el velo mientras la otra sigue.
 */
const inFlight = ref(0);
const busy = computed(() => inFlight.value > 0);

/** Envuelve una llamada para que cuente en el velo. No cambia el resultado ni traga errores. */
async function tracked(promise) {
    inFlight.value++;

    try {
        return await promise;
    } finally {
        inFlight.value--;
    }
}


/**
 * El DÍA vive en su propio store (`stores/date.js`, reorganización del 2026-08-22): los días
 * ofrecidos, el elegido, el mes visible y todo lo que de ahí se deriva —la rejilla, los meses
 * navegables, los rótulos y el precio del día—.
 *
 * ⚠️ La composición de la rejilla sigue siendo de `calendar.js`, y su paridad contra el servidor la
 * fija `SidebarCalendarParityTest` dato a dato: mover el estado no mueve la regla.
 */
const dateStore = useDateStore();

/**
 * ⚠️⚠️ **AQUÍ SE DISPARABA EL CONFETI, y se ha RETIRADO** (`#278`, `[DECIDIDO owner]`: «quitamos el
 * confeti, tampoco vamos a saturar al cliente»).
 *
 * ▶ **Marcaba lo mismo dos veces.** Desde `#258` el desenlace enseña la PEGATINA de éxito, y el
 * artboard de estados escribe que «la pegatina nunca convive con otra en la misma pantalla»; el de
 * movimiento pone el techo en **dos** piezas animándose a la vez. Con confeti, pegatina y sello eran
 * tres.
 * ▶ La celebración no se pierde: pasa al CAJÓN y la hace el sistema de diseño del cliente — el check
 * entra con su curva y el código de la reserva se SELLA.
 *
 * ⚠️⚠️ **Aquí ESTABA también el puente de `mode`/`identifying`, y se subió a la raíz el 2026-08-22**
 * (`specs/area-cliente.md` §4.5). Desde que el cajón tiene dos secciones, esas dos señales dependen
 * de **la sección activa además del paso**, y un `watch` sobre el paso **no se dispara al conmutar**.
 * Con el confeti fuera, este `watch` se queda sin sujeto y se retira entero: un observador que no
 * observa nada es ruido que el siguiente agente tiene que descartar.
 */

/**
 * ⚠️ **El sondeo se para al SALIR del paso 11, y va en su propio observador a propósito.**
 *
 * El de arriba se rinde en cuanto no hay store de Alpine —es lo correcto para lo que hace: publicar
 * señales hacia fuera—, así que colgar de él la parada dejaría el temporizador vivo en cualquier página
 * sin Alpine. Un intervalo suelto no rompe nada visible: sigue preguntando a la API cada cinco
 * segundos, gastando fichas del limitador, y **ningún diff de árbol puede verlo**.
 */
watch(() => store.step, (step) => {
    if (step !== STEPS.VERIFYING) stopPolling();
});

/** Y al desmontar el motor. Es la otra forma de dejarlo suelto, y la que no avisa. */
onUnmounted(stopPolling);

/**
 * El catálogo se pide al MONTAR, y montar ocurre al abrir el cajón (§4.7).
 *
 * ⚠️ Ese reparto es lo que respeta `PERF-02`: una raíz que pidiera catálogo al cargar la página
 * añadiría una petición por visita en la ruta de más tráfico del sitio.
 */
onMounted(async () => {
    // ⚠️ El estado de la pausa se PIDE y no se inyecta en el montaje, y está decidido: `/config` es
    // estático por despliegue, pero la pausa la acciona la dueña **con clientes navegando**. Una
    // landing abierta horas con un snapshot mentiría desde el segundo en que se toca el interruptor.
    const [catalog, config] = await tracked(Promise.all([
        api.get('/catalog/products'),
        api.get('/config'),
        refreshBookingStatus(),
    ]));

    if (catalog.ok) catalogStore.setSections(sectionsFrom(catalog.data?.data ?? []));

    // El umbral lo decide el SERVIDOR y viaja con su operador en la descripción del contrato
    // (`total > umbral`): el cliente compara, no reinventa la regla.
    if (config.ok) {
        const threshold = config.data?.catalog_search_min_items;
        catalogStore.setSearchEnabled(searchIsEnabled(catalogStore.sections, threshold));
        // El tope de líneas lo publica el servidor: quemarlo aquí sería el cuarto sitio del que leer
        // el mismo número.
        cartStore.setMaxLines(config.data?.cart_max_lines);
        // El umbral del aviso «casi llena» (`#239`). Mismo trato que los dos de arriba: el número lo
        // decide el operador desde el panel y viaja con su operador escrito en el contrato
        // (`available <= low_availability_max`). Si no llega, el store se queda en 0 y NO se avisa:
        // inventar escasez que no se ha podido leer es peor que callar.
        timeStore.setLowMax(config.data?.low_availability_max);
        // ⚠️ El BIT del anti-bot, no su clave: no nulo ⟺ el alta exige captcha, y entonces el cajón
        // el cajón monta su propio widget de Turnstile con ella (`turnstile.js`, 4.4b·2).
        authStore.setSignupSiteKey(signupRequiresCaptcha(config.data) ? config.data.turnstile_site_key : '');
        // El enlace de registro del parque, que solo pinta el paso 6. Llega ya SANEADO (`SEC-07`): lo
        // edita un operador y un cliente JSON no tiene escape de plantilla que remate la defensa.
        outcomeStore.setRegistration(config.data?.registration);
    }

    // ⚠️ **En paralelo y no en cadena**: son independientes, y con un desenlace en pantalla el cliente
    // acaba de pagar — encadenarlas le regalaría la espera de la cesta antes de ver su reserva.
    await Promise.all([restoreCart(), loadOutcome()]);
});

/**
 * Restaura la cesta guardada y la deja lista para pintarse.
 *
 * Tres cosas en orden, y ninguna es opcional:
 *  1. **saneado y purga** las hace el módulo (formato, titular, caducidad y tope);
 *  2. **las ETIQUETAS de los campos del pack** se piden para los productos restaurados. El presupuesto
 *     no devuelve las respuestas del evento —son datos de un menor— y sin el esquema no habría con qué
 *     emparejarlas; da igual que vuelvan vacías: el esquema también dice si la línea está incompleta;
 *  3. **el presupuesto**, que además reconcilia y borra lo que ya no vale.
 *
 * ⚠️ Y como la web: con cesta, el cajón abre EN el carrito.
 */
async function restoreCart() {
    const { lines } = cartStore.restore(todayIso());

    if (lines.length === 0) {
        return;
    }

    cartStore.setLines(lines);

    await tracked(catalogStore.loadFieldsFor({ api, ids: lines.map((line) => line.product_id) }));

    // Con sesión, los menores a cargo (tanda 4): la lista viva, y fuera de la cesta los ids que ya no
    // se pueden asignar (§4.8·2). Sin sesión no hay lista que pedir ni ids que valgan.
    if (cartStore.owner !== null) await loadDependents();

    await tracked(cartStore.refreshQuote({ api }));

    // ⚠️ **El desenlace MANDA sobre la cesta, y el orden es el de Livewire**: su `mount()` coloca el
    // paso 4 si hay cesta y **después** deja que la vuelta de la pasarela lo pise. Aquí la cesta se
    // restaura igual —quien pulse «hacer otra reserva» la encuentra— pero no navega. No es teórico: la
    // cesta vive en `localStorage`, así que otra pestaña puede haberla llenado mientras se pagaba en
    // ésta, y quien vuelve de pagar aterrizaría en un carrito en vez de en su reserva confirmada.
    // La precedencia vive en `machine.js` porque aquí dentro no tendría red (`CE-6`).
    if (cartStore.lines.length > 0 && ! isOutcome(store.step)) store.enter(STEPS.CART);
}

/**
 * Elegir producto lleva al calendario y pide su oferta de días.
 *
 * ⚠️ Los días **no se calculan**: `GET availability/{id}/dates` los devuelve ya filtrados por
 * `SlotOffer` (`AFORO-02`), con su precio y su clave de tarifa. Componer la rejilla del mes a partir
 * de ellos sí es presentación.
 */
async function selectProduct(id) {
    // ⚠️ La FILA del catálogo se resuelve del listado que YA está en memoria, no de la ficha que se
    // está pidiendo: la banda de progreso enseña nombre y tipo de inmediato al entrar en el
    // calendario, y esperar a la ficha dejaría el contexto en blanco durante el viaje.
    catalogStore.select(id);
    dateStore.clearSelection();
    timeStore.clearSelection();
    selectionStore.clear();
    store.go(STEPS.DATE);

    // ⚠️ La ficha y los días se piden JUNTOS, no en cadena: los dos hacen falta antes de que el
    // cliente pueda elegir nada, y encadenarlos sumaría dos esperas donde cabe una.
    await tracked(Promise.all([
        dateStore.loadOffer({ api, productId: id }),
        catalogStore.loadProduct({ api, id }),
    ]));
}

/** Lo que el paso 3 necesita. Todo llega de la API; aquí no se decide nada (`CE-4`). */
const timeStore = useTimeStore();

/**
 * Los menores a cargo del titular (Fase 6 · tanda 4, `menores-a-cargo.md` §9.9.3 D9): el mismo store
 * que la zona de la cuenta. Aquí solo se PIDE —con sesión— y se reconcilia la cesta con la lista viva;
 * a quién se ofrece y qué cabe lo decide `assignment.js`.
 */
const dependentsStore = useDependentsStore();

async function loadDependents() {
    await tracked(dependentsStore.ensure({ api }));
    cartStore.dropUnassignable();
}

// ── La CESTA ──────────────────────────────────────────────────────────────────────────────────
//
// En memoria en 4.3·2. La persistencia en `localStorage` —con su dueño, su purga al cambiar de
// identidad y su reconciliación contra el presupuesto— llega en 4.3·3, y por eso el módulo `cart.js`
// no toca el almacén: se le pasará por parámetro.

/**
 * La CESTA vive en su propio store (`stores/cart.js`, reorganización del 2026-08-22): las líneas, su
 * dueño, el presupuesto que devolvió el servidor, los avisos y la persistencia.
 *
 * ⚠️ La tarificación sigue siendo de `POST /orders/quote` (`PAY-12`) y el saneado, la propiedad y la
 * caducidad de la cesta guardada, de `cart.js`. Mover el estado no movió ninguna regla.
 */
const cartStore = useCartStore();

// ⚠️ **Con sesión, la lista se pide en cuanto se SABE quién es el titular** —al nacer (el boot ya lo
// sembró, U0), al identificarse en el paso 5 y al cambiar de titular—, no solo al restaurar una cesta.
// Lo cazó el guion headless (§5.undecies), no ningún test: el cajón que nace abierto en `/entradas`
// con sesión y sin cesta no pasa por `refreshIdentity()`, y el paso 3 salía SIN el selector.
// `restoreCart()` y el login la vuelven a pedir a propósito: esperan a la MISMA petición y podan
// después de tener las líneas en la mano.
//
// ⚠️⚠️ **Va DEBAJO de `const cartStore`, y no es estilo: es un TDZ que Vue TRAGA** (`DECISIONES
// #210`). Hasta el 2026-08-28 esta línea estaba quince líneas más arriba, antes de la declaración:
// el getter lanzaba `ReferenceError: Cannot access 'cartStore' before initialization` en CADA
// montaje, Vue lo capturaba, lo escribía en consola y **llamaba al callback con `undefined`** — que
// `!== null`—, así que la carga saltaba una vez para todo el mundo (un `GET /me/dependents → 401`
// por visitante anónimo) y el observador nacía SIN dependencias: nunca volvía a dispararse. La
// spec de menores §9.9.8·4 daba este arreglo por hecho y no lo estaba. Lo vigila
// `SidebarSetupBindingsTest`.
watch(() => cartStore.owner, (owner) => { if (owner !== null) loadDependents(); }, { immediate: true });

/** El día de HOY en el huso del navegador es `cart.js::todayIso()` (se mudó en la tanda 4 de menores). */

/** Persiste la cesta. Nunca con `event_data`: eso lo garantiza el módulo (`DECISIONES #38(d)`). */

/**
 * El badge y el recuento salen del PRESUPUESTO, no de `cart.length`.
 *
 * ⚠️ Fue un fallo real de la web (P8): contar el array local incluye las líneas cuyo producto dejó de
 * venderse —que el presupuesto no tarifica— y el badge decía «1 artículo» sobre un total de 0,00 €.
 */


/**
 * Las filas que pinta el carrito: cada línea tarificada con las respuestas del pack emparejadas.
 *
 * ⚠️ El emparejado va por `index` y no por posición: una línea no vendible desaparece del presupuesto
 * y su hueco en la secuencia es la única señal de que existió.
 */


/** Pide el presupuesto de la cesta actual. Es la ÚNICA fuente de los importes del carrito. */



/**
 * Elegir día pide las HORAS, y la petición **lleva la cesta**.
 *
 * ⚠️ `AFORO-02`: `offerableTimes()` descuenta los ocupantes que la propia cesta ya retiene, así que
 * una consulta sin cesta ofrece horas que el checkout rechazaría. Hoy la cesta va vacía porque el
 * paso 4 llega después; el cuerpo ya viaja con su clave para que no se olvide al añadirla.
 */
async function selectDate(date) {
    dateStore.select(date);
    timeStore.clearSelection();
    store.go(STEPS.TIME);

    // ⚠️ La consulta LLEVA la cesta (`AFORO-02`); el porqué vive en la acción del store.
    await tracked(timeStore.loadOffer({
        api,
        productId: catalogStore.selectedId,
        date,
        cartLines: cartStore.lines,
    }));
}

/** Elegir hora fija la cantidad en el mínimo contratable y resuelve los complementos. */
async function selectTime(time) {
    timeStore.select(time);
    // ⚠️ La regla y su equivalencia con la del servidor —que PARECE distinta y no lo es— viven en
    // `offer.js` con la medición que lo demuestra.
    selectionStore.setQuantity(initialQuantity(catalogStore.product, timeStore.offered, time));

    await refreshAddons();
}

/**
 * Los complementos RESUELTOS contra la selección actual.
 *
 * ⚠️ La partición en grupos, las notas, las unidades gratis, los topes y la poda EN CADENA de las
 * dependencias las decide el servidor: reimplementarlas aquí es lo que `CE-4` prohíbe. La respuesta
 * trae además el dinero de la línea, para que un clic no cueste dos peticiones.
 */
async function refreshAddons() {
    await tracked(selectionStore.loadAddons({
        api,
        productId: catalogStore.selectedId,
        date: dateStore.selected,
        time: timeStore.selected,
    }));
}

/** El precio unitario a enseñar. La regla y su porqué, en `quantity.js` (`CE-6`). */
const unitPriceCents = computed(() => unitPriceToShow(selectionStore.line, dateStore.priceCents));

/**
 * El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2): qué hace ESTE producto.
 *
 * ⚠️ Son DOS estados y no un booleano con más fuerza: `optional` pinta una CASILLA —lo único que el
 * cliente sabe y el catálogo no— y `required` pinta una NOTA, porque ahí no hay nada que preguntar y
 * una casilla marcada e inerte invita a intentar desmarcarla.
 *
 * ⚠️ Un valor desconocido cae en «ninguno de los dos», que es el estado que no pinta nada: el
 * servidor ya lo sanea, y repetir aquí ese saneo sería una segunda regla que puede divergir.
 */
const guardianMode = computed(() => catalogStore.product?.guardian_authorization ?? 'none');


/**
 * Adopta una cantidad nueva, venga de `+`/`−` o del campo escrito. `null` = no cambia nada, y
 * entonces se ahorra la consulta. La regla y los extremos —los MISMOS para los dos caminos— viven en
 * `quantity.js`; aquí solo se aplica y se refrescan los complementos, cuyo precio por-invitado
 * depende de la cantidad.
 */
function applyQuantity(change) {
    const next = nextQuantity(change, {
        floor: catalogStore.minQuantity,
        ceiling: timeStore.maxQuantity,
        current: selectionStore.quantity,
    });

    if (next === null) return;
    selectionStore.setQuantity(next);
    refreshAddons();
}

function chooseAddon(group, productId) {
    selectionStore.choices = [...selectionStore.choices.filter((c) => c.group !== group), { group, product_id: productId }];
    refreshAddons();
}

function setAddonQuantity(productId, qty) {
    selectionStore.quantities = [...selectionStore.quantities.filter((a) => a.product_id !== productId), { product_id: productId, quantity: qty }];
    refreshAddons();
}

/**
 * Las cabeceras de día y el nombre del mes, en el idioma del documento.
 *
 * ⚠️ El servidor los compone con Carbon y su locale; aquí se usa `Intl`, así que **el texto visible
 * puede diferir** (§4.5 lo declara y la paridad lo compara explícitamente). Es el único punto del
 * paso 2 donde los dos motores no comparten la fuente del texto.
 */
const locale = document.documentElement.lang || 'es';

// El store NO lee el DOM a propósito —así se prueba con `node --test` sin navegador—, así que el
// idioma se le INYECTA desde aquí, el único sitio del cajón que sí puede mirarlo.
dateStore.setLocale(locale);

/**
 * La banda de progreso de las CINCO pantallas del camino, del día al pago (`#555`).
 *
 * ⚠️ **Hasta 4.3·1 esto era `null` fijo**, así que el cajón SPA vivo iba sin «Volver» y sin contador
 * de fases aunque el componente existiera y el gate lo comparase en verde: el diff alimenta a Vue con
 * el view-model del SERVIDOR. La composición vive en `progress.js` —módulo plano— para poder
 * compararla dato a dato.
 */
const progress = computed(() => buildProgress({
    step: store.step,
    productName: catalogStore.selectedRow?.name ?? '',
    date: dateStore.selected,
    time: timeStore.selected,
    messages: props.messages,
    locale,
}));

/**
 * Vuelve a resolver quién es el titular y purga la cesta si ha cambiado.
 *
 * ⚠️ **Un fallo de red NO es un cierre de sesión.** Solo un 401 significa «ya no hay nadie»; con
 * cualquier otro fallo se conserva la identidad conocida, porque purgar por un corte de red destruiría
 * la cesta de quien no ha hecho nada malo — y no habría manera de recuperarla.
 */
async function refreshIdentity() {
    return actOnIdentity(await cartStore.identify({ api }));
}

/**
 * Aplica una respuesta de identidad y **vuelve al catálogo si la cesta se purgó**.
 *
 * ⚠️⚠️ **La navegación vive AQUÍ y no en el store, y hay que llamarla desde los TRES sitios.** El
 * store decide y ejecuta la purga —vaciar y olvidar— pero no mueve el paso, porque un store que
 * navega es un store que sabe de embudos. Lo que no puede pasar es que se purgue la cesta y el
 * cliente se quede mirando un carrito vacío: eso fue una regresión real del 2026-08-22, introducida
 * al mudar la identidad y cazada al revisar los sitios de llamada uno a uno.
 */
function actOnIdentity(decision) {
    if (decision === 'purge') store.enter(STEPS.CATALOG);

    return decision;
}

/**
 * Traduce una respuesta de `GET /me` a la identidad de ahora y se la aplica a la cesta.
 *
 * Está aparte de `refreshIdentity()` desde 4.4a·1 porque el paso al pago pide `/me` **en paralelo**
 * con la elegibilidad —dos preguntas, un viaje— y necesita aplicar el resultado sin volver a pedirlo.
 * La regla del 401 vive aquí, en un solo sitio: es lo que impide que un corte de red purgue la cesta
 * de quien no ha hecho nada.
 *
 * @returns {'keep'|'purge'} qué se hizo con la cesta
 */

/**
 * Aplica una identidad a la cesta que hay en memoria.
 *
 * La purga alcanza a la cesta guardada Y a la de memoria: no basta con borrar el almacén, porque la
 * pantalla seguiría enseñando las líneas del titular anterior hasta la próxima recarga.
 *
 * @returns {'keep'|'purge'}
 */

/**
 * Lo que esta sección publica hacia fuera. Lo consume la RAÍZ, que se limita a reenviar.
 *
 * ⚠️ `refreshIdentity` pasa por `actOnIdentity()`: si la cesta se purga, esta sección vuelve a su
 * catálogo. Exponer el store pelado en su lugar perdería esa navegación — pasó dos veces el
 * 2026-08-22, la segunda al partir el componente.
 */
defineExpose({ refreshBookingStatus, refreshIdentity });

/**
 * El aviso de pausa, si toca en este paso (`paused.js`).
 *
 * ⚠️ No tapa los pasos de RESULTADO: quien vuelve de la pasarela tiene que ver en qué quedó su pago,
 * aunque las reservas se hayan pausado entre medias.
 */
const notice = computed(() => buildNotice({
    status: bookingStore.status,
    step: store.step,
    messages: props.messages,
}));

/**
 * El PIE, compuesto para el estado actual (`foot.js`).
 *
 * Devolver `null` es tan significativo como devolver una barra: en el catálogo con la cesta vacía y
 * en la cesta vacía el servidor no emite pie, y pintarlo igual enseñaría «0,00 €» donde la web no
 * enseña nada.
 */
const footer = computed(() => buildFooter({
    step: store.step,
    messages: props.messages,
    locale,
    cartCount: cartStore.count,
    cartTotalCents: cartStore.quote?.total_cents ?? 0,
    cartOnlineCents: cartStore.quote?.online_amount_cents ?? 0,
    hasDate: dateStore.selected !== null,
    hasTime: timeStore.selected !== null,
    lineTotalCents: selectionStore.line?.total_cents ?? null,
    lineHasDeposit: selectionStore.line?.has_deposit ?? false,
    lineDepositCents: selectionStore.line?.deposit_cents ?? 0,
    lineGateRemainderCents: selectionStore.line?.gate_remainder_cents ?? 0,
}));

/**
 * Las acciones del pie, con los MISMOS nombres que las del componente Livewire.
 *
 * Se conservan los nombres porque son el vocabulario de la paridad durante la convivencia: el
 * view-model del pie los publica y el test los compara campo a campo.
 */
function runAction(action) {
    if (action === 'goToTime') return goToTime();
    if (action === 'addToCart') return addToCart();
    if (action === 'goToCart') return goToCart();
    if (action === 'checkout') return checkout();
    if (action === 'confirmReservation') return confirmReservation();
}

/**
 * Los pasos que el motor SPA sabe PINTAR hoy. Un veredicto que lleve a otro **no navega**.
 *
 * ⚠️ No es una precaución teórica: ir al paso 8 antes de 4.5 dejaría el cajón con el armazón y la
 * ranura vacía —velo, banda y pie, y nada dentro—, que es peor que un CTA mudo. La lista **solo
 * crece**: al transcribir el pago se añade aquí y en `render-sidebar.mjs`, que es su espejo.
 */
const TRANSCRIBED_STEPS = [
    STEPS.CATALOG, STEPS.DATE, STEPS.TIME, STEPS.CART, STEPS.IDENTIFY, STEPS.VERIFY_EMAIL,
    STEPS.PAY, STEPS.REDIRECTING, STEPS.CONFIRMED, STEPS.DECLINED, STEPS.VERIFYING,
];

/** Lleva el cajón al paso que diga el veredicto, si esa pantalla ya existe. */
function goToVerdict(step) {
    if (TRANSCRIBED_STEPS.includes(step)) store.go(step);
}

/**
 * «Ir a pagar». Espejo de `Purchase::checkout()`.
 *
 * **Aquí solo está el cableado**: preguntar, aplicar la identidad y decidir es `runCheckout()`, que
 * vive en `admission.js` porque la lógica que baja a un `.vue` pierde su red (CE-6) — un árbol no
 * dice a quién se preguntó, en qué orden, ni si la cesta se purgó por el camino.
 *
 * Desde 4.4a·2 el invitado SÍ llega a su pantalla; el paso de pago sigue sin transcribir (4.5), así
 * que ese destino lo filtra `goToVerdict()`.
 */
async function checkout() {
    const verdict = await tracked(runCheckout({
        cartCount: cartStore.lines.length,
        // ⚠️ La guarda de las líneas incompletas la aplica el módulo, ANTES de preguntar nada al
        // servidor (`#38(d)`, 4.5·1): una cesta que no se puede comprar todavía no gasta dos peticiones.
        incompleteLines: hasPendingEventFields(cartStore.rows),
        api,
        messages: props.messages,
        applyIdentity: (response) => actOnIdentity(cartStore.applyIdentityResponse(response)),
        // ⚠️ **La pausa se enseña releyendo el estado, no pintando un error**: el componente Livewire
        // escribe su mensaje en el bag y el cartel de mantenimiento lo tapa antes de que llegue a
        // pintarse (medido). Releer es además lo que cierra el residual de 4.3·3 — un cajón ya
        // ABIERTO cuando se acciona el interruptor no se enteraba hasta cerrarlo y volver a abrirlo.
        refreshStatus: refreshBookingStatus,
    }));

    // El titular cambió: la cesta ya se purgó y el cajón está en el catálogo. No hay compra que seguir.
    if (verdict.purged) {
        return;
    }

    cartStore.error = verdict.error;
    goToVerdict(verdict.step);
}

// ── El paso 5: identificarse sin salir del cajón ──────────────────────────────────────────────

/**
 * Todo el paso 5 vive en `stores/auth.js` (reorganización del 2026-08-22): los campos de los dos
 * formularios, los avisos del último intento, la pestaña activa, el bit del anti-bot y las dos
 * secuencias de petición.
 *
 * ⚠️ **Lo que se queda AQUÍ es lo de después**: avisar a Livewire, aplicar la identidad a la cesta y
 * continuar el checkout cruzan tres dominios, así que son del embudo y no de la auth.
 *
 * ▶ Y es el dominio que compartirá el ÁREA DE CLIENTE (`DECISIONES #66`): entrar y darse de alta
 * serán suyos también, y encontrárselo ya fuera del componente del embudo era el objetivo.
 */
const authStore = useAuthStore();

/**
 * Envía las credenciales y, si entra, continúa la compra donde la dejó.
 *
 * ⚠️ **Tres cosas pasan al entrar, y ninguna sobra**:
 *  1. **se avisa de que hay sesión** (`account/session-gained.js`): repinta el bloque de cuenta, que
 *     hasta ese instante saluda como invitado, e invalida las próximas reservas del titular anterior.
 *     Sin esto el panel seguiría ofreciendo «Entrar» a alguien que acaba de entrar, y **ningún test
 *     de este repo lo vería** — solo el navegador. (Hasta el 2026-08-23 era un `dispatch('logged-in')`
 *     por el bus de Livewire, porque quien escuchaba vivía fuera del motor.);
 *  2. **se aplica la identidad** con la respuesta del propio login —`POST auth/login` devuelve el
 *     perfil con la misma forma que `GET /me` justo para esto—, así que la cesta de invitado se queda
 *     con su nuevo dueño sin una petición más;
 *  3. **se continúa el checkout**, que es lo que hace `Purchase::onAuthenticated()` llamando a
 *     `proceed()`: quien se identifica con el tope de pendientes lleno tiene que enterarse aquí.
 */
async function submitLogin() {
    const result = await tracked(authStore.login({ api, messages: props.messages, auth: props.auth }));

    if (! result.ok) return;

    await enterWith(result.response);
}

/**
 * Crea la cuenta y sigue la compra. Espejo de `Register::register()` embebido.
 *
 * ⚠️ **El 201 NO dice si hubo cuenta**, y por eso `runRegister()` pregunta después por `GET /me`: la
 * respuesta del alta es idéntica para un alta real y para un señuelo que actuó —si no lo fuera, un bot
 * distinguiría las dos de un vistazo—. Con sesión, la compra continúa como tras un login; sin ella, el
 * cajón va a «revisa tu correo», que es exactamente lo que hace la web con `registration-submitted`.
 *
 * ⚠️⚠️ **`CONTEXT_PURCHASE` va EXPLÍCITO desde el 2026-08-23**, y es lo que activa el pay-first en el
 * servidor: sin correo de verificación y con sesión abierta, porque el pago la sustituye. Estaba
 * quemado dentro de `register.js` mientras el embudo fue su único cliente; con el alta también en el
 * área de cliente, dejarlo allí habría convertido el alta SUELTA en pay-first sin que nadie lo
 * decidiera (`specs/auth-en-cajon.md` §4.3). Si esta línea pierde el contexto, el cliente que compra
 * se queda en «revisa tu correo» en mitad del embudo.
 */
async function submitRegister() {
    const result = await tracked(authStore.register({ api, messages: props.messages, auth: props.auth, context: CONTEXT_PURCHASE }));

    if (! result.ok) return;

    if (! result.identified) {
        // El señuelo actuó: misma pantalla que ve un alta legítima sin sesión. No se distingue.
        authStore.reset();
        goToVerdict(STEPS.VERIFY_EMAIL);

        return;
    }

    await enterWith(result.me);
}

/**
 * Lo que pasa cuando el cajón acaba de conseguir una sesión, venga de un login o de un alta.
 *
 * ⚠️ **Tres cosas, y ninguna sobra**:
 *  1. **se avisa de que hay sesión** (`account/session-gained.js`): repinta el bloque de cuenta, que
 *     hasta ese instante saluda como invitado, e invalida las próximas reservas del titular anterior.
 *     Sin esto el panel seguiría ofreciendo «Entrar» a alguien que acaba de entrar, y **ningún test
 *     de este repo lo vería** — solo el navegador. (Hasta el 2026-08-23 era un `dispatch('logged-in')`
 *     por el bus de Livewire, porque quien escuchaba vivía fuera del motor.);
 *  2. **se aplica la identidad** con la respuesta que ya se tiene —`POST auth/login` devuelve el perfil
 *     con la misma forma que `GET /me`, y el alta lo consulta—, así que la cesta de invitado se queda
 *     con su nuevo dueño sin una petición más;
 *  3. **se continúa el checkout**, que es lo que hacen `onAuthenticated()` y el alta embebida llamando
 *     a `proceed()`: quien entra con el tope de pendientes lleno tiene que enterarse aquí.
 */
async function enterWith(identity) {
    // ⚠️ Se ESPERA a propósito: el bloque de cuenta pide su contexto al servidor, y quien vuelve al
    // catálogo justo después tiene que encontrarse ya su nombre. No bloquea nada visible — el embudo
    // sigue en el paso 5 mientras tanto.
    await notifyLoggedIn();
    actOnIdentity(cartStore.applyIdentityResponse(identity));
    authStore.reset();
    // 4. (tanda 4) los menores del titular que acaba de entrar, y la puerta 2: con menores asignables
    //    y entradas sin asignar se vuelve al carrito (`DECISIONES #202`·1). La regla es de `admission.js`.
    await loadDependents();

    const verdict = await tracked(continueAfterIdentification({
        cartCount: cartStore.lines.length,
        me: identity,
        api,
        messages: props.messages,
        refreshStatus: refreshBookingStatus,
        needsAssignment: needsAssignment(cartStore.rows, dependentsStore.assignable.length),
    }));

    cartStore.error = verdict.error;
    cartStore.setNotice(verdict.notice);
    goToVerdict(verdict.step);
}

/**
 * Avisa de que hay sesión: al bloque de cuenta, al índice del área y al resto de la página.
 *
 * ⚠️⚠️ **Aquí se despachaba `logged-in` por el bus de Livewire, y ese evento MURIÓ el 2026-08-23**
 * (`specs/account-context-vue.md` §4.6). Existía porque quien escuchaba era `account-context`, un
 * componente Livewire **fuera** del motor: con el bloque ya dentro del cajón, el estado está a un
 * store de distancia y la vuelta por el bus no tenía sentido.
 *
 * Las tres cosas que hay que hacer —y por qué ninguna sobra— viven en `account/session-gained.js`,
 * módulo plano con su `node --test`. Aquí solo se le da acceso al mundo.
 */
function notifyLoggedIn() {
    return sessionGained();
}

// ── El paso 8: confirmar y salir hacia la pasarela ────────────────────────────────────────────

/**
 * Todo el DESENLACE vive en `stores/outcome.js` (reorganización del 2026-08-22): el formulario firmado
 * de la pasarela, el código del pedido en juego, el resumen, el motivo del rechazo, los dos
 * indicadores de «algo en vuelo» y el bloque de registro del parque.
 *
 * ⚠️ El SONDEO se queda aquí: su temporizador muere con el motor y su desenlace MUEVE el paso. Las dos
 * cosas son del embudo.
 */
const outcomeStore = useOutcomeStore();

// ⚠️ El código del pedido nace del MONTAJE: es lo que dejó la vuelta de la pasarela, y llega ya
// consumido por `Http\Sidebar\SidebarEntry` (mirarlo dos veces reabriría el cajón en cada página).
outcomeStore.setOrderCode(props.orderCode);

/**
 * **LO QUE EL COMPRADOR DEBE ANTES DE PAGAR** (`specs/auth-con-google.md` §21.4.2, `#349`).
 *
 * Vive aquí y no en un store porque **muere con la compra**: son dos campos de UNA pantalla, y en el
 * store global los pagaría en peso todo el que abre el cajón — el mismo criterio que `#343` aplicó a
 * la pantalla del alta con Google.
 *
 * ⚠️ **PII en un dispositivo compartido**: el teléfono se vacía al terminar, como los campos de auth.
 */
const buyerDue = reactive(emptyBuyerDue());
const accountContext = useAccountContextStore();
const buyerNeed = computed(() => buyerNeeds(accountContext.context, buyerDue.errors));


/**
 * «Pagar con tarjeta». Espejo de `Purchase::confirmReservation()`.
 *
 * ⚠️ **Una sola petición, y no es una simplificación**: `POST /orders` admite consumiendo ficha, crea
 * el pedido con su ventana de retención (`AFORO-10`) y abre el cobro, **en ese orden**, porque el orden
 * es una regla del dominio (`CheckoutOrchestrator`, `DECISIONES #37`). Partirlo en dos llamadas desde
 * aquí sería reimplementar esa secuencia en una superficie nueva, que es lo que `CheckoutSequenceTest`
 * prohíbe fuera de `app/Domain`.
 *
 * ⚠️ **Y a partir del 201 el pedido EXISTE y retiene aforo.** Por eso la cesta se vacía aquí y no
 * antes, y por eso un fallo del formulario no se trata como «no ha pasado nada»: se avisa igual que el
 * 502 del puerto de pasarela, que es lo que el cliente ha vivido.
 */
async function confirmReservation() {
    if (outcomeStore.confirming) return;

    outcomeStore.confirming = true;
    buyerDue.errors = {};

    try {
        const result = await tracked(runConfirm({
            // Con los menores asignados por línea (tanda 4): SOLO aquí, no en los endpoints públicos.
            items: toCheckoutItems(cartStore.lines),
            // Lo que el comprador debe (`#349`). El módulo los manda **solo si tienen valor**.
            buyer: { accept_terms: buyerDue.acceptTerms, phone: buyerDue.phone },
            api,
            messages: props.messages,
        }));

        if (result.rereadStatus) {
            await tracked(refreshBookingStatus());
        }

        // ⚠️⚠️ **Un «no» sobre lo que el comprador debe NO devuelve al carrito** (`#349`), y es la
        // única excepción de este desenlace. Su campo está en ESTA pantalla: mandar al cliente al
        // carrito para que arregle algo que se teclea aquí lo deja sin la corrección a la vista.
        // ▶ Y la pista del contexto puede haberse quedado corta —`terms_pending` se sembró al cargar
        // la página—, así que el «no» del servidor también ENCIENDE el campo: por eso `needsTerms` y
        // `needsPhone` miran además estos errores.
        if (result.due) {
            buyerDue.errors = result.due;

            return;
        }

        if (! result.ok) {
            // Un 422 sobre la asignación deja esas líneas sin asignar: el pedido no se creó (D3).
            cartStore.applyAssignmentRejections(result.fields);
            cartStore.error = result.error;
            // Espejo del componente Livewire: cualquier «no» al confirmar devuelve al CARRITO, que es
            // donde el cliente puede arreglarlo —quitar una línea, cambiar una franja—.
            goToVerdict(STEPS.CART);

            return;
        }

        // La reserva es firme: la cesta se vacía y se persiste vacía, para que una recarga no la
        // resucite y el cliente acabe comprando dos veces lo mismo.
        cartStore.empty();
        cartStore.persist();

        outcomeStore.setOrderCode(result.orderCode);
        outcomeStore.setGateway(result.form);
        store.go(STEPS.REDIRECTING);
    } finally {
        outcomeStore.confirming = false;
    }
}

// ── El DESENLACE de la pasarela: los pasos 6, 10 y 11 ─────────────────────────────────────────




/**
 * Trae lo que el paso 6 enseña.
 *
 * ⚠️ **Solo si el cajón está de verdad en un desenlace.** El código del pedido llega en el montaje de
 * TODAS las páginas cuando hay uno en sesión; pedir su resumen sin estar en la pantalla que lo pinta
 * sería dinero de peticiones a cambio de nada.
 */
async function loadOutcome() {
    if (outcomeStore.orderCode === '') {
        return;
    }

    if (store.step === STEPS.CONFIRMED) {
        await tracked(outcomeStore.loadConfirmation({ api }));

        return;
    }

    // El paso 10 necesita el motivo del rechazo, y sale del MISMO endpoint que sondea el paso 11: es
    // pequeño a propósito porque se pregunta en bucle. Livewire lo resuelve en `mount()` leyendo el
    // último `Payment` fallido; aquí lo pregunta quien lo pinta.
    if (store.step === STEPS.DECLINED) {
        const status = await tracked(loadPaymentStatus({ orderCode: outcomeStore.orderCode, api }));

        outcomeStore.applyDeclinedReason(props.messages, status);

        return;
    }

    if (store.step === STEPS.VERIFYING) startPolling();
}

// ── El paso 11: el sondeo ─────────────────────────────────────────────────────────────────────

/**
 * Cada cuánto se pregunta por el desenlace, en milisegundos.
 *
 * Son los mismos 5 s del `wire:poll.5s` de Livewire, y la cifra es paridad, no gusto. ⚠️ Lo que sí es
 * distinto es a QUIÉN le cuesta: el sondeo de Livewire va por su propio canal y éste gasta 12 fichas
 * por minuto del limitador genérico de la API (60/min, compartido con todo lo demás). Acelerarlo
 * estrecharía ese margen para el resto del cajón.
 */
const POLL_MS = 5000;

/** El temporizador del sondeo. `null` = no se está sondeando. */
let pollTimer = null;

/**
 * Arranca el sondeo del paso 11.
 *
 * ⚠️ **Un intervalo que no se para es un fallo que ningún diff de árbol puede ver**, y aquí hay dos
 * formas de dejarlo suelto: salir del paso 11 (el desenlace llegó) y desmontar el motor. Por eso hay
 * una sola función de parada, se llama desde las dos y `startPolling()` es idempotente — llamarla dos
 * veces dejaría dos temporizadores preguntando a la vez.
 */
function startPolling() {
    if (pollTimer !== null) return;

    pollTimer = setInterval(() => { void poll(); }, POLL_MS);
}

function stopPolling() {
    if (pollTimer === null) return;

    clearInterval(pollTimer);
    pollTimer = null;
}

/**
 * Una vuelta del sondeo. Espejo de `Purchase::checkPaymentStatus()`.
 *
 * ⚠️ **No pasa por `tracked()` y es deliberado**: encender el velo de carga cada cinco segundos haría
 * parpadear una pantalla cuyo mensaje es «espera». El velo es para lo que el cliente acaba de pedir.
 */
async function poll() {
    if (store.step !== STEPS.VERIFYING || outcomeStore.orderCode === '') {
        stopPolling();

        return;
    }

    const verdict = pollVerdict(await loadPaymentStatus({ orderCode: outcomeStore.orderCode, api }));

    if (verdict === 'wait') return;

    stopPolling();

    if (verdict === 'confirmed') {
        store.go(STEPS.CONFIRMED);
        // El paso 6 pide el pedido ENTERO, que este endpoint no trae: es pequeño justamente porque se
        // pregunta en bucle.
        await loadOutcome();

        return;
    }

    // Caducó antes de llegar la notificación. La plaza volvió al inventario, así que no hay nada que
    // reintentar: el cliente vuelve al catálogo con el mismo aviso que da la web.
    outcomeStore.orderCode = '';
    cartStore.error = t('errors.retry_expired');
    store.go(STEPS.CATALOG);
}

// ── El paso 10: el reintento ──────────────────────────────────────────────────────────────────


/**
 * «Reintentar el pago». Espejo de `Purchase::retryPayment()`.
 *
 * ⚠️ **El pedido NO se toca si algo falla**, al revés que al crearlo: sigue vivo con su retención recién
 * extendida, así que el cliente puede volver a intentarlo desde esta misma pantalla. La regla es del
 * dominio (`ReservationCheckout::retry()`); aquí solo se traduce el «no».
 */
async function retryPayment() {
    if (outcomeStore.retrying) return;

    outcomeStore.retrying = true;

    try {
        const result = await tracked(runRetry({ orderCode: outcomeStore.orderCode, api, messages: props.messages }));

        if (result.rereadStatus) await tracked(refreshBookingStatus());

        if (result.ok) {
            outcomeStore.declinedReason = '';
            cartStore.error = '';
            outcomeStore.gateway = result.form;
            store.go(STEPS.REDIRECTING);

            return;
        }

        // ⚠️ **Este aviso hoy NO SE VE en el paso 10, y es fiel: Livewire tampoco lo pinta.** Su bloque
        // no lleva `@error('cart')` y el pie es nulo en los pasos de resultado, así que un reintento
        // denegado por pausa, por frecuencia o por la pasarela deja el botón mudo en los dos motores.
        // Medido, no supuesto. Está anotado como deuda de PRODUCTO en `DEUDA.md`: arreglarlo cambia la
        // web, no la transcripción.
        cartStore.error = result.error;

        if (result.goTo === 'catalog') {
            outcomeStore.orderCode = '';
            outcomeStore.declinedReason = '';
            outcomeStore.gateway = null;
            store.go(STEPS.CATALOG);
        }

        // La sesión se perdió por el camino: `retryPayment()` hace `$this->step = $user ? 1 : 5`, y esa
        // salida está declarada en el mapa de transiciones desde 4.6·2.
        if (result.goTo === 'identify') store.go(STEPS.IDENTIFY);
    } finally {
        outcomeStore.retrying = false;
    }
}

/** Del calendario a la hora. Espejo de `Purchase::goToTime()`: exige día elegido. */
function goToTime() {
    if (dateStore.selected === null) return;

    store.go(STEPS.TIME);
}

/** Vuelve al carrito desde una compra en curso, si hay cesta. */
function goToCart() {
    if (cartStore.lines.length > 0) store.go(STEPS.CART);
}

/**
 * Añade la línea elegida a la cesta.
 *
 * ⚠️ **No decide: pregunta.** Qué puede entrar —producto elegible, franja ofrecida, cantidad, tope de
 * líneas, campos obligatorios del pack— y en qué queda —cantidad efectiva y fusión— lo dice
 * `POST /cart/validate-line`, el mismo contrato que consume la compra web desde 4.0b·6. Lo que sí es
 * de esta pantalla es la PRESENTACIÓN del «no»: validar al pulsar en vez de deshabilitar el CTA, y un
 * aviso que NOMBRA los campos que faltan.
 */
async function addToCart() {
    cartStore.setError('');
    cartStore.setFieldErrors({});

    const candidate = {
        product_id: catalogStore.selectedId,
        date: dateStore.selected,
        time: timeStore.selected,
        quantity: selectionStore.quantity,
        event_data: { ...selectionStore.eventData },
        // Lo que se guarda es la selección que el dominio RESOLVIÓ (obligatorios inyectados,
        // dependientes huérfanos podados), no la que se pidió.
        addons: selectionStore.resolved,
        // Los menores marcados en el paso 3 (tanda 4): solo ids; `addLine` los funde y recorta.
        dependent_ids: selectionStore.dependentIds,
        // El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2). ⚠️ Se manda
        // también cuando el producto lo EXIGE, y no porque haga falta —el servidor marca la línea
        // igual— sino para que la cesta pinte el aviso sin volver a consultar el catálogo.
        guardian_authorization: guardianMode.value === 'required' || selectionStore.guardianAuthorization,
    };

    const response = await tracked(cartStore.validateLine({ api, line: candidate }));

    if (! response.ok) {
        cartStore.setError(t('errors.choose_one'));

        return;
    }

    const verdict = response.data;

    if (! verdict.valid) {
        showLineProblems(verdict.problems ?? []);

        return;
    }

    cartStore.setLines(addLine(cartStore.lines, candidate, verdict));
    cartStore.persist();
    clearSelection();
    store.go(STEPS.CART);

    await tracked(cartStore.refreshQuote({ api }));
}

/**
 * Traduce el «no» del servidor a lo que esta pantalla enseña.
 *
 * La regla —qué campo se resalta, qué aviso se compone— es `line-problems.js` desde la tanda 4 de
 * menores (`CE-6`, y el presupuesto de este fichero solo encoge); aquí solo se aplica lo que devuelve.
 */
function showLineProblems(problems) {
    cartStore.applyLineProblems(lineProblems(problems, catalogStore.product?.event_fields ?? [], props.messages));
}

/** Quita una línea. Con la cesta vacía se vuelve al catálogo, como hace la web. */
async function removeLine(index) {
    // El store dice si la cesta quedó VACÍA; a dónde ir con esa noticia es del embudo, no suyo.
    if (cartStore.remove(index)) {
        store.enter(STEPS.CATALOG);

        return;
    }

    await tracked(cartStore.refreshQuote({ api }));
}

/** Contesta un campo del evento de una línea de la cesta. El porqué de que NO se persista, en el store. */
function updateCartField(index, key, value) {
    cartStore.updateField(index, key, value);
}

/**
 * «Añadir otra reserva»: vuelve al catálogo con la selección limpia.
 *
 * Espejo de `Purchase::addAnother()`, incluido su barrido del contexto del pedido anterior —código,
 * formulario firmado y resumen—: no se ve en el paso 1, pero dejarlo latente es lo que hace que una
 * segunda compra herede el desenlace de la primera.
 */
function addAnother() {
    clearSelection();
    outcomeStore.clear();
    store.enter(STEPS.CATALOG);
}

/** Deja la SELECCIÓN en blanco sin tocar la cesta. Espejo de `Purchase::clearSelection()`. */
function clearSelection() {
    catalogStore.clearSelection();
    dateStore.clearSelection();
    timeStore.clearSelection();
    selectionStore.clear();
}

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);

/**
 * **«Volver» de la banda, y desde `#555` el ÚNICO del embudo.**
 *
 * Hasta esta tanda la banda solo existía en los pasos 2 y 3, así que la cesta, la identificación y el
 * pago traían cada uno su propio `bk-back` encima del título — tres piezas para un gesto. Con la banda
 * en las cinco pantallas eso serían **dos «Volver» en la misma pantalla**, así que los tres se retiran.
 *
 * ⚠️ **A dónde vuelve y qué se deshace NO se decide aquí**: sale de la misma fila que su rótulo, en
 * `progress.js` (`CE-6`). Aquí solo se APLICA — que es lo que evita que el botón diga «Volver al
 * carrito» y lleve a otro sitio sin que nada falle.
 */
function goBack() {
    // ⚠️ `?? {}` y no una guarda: en un paso sin banda no hay «Volver» que pulsar, así que el plan
    // ausente no es un caso a contemplar — es que nadie puede llegar aquí. Sin destino no se navega.
    const { to, clear } = backPlan(store.step) ?? {};

    if (clear === 'time') {
        timeStore.clearSelection();
        selectionStore.setQuantity(0);
    } else if (clear === 'selection') {
        clearSelection();
        outcomeStore.clear();
    }

    if (to) store.go(to);
}
</script>

<template>
    <Shell :busy="busy" :progress="progress" :footer="footer" :notice="notice" :messages="messages" :ui="ui"
           @back="goBack" @action="runAction">
        <CatalogStep
            v-if="store.step === STEPS.CATALOG"
            :sections="catalogStore.sections"
            :search-enabled="catalogStore.searchEnabled"
            :messages="messages"
            @select="selectProduct" />

        <DateStep
            v-else-if="store.step === STEPS.DATE"
            :strip="dateStore.strip"
            :calendar-open="dateStore.calendarOpen"
            :weeks="dateStore.weeks"
            :weekday-headers="dateStore.weekdayHeaders"
            :month-label="dateStore.monthLabel"
            :can-prev="dateStore.canPrev"
            :can-next="dateStore.canNext"
            :selected-date="dateStore.selected"
            :messages="messages"
            @select="selectDate"
            @toggle-calendar="dateStore.toggleCalendar"
            @prev-month="dateStore.shift(-1)"
            @next-month="dateStore.shift(1)" />

        <TimeStep
            v-else-if="store.step === STEPS.TIME"
            :times="timeStore.offered"
            :low-max="timeStore.lowMax"
            :selected-time="timeStore.selected"
            :quantity="selectionStore.quantity"
            :min-quantity="catalogStore.minQuantity"
            :max-quantity="timeStore.maxQuantity"
            :is-pack="catalogStore.isPack"
            :day-price-cents="unitPriceCents"
            :event-fields="catalogStore.product?.event_fields ?? []"
            :period-label="catalogStore.product?.period_label ?? ''"
            :addons="selectionStore.addons"
            :errors="cartStore.fieldErrors"
            :messages="messages"
            :dependent-options="dependentsStore.optionsFor(messages)"
            :dependent-ids="selectionStore.dependentIds"
            :guardian-mode="guardianMode"
            :guardian-checked="selectionStore.guardianAuthorization"
            @select-time="selectTime"
            @toggle-guardian="selectionStore.setGuardianAuthorization"
            @inc="applyQuantity({ delta: 1 })"
            @dec="applyQuantity({ delta: -1 })"
            @set-qty="(raw) => applyQuantity({ raw })"
            @update-field="selectionStore.answer"
            @toggle-dependent="selectionStore.toggleDependent"
            @choose-addon="chooseAddon"
            @toggle-addon="(id) => setAddonQuantity(id, selectionStore.addons.singles.find((a) => a.product_id === id)?.selected ? 0 : 1)"
            @inc-addon="(id) => setAddonQuantity(id, (selectionStore.addons.singles.find((a) => a.product_id === id)?.quantity ?? 0) + 1)"
            @dec-addon="(id) => setAddonQuantity(id, Math.max(0, (selectionStore.addons.singles.find((a) => a.product_id === id)?.quantity ?? 0) - 1))" />

        <CartStep
            v-else-if="store.step === STEPS.CART"
            :lines="cartStore.rows"
            :error="cartStore.error"
            :messages="messages"
            :locale="locale"
            :dependent-options="dependentsStore.optionsFor(messages)"
            :notice="cartStore.notice"
            @remove="removeLine"
            @add-another="addAnother"
            @update-field="updateCartField"
            @toggle-dependent="cartStore.assign" />

        <IdentifyStep
            v-else-if="store.step === STEPS.IDENTIFY"
            v-model:form="authStore.form"
            :mode="authStore.mode"
            :login-errors="authStore.loginError"
            :register-errors="authStore.registerError"
            :submitting="authStore.busy"
            :messages="messages"
            :account="account"
            :turnstile-site-key="authStore.signupSiteKey"
            :google-url="urls.google ?? ''"
            @set-mode="authStore.setMode"
            @submit-login="submitLogin"
            @submit-register="submitRegister"
            @recover="authStore.startPasswordRecovery()" />

        <VerifyStep
            v-else-if="store.step === STEPS.VERIFY_EMAIL"
            :messages="messages" />

        <PayStep
            v-else-if="store.step === STEPS.PAY"
            v-model:accept-terms="buyerDue.acceptTerms"
            v-model:phone="buyerDue.phone"
            :lines="cartStore.rows"
            :error="cartStore.error"
            :messages="messages"
            :locale="locale"
            :need="buyerNeed"
            :due-errors="buyerDue.errors" />

        <RedirectStep
            v-else-if="store.step === STEPS.REDIRECTING"
            :form="outcomeStore.gateway"
            :messages="messages" />

        <ConfirmedStep
            v-else-if="store.step === STEPS.CONFIRMED"
            :confirmation="outcomeStore.confirmation"
            :order-code="outcomeStore.orderCode"
            :registration="outcomeStore.registration"
            :messages="messages"
            :locale="locale"
            @add-another="addAnother" />

        <DeclinedStep
            v-else-if="store.step === STEPS.DECLINED"
            :order-code="outcomeStore.orderCode"
            :reason="outcomeStore.declinedReason"
            :retrying="outcomeStore.retrying"
            :contact-url="urls.contact ?? ''"
            :messages="messages"
            @retry="retryPayment"
            @add-another="addAnother" />

        <VerifyingStep
            v-else-if="store.step === STEPS.VERIFYING"
            :order-code="outcomeStore.orderCode"
            :orders-url="urls.my_orders ?? ''"
            :messages="messages" />
    </Shell>
</template>
