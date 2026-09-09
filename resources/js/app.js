import { installScrollLock } from './ui/scroll-lock.js';
import { reveal } from './ui/account-host.js';
import { shouldHideNav } from './ui/nav-choreography.js';
import { installScrollMagnet } from './ui/scroll-magnet.js';
import { installHeroSwitch } from './ui/hero-switch.js';

// Livewire (Fase 4) trae su propio Alpine y lo arranca él. Por eso aquí NO
// importamos ni iniciamos Alpine: registramos nuestros componentes/almacenes
// dentro de `alpine:init` usando el Alpine global que expone Livewire.
// Ver docs/DECISIONES.md #29 (la landing usaba Alpine standalone "aún").

document.addEventListener('alpine:init', () => {
    // ── EL BLOQUEO DE SCROLL, con UN SOLO DUEÑO (`sidebar-spa.md` §6) ──────────────────────────
    // Cinco superpuestos tapan la página y hasta aquí cada uno escribía `body.no-scroll` por su
    // cuenta. Con un booleano y varios escritores, el último en cerrar manda: con el cajón de compra
    // abierto, cerrar el modal de auth —al que se llega desde el propio bloque de cuenta del cajón—
    // desbloqueaba el scroll con el panel todavía delante. Ahora cada uno pide y suelta SU llave.
    // La lógica y la manipulación de la clase viven en `ui/scroll-lock.js` —el dueño único, que
    // `ScrollLockOwnerTest` vigila—; esto es solo el envoltorio que le da acceso a las plantillas.
    const scrollLock = installScrollLock();

    // ── EL IMÁN DE LOS DOS PUNTOS ESTÁTICOS (`#252`, `[DECIDIDO owner]`) ───────────────────────
    // Solo en la portada, que es la única vista con heroes: en las otras once los dos recorridos
    // valen 0 y el módulo devuelve `null` sin tocar nada. Los recorridos se LEEN del CSS en cada
    // decisión —no se cachean— porque los dos cambian con el ancho y con `prefers-reduced-motion`,
    // donde valen 0 y el imán desaparece con ellos.
    const px = (el, prop) => (el ? parseFloat(getComputedStyle(el).getPropertyValue(prop)) || 0 : 0);
    installScrollMagnet({
        locked: () => document.documentElement.classList.contains('no-scroll'),
        runways: () => ({
            heroRunway: px(document.querySelector('.hero.hero--full'), '--hero-runway'),
            cierreRunway: px(document.querySelector('.reserve__runway'), 'height')
                || px(document.body, '--cierre-runway'),
        }),
    });
    window.Alpine.store('scrollLock', scrollLock);

    // ⚠️⚠️ **Aquí vivía `$store.auth`, el almacén del modal de autenticación, y se retiró el
    // 2026-08-23** (`specs/auth-en-cajon.md`, `DECISIONES #122`). Con él se van su llave del cerrojo,
    // el `data-auth-modal` que lo abría por URL, el vaciado visual de sus campos y el evento
    // `auth-modal-closed` que reseteaba los tres componentes Livewire en servidor.
    //
    // ⚠️ **Lo que hacía y NO tiene sucesor todavía**: la defensa de la tablet compartida. Aquel
    // almacén vaciaba los campos al cerrar y recargaba la página tras un alta, para que el siguiente
    // cliente no encontrara el correo del anterior en pantalla. En el cajón, el equivalente es
    // `stores/auth.js::reset()` —que ya limpia los tres formularios y el correo pendiente— pero
    // **nadie lo llama al CERRAR el cajón**: hoy solo se limpia al cambiar de zona. Está anotado en
    // `DEUDA.md` y en `specs/auth-en-cajon.md` §4.10·1.
    // ▶ El camino más frecuente sí queda cubierto por otra vía: al conseguir sesión el cajón NAVEGA
    // (`account/after-auth.js`), y una carga de página nueva se lleva el formulario entero.

    // Sidebar de compra de entradas (Fase 5.2): se abre sobre la página actual (sin
    // redirigir). El contenido (asistente paso a paso) es un componente Livewire `lazy`.
    window.Alpine.store('purchase', {
        isOpen: document.body.dataset.purchaseOpen === '1',
        /**
         * La ZONA del área de cliente con la que el servidor pide abrir (`AccountDoor`).
         *
         * Es lo que hace que las rutas de `/mi-cuenta/…` sigan llevando a algún sitio útil después de
         * que sus vistas se retiraran: **8 correos ya entregados** apuntan ahí. Mismo mecanismo que
         * `/entradas`, con una señal más.
         */
        accountZone: document.body.dataset.accountZone || '',
        // Se pone a true si el cliente inicia sesión DENTRO del sidebar (login embebido, #69):
        // el resto de la página (nav) se quedó con el estado de invitado y hay que refrescarlo.
        authChanged: false,
        // ── CONTRATO DEL MOTOR (Fase 4 · paso 4.0a) ────────────────────────────────────────
        // `mode` e `identifying` son señales que el CAJÓN publica y que consume gente de FUERA
        // del cajón: `layout.blade.php` pinta `is-{modo}` en `.sidecart__panel` (minimiza el
        // bloque de cuenta y posiciona el footer) y `livewire/site/account-context` deshabilita
        // sus botones de login con `identifying`.
        //
        // ⚠️ **Las escribe EL MOTOR, sea cual sea.** Hoy las escribe el puente reactivo de
        // `purchase.blade.php` (`x-effect` ← `$wire.step`), que es el único escritor. Un motor
        // nuevo que no las escriba deja el panel en `is-catalog` para siempre y los botones de
        // invitado activos durante la identificación — dos regresiones silenciosas, porque
        // ninguna de las dos clases aparece en el marcado del cajón: viven en el layout.
        // ────────────────────────────────────────────────────────────────────────────────────
        // «modo» del flujo: catalog | booking | cart | result.
        mode: 'catalog',
        setMode(m) {
            this.mode = m || 'catalog';
        },
        // `true` SOLO en el paso de identificación (login/registro embebido, paso 5). La escribe
        // el MOTOR (ver el contrato de arriba); hoy, el puente reactivo de `purchase.blade.php`.
        // El bloque de cuenta de invitado lo lee para BLOQUEAR sus botones «Iniciar sesión»
        // y «Ver mis reservas» (ambos abren el modal de login) mientras el flujo ya pide identificarse.
        // NO se resetea en close() a propósito: el componente persiste en el paso 5, así que al cerrar y
        // reabrir el sidebar con open() (sin round-trip Livewire → el x-effect no re-dispara) el bloqueo
        // debe SEGUIR activo. Solo cambia cuando cambia el paso (x-effect) o al recargar (default false).
        identifying: false,
        // ── COSTURA DE INTENCIÓN (Fase 4 · paso 4.0a, `docs/specs/sidebar-spa.md` §4.1) ──
        // Tres vistas de la landing no abren el cajón «vacío»: lo abren PIDIENDO algo concreto
        // —la sección de packs, o las entradas de una zona—. Hasta ahora lo hacían despachando
        // un evento de Livewire directamente desde el `@click`, lo que ataba la landing al motor
        // del cajón: con otro motor esos `dispatch` **no fallan, no hacen nada**, y el cliente
        // acaba en el catálogo raíz sin que nada avise.
        //
        // La intención se declara aquí, y CADA MOTOR registra cómo se aplica. La landing ya no
        // sabe qué hay dentro del cajón.
        intent: null,
        intentAdapter: null,
        /** El motor del cajón declara cómo se aplica una intención. */
        useIntentAdapter(fn) {
            this.intentAdapter = fn;
            this.flushIntent();
        },
        /** Abre el cajón pidiendo algo: `{ type: 'packs' }` · `{ type: 'zone', slug }`. */
        openWith(intent) {
            this.open();
            this.intent = intent;
            this.flushIntent();
        },
        flushIntent() {
            if (! this.intent || ! this.intentAdapter) return;

            const intent = this.intent;
            this.intent = null;      // se consume antes de aplicar: un adaptador que falle no la repite
            this.intentAdapter(intent);
        },
        // ── MOTOR SPA (Fase 4 · paso 4.1, `docs/specs/sidebar-spa.md` §4.7) ─────────────────
        // El entry de Vue se trae con `import()` en la PRIMERA apertura, nunca con la página: la
        // landing sirve hoy 15 kB de JS propio y meter Vue + Pinia + once pasos en el bundle de
        // todas las páginas públicas es un orden de magnitud más — y durante la convivencia del
        // flag se enviarían LOS DOS motores. El precedente correcto ya existía con `html2canvas`.
        //
        // ⚠️ Montar al ABRIR y no al cargar también evita el riesgo que sí toca `PERF-02`: una raíz
        // Vue ávida pidiendo catálogo en cada carga de landing añadiría una petición por visita en
        // la ruta de más tráfico del sitio.
        // ⚠️⚠️ **Aquí vivía `followAccountLink()`, y se retiró el 2026-08-23**
        // (`specs/account-context-vue.md` §4.9). Era el puente para un clic dado **mientras el chunk
        // del motor todavía cargaba**: conservaba el `href` y solo se tragaba el clic si el motor ya
        // estaba. Sus ÚNICOS dos llamantes eran los `@click` del bloque de cuenta, y ese bloque lo
        // pinta ahora Vue **dentro** del cajón — o sea que solo existe con el motor ya montado, y la
        // ventana que el puente cubría dejó de existir. Medido antes de borrar: cero llamantes.
        //
        // ⚠️ **`openAccount()` NO se fue, y la asimetría es la que ya estaba escrita**: aquél lo usan
        // los CTA del NAV, donde el cajón está CERRADO y el motor puede no existir todavía.
        /**
         * **Abre el cajón EN una zona de la cuenta, desde fuera de él.**
         *
         * ⚠️ Es distinto de `followAccountLink()` y las dos hacen falta. Aquél lo usan botones que
         * viven DENTRO del cajón —ya está abierto, solo hay que conmutar de sección—; éste lo usan
         * los de la cabecera, donde el cajón está **cerrado y el motor puede no existir todavía**.
         * Fundirlos habría dejado el caso de la cabecera abriendo una sección de un cajón invisible.
         *
         * ⚠️⚠️ **El `href` se conserva y solo se previene el default aquí**, igual que en el otro
         * puente: las tres rutas de auth siguen existiendo como PUERTA, así que un clic central, un
         * «abrir en pestaña nueva» o un navegador sin JS acaban en la misma pantalla por el camino
         * largo. Sin `href`, esos tres casos no harían **nada** (`DECISIONES #117`).
         *
         * ⚠️⚠️⚠️ **Y la zona se aplica colgando de la PROMESA, no después de `open()`.** El motor se
         * trae con `import()`: entre el clic y el montaje hay una ventana real en la que `spaHandle`
         * es `null`, así que aplicarla a continuación sería aplicarla sobre nada — el clic «no
         * fallaría y no haría nada». Se guarda en `accountZone` y la consume un único sitio.
         *
         * @param {MouseEvent} event
         * @param {string} zone
         */
        openAccount(event, zone) {
            if (! zone) return;

            event.preventDefault();
            this.accountZone = zone;
            this.open();
            this.bootSpaEngine()?.then?.((handle) => this.applyAccountZone(handle));
        },
        /**
         * **El ÚNICO sitio que consume `accountZone`.**
         *
         * ⚠️ Un solo consumidor no es estilo: la señal se **vacía** al aplicarla, y con dos sitios que
         * la lean uno acabaría llegando tarde a una zona ya consumida —o, peor, sin vaciarla, cerrar y
         * reabrir el cajón devolvería al cliente a esa pantalla una y otra vez—. Es la trampa que
         * 4.0a pagó con el desenlace del pago y que `#120(u)` volvió a pagar con la puerta por ruta.
         */
        applyAccountZone(handle) {
            if (! this.accountZone || ! handle) return;

            handle.showAccount(this.accountZone);
            this.accountZone = '';
        },
        spaHandle: null,
        spaLoading: false,
        async bootSpaEngine() {
            if (this.spaHandle || this.spaLoading) return this.spaHandle;

            const host = document.getElementById('sidecart-spa');
            if (! host) return null;      // motor Livewire: no hay hueco que montar

            this.spaLoading = true;

            try {
                const mod = await import('./sidebar/index.js');
                // Lo que el servidor dejó en el montaje: el desenlace del pago (ya consumido, con
                // un solo dueño desde el paso 4.0a) y las traducciones del grupo `tickets`.
                const boot = JSON.parse(host.dataset.boot || '{}');

                this.spaHandle = mod.mount(host, boot);
                this.useIntentAdapter((intent) => this.spaHandle.applyIntent(intent));

                // ⚠️⚠️ **La zona del área de cliente se aplica AQUÍ y no en `open()`, y esto lo cazó
                // el navegador** (`V12`): el cajón que llega por una puerta **nace abierto**, así que
                // `open()` no se llama nunca — es literalmente el mismo camino que dejó el hueco
                // vacío en `#59(b)` y que el bloque de más abajo documenta. `bootSpaEngine()` es el
                // punto por donde pasan los DOS.
                //
                // ⚠️ **Y se CONSUME**, igual que el desenlace del pago: sin vaciarla, cerrar y
                // reabrir el cajón devolvería al cliente a la zona una y otra vez y no podría llegar
                // al embudo sin recargar. Es la trampa que 4.0a pagó con `SidebarEntry`.
                //
                // ⚠️ Desde el 2026-08-23 la consumición vive en `applyAccountZone()` y no en línea:
                // los clics de la cabecera abren el cajón con el motor a medio cargar y necesitan el
                // mismo camino, y **dos sitios que vacíen la misma señal es la receta de que uno
                // llegue tarde**.
                this.applyAccountZone(this.spaHandle);
            } catch (e) {
                // Que el chunk no cargue (red caída, despliegue a media navegación) no puede dejar
                // el cajón abierto y mudo sin dejar rastro de por qué.
                console.error('[sidebar] no se pudo cargar el motor SPA', e);
                // ⚠️⚠️ **Y se REVELA el suelo del bloque de cuenta** (`specs/account-context-vue.md`
                // §4.8). El hueco nace colapsado con el formulario de cerrar sesión dentro; si el
                // motor no llega, esto es lo único que le queda al cliente para salir — medido,
                // `route('logout')` aparece UNA sola vez en toda la aplicación y es ésa. Sin esta
                // línea, un fallo de red deja a un titular sin poder cerrar sesión, y en un
                // dispositivo compartido eso no es una molestia.
                // ⚠️ Lo hace el dueño ÚNICO del hueco (`ui/account-host.js`), no un `classList` suelto
                // aquí: repartir esa clase entre dos escritores es la receta de `body.no-scroll`.
                reveal(document.getElementById('sidecart-account'));
                // Y tampoco puede dejar el velo girando para siempre: normalmente lo retira Vue al
                // montar (`container.textContent = ''`), pero si no hay montaje nadie lo haría. Un
                // spinner eterno MIENTE —dice «esto va a llegar»—; vaciarlo devuelve el cajón al
                // estado que tenía antes de que el velo existiera.
                host.textContent = '';
            } finally {
                this.spaLoading = false;
            }

            return this.spaHandle;
        },
        open() {
            this.isOpen = true;
            scrollLock.lock('sidecart');
            // ⚠️ Al abrir se RELEE el estado de las reservas: el motor SPA se monta una sola vez por
            // carga de página, así que sin esto la pausa solo entraría al recargar. En la primera
            // apertura el propio montaje ya la pide, y `refreshStatus` es un no-op sobre un motor que
            // todavía no existe.
            this.bootSpaEngine()?.then?.((handle) => {
                handle?.refreshStatus?.();
                // Y se re-resuelve el titular: la cesta del cajón vive en `localStorage` y lleva su
                // dueño dentro, así que abrir es el momento de comprobar que sigue siendo el mismo.
                handle?.refreshIdentity?.();
            });
        },
        close() {
            this.isOpen = false;
            // ⚠️⚠️ **Aquí se hacía `this.mode = 'catalog'`, y se RETIRÓ el 2026-08-23 porque era un
            // SEGUNDO ESCRITOR de una señal con dueño único.** El modo lo publica el motor —el `watch`
            // de `Sidebar.vue`, a partir de la sección activa y el paso del embudo— y escribirlo aquí
            // lo desincronizaba: al reabrir, el panel decía `is-catalog` mientras el cajón seguía en
            // «mi cuenta» o en el paso de la fecha, así que **el bloque de cuenta reaparecía** en
            // pantallas donde el CSS lo colapsa. Y el `watch` no lo corregía: no había cambiado nada
            // reactivo, así que no se volvía a disparar.
            // ▶ Era herencia del motor Livewire, donde reabrir provocaba un round-trip y el `x-effect`
            // re-publicaba el modo. Con la SPA ese round-trip no existe.
            // ▶ Es el mismo razonamiento que el párrafo de abajo lleva escrito para `identifying`
            // desde 4.0a — solo que a `mode` no se le aplicó.
            //
            // OJO: `identifying` NO se resetea aquí (ver su declaración). Si se pusiera a false, al
            // reabrir el sidebar sin round-trip Livewire el x-effect no re-dispararía y el botón de
            // login quedaría desbloqueado en pleno paso de identificación.
            scrollLock.unlock('sidecart');
            // Si hubo login dentro del sidebar, recargamos la PÁGINA ACTUAL (no navegamos a otro
            // sitio) para que el nav refleje la sesión. Al cierre, no a mitad del flujo; el carrito
            // vive en sesión, así que no se pierde nada. Mismo patrón que el modal (#51).
            if (this.authChanged) {
                window.location.reload();
            }
        },
        // ⚠️⚠️ **AQUÍ VIVÍA EL CONFETI, y retirarlo fue una decisión, no limpieza** (`#278`,
        // `[DECIDIDO owner]`: «quitamos el confeti, tampoco vamos a saturar al cliente»).
        //
        // ▶ **Ya marcaba lo mismo dos veces.** Desde `#258` el desenlace enseña la PEGATINA de éxito
        // —el estado del sistema de diseño del cliente—, y el artboard de estados escribe su propia
        // regla: «la pegatina de estado nunca convive con otra en la misma pantalla». El confeti era
        // la otra. Y el de movimiento pone el techo en dos piezas animándose a la vez: con confeti,
        // pegatina y sello eran tres.
        //
        // ▶ Lo que celebra ahora está en el CAJÓN y es del cliente: el check entra con su curva y el
        // código de la reserva se SELLA (`#278`). Dos piezas, que es el máximo que su norma admite.
        //
        // ⚠️ Eran 130 partículas a 150 fotogramas sobre un `<canvas>` a pantalla completa creado a
        // mano; nada lo echará de menos en un teléfono.
    });

    // ⚠️ Aquí vivía el adaptador de intención del motor LIVEWIRE, y BORRARLO FUE UN ARREGLO, no solo
    // limpieza (4.7·2b·3, `DECISIONES #111`). Se registraba en el arranque y SIN mirar el motor, así
    // que con el cajón SPA activo se quedaba con la intención del primer `openWith()` de cada carga
    // —`flushIntent()` la CONSUME antes de aplicarla— y la despachaba a un componente que ya no se
    // renderizaba. El adaptador de la SPA se registra dentro de `bootSpaEngine()`, tras un
    // `await import()`, así que siempre llegaba tarde y encontraba `intent === null`.
    // Efecto medido en staging: los tres enlaces profundos de la landing (zona, packs, eventos)
    // abrían el cajón en el catálogo raíz. Sin este adaptador, `flushIntent()` sale por
    // `! this.intentAdapter` CONSERVANDO la intención, que espera al `useIntentAdapter` de la SPA.

    // Consentimiento de cookies (#219): banner de 2 capas + bloqueo previo de iframes de tercero.
    // El estado inicial lo calcula el SERVIDOR (`CookieConsent::state`) y llega por `data-*` del body
    // (mismo patrón que `purchase`/`auth`). El servidor es la AUTORIDAD: este store solo refleja la
    // UI y dispara el POST que persiste la cookie canónica + registra la prueba (`cookie_consent_logs`).
    window.Alpine.store('cookies', {
        enabled: document.body.dataset.cookieEnabled === '1',
        decided: document.body.dataset.cookieDecided === '1',
        panel: false, // 2.ª capa (preferencias) abierta
        prefs: {
            maps: document.body.dataset.cookieMaps === '1',
            social: document.body.dataset.cookieSocial === '1',
        },
        get visible() {
            // Banner de 1.ª capa: solo si está habilitado y aún no hay una decisión registrada.
            return this.enabled && !this.decided;
        },
        acceptAll() {
            this.persist({ maps: true, social: true });
        },
        rejectAll() {
            this.persist({ maps: false, social: false });
        },
        // Conceder una categoría desde el placeholder del propio iframe («Cargar mapa/feed»).
        grant(category) {
            this.persist({ ...this.prefs, [category]: true });
        },
        openPanel() {
            this.panel = true;
        },
        closePanel() {
            this.panel = false;
        },
        savePanel(prefs) {
            this.persist(prefs);
        },
        persist(prefs) {
            this.prefs = { maps: !!prefs.maps, social: !!prefs.social };
            this.panel = false;
            const meta = document.querySelector('meta[name="csrf-token"]');
            // La URI llega por data-attr del body (route('cookies.consent')) → un rename de la ruta
            // no rompe la persistencia en silencio. Fallback defensivo a la ruta conocida.
            const endpoint = document.body.dataset.cookieEndpoint || '/cookies/consentimiento';
            // Atomicidad «iframe de tercero cargado ⇔ prueba RGPD persistida» (auditoría Fase 1 ·
            // Sistema 6 · W6): solo damos la decisión por buena —ocultamos el banner (`decided`) y
            // cargamos los iframes de tercero (`cookies-updated`)— si el SERVIDOR confirmó (`res.ok`).
            // `fetch` NO rechaza ante un HTTP de error, así que comprobamos `res.ok`: un 419 (CSRF
            // caducado tras la sesión), 429 (throttle) o 500 deja el banner visible y los terceros
            // bloqueados → no se instalan cookies de tercero sin su prueba acreditativa, y la próxima
            // visita vuelve a pedir la decisión. El `.catch` cubre además los fallos de red.
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': meta ? meta.content : '',
                },
                body: JSON.stringify(this.prefs),
            })
                .then((res) => {
                    if (! res.ok) {
                        return;
                    }
                    this.decided = true;
                    // Avisa a los iframes ya pintados (consentFrame) para que carguen sin recargar.
                    window.dispatchEvent(new CustomEvent('cookies-updated', { detail: this.prefs }));
                })
                .catch(() => {});
        },
    });

    // Bloqueo previo de un iframe de tercero (#219): solo carga con consentimiento. `consented`
    // (server) fija el estado inicial; si más tarde se concede la categoría (banner «Aceptar» o el
    // botón del placeholder), el evento `cookies-updated` inyecta el `src` desde `data-src` sin recargar.
    window.Alpine.data('consentFrame', (category, consented) => ({
        loaded: consented,
        _onUpdate: null,
        init() {
            // Guardamos la referencia del handler para poder retirarlo en destroy() (evita
            // acumular listeners huérfanos si la vista se desmontara con wire:navigate).
            this._onUpdate = (e) => {
                if (e.detail && e.detail[category] && !this.loaded) {
                    this.load();
                }
            };
            window.addEventListener('cookies-updated', this._onUpdate);
        },
        destroy() {
            if (this._onUpdate) {
                window.removeEventListener('cookies-updated', this._onUpdate);
            }
        },
        load() {
            const f = this.$refs.frame;
            if (f && f.dataset.src && !f.getAttribute('src')) {
                f.setAttribute('src', f.dataset.src);
            }
            this.loaded = true;
        },
        accept() {
            window.Alpine.store('cookies').grant(category);
        },
    }));

    // T4.1/T4.2 — accesibilidad de modal/sidecart: focus trap (Tab/Shift+Tab no escapa) y
    // foco automático al primer elemento interactivo cuando se abre el panel. Sin esto el
    // usuario de teclado/lector de pantalla puede tabular detrás del backdrop y perderse.
    // Implementación manual (~25 líneas) para no depender del plugin @alpinejs/focus.
    // ── EL CAJÓN PUEDE NACER ABIERTO, y eso tiene DOS consecuencias ────────────────────────────
    // El servidor lo abre solo en dos casos (`data-purchase-open`): el enlace profundo `/entradas` y
    // **la vuelta de la pasarela con un desenlace pendiente**.
    //
    // ⚠️ (1) **Hay que ARRANCAR EL MOTOR aquí.** `bootSpaEngine()` colgaba solo de `open()`, que en
    // este camino no se llama nunca: con `sidebar.engine = spa`, `/entradas` y —peor— la vuelta del
    // pago abrían el cajón con **el hueco VACÍO**. Encontrado en el extremo a extremo con navegador
    // (`VERIFICACION-E2E-CAJON.md`); ninguna paridad podía verlo, porque todas montan los componentes
    // por su cuenta y nunca pasan por este arranque. Es no-op con el motor Livewire (no hay hueco).
    //
    // ⚠️ (2) El bloqueo de scroll también es del dueño único, y aquí había una asimetría: el modal de
    // auth abierto al cargar (`/registro`) no bloqueaba nada, porque el `x-init` que lo hacía vivía
    // solo en el cajón. Pedir la llave aquí arregla los dos casos y retira ese sexto escritor.
    //
    // ⚠️ **Desde el 2026-08-23 el caso del modal ya no existe** (`DECISIONES #122`): `/registro` y sus
    // dos hermanas son PUERTAS que abren el CAJÓN, así que quien pide la llave al cargar es la rama
    // de arriba. Se retiró la segunda línea, no la explicación: la asimetría que documenta es la
    // razón de que esta llave se pida aquí y no en una plantilla.
    if (window.Alpine.store('purchase').isOpen) {
        scrollLock.lock('sidecart');
        window.Alpine.store('purchase').bootSpaEngine();
    }

    window.Alpine.data('a11yPanel', (openExpr) => ({
        _focusableSelector:
            'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])',
        init() {
            this.$watch(openExpr, (open) => {
                if (open) {
                    this.$nextTick(() => this.focusFirst());
                }
            });
            // Si el panel ya estaba abierto al cargar (auth-modal por URL, sidecart por /entradas).
            if (this.$data.$evaluate ? this.$data.$evaluate(openExpr) : false) {
                this.$nextTick(() => this.focusFirst());
            }
        },
        focusFirst() {
            const target = this.$el.querySelector(this._focusableSelector);
            target?.focus();
        },
        trap(e) {
            if (e.key !== 'Tab') return;
            const items = [...this.$el.querySelectorAll(this._focusableSelector)]
                .filter((el) => el.offsetParent !== null);
            if (items.length === 0) return;
            const first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (! e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        },
    }));

    // Tarjeta de invitación de cumpleaños editable (#8, 2026-06-01). El usuario edita
    // nombre/edad/fecha/hora y la tarjeta se previsualiza en vivo; puede descargarla como
    // PNG o compartirla. La librería html2canvas se carga BAJO DEMANDA (import dinámico →
    // chunk aparte de Vite) para no engordar el bundle de la landing. Compartir usa la
    // Web Share API nativa (sin dependencias) con descarga como fallback en escritorio.
    window.Alpine.data('birthdayInvite', (cfg = {}) => ({
        name: cfg.name || '',
        age: cfg.age ?? '',
        date: '',
        time: cfg.time || '17:00',
        // Color de la tarjeta (#231): selector INDEPENDIENTE del pack.
        // ⚠️ Guardaba la cadena `'jump'` y el blade la comparaba con ternarios, así que solo sabía
        // pintar dos zonas —las del primer cliente— y un parque con otras se quedaba con una sola
        // opción y un color ajeno (`DECISIONES #139`). Ahora es la CLAVE de una paleta y el estilo
        // completo llega en `invStyles`: el blade solo lo transporta, sin decidir nada.
        invZone: cfg.invZone || '',
        invStyles: cfg.invStyles || {},
        busy: false,
        park: cfg.park || '',
        labels: cfg.labels || {},

        // —— previsualización (getters reactivos) ——
        get displayName() {
            return (this.name || '').trim() || this.labels.nameFallback || '…';
        },
        get displayAge() {
            const n = parseInt(this.age, 10);
            return Number.isFinite(n) && n > 0 && n < 130 ? n : null;
        },
        get displayDate() {
            if (!this.date) return this.labels.dateFallback || '—';
            const d = new Date(this.date + 'T00:00:00');
            if (isNaN(d.getTime())) return this.labels.dateFallback || '—';
            try {
                return d.toLocaleDateString(document.documentElement.lang || 'es', {
                    weekday: 'long', day: 'numeric', month: 'long',
                });
            } catch (e) {
                return this.date;
            }
        },
        get displayTime() {
            return this.time || '—';
        },

        // —— exportación a imagen ——
        async _render() {
            const mod = await import('html2canvas');
            const html2canvas = mod.default || mod;
            const card = this.$refs.card;

            // 100% fidedigno (#231 p2): html2canvas NO resuelve var() dentro del SVG serializado
            // de la estrella ni en algunos contextos del clon → resolvemos --inv/--inv2 a un color
            // CONCRETO (rgb) con una sonda y los reinyectamos en el clon. Además, una clase de
            // captura deja la tarjeta RECTA y en reposo (sin animaciones a medias).
            const cs = getComputedStyle(card);
            const probe = document.createElement('span');
            probe.style.display = 'none';
            card.appendChild(probe);
            const resolve = (raw) => {
                probe.style.color = '';
                probe.style.color = (raw || '').trim() || 'transparent';
                return getComputedStyle(probe).color;
            };
            const invC = resolve(cs.getPropertyValue('--inv'));
            const inv2C = resolve(cs.getPropertyValue('--inv2'));
            card.removeChild(probe);

            return html2canvas(card, {
                backgroundColor: null,
                scale: 2,            // nitidez para móvil/retina
                useCORS: true,
                logging: false,
                onclone: (doc, clone) => {
                    clone.classList.add('bd-card--capturing');
                    clone.style.setProperty('--inv', invC);
                    clone.style.setProperty('--inv2', inv2C);
                    // El polígono del SVG no hereda var() en el clon serializado → fill concreto.
                    const poly = clone.querySelector('.bd-card__star-svg polygon');
                    if (poly) poly.setAttribute('fill', invC);
                },
            });
        },
        _save(canvas) {
            const link = document.createElement('a');
            link.download = (this.labels.fileName || 'invitacion') + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        },
        async download() {
            if (this.busy) return;
            this.busy = true;
            try {
                this._save(await this._render());
            } catch (e) {
                console.error('invite: download failed', e);
            } finally {
                this.busy = false;
            }
        },
        async share() {
            if (this.busy) return;
            this.busy = true;
            try {
                const canvas = await this._render();
                const blob = await new Promise((res) => canvas.toBlob(res, 'image/png'));
                const file = blob
                    ? new File([blob], (this.labels.fileName || 'invitacion') + '.png', { type: 'image/png' })
                    : null;
                const text = this.labels.shareText || '';
                if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({ files: [file], title: this.park, text });
                } else if (navigator.share) {
                    await navigator.share({ title: this.park, text, url: window.location.href });
                } else {
                    // Escritorio sin Web Share: descarga como alternativa.
                    this._save(canvas);
                }
            } catch (e) {
                // El usuario canceló el diálogo de compartir → no es un error.
                if (e && e.name !== 'AbortError') console.error('invite: share failed', e);
            } finally {
                this.busy = false;
            }
        },
    }));

    // Sección «Proceso» del cumpleaños (#231): 5 pasos de la reserva con un paso protagonista
    // + raíl de cubos. Auto-avanza suave hasta que el usuario interactúa (flechas/raíl), y se
    // detiene del todo entonces. Respeta `prefers-reduced-motion` (sin auto-avance).
    window.Alpine.data('birthdayProcess', (steps = [], deposit = 30) => ({
        steps,
        deposit,
        active: 0,
        touched: false,
        _timer: null,
        init() {
            if (!Array.isArray(this.steps) || this.steps.length < 2) return;
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this._timer = setInterval(() => {
                if (this.touched) return;
                this.active = (this.active + 1) % this.steps.length;
            }, 3400);
        },
        destroy() {
            if (this._timer) { clearInterval(this._timer); this._timer = null; }
        },
        go(i) {
            this.touched = true;
            if (this._timer) { clearInterval(this._timer); this._timer = null; }
            const n = this.steps.length || 1;
            this.active = ((i % n) + n) % n;
        },
    }));

    // "Reveal on scroll" del CTA filled del nav en DESKTOP (espejo del sticky móvil).
    // En páginas con hero (`body[data-has-hero]`), el `.nav-cta-med` empieza oculto
    // por CSS y aparece cuando el `.hero__sentinel` (el final del hero)
    // sale del viewport. En páginas sin marker no se observa nada y el CSS deja el
    // botón visible por defecto.
    //
    // Robustez:
    //  • Sin marker → cortocircuita en `init()` (otras páginas conservan el CTA visible).
    //  • Sin `.hero__sentinel` aún en DOM → cortocircuita (el CSS lo deja oculto;
    //    estado seguro: aunque pase, el CTA "prime" del hero sigue al cargar).
    //  • Sin `IntersectionObserver` (navegadores muy antiguos) → cortocircuita, el CTA
    //    queda oculto pero el CTA del hero está visible al cargar — no se pierde el
    //    acceso primario.
    //  • `destroy()` desconecta el observer (relevante si la página usa wire:navigate).
    // ════════════════════════════════════════════════════════════════════════════════
    // COREOGRAFÍA DEL HERO (`#195` · `specs/tema-por-instalacion.md` §12)
    // --------------------------------------------------------------------------------
    // El hero empieza a pantalla completa y encoge a una tarjeta centrada mientras se baja.
    //
    // ⚠️ Este componente **NO decide nada de diseño**: publica UNA custom property,
    //    `--hero-p`, con el progreso de 0 a 1. Los dos estados —margen, alto, ancho, hueco
    //    del nav— los define el CSS con `calc()`. Es lo que permite que un paquete de
    //    instalación cambie el efecto sin tocar JavaScript; si los números vivieran aquí,
    //    serían la única parte del tema que un cliente no podría cambiar.
    //
    // Robustez:
    //  • `prefers-reduced-motion` → NO se monta. `--hero-p` se queda en 0 y el CSS deja el
    //    hero en su estado de partida, que es completo por sí solo (y pone el recorrido a 0
    //    para que no quede una pantalla de scroll vacío).
    //  • Sin JS → lo mismo, sin ninguna rama que mantener.
    //  • El scroll va con `{ passive: true }` y coalesce en un `requestAnimationFrame`: como
    //    mucho una escritura por frame, aunque el navegador dispare veinte eventos.
    //  • No escribe si el valor redondeado no cambia — evita invalidar el estilo en cada
    //    frame cuando ya se ha llegado al final del recorrido, que es donde más tiempo se
    //    pasa el visitante.
    //  • `destroy()` suelta el listener y cancela el frame pendiente.
    // ══ EL HERO DEL CIERRE (`#229`) ══════════════════════════════════════════════════════════
    //  Publica `--cierre-p`, el progreso de 0 a 1 con el que la tarjeta de cierre crece hasta
    //  llenar la pantalla. **No decide nada de diseño**: cuánto crece, desde qué talla y con qué
    //  cantos lo dice el CSS, igual que en el hero de cabecera (`#195`).
    //
    //  ⚠️ El mockup hace esto con `position: fixed` y escribiendo `left`/`width`/`height` en cada
    //  fotograma. Aquí la tarjeta es `sticky` dentro de una sección con recorrido real, así que
    //  basta con medir cuánto se ha consumido ese recorrido. Menos código y, sobre todo, la
    //  tarjeta no sale nunca del flujo: no hay que devolverle su hueco a mano.
    //
    //  ⚠️ `prefers-reduced-motion` → NO se monta. `--cierre-p` se queda en 0 y el CSS deja la
    //  tarjeta en su talla de reposo; el recorrido también se anula ahí, así que no queda hueco.
    // ══ «SALTA LA CIUDAD» — el minijuego del hero del cierre (`#231`) ═════════════════════════
    //  Aquí vive el ESTADO (qué fase, qué se enseña); el motor vive en `site/salta.js` y no sabe
    //  nada de Alpine ni de esta página.
    //
    //  ⚠️⚠️ **Se carga con `import()` dinámico, y no es una optimización cosmética**: el bundle de
    //  la landing pesa ~19 KB y el motor otros ~15. Meterlo dentro lo casi duplicaría para TODOS
    //  los visitantes por algo que solo se alcanza al final del todo de la portada. Vite lo emite
    //  en su propio trozo y se descarga la primera vez que la tarjeta de cierre se abre.
    //
    //  ⚠️ **Y no se carga con `prefers-reduced-motion`… hasta que alguien lo pide.** El motor
    //  respeta la preferencia (pinta un fotograma quieto en vez de la demo), pero descargarlo
    //  igualmente costaría 15 KB a quien ha dicho que no quiere movimiento. Se trae al pulsar.
    window.Alpine.data('saltaJuego', () => ({
        fase: 'off',            // off · listo · jugando · fin
        metros: 0, pulseras: 0, record: 0, nuevoRecord: false,
        _motor: null, _cargando: false, _abierto: false, _io: null,
        get reduce() { return !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches; },

        /** La altura del lienzo, que es también el zoom del juego (`k = alto / 300`). */
        altoLienzo() {
            if (this.fase !== 'jugando' && this.fase !== 'fin') return 150;
            return Math.max(248, Math.min(358, Math.round((window.innerHeight || 760) * 0.44)));
        },

        /** Publica la altura de AHORA para que el marcador y el resultado se coloquen sobre ella. */
        _publicaAlto() {
            this.$el?.style.setProperty('--salta-h-now', this.altoLienzo() + 'px');
        },

        /** ¿Puntero grueso? El mockup cambia los rótulos: «Espacio para saltar» en un móvil es una
         *  instrucción que no se puede seguir. */
        get tactil() { return !!window.matchMedia?.('(pointer: coarse)').matches; },

        init() {
            this._onAbierto = (e) => { this.abierto(!!e.detail); };
            window.addEventListener('cierre:abierto', this._onAbierto);
            // ⚠️⚠️ **La DEMO arranca cuando el lienzo SE VE, no cuando la tarjeta llena la
            // pantalla** (`#256`, `[DECIDIDO owner]`: «que la animación del juego esté activada en
            // su punto estático»). Es lo que hace el mockup y nosotros no hacíamos: su bucle corre
            // en modo demo siempre que el lienzo está a la vista (`vigilaVista` → `casBucle`), y
            // `q > 0,985` allí solo decide si se puede JUGAR. Aquí el motor ni se descargaba hasta
            // ese umbral, así que en el punto estático la tira estaba **en blanco**.
            // ▶ **Y el anclaje NO sirve como señal, aunque lo parezca**: medido, en el punto
            //   estático la tarjeta está anclada a 1366, 1280 y 390 px, pero a 1440 y 1920 **no**
            //   —ahí la composición cabe con la sección todavía 20 px por debajo del tope—, así
            //   que el juego se habría quedado en blanco justo en las pantallas grandes.
            // ▶ Los dos umbrales siguen siendo dos y no se tocan el uno al otro: la VISTA enciende
            //   la animación, `cierre:abierto` la hace jugable. Sin esa separación, en el punto
            //   estático el espacio dejaría de desplazar la página para empezar una partida que
            //   nadie ha pedido — y la invitación saldría en una tarjeta que aún no es el juego.
            // ⚠️ `$nextTick`: en `init()` Alpine todavía no ha recorrido a los hijos, así que
            // `$refs.lienzo` puede no existir aún. Con el observador registrado en el tick
            // siguiente, el lienzo ya está y —si la página carga directamente al final— el
            // observador dispara de inmediato, sin esperar a que alguien desplace.
            this.$nextTick(() => this._observa());

            // ⚠️⚠️ **La tecla ARRANCA la partida, no solo salta — y ésta era la queja del owner.**
            // La primera versión solo actuaba con `fase === 'jugando'`, así que la única forma de
            // empezar era tabular hasta el botón y pulsar Enter. En el mockup el mismo manejador
            // hace las dos cosas: si no se juega, empieza; si se juega, salta.
            // ⚠️ Y sigue sin escucharse en `off`: fuera del cierre, el espacio tiene que seguir
            // haciendo scroll en toda la portada.
            const esSalto = (e) => e.key === ' ' || e.code === 'Space' || e.key === 'ArrowUp'
                || e.key === 'w' || e.key === 'W' || e.key === 'Enter';
            this._onTecla = (e) => {
                if (this.fase === 'off') return;
                if (!esSalto(e)) return;
                // Si el foco está en un botón, dejar que el navegador lo active él: si no,
                // «Enter» dispararía el juego Y el botón, y `Reservar` abriría el cajón a la vez.
                if (e.target instanceof HTMLElement && e.target.closest('button, a')) return;
                e.preventDefault();
                if (e.repeat) return;
                if (this.fase === 'jugando') this._motor?.pulsa();
                else this.juega();
            };
            // `Esc` sale de la partida, como dice el propio rótulo del marcador.
            this._onEsc = (e) => { if (e.key === 'Escape' && this.fase !== 'off' && this.fase !== 'listo') this.sal(); };
            window.addEventListener('keydown', this._onEsc);
            this._onSuelta = (e) => {
                if (e.type === 'keyup' && e.key !== ' ' && e.code !== 'Space' && e.key !== 'ArrowUp' && e.key !== 'w' && e.key !== 'W') return;
                this._motor?.suelta();
            };
            window.addEventListener('keydown', this._onTecla);
            window.addEventListener('keyup', this._onSuelta);
            window.addEventListener('pointerup', this._onSuelta);
            // El motor mira si el lienzo se ve en cada scroll: es su propio interruptor de encendido.
            this._onScroll = () => this._motor?.vigila();
            window.addEventListener('scroll', this._onScroll, { passive: true });
        },

        async carga() {
            if (this._motor || this._cargando) return this._motor;
            this._cargando = true;
            try {
                const { montaSalta } = await import('./site/salta.js');
                const cv = this.$refs.lienzo;
                if (!cv) return null;
                this._motor = montaSalta(cv, {
                    // ⚠️⚠️ **La altura del lienzo depende de la FASE, y de ahí sale el ZOOM.**
                    // El motor escala todo con `k = alto / 300`, así que la altura del lienzo ES
                    // el nivel de zoom. En reposo el lienzo es una tira decorativa de 150 px
                    // (k = 0,5); jugando ocupa el **44 % de la ventana** —entre 248 y 358— y k
                    // sube a ~1,19. Son los números del mockup (`altoJuego`).
                    // ▶ La primera versión usaba una altura FIJA de 156 px, así que jugando se
                    // veía a **k = 0,52 en vez de 1,19: el doble de lejos**. Lo cazó el ojo del
                    // owner, no una medición: un juego «más pequeño» no falla, se ve mal.
                    alto: () => this.altoLienzo(),
                    marcador: (m, p, r) => { this.metros = m; this.pulseras = p; this.record = r; },
                    fin: (m, p, nuevo) => { this.metros = m; this.pulseras = p; this.nuevoRecord = nuevo; this.fase = 'fin'; this._publicaAlto(); },
                    reduce: this.reduce,
                });
                this.record = this._motor.record;
                this._motor.vigila();
            } finally {
                this._cargando = false;
            }
            return this._motor;
        },

        /**
         * **Trae el motor la primera vez que el lienzo asoma por la ventana**, y se desengancha.
         *
         * ⚠️ Aquí SÍ vale un `IntersectionObserver`, y el motor explica por qué él no lo usa: su
         * lienzo se pega y CRECE con el scroll, y un observador sobre algo que cambia de tamaño da
         * entradas y salidas espurias. Eso importa cuando la respuesta se consulta sesenta veces
         * por segundo para encender y apagar un bucle; aquí la pregunta se hace **una vez** y se
         * cierra. Un falso positivo adelanta 12 kB; no hay falso negativo posible.
         *
         * ⚠️ **La fase NO cambia**: sigue en `off`, que es lo que deja el lienzo sin puntero, la
         * invitación oculta y el espacio desplazando la página. Lo que aparece es la demo.
         *
         * ⚠️ Con movimiento reducido no se trae nada: son 12 kB de una animación que esa persona
         * ha pedido no ver. Es la misma promesa que `abierto()`.
         *
         * ⚠️ Sin `IntersectionObserver` no se hace nada y el motor sigue llegando por
         * `cierre:abierto`, que es exactamente la conducta que había antes de `#256`: se pierde la
         * demo en reposo, no el juego.
         */
        _observa() {
            if (this.reduce || !('IntersectionObserver' in window)) return;
            const cv = this.$refs.lienzo;
            if (!cv) return;
            this._io = new IntersectionObserver((entradas) => {
                if (!entradas.some((e) => e.isIntersecting)) return;
                this._io.disconnect();
                this._io = null;
                this.carga();
            });
            this._io.observe(cv);
        },

        async abierto(si) {
            if (si) {
                if (this.fase === 'off') this.fase = 'listo';
                this._publicaAlto();
                // Con movimiento reducido no se precarga: el fondo no se va a mover de todas formas.
                if (!this.reduce) await this.carga();
            } else if (this.fase !== 'off') {
                this._motor?.para();
                this.fase = 'off';
            }
        },

        async juega() {
            const m = await this.carga();
            if (!m) return;
            this.metros = 0; this.pulseras = 0; this.nuevoRecord = false;
            this.fase = 'jugando';
            this._publicaAlto();
            m.arranca();
        },

        /**
         * ⚠️⚠️ **En TÁCTIL el lienzo no arranca la partida y no cancela el gesto** (`#253`), y las
         * dos mitades arreglan el mismo fallo que el owner vio en un teléfono: *«no puedo hacer
         * scroll hacia arriba, me quedo bloqueado en el juego»*.
         * · `preventDefault()` sobre un `pointerdown` **cancela el gesto de desplazar** que el
         *   navegador iba a empezar con ese dedo. Sobre un lienzo que ocupa el ancho de la tarjeta
         *   a pantalla completa, eso deja al visitante encerrado. En ratón sí se llama: ahí no hay
         *   gesto que cancelar y evita el `click` fantasma.
         * · Y **tocar el lienzo no EMPIEZA la partida**: para eso está el botón, que es explícito.
         *   Es la regla del mockup (`pulsaJuego`: `if (f !== 'jugando' && lienzo && touch) return`),
         *   y sin ella el primer arrastre para desplazar arranca un juego que nadie pidió.
         */
        toca(e) {
            if (this.fase === 'off') return;

            // Un botón o un enlace se activa SOLO: es la misma regla que ya aplica el manejador de
            // teclado, y sin ella tocar «Otra vez» o «Reservar» dispararía las dos cosas.
            if (e.target instanceof HTMLElement && e.target.closest('button, a')) return;

            const tactil = e.pointerType === 'touch';

            // ── JUGANDO: cualquier punto de la tarjeta salta ──────────────────────────────────
            // Responde al `pointerdown` y no al `up` a propósito: un juego se juega con la latencia
            // del dedo que baja, no con la del que se levanta.
            if (this.fase === 'jugando') {
                if (! tactil) e.preventDefault();
                this._motor?.pulsa();

                return;
            }

            // ── NO SE JUEGA: empezar ─────────────────────────────────────────────────────────
            // Con ratón, al bajar: ahí no hay gesto de desplazar que confundir.
            if (! tactil) { e.preventDefault(); this.juega(); return; }

            // ⚠️⚠️ **Con el DEDO no se empieza aquí: se anota y se decide al levantar** (ver
            // `sueltaTap`). Es lo que concilia la petición del owner —tocar cualquier parte del
            // hero empieza la partida— con la mitad de `#253` que sigue viva: *«sin ella el primer
            // arrastre para desplazar arranca un juego que nadie pidió»*.
            this._tap = { x: e.clientX, y: e.clientY, t: performance.now() };
        },

        /**
         * **El toque que EMPIEZA la partida, distinguido de un arrastre para desplazar** (`#352`).
         *
         * `[owner, 2026-09-02]`: *«darle tap a cualquier parte del hero empieza a jugar y puede
         * saltar, no solo en la parte inferior»*. Hasta hoy solo respondía el lienzo, que es una
         * tira pegada al borde inferior de la tarjeta.
         *
         * ⚠️⚠️ **Y aquí está la mitad delicada, porque `#253` decidió lo contrario POR UN MOTIVO
         * QUE SIGUE SIENDO CIERTO**: en aquella tanda el owner se quejó de quedarse *«bloqueado en
         * el juego»* en un teléfono, y una de las dos causas fue que tocar el lienzo arrancaba la
         * partida — así que el primer arrastre para desplazar empezaba un juego que nadie pidió.
         * ▶ Lo que se cambia es **dónde** se puede tocar, no **qué cuenta como toque**: un dedo que
         * se mueve más de 12 px o que se queda más de 700 ms es un ARRASTRE o una pulsación larga,
         * y no empieza nada. La otra mitad de `#253` —no llamar a `preventDefault()` en táctil, que
         * es lo que de verdad cancelaba el gesto de desplazar— **no se toca**.
         */
        sueltaTap(e) {
            const tap = this._tap;
            this._tap = null;

            if (! tap || this.fase === 'off' || this.fase === 'jugando') return;
            if (e.target instanceof HTMLElement && e.target.closest('button, a')) return;
            if (Math.hypot(e.clientX - tap.x, e.clientY - tap.y) > 12) return;
            if (performance.now() - tap.t > 700) return;

            this.juega();
        },

        sal() { this._motor?.para(); this.record = this._motor?.record ?? this.record; this.fase = 'listo'; this._publicaAlto(); },

        destroy() {
            this._io?.disconnect();
            window.removeEventListener('cierre:abierto', this._onAbierto);
            window.removeEventListener('keydown', this._onEsc);
            window.removeEventListener('keydown', this._onTecla);
            window.removeEventListener('keyup', this._onSuelta);
            window.removeEventListener('pointerup', this._onSuelta);
            window.removeEventListener('scroll', this._onScroll);
            this._motor?.destruye();
        },
    }));

    window.Alpine.data('cierreChoreo', () => ({
        _raf: null, _last: null, _lastQ: null, _fija: false, _r0: null, _abierto: false,
        init() {
            if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
            this._onScroll = () => {
                if (this._raf !== null) return;
                this._raf = requestAnimationFrame(() => { this._raf = null; this.apply(); });
            };
            window.addEventListener('scroll', this._onScroll, { passive: true });
            this._onResize = () => { this._r0 = null; this._last = null; this._lastQ = null; this.suelta(); this._onScroll(); };
            window.addEventListener('resize', this._onResize, { passive: true });
            this.apply();
        },

        /** Devuelve la tarjeta al flujo y limpia lo que solo vale anclada. */
        suelta() {
            if (!this._fija) return;
            this.$el.classList.remove('reserve--fija');
            document.body.classList.remove('cierre--anclado');
            this._fija = false;
        },

        /**
         * ⚠️⚠️ **Se publican DOS progresos, y confundirlos fue un fallo real** (`#250`).
         * `p` es el progreso SUAVIZADO y manda la geometría —la tarjeta crece con él—; `q` es el
         * CRUDO, y es el que manda todo lo demás: la retirada del armazón, el umbral de «esto ya
         * está a pantalla completa» y el arranque del minijuego. En el mockup son dos variables
         * distintas (`q` y `e`) y solo `e` entra en los `lerp`.
         * ▶ Medido con la fórmula del mockup al lado: usando el suavizado para la retirada, al 7 %
         * del crecimiento su armazón valía **0,19 de opacidad y el nuestro 0,55** — el suavizado va
         * por detrás del crudo justo en el tramo donde el armazón tiene que irse.
         */
        publica(p, q) {
            if (p === this._last && q === this._lastQ) return;
            this._last = p; this._lastQ = q;
            this.$el.style.setProperty('--cierre-p', String(p));
            // ⚠️ **También en el `<body>`**: quien lo lee —el armazón, que se retira ante el
            // cierre— es HERMANO de esta sección, y una custom property solo baja por el árbol.
            document.body.style.setProperty('--cierre-p', String(p));
            document.body.style.setProperty('--cierre-q', String(q));
            document.body.classList.toggle('cierre--live', q >= 0.3);
            const abierto = q >= 0.985;
            if (abierto !== this._abierto) {
                this._abierto = abierto;
                window.dispatchEvent(new CustomEvent('cierre:abierto', { detail: abierto }));
            }
        },

        apply() {
            const el = this.$el;
            const caja = el.querySelector('.reserve__box');
            const pie = document.querySelector('.foot');
            const carril = document.querySelector('.reserve__runway');
            if (!caja || !pie || !carril) return;

            const vh = window.innerHeight;
            const y = window.scrollY || 0;
            const maxY = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight) - vh;
            const tope = parseFloat(getComputedStyle(el).paddingTop) >= 0
                ? (parseFloat(getComputedStyle(caja).top) || 10) : 10;

            // ⚠️ **El alto del PIE, publicado, y es lo único que el CSS no puede saber** (`#254`).
            // `[DECIDIDO owner]`: en el punto estático la tarjeta puede ser más alta, porque el
            // armazón deja de tener que verse ahí. «Más alta» solo significa algo si es *el hueco
            // que deja el pie*: una talla fija volvería a no caber en la siguiente ventana.
            // Se mide en cada decisión —no se cachea— porque el pie cambia de alto con el ancho y
            // con las imágenes que carguen tarde (`DEUDA`).
            document.body.style.setProperty('--foot-h', Math.round(pie.offsetHeight) + 'px');

            // ⚠️ **«En la cola» y «anclada» son DOS condiciones, y hacen falta las dos.** Con solo
            // «la sección ha llegado arriba», la tarjeta se fijaría también al pasar por delante
            // en un scroll rápido hacia arriba. Con solo «estamos al final», se fijaría antes de
            // llegar. Son los dos filtros del mockup (`aplicaCierre`).
            const enCola = maxY > vh && (maxY - y) <= pie.offsetHeight + carril.offsetHeight + 80;
            const anclada = enCola && el.getBoundingClientRect().top <= tope;

            if (!anclada) {
                this.suelta();
                // Su caja NATURAL se mide mientras está en el flujo: una vez fija ya no se puede.
                this._r0 = caja.getBoundingClientRect();
                this.publica(0, 0);
                return;
            }

            if (!this._fija) {
                const r0 = this._r0 || caja.getBoundingClientRect();
                // El JS publica lo único que el CSS no puede saber: dónde y cuánto mide la tarjeta
                // en el flujo. El estado final y la curva siguen en la hoja.
                el.style.setProperty('--c-x0', Math.round(r0.left) + 'px');
                el.style.setProperty('--c-w0', Math.round(r0.width) + 'px');
                el.style.setProperty('--c-h0', Math.round(r0.height) + 'px');
                el.classList.add('reserve--fija');
                // ⚠️ **El armazón se retira EN CUANTO la tarjeta se ancla** (`#254`,
                // `[DECIDIDO owner]`: «no hace falta que el punto estático tenga el logo, el CTA y
                // el menú a la vista»). Antes empezaba a irse con el progreso (`q > 0,02`), o sea
                // que en el punto estático estaba entero. Ahora el anclaje es la señal, y por eso
                // es una CLASE y no un número: no es una interpolación, es un hecho.
                document.body.classList.add('cierre--anclado');
                this._fija = true;
            }

            // ⚠️ El progreso va de «el pie cabe entero en la ventana» hasta «el final del
            // documento», que es el recorrido que abre `.reserve__runway` detrás del pie.
            const pieR = pie.getBoundingClientRect();
            const yExp = y + pieR.bottom - vh;
            const bruto = Math.min(1, Math.max(0, (y - yExp) / Math.max(1, maxY - yExp)));
            // La curva del mockup: arranque lineal y final que se posa. No la cúbica del hero —
            // aquí lo que crece tiene que notarse desde el primer píxel o parece que no pasa nada.
            const p = Math.round((0.22 * bruto + 0.78 * (bruto * bruto * (3 - 2 * bruto))) * 1000) / 1000;
            this.publica(p, Math.round(bruto * 1000) / 1000);
        },

        destroy() {
            if (this._onScroll) window.removeEventListener('scroll', this._onScroll);
            if (this._onResize) window.removeEventListener('resize', this._onResize);
            if (this._raf !== null) cancelAnimationFrame(this._raf);
        },
    }));

    window.Alpine.data('heroChoreo', () => ({
        _raf: null,
        _last: null,
        _lastNav: null,
        _onScroll: null,
        init() {
            if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
            this._onScroll = () => {
                if (this._raf !== null) return;
                this._raf = requestAnimationFrame(() => { this._raf = null; this.apply(); });
            };
            window.addEventListener('scroll', this._onScroll, { passive: true });
            window.addEventListener('resize', this._onScroll, { passive: true });
            this.apply();
        },
        apply() {
            // El recorrido lo declara el CSS (`--hero-runway`), no este fichero: así el
            // cliente puede alargarlo o acortarlo desde su paquete.
            const cs = getComputedStyle(this.$el);
            const runway = parseFloat(cs.getPropertyValue('--hero-runway')) || 0;
            if (runway <= 0) return;                       // movimiento reducido, o sin recorrido
            const raw = Math.min(1, Math.max(0, window.scrollY / runway));
            // Curva de salida cúbica: rápida al principio, se posa al final. Es la del mockup.
            const p = Math.round((1 - Math.pow(1 - raw, 3)) * 1000) / 1000;

            // ══ EL ARMAZÓN ENTRA CON EL HERO (armazón · tanda 2c·8, `#216`) ═══════════════════
            // `[DECIDIDO owner, 2026-08-28]`: como el mockup. Logo, CTA y hamburguesa **no están**
            // mientras el hero llena la pantalla; entran cuando el hero empieza a encoger.
            //
            // ⚠️ **Sale del MISMO recorrido, no de un segundo observador.** El mockup lo calcula
            // así (`aplicaFlotantes`): `nb = (bruto − 0,18) / 0,44`, sobre el progreso LINEAL del
            // hero, y encima la misma curva cúbica. Con dos señales distintas —un
            // `IntersectionObserver` por un lado y el scroll por otro— el armazón podía entrar
            // antes o después que el hero según el navegador; con una sola, van pegados.
            //
            // ⚠️ **El retardo y la ventana los declara el CSS**, igual que el recorrido: son
            // TIEMPO, y el tiempo es tema. Aquí solo se aplica la aritmética.
            const start = parseFloat(cs.getPropertyValue('--nav-reveal-start'));
            const span = parseFloat(cs.getPropertyValue('--nav-reveal-span'));
            const live = parseFloat(cs.getPropertyValue('--nav-reveal-live'));
            let navP = 1;
            if (span > 0) {
                const nb = Math.min(1, Math.max(0, (raw - (start || 0)) / span));
                navP = Math.round((1 - Math.pow(1 - nb, 3)) * 1000) / 1000;
            }

            if (p === this._last && navP === this._lastNav) return;
            this._last = p;
            this._lastNav = navP;
            this.$el.style.setProperty('--hero-p', String(p));
            // ⚠️ Va en el `<body>`, no en el hero: quien lo lee —los dos racimos— es hermano del
            // hero, no descendiente suyo, y una custom property solo baja por el árbol.
            document.body.style.setProperty('--nav-p', String(navP));
            // ⚠️ **Un hecho binario, y hace falta**: `opacity: 0` NO deja de recibir clics. La
            // clase dice «ya se puede tocar»; qué significa eso lo decide el CSS.
            document.body.classList.toggle('nav--live', navP >= (live || 1));
        },
        destroy() {
            if (this._onScroll) {
                window.removeEventListener('scroll', this._onScroll);
                window.removeEventListener('resize', this._onScroll);
            }
            if (this._raf !== null) cancelAnimationFrame(this._raf);
            // ⚠️ Se DEVUELVE el armazón al desmontar. Con `wire:navigate` el hero desaparece y
            // este componente con él; si se quedara el último valor publicado, la página
            // siguiente heredaría un armazón invisible y sin hero que lo devolviera.
            document.body.style.removeProperty('--nav-p');
            document.body.classList.remove('nav--live');
        },
    }));

    // ⚠️⚠️ **Aquí vivía `navCtaReveal`, y se RETIRA con la tanda 2c·8** (`#216`). Su trabajo
    // —ocultar el CTA de comprar mientras dura el hero y destaparlo al pasarlo— lo hace ahora la
    // coreografía del armazón, que oculta **los tres** (logo, CTA y hamburguesa) como el mockup.
    // ▶ Mantener los dos era tener **dos mecanismos ocultando el mismo botón con señales
    // distintas** —un `IntersectionObserver` sobre el sentinel y el progreso del scroll—, que es
    // exactamente la clase de solape que se ve bien en una máquina y mal en otra.
    // ⚠️ Con él se va `body.nav-cta-revealed`, que **ninguna regla de CSS leía**: se ponía y se
    // quitaba para que otros elementos «pudieran reaccionar» y nadie llegó a reaccionar nunca.
    // ▶ El `.hero__sentinel` NO se retira: lo sigue observando `mobileBookBar`, que es de quien
    // era la otra mitad del trabajo.

    // Barra flotante de reserva en MÓVIL (mockup `design_mockup/jerarquia-ctas.html` §03). En móvil
    // el CTA «Reservas aquí» sale del header y reaparece como barra fija inferior:
    //  • LANDING (`body[data-has-hero]`, la única página con hero): aparece cuando el sentinel del hero
    //    (`.hero__sentinel`) abandona el viewport → hero y barra nunca co-visibles (misma señal que
    //    el reveal del header). Sigue oculta en la primera pantalla.
    //  • RESTO DE PÁGINAS (sin hero): aparece al hacer scroll, con un umbral MENOR que la landing
    //    (~1/3 de pantalla, no el hero completo) → emerge antes.
    //  • En AMBOS casos se oculta cuando el pie real (`.foot`) entra en viewport (no tapa legales/idioma);
    //    el resto de capas que la ocultan (sidecart abierto, banner de cookies) las cubre el `:class` del blade.
    // El deslizamiento (translateY) y la visibilidad responsive (solo <=720px) viven en CSS (`.book-bar`).
    window.Alpine.data('mobileBookBar', () => ({
        // ⚠️ **`mode` ya NO vive aquí**: subió al store `ctaPair` en la 2c·7, porque el racimo de
        // la cabecera y esta barra son la MISMA decisión y no puede haber dos. El arranque en
        // `buy` —comprar cuesta un gesto y registrarse dos— se conserva, ahora en el store.
        revealed: false,
        nearFoot: false,
        _io: null,
        _footIo: null,
        _onScroll: null,
        init() {
            // Footer-hide: común a todas las páginas. `rootMargin` negativo evita que parpadee al
            // rozar el borde exacto del pie.
            const foot = document.querySelector('.foot');
            if (foot && 'IntersectionObserver' in window) {
                this._footIo = new IntersectionObserver(
                    ([entry]) => { this.nearFoot = entry.isIntersecting; },
                    { threshold: 0, rootMargin: '0px 0px -8px 0px' }
                );
                this._footIo.observe(foot);
            }

            if (document.body.dataset.hasHero) {
                // ⚠️⚠️ **LANDING: la barra ya NO espera al hero** (`#227`,
                // `[DECIDIDO owner, 2026-08-28]`). Aquí había un `IntersectionObserver` sobre
                // `.hero__sentinel` cuya regla era «hero y barra nunca co-visibles», heredada de
                // cuando el hero tenía su propio CTA grande y los dos habrían competido.
                //
                // ▶ Ese motivo ya no existe: `#226` vació el hero y `#227` le puso el CTA del
                // armazón, **que en móvil no se pinta justamente porque el CTA vive aquí abajo**.
                // Esperar al hero dejaba la primera pantalla de móvil sin ningún sitio donde
                // comprar — el mismo agujero que en escritorio cierra el par del hero.
                //
                // ⚠️ **En móvil no hay relevo, y por eso esto es tan corto**: el CTA de escritorio
                // viaja de la esquina del hero a la cabecera, pero aquí ya está donde tiene que
                // estar. La barra flotante ES el CTA de la primera pantalla y el de todas las
                // demás; lo único que sigue haciendo falta es que se aparte del pie, y de eso se
                // encarga el observador de `.foot`, que no cambia.
                this.revealed = true;
            } else {
                // RESTO DE PÁGINAS (sin hero): la barra aparece al hacer SCROLL, pero con un umbral
                // MENOR que la landing (que espera a que el hero completo —~1 viewport— salga del
                // viewport). Aquí basta ~1/3 de la pantalla → emerge antes, tras un scroll leve. El
                // umbral se recalcula en cada scroll (sobrevive a rotación/resize). Se oculta al llegar
                // al pie (`nearFoot`, el IO común de arriba). En la carga (scrollY≈0) arranca oculta.
                this._onScroll = () => {
                    this.revealed = window.scrollY > window.innerHeight * 0.3;
                };
                this._onScroll();
                window.addEventListener('scroll', this._onScroll, { passive: true });
            }
        },
        // Estado VISIBLE efectivo de la barra: reúne todas las condiciones (revelada, no en el pie,
        // sin sidecart/cookies/modal-ofertas por encima). Lo consume el blade para el `:class` de la
        // propia barra Y para exponer `body.book-bar-visible`, que reposiciona el widget de ofertas en
        // móvil (lo sube por encima de la barra cuando aparece; #270).
        get visible() {
            return this.revealed && ! this.nearFoot
                && ! this.$store.purchase.isOpen
                && ! this.$store.cookies.visible && ! this.$store.cookies.panel
                && ! (this.$store.offers && this.$store.offers.open);
        },

        destroy() {
            this._io?.disconnect();
            this._footIo?.disconnect();
            if (this._onScroll) window.removeEventListener('scroll', this._onScroll);
        },
    }));

    /**
     * **EL CARRIL DE TARIFAS DE LA SECCIÓN «CUÁNTO»** (`#479`, carril de diseño Fase 2 · T2c).
     *
     * Dos cosas y ninguna más: qué zona se está mirando, y **cuál de sus tarjetas tiene el foco**.
     * El foco es la pieza del diseño: la tarjeta viva va a tamaño natural con su sombra y las
     * vecinas en Nube al 94 %, con el relleno de acción viajando con ella.
     *
     * ⚠️⚠️ **En móvil el foco lo pone el ARRASTRE y en escritorio el CURSOR o el Tab**, y por eso
     * hay dos entradas (`mira` y `enfoca`) y no una. Son la misma decisión dicha con el gesto que
     * cada superficie tiene: en un teléfono no existe `hover`, y en un escritorio sin carril no
     * existe el arrastre.
     *
     * ⚠️⚠️ **El foco se MIDE sobre los hijos, nunca con un paso fijo.** La tarjeta destacada es más
     * ancha que las demás (302 contra 262), así que dividir `scrollLeft` entre un ancho supuesto
     * miente en cuanto hay una destacada — que es el caso normal. Es la misma trampa que el propio
     * mockup dejó anotada en su carril.
     *
     * ⚠️ **El CSS no puede resolverlo solo.** `scroll-snap` deja la tarjeta en su sitio pero no dice
     * CUÁL quedó centrada, y `:hover` no existe con el dedo. Sin este puñado de líneas la sección
     * se queda con todas las tarjetas iguales, que es justo la opción que el canvas descartó.
     *
     * @param {string} inicial  slug de la zona que abre la sección
     * @param {Record<string, number>} destacadas  índice de la tarjeta destacada de cada zona
     */
    window.Alpine.data('rateRail', (inicial, destacadas = {}) => ({
        zone: inicial,
        /** Índice de la tarjeta enfocada, POR ZONA: cambiar de pestaña no reinicia la otra. */
        foco: { ...destacadas },

        pick(slug) {
            this.zone = slug;
        },

        /** Foco por puntero o por teclado (escritorio, y también el Tab en móvil). */
        enfoca(slug, i) {
            if (this.foco[slug] !== i) this.foco[slug] = i;
        },

        /**
         * Foco por arrastre (móvil). Se mide cuál de las tarjetas arranca más cerca del borde
         * izquierdo del carril, sumándole su propio sangrado.
         *
         * ⚠️ El sangrado se LEE del elemento (`padding-left`) en vez de escribirse aquí: vive en la
         * hoja, y un número repetido en el JS se separa del CSS en cuanto alguien toca el carril.
         */
        mira(slug) {
            const carril = this.$refs['rail-' + slug];
            if (!carril || !carril.children.length) return;

            const sangrado = parseFloat(getComputedStyle(carril).paddingLeft) || 0;
            const x = carril.scrollLeft + sangrado;

            let mejor = Infinity;
            let cual = 0;
            for (let k = 0; k < carril.children.length; k++) {
                const d = Math.abs(carril.children[k].offsetLeft - carril.offsetLeft - x);
                if (d < mejor) {
                    mejor = d;
                    cual = k;
                }
            }

            this.enfoca(slug, cual);
        },
    }));

    // Interacciones de la landing (menú móvil, dropdown de idioma, FAQ).
    window.Alpine.data('landing', () => ({
        menuOpen: false, // el menú del armazón: a pantalla completa en escritorio, cajón en móvil
        navHidden: false, // el armazón se retira al bajar y vuelve al subir (tanda 2c·2)
        langOpen: false, // selector de idioma
        faqOpen: 0, // índice de FAQ abierta

        init() {
            // ⚠️ Aquí arrancaba el CARRUSEL de atracciones: leía la primera zona del DOM, teñía
            // `#rides` con su paleta y medía la barra de progreso. La sección 03 pasó a un mosaico
            // de cinco fotos que no tiene zona activa, ni carril, ni progreso (`#482`), así que se
            // fue con él — y con él `zone`, `setZone()`, `applyZoneAccent()`, `scrollSlider()`,
            // `updateProgress()` y los dos números del progreso.

            // Los bucles del icono de calcetines (ocho) solo corren mientras el icono SE VE
            // (auditoría M10, `#435`): el CSS los deja pausados y aquí se encienden al entrar en
            // pantalla. Sin `IntersectionObserver` se quedan en su pose de reposo.
            if ('IntersectionObserver' in window) {
                const vistos = new IntersectionObserver((entradas) => {
                    entradas.forEach((e) => e.target.classList.toggle('is-onscreen', e.isIntersecting));
                });
                document.querySelectorAll('.ic-s1').forEach((el) => vistos.observe(el));
            }

            // Drawer móvil = overlay accesible (Lote 10), alineado con el modal de auth / sidecart.
            // Un único `$watch` cubre TODAS las vías de apertura/cierre (☰ / ✕ / backdrop / Escape /
            // un enlace —incluido un ancla same-page que NO recarga): al abrir bloquea el scroll del
            // fondo y pasa el foco al panel; al cerrar lo restaura y devuelve el foco al botón ☰.
            // (Dispara solo en CAMBIOS → no pelea con el `no-scroll` que el sidecart pone al cargar.)
            this.$watch('menuOpen', (open) => {
                this.$store.scrollLock.set('nav', open);
                if (open) {
                    // La geometría ANTES de que se vea: si el recorte naciera en el sitio de la
                    // apertura anterior, el menú se abriría desde una esquina que ya no es la suya.
                    this.publishMenuOrigin();
                    this.$nextTick(() => this.visibleMenuPanel()?.querySelector('a[href],button:not([disabled])')?.focus());
                } else {
                    this.$refs.burger?.focus();
                }
            });

            // Reencuadrar mueve la hamburguesa; con el menú abierto, su origen deja de valer.
            this._onMenuResize = () => { if (this.menuOpen) this.publishMenuOrigin(); };
            window.addEventListener('resize', this._onMenuResize, { passive: true });

            this.watchScrollDirection();
        },

        /**
         * **La coreografía del armazón: se retira al bajar y vuelve al subir.**
         *
         * ⚠️ **La spec (§4.4) decía «en una página con hero nace oculto y aparece al terminar
         * el hero», y aquí NO se implementa así — con motivo medido.** Esa regla es la del
         * mockup, y el mockup tiene un hero a pantalla completa. El nuestro dejó de serlo en
         * `#195`: es una TARJETA con `max-height: calc(100vh - 160px)` dentro de un `padding`
         * de 96 px. Aplicarla tal cual dejaría la portada **sin logotipo y sin ☰** flotando
         * sobre una tarjeta que no llena la pantalla, o sea el sitio sin ninguna navegación
         * visible en su primera pantalla. Se implementa lo que sí se sostiene: **visible desde
         * el primer píxel en las doce**, y la retirada al bajar.
         *
         * ⚠️ **Nunca se retira con un overlay abierto.** El ☰ es la forma de cerrar el menú: si
         * se fuera con el scroll del propio menú, el visitante se quedaría dentro sin salida
         * visible. Escape seguiría funcionando, pero eso no es una salida que nadie vea.
         *
         * ⚠️ **Y no reacciona a cualquier píxel**: por debajo del umbral, el temblor de un
         * trackpad o el rebote elástico de iOS harían parpadear el armazón sin que nadie haya
         * pedido nada. Un gesto es una intención, no un evento.
         */
        watchScrollDirection() {
            let anterior = window.scrollY || 0;

            this._onNavScroll = () => {
                const y = window.scrollY || 0;
                const decision = shouldHideNav({
                    y,
                    previous: anterior,
                    locked: this.menuOpen || !! this.$store.purchase?.isOpen,
                    // ⚠️ **En la portada el suelo es el final del hero, no la primera pantalla**
                    // (2c·8, `#216`): mientras el armazón está ENTRANDO con el scroll, la
                    // dirección no puede sacarlo — serían dos mecanismos moviendo lo mismo. Se
                    // mide en cada evento porque el hero cambia de alto al redimensionar.
                    floor: this.navFloor(),
                });

                // `null` es «no me consta»: sin intención, ni se mueve el armazón ni se mueve la
                // referencia — si la referencia avanzara, un arrastre lento nunca acumularía
                // suficiente delta y la coreografía no se dispararía jamás.
                if (decision === null) return;

                anterior = y;
                this.navHidden = decision;
            };

            window.addEventListener('scroll', this._onNavScroll, { passive: true });
        },

        /**
         * El suelo por debajo del cual la dirección del scroll NO retira el armazón.
         *
         * En la portada es el final del hero —donde termina de entrar—; en las once páginas sin
         * hero, `undefined` deja el valor por defecto del módulo (la primera pantalla).
         */
        navFloor() {
            if (! document.body.dataset.hasHero) return undefined;
            const hero = document.querySelector('.hero');
            if (! hero) return undefined;
            return hero.offsetTop + hero.offsetHeight;
        },

        /**
         * **De dónde nace el recorte circular del menú.**
         *
         * ⚠️ **Publica GEOMETRÍA, no diseño** (misma regla que el hero, `#195`): aquí solo se
         * mide dónde está la hamburguesa. El radio, la curva y la duración viven en el CSS,
         * porque si los números vivieran aquí serían la única parte del tema que un cliente no
         * puede tocar desde su hoja.
         */
        publishMenuOrigin() {
            const burger = this.$refs.burger;
            if (! burger) return;
            const box = burger.getBoundingClientRect();
            const root = document.documentElement;
            root.style.setProperty('--menu-x', `${Math.round(box.left + box.width / 2)}px`);
            root.style.setProperty('--menu-y', `${Math.round(box.top + box.height / 2)}px`);
        },

        /**
         * **El panel que se está viendo AHORA.**
         *
         * El armazón sirve DOS overlays con un solo estado —el menú a pantalla completa en
         * escritorio y el cajón en móvil— y el CSS decide cuál se ve. El foco tiene que ir al
         * que existe, no al primero que se encuentre: `offsetParent` es `null` para lo que está
         * en `display:none`, que es exactamente como se oculta el que no toca.
         */
        visibleMenuPanel() {
            return [this.$refs.menuPanel, this.$refs.mobPanel]
                .find((panel) => panel && panel.offsetParent !== null) ?? null;
        },

        destroy() {
            if (this._onNavScroll) window.removeEventListener('scroll', this._onNavScroll);
            if (this._onMenuResize) window.removeEventListener('resize', this._onMenuResize);
        },

        // Trap de foco del overlay (Lote 10): Tab cíclico dentro del panel mientras está abierto
        // (mismo gesto que `a11yPanel` del modal/sidecart; aquí vive en el componente porque el
        // estado `menuOpen` es propio del armazón, no un store).
        trapMenu(e) {
            if (e.key !== 'Tab' || !this.menuOpen) return;
            const panel = this.visibleMenuPanel();
            if (!panel) return;
            const items = [...panel.querySelectorAll(
                'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),[tabindex]:not([tabindex="-1"])'
            )].filter((el) => el.offsetParent !== null);
            if (items.length === 0) return;
            const first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (! e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        },

        // ⚠️ **De este componente se han ido TRES generaciones de la misma sección**: `goToRides(z)`
        // con `#302`, y con `#482` la zona activa, `setZone()`, `applyZoneAccent()`, el carril y su
        // barra de progreso. La sección 03 es hoy un mosaico de cinco fotos que no necesita ni una
        // línea de JavaScript.
        // ▶ Un método sin llamante es lo mismo que una regla de CSS sin pantalla: se va con él.

    }));

    // Cloudflare Turnstile dentro de un componente Livewire que puede aparecer por un MORPH
    // (registro embebido en el flujo de compra). CLAVE: x-init/init() de Alpine SÍ corre en
    // nodos inyectados por morph; un <script> plano NO (por eso el widget no se dibujaba en
    // producción y el token llegaba vacío → "no eres un robot"). Aquí (1) cargamos api.js bajo
    // demanda con createElement (esto SÍ ejecuta, una sola vez para toda la página) y (2)
    // renderizamos el widget EXPLÍCITAMENTE sobre $el (el auto-render de api.js solo detecta
    // los .cf-turnstile presentes en la carga inicial, no los inyectados). El div lleva
    // `wire:ignore` para que el <iframe> de Turnstile sobreviva a los re-render de validación.
    window.Alpine.data('turnstileField', (sitekey) => ({
        _rendered: false,
        _poll: null,
        init() {
            const set = (token) => this.$wire?.set('turnstileToken', token ?? '');
            const ready = () => !!(window.turnstile && window.turnstile.render);
            const render = () => {
                if (this._rendered || !ready()) return;
                this._rendered = true;
                window.turnstile.render(this.$el, {
                    sitekey,
                    callback: (token) => set(token),
                    'error-callback': () => set(''),
                    'expired-callback': () => set(''),
                });
            };

            if (ready()) { render(); return; }

            // Cargar api.js una sola vez (createElement ejecuta; un <script> por morph no).
            if (!window.__cfTurnstileLoading) {
                window.__cfTurnstileLoading = true;
                const s = document.createElement('script');
                s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
                s.async = true;
                s.defer = true;
                document.head.appendChild(s);
            }

            let tries = 0;
            this._poll = setInterval(() => {
                if (ready()) { clearInterval(this._poll); render(); }
                else if (++tries > 80) { clearInterval(this._poll); } // ~12 s y se rinde
            }, 150);
        },
        destroy() {
            if (this._poll) clearInterval(this._poll);
        },
    }));

    // Widget flotante «caja de regalo» de OFERTAS (#270, docs/PLAN-OFERTAS-WIDGET.md). El store
    // `offers` expone SOLO el flag `open` (lo lee la book-bar para cederle sitio); toda la lógica
    // (carrusel, destello, posicionado, focus-trap) vive en el componente para no dispersar estado.
    window.Alpine.store('offers', { open: false });

    /**
     * **EL CTA DOBLE — cuál de las dos mitades está expandida** (armazón · tanda 2c·7).
     *
     * El racimo de la cabecera y la barra flotante de móvil **son la misma decisión**, y por eso
     * el estado vive en UN store y no dos veces: nunca coexisten en pantalla, pero si alguien
     * expande «mi cuenta» en escritorio y estrecha la ventana, la barra tiene que salir igual.
     * Antes `mode` era local de `mobileBookBar` y la cabecera no tenía nada.
     *
     * ⚠️ **Publica ESTADO y nada más.** Qué mitad es ancha, cuánto mide, cómo se anima y cuándo
     * deja de invitar lo decide el CSS a partir de dos clases. Si los anchos vivieran aquí serían
     * la única parte del tema que un cliente no puede tocar desde su hoja — misma regla que el
     * hero (`#195`), el recorte del menú (`#201`) y la propia barra (`#205`).
     *
     * ⚠️ **`touched` no es cosmético**: apaga la invitación **para siempre en esa visita**. Una
     * animación que sigue llamando la atención después de que el visitante ya ha respondido deja
     * de ser una invitación y pasa a ser ruido.
     */
    window.Alpine.store('ctaPair', {
        /**
         * `'buy'` (comprar expandido) | `'account'` (la cuenta expandida).
         * El ARRANQUE lo dice el servidor (`<body data-cta-mode>`, 2026-09-01, `[DECIDIDO owner]`):
         * sin sesión, `account` —registrarse es lo primero—; con sesión, `buy`. Sin el atributo
         * (una plantilla que no lo emita), comprar, como siempre.
         */
        mode: (document.body && document.body.dataset && document.body.dataset.ctaMode === 'account') ? 'account' : 'buy',
        /** ¿Ha interactuado ya alguien con el par? Mientras sea `false`, la otra mitad invita. */
        touched: false,
        /** Expande una mitad. Cualquier uso del par apaga la invitación, se expanda lo que se expanda. */
        show(mode) {
            this.mode = mode;
            this.touched = true;
        },
    });

    // Componente del widget. Envuelve lanzador + scrim + estallido + modal-carrusel en UN x-data
    // (el modal NO tiene x-data propio → comparte `i`/`go`/`close`/`loaded` sin problemas de scope).
    // Al abrir: calcula el origen (centro de la caja) en CSS vars --ox/--oy/--dx/--dy y dispara el
    // estallido; el vuelo del modal es CSS (`.offw-modal.show`). Imágenes perezosas: `loaded` no se
    // pone a true hasta la 1ª apertura → 0 bytes de imagen en la carga de página.
    window.Alpine.data('offersWidget', (count = 0) => ({
        count,
        i: 0,
        loaded: false,
        shown: false, // el modal-card está en su estado «volado» (t≈300ms tras abrir)
        burst: false, // destello activo (t≈170ms tras abrir)
        _timers: [],
        _focusable: 'a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])',

        init() {
            // Cuando el card VUELA (shown), lleva el foco dentro (focus-trap ligero, como `a11yPanel`).
            this.$watch('shown', (v) => {
                if (v) this.$nextTick(() => this.focusFirst());
            });
        },

        // Estado canónico de apertura en el store (lo ve la book-bar).
        get open() {
            return this.$store.offers.open;
        },

        // Secuencia FIEL al mockup (caja-modal): la caja se abre y entra el scrim (t=0) → el destello
        // sale de DENTRO de la caja (t=170 ms) → el modal-card VUELA desde la caja al centro (t=300 ms).
        // El retraso de `shown` además garantiza que el estado inicial (card pequeño en la caja, con
        // --dx/--dy ya fijados) se PINTE antes de animar; si no, saltaría directo al centro sin vuelo
        // (era el bug «el efecto al abrir no se aplica»).
        openModal() {
            if (this.$store.offers.open) return;
            this._clearTimers();
            this.positionOrigin();
            this.loaded = true; // carga las imágenes on-demand (perezosas hasta la 1ª apertura)
            this.$store.offers.open = true; // t=0: scrim entra + la caja se abre (tapa + confeti)
            this.$store.scrollLock.lock('offers');

            if (this._reduced()) {
                this.shown = true; // sin animación: modal directo
                return;
            }
            this._timers.push(setTimeout(() => { this.burst = true; }, 170));   // destello desde la caja
            this._timers.push(setTimeout(() => { this.shown = true; }, 550));   // el card vuela y crece
            this._timers.push(setTimeout(() => { this.burst = false; }, 1550)); // limpia el destello
        },

        close() {
            this._clearTimers();
            this.shown = false;
            this.burst = false;
            this.$store.offers.open = false;
            this.$store.scrollLock.unlock('offers');
        },

        // Carrusel por índice con vuelta infinita (patrón `birthdayProcess`).
        go(n) {
            const len = this.count || 1;
            this.i = ((n % len) + len) % len;
        },

        // Origen dinámico del estallido/vuelo: centro de la caja + desfase caja→centro de pantalla.
        positionOrigin() {
            const btn = this.$refs.launch;
            if (!btn) return;
            const r = btn.getBoundingClientRect();
            const cx = r.left + r.width / 2;
            const cy = r.top + r.height / 2;
            const el = this.$el;
            el.style.setProperty('--ox', cx + 'px');
            el.style.setProperty('--oy', cy + 'px');
            el.style.setProperty('--dx', (cx - window.innerWidth / 2) + 'px');
            el.style.setProperty('--dy', (cy - window.innerHeight / 2) + 'px');
        },

        onResize() {
            if (this.$store.offers.open) this.positionOrigin();
        },

        focusFirst() {
            this.$refs.card?.querySelector(this._focusable)?.focus();
        },

        trap(e) {
            if (e.key !== 'Tab' || !this.$refs.card) return;
            const items = [...this.$refs.card.querySelectorAll(this._focusable)]
                .filter((el) => el.offsetParent !== null);
            if (items.length === 0) return;
            const first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        },

        _reduced() {
            return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },

        _clearTimers() {
            this._timers.forEach(clearTimeout);
            this._timers = [];
        },

        destroy() {
            this._clearTimers();
        },
    }));
});

// ── EL INTERRUPTOR DEL TITULAR VUELVE A SALTAR AL VOLVER EL HERO (`#280`) ──────────────────────
// `[DECIDIDO owner]`: el interruptor para tras `--switch-cycles` ciclos y descansa ENCENDIDO —eso
// lo hace el CSS solo— y vuelve a arrancar cuando el hero regresa al viewport.
//
// ▶ **Va fuera de `alpine:init` a propósito**: no registra nada en Alpine ni lee ningún almacén, así
// que colgarlo de ese evento sería atarlo a un motor que no usa. En las diez vistas sin interruptor
// devuelve `null` sin tocar el documento.
installHeroSwitch();

// ⚠️⚠️ **Aquí escuchaba el evento `logged-in` de Livewire, y se retiró el 2026-08-23**
// (`specs/account-context-vue.md` §4.6). Ese bus existía porque el bloque de cuenta era un componente
// Livewire FUERA del motor; con el bloque dentro del cajón, el propio motor marca `authChanged` sin
// dar la vuelta por un bus de eventos (`sidebar/account/session-gained.js`).
//
// Lo que hacía **no se ha perdido**, que es lo único que importa: el login embebido sigue sin recargar
// la página, el nav sigue quedándose con el estado de invitado, y cerrar el cajón sigue recargando.
// Medido al retirarlo: tras esto **nadie emite ni escucha `logged-in`** en el repo.
