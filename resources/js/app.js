// Livewire (Fase 4) trae su propio Alpine y lo arranca él. Por eso aquí NO
// importamos ni iniciamos Alpine: registramos nuestros componentes/almacenes
// dentro de `alpine:init` usando el Alpine global que expone Livewire.
// Ver docs/DECISIONES.md #29 (la landing usaba Alpine standalone "aún").

document.addEventListener('alpine:init', () => {
    // Almacén global del modal de autenticación (login/registro).
    // El valor inicial puede venir de la URL (p. ej. /registro) vía data-attr.
    window.Alpine.store('auth', {
        modal: document.body.dataset.authModal || null,
        completed: false, // un registro se completó en este modal (datos sensibles en pantalla)
        open(name) {
            // Defensa por si quedó una confirmación previa sin cerrar (tablet compartida).
            if (this.completed) {
                window.location.reload();

                return;
            }
            this.modal = name;
            // T4.4 — bloquea el scroll del body mientras el modal está abierto (consistente
            // con el sidebar de compra que ya lo hacía).
            document.body.classList.add('no-scroll');
        },
        close() {
            const wasCompleted = this.completed;
            this.completed = false;
            document.body.classList.remove('no-scroll');
            // Si hubo un registro completado (muestra el email del cliente), recargamos
            // para no dejar datos al siguiente cliente (tablet compartida).
            if (wasCompleted) {
                this.modal = null;
                window.location.reload();

                return;
            }
            // Limpieza VISUAL instantánea (no esperamos al round-trip de Livewire, que en
            // local puede ir lento): vaciamos campos y quitamos los mensajes de error.
            this.clearModalUi();
            this.modal = null;
            // Reset autoritativo en el servidor (propiedades + bag de validación) en segundo
            // plano, para dejar el estado coherente. El usuario ya ve el modal limpio.
            if (window.Livewire) {
                window.Livewire.dispatch('auth-modal-closed');
            }
        },
        clearModalUi() {
            document.querySelectorAll('.modal__panel input').forEach((el) => {
                if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = false;
                } else {
                    el.value = '';
                }
                el.dispatchEvent(new Event('input', { bubbles: true }));
            });
            document
                .querySelectorAll('.modal__panel .form__error, .modal__panel .auth__errors')
                .forEach((el) => el.remove());
        },
    });

    // Sidebar de compra de entradas (Fase 5.2): se abre sobre la página actual (sin
    // redirigir). El contenido (asistente paso a paso) es un componente Livewire `lazy`.
    window.Alpine.store('purchase', {
        isOpen: document.body.dataset.purchaseOpen === '1',
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
            } catch (e) {
                // Que el chunk no cargue (red caída, despliegue a media navegación) no puede dejar
                // el cajón abierto y mudo sin dejar rastro de por qué.
                console.error('[sidebar] no se pudo cargar el motor SPA', e);
            } finally {
                this.spaLoading = false;
            }

            return this.spaHandle;
        },
        open() {
            this.isOpen = true;
            document.body.classList.add('no-scroll');
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
            this.mode = 'catalog';
            // OJO: `identifying` NO se resetea aquí (ver su declaración). Si se pusiera a false, al
            // reabrir el sidebar sin round-trip Livewire el x-effect no re-dispararía y el botón de
            // login quedaría desbloqueado en pleno paso de identificación.
            document.body.classList.remove('no-scroll');
            // Si hubo login dentro del sidebar, recargamos la PÁGINA ACTUAL (no navegamos a otro
            // sitio) para que el nav refleje la sesión. Al cierre, no a mitad del flujo; el carrito
            // vive en sesión, así que no se pierde nada. Mismo patrón que el modal (#51).
            if (this.authChanged) {
                window.location.reload();
            }
        },
        // Feedback celebratorio al confirmar la reserva (paso 6). Ligero, sin dependencias y
        // accesible: se omite si el usuario pidió menos animación (prefers-reduced-motion).
        celebrate() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            const canvas = document.createElement('canvas');
            canvas.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:9999';
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            document.body.appendChild(canvas);
            const ctx = canvas.getContext('2d');
            // Paleta del confeti derivada de los tokens de marca (white-label): si la clienta
            // cambia el color desde el panel, la celebración lo respeta. Fallbacks = defaults.
            const root = getComputedStyle(document.documentElement);
            const tok = (name, fallback) => (root.getPropertyValue(name).trim() || fallback);
            const colors = [
                tok('--zone-1', '#ff5b22'),
                tok('--zone-2', '#c6ff3a'),
                tok('--fg', '#14130f'),
                tok('--zone-1', '#ff5b22'),
                tok('--zone-2', '#c6ff3a'),
            ];

            const parts = Array.from({ length: 130 }, () => ({
                x: window.innerWidth / 2,
                y: window.innerHeight / 3,
                vx: (Math.random() - 0.5) * 12,
                vy: Math.random() * -12 - 4,
                size: Math.random() * 6 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                rot: Math.random() * Math.PI,
                vr: (Math.random() - 0.5) * 0.3,
            }));

            let frame = 0;
            const tick = () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                parts.forEach((p) => {
                    p.vy += 0.3;
                    p.x += p.vx;
                    p.y += p.vy;
                    p.rot += p.vr;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rot);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                    ctx.restore();
                });
                frame++;
                if (frame < 150) {
                    requestAnimationFrame(tick);
                } else {
                    canvas.remove();
                }
            };
            requestAnimationFrame(tick);
        },
    });

    // Adaptador de intención del motor LIVEWIRE (Fase 4 · paso 4.0a). Es la única línea del
    // sistema que sabe que el cajón lo mueve Livewire, y la que el paso 4.7 sustituye por la de
    // Vue. Se registra en el arranque —no al hidratarse el componente `lazy`— para conservar
    // EXACTAMENTE el momento en que se despachaba antes: en el `@click`.
    window.Alpine.store('purchase').useIntentAdapter((intent) => {
        if (! window.Livewire) return;

        if (intent.type === 'packs') {
            window.Livewire.dispatch('show-packs');
        } else if (intent.type === 'zone') {
            window.Livewire.dispatch('show-entradas-zone', { slug: intent.slug });
        }
    });

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
        // Color de la tarjeta (#231): selector independiente del pack (swatches Jump/Kids).
        // El blade lo lee para fijar --inv/--inv2 de la `.bd-card`.
        invZone: cfg.invZone || 'jump',
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
    // por CSS y aparece cuando el `.hero__stage-bottom` (que contiene el CTA "prime")
    // sale del viewport. En páginas sin marker no se observa nada y el CSS deja el
    // botón visible por defecto.
    //
    // Robustez:
    //  • Sin marker → cortocircuita en `init()` (otras páginas conservan el CTA visible).
    //  • Sin `.hero__stage-bottom` aún en DOM → cortocircuita (el CSS lo deja oculto;
    //    estado seguro: aunque pase, el CTA "prime" del hero sigue al cargar).
    //  • Sin `IntersectionObserver` (navegadores muy antiguos) → cortocircuita, el CTA
    //    queda oculto pero el CTA del hero está visible al cargar — no se pierde el
    //    acceso primario.
    //  • `destroy()` desconecta el observer (relevante si la página usa wire:navigate).
    window.Alpine.data('navCtaReveal', () => ({
        _io: null,
        init() {
            if (!document.body.dataset.hasHero) return;
            const trigger = document.querySelector('.hero__stage-bottom');
            if (!trigger || !('IntersectionObserver' in window)) return;
            this._io = new IntersectionObserver(
                ([entry]) => {
                    const revealed = !entry.isIntersecting;
                    // Clase en el propio botón (CSS controla animación de aparición).
                    this.$el.classList.toggle('is-revealed', revealed);
                    // Clase en body: permite a OTROS elementos del nav reaccionar al
                    // estado (p. ej. el ghost en móvil colapsa a "solo icono" para
                    // ceder espacio al filled emergente — selector hermano no
                    // funciona aquí porque ghost va ANTES que filled en el DOM).
                    document.body.classList.toggle('nav-cta-revealed', revealed);
                },
                { threshold: 0 }
            );
            this._io.observe(trigger);
        },
        destroy() {
            this._io?.disconnect();
            document.body.classList.remove('nav-cta-revealed');
        },
    }));

    // Barra flotante de reserva en MÓVIL (mockup `design_mockup/jerarquia-ctas.html` §03). En móvil
    // el CTA «Reservas aquí» sale del header y reaparece como barra fija inferior:
    //  • LANDING (`body[data-has-hero]`, la única página con hero): aparece cuando el sentinel del hero
    //    (`.hero__stage-bottom`) abandona el viewport → hero y barra nunca co-visibles (misma señal que
    //    el reveal del header). Sigue oculta en la primera pantalla.
    //  • RESTO DE PÁGINAS (sin hero): aparece al hacer scroll, con un umbral MENOR que la landing
    //    (~1/3 de pantalla, no el hero completo) → emerge antes.
    //  • En AMBOS casos se oculta cuando el pie real (`.foot`) entra en viewport (no tapa legales/idioma);
    //    el resto de capas que la ocultan (sidecart abierto, banner de cookies) las cubre el `:class` del blade.
    // El deslizamiento (translateY) y la visibilidad responsive (solo <=720px) viven en CSS (`.book-bar`).
    window.Alpine.data('mobileBookBar', () => ({
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
                // LANDING (única página con hero): comportamiento original — la barra entra cuando el
                // CTA «prime» del hero abandona el viewport (mismo sentinel que el reveal del header) →
                // hero y barra nunca co-visibles; sigue oculta en la primera pantalla.
                const trigger = document.querySelector('.hero__stage-bottom');
                if (trigger && 'IntersectionObserver' in window) {
                    this._io = new IntersectionObserver(
                        ([entry]) => { this.revealed = !entry.isIntersecting; },
                        { threshold: 0 }
                    );
                    this._io.observe(trigger);
                } else {
                    this.revealed = true; // fail-open: sin observer, mejor la barra visible que ausente
                }
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

    // Interacciones de la landing (zona activa, menú móvil, dropdown, slider, FAQ).
    window.Alpine.data('landing', () => ({
        zone: 'jump', // zona activa (jump | kids)
        mobileOpen: false, // menú móvil
        parkOpen: false, // desplegable "El parque" del nav (2026-05-27)
        servicesOpen: false, // desplegable "Servicios" del nav (2026-05-27)
        langOpen: false, // selector de idioma
        faqOpen: 0, // índice de FAQ abierta
        progressLeft: 0, // barra de progreso del slider
        progressWidth: 0.33,

        init() {
            // El acento de la zona activa se aplica SOLO a la sección «Atracciones» (#rides);
            // lo genérico de la web usa el color de marca global (`--zone-1` en :root, #7.10).
            this.applyZoneAccent();
            this.$nextTick(() => this.updateProgress());

            // Drawer móvil = overlay accesible (Lote 10), alineado con el modal de auth / sidecart.
            // Un único `$watch` cubre TODAS las vías de apertura/cierre (☰ / ✕ / backdrop / Escape /
            // un enlace —incluido un ancla same-page que NO recarga): al abrir bloquea el scroll del
            // fondo y pasa el foco al panel; al cerrar lo restaura y devuelve el foco al botón ☰.
            // (Dispara solo en CAMBIOS → no pelea con el `no-scroll` que el sidecart pone al cargar.)
            this.$watch('mobileOpen', (open) => {
                document.body.classList.toggle('no-scroll', open);
                if (open) {
                    this.$nextTick(() => this.$refs.mobPanel?.querySelector('a[href],button:not([disabled])')?.focus());
                } else {
                    this.$refs.burger?.focus();   // no-op si el ☰ está oculto (desktop)
                }
            });
        },

        // Trap de foco del drawer móvil (Lote 10): Tab cíclico dentro del panel mientras está
        // abierto (mismo gesto que `a11yPanel` del modal/sidecart; aquí vive en el componente
        // porque el estado `mobileOpen` es propio del nav, no un store).
        trapMobile(e) {
            if (e.key !== 'Tab' || !this.mobileOpen) return;
            const panel = this.$refs.mobPanel;
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

        setZone(z) {
            this.zone = z;
            this.applyZoneAccent();
            this.$nextTick(() => this.updateProgress());
        },

        // Tiñe la sección de atracciones con el color de la zona activa (white-label: el color
        // sale del `data-color` del slider, que lo trae de `zones.color`). Scoped a #rides para
        // no recolorear toda la página (el resto se queda en la marca global). El secundario
        // sigue el acento del mockup (amarillo Jump / rosa Kids).
        applyZoneAccent() {
            const rides = document.getElementById('rides');
            if (!rides) return;
            const slider = rides.querySelector('.slider[data-zone="' + this.zone + '"]');
            const color = slider && slider.dataset.color;
            if (color) {
                rides.style.setProperty('--zone-1', color);
                // --on-brand de la zona (mirror de ThemeSettings::onBrand): blanco salvo que el
                // color sea tan claro que el blanco no contraste → la lima de Kids recibe oscuro.
                const m = /^#?([0-9a-fA-F]{6})$/.exec(color.trim());
                if (m) {
                    const n = parseInt(m[1], 16);
                    const lin = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
                    const L = 0.2126 * lin((n >> 16) & 255) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
                    rides.style.setProperty('--on-brand', 1.05 / (L + 0.05) >= 3 ? '#FFFFFF' : '#14130F');
                }
            }
            rides.style.setProperty('--zone-2', this.zone === 'kids' ? 'var(--kids-2)' : 'var(--jump-2)');
        },

        goToRides(z) {
            this.setZone(z);
            this.$nextTick(() => {
                document.getElementById('rides')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },

        scrollSlider(dir) {
            const el = this.$refs['slider_' + this.zone];
            if (!el) return;
            const card = el.querySelector('.ride-card');
            const step = card ? card.offsetWidth + 18 : 320;
            el.scrollBy({ left: step * dir, behavior: 'smooth' });
        },

        updateProgress() {
            const el = this.$refs['slider_' + this.zone];
            if (!el) return;
            const total = el.scrollWidth - el.clientWidth;
            if (total <= 0) {
                this.progressLeft = 0;
                this.progressWidth = 1;
                return;
            }
            const pct = el.scrollLeft / total;
            const visible = el.clientWidth / el.scrollWidth;
            this.progressLeft = pct * (1 - visible);
            this.progressWidth = visible;
        },
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
            document.body.classList.add('no-scroll');

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
            document.body.classList.remove('no-scroll');
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

// Login EMBEBIDO en el sidebar (#69): a propósito NO recarga la página, así que el nav se queda
// con el estado de invitado. Marcamos el cambio para refrescar al CERRAR el sidebar (ver el
// almacén 'purchase'). El login del modal normal sí redirige, así que no necesita esto.
document.addEventListener('livewire:init', () => {
    window.Livewire.on('logged-in', () => {
        const purchase = window.Alpine?.store('purchase');
        if (purchase) {
            purchase.authChanged = true;
        }
    });
});
