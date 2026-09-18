/**
 * **EL CONTROLADOR DEL CAJÓN, sin framework** (F4 · T2, `docs/specs/cajon-empaquetable.md` §4.2).
 *
 * Hasta el 2026-09-18 todo esto ERA el store `purchase` de Alpine, declarado dentro de `app.js`. Y Alpine, en
 * este producto, llega DENTRO de Livewire: una página que no fuera del producto —la landing a mano de una
 * instancia— habría tenido que cargar Livewire solo para poder ABRIR el cajón. Aquí vive la misma lógica, con
 * las mismas trampas pagadas en sus comentarios, en un objeto plano que no sabe qué framework lo mira:
 *
 *  · se publica en `window.JumpWeb.cajon` — la API de apertura para cualquier página;
 *  · `app.js` lo registra además como `Alpine.store('purchase', …)`, de modo que los 23 usos de
 *    `$store.purchase` de la landing del producto y los `:class` de la carcasa siguen reaccionando igual.
 *
 * ⚠️⚠️ **Con Alpine, `window.JumpWeb.cajon` apunta al PROXY reactivo del store, no a este objeto crudo**
 * (lo reasigna `app.js` en `alpine:init`). No es un detalle: Alpine solo se entera de un cambio si la escritura
 * pasa por su proxy. Un `open()` llamado sobre el objeto crudo pondría `isOpen` a `true` y la carcasa no se
 * movería. Por eso todos los métodos escriben en `this`, y quien llama decide por qué puerta entra.
 *
 * ⚠️ El MOTOR (Vue) escribe aquí `setMode()`, `identifying` y `authChanged` a través de
 * `sidebar/host-bridge.js`; ya no nombra a Alpine.
 */
import { reveal } from '../ui/account-host.js';

/** Lo que pasa en el cajón, contado a la página que lo aloja sin que tenga que saber nada del motor. */
function announce(name, detail = {}) {
    document.dispatchEvent(new CustomEvent(`jw:cajon:${name}`, { detail }));
}

/**
 * @param {{scrollLock: {lock: (key: string) => void, unlock: (key: string) => void}}} deps
 *   El cerrojo de scroll es de DUEÑO ÚNICO (`ui/scroll-lock.js`) y lo comparten todos los superpuestos de la
 *   página: se instancia una vez en `app.js` y llega aquí por parámetro.
 */
export function createCajonController({ scrollLock }) {
    return {
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
            // ⚠️ Y se ANUNCIA (F4 · T3a): la clase `is-{modo}` del panel ya no la pinta un `:class` de Alpine
            // sino la carcasa sin framework (`cajon/shell.js`), que se entera por aquí. Sin este aviso el
            // panel se queda en `is-catalog` para siempre y el bloque de cuenta no se colapsa (`#118`).
            announce('mode', { mode: this.mode });
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
        /** Abre el cajón pidiendo algo: `{ type: 'packs' }` · `{ type: 'zone', slug }` · `{ type: 'product', id }`. */
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
        // flag se enviarían LOS DOS motores. Es el patrón que ya usaba `html2canvas` en el editor
        // de invitaciones (retirado con él en `#528`).
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
                const mod = await import('../sidebar/index.js');
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
            announce('open');
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
            // ⚠️ Cerrar un cajón CERRADO no es un cierre: no se anuncia. Pasaba con Escape, que llega aquí
            // esté como esté el cajón — la página habría recibido un `jw:cajon:close` por cada pulsación.
            const wasOpen = this.isOpen;
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
            // ⚠️ Se anuncia ANTES de la recarga de abajo: quien escucha (una landing que mide embudo, por
            // ejemplo) tiene que enterarse del cierre también cuando ese cierre se lleva la página.
            if (wasOpen) announce('close', { reloading: this.authChanged });
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
    };
}
