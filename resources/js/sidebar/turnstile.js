/**
 * El widget de Cloudflare Turnstile del cajón SPA (Fase 4 · paso 4.4b·2).
 *
 * Transcribe `Alpine.data('turnstileField')` de `resources/js/app.js`, que es el patrón que YA
 * funciona en el modal de auth de la cabecera. La lógica vive aquí y no en el `.vue` por `CE-6`: los
 * componentes solo pintan, y así esto se prueba con `node --test` sin montar un runner de componentes.
 *
 * ⚠️ **NADA de `window`/`document` en el ámbito del módulo, y no es una manía de estilo.** Este
 * fichero acaba importado por `RegisterForm.vue` → `IdentifyStep.vue` → `scripts/render-sidebar.mjs`,
 * que `SidebarDomContractTest` **ejecuta con `node` puro** para comparar el árbol de los dos motores.
 * Un `document.createElement` suelto en el cuerpo tumbaría los tres casos del paso 5 con un stack de
 * Node bajo el mensaje «El renderizador de Vue falló», que no menciona el widget por ningún lado.
 * Por eso el entorno entra por PARÁMETRO (`win`/`doc`), que además es lo que lo hace testable.
 *
 * ⚠️⚠️ **Las tres trampas medidas que hacen que esto NO sea un copia-pega del original:**
 *
 * **1. El guard `__cfTurnstileLoading` se COMPARTE, y no se puede usar para cortar.** El modal de auth
 * de la cabecera se renderiza *eager* en TODA página pública para invitados (`layout.blade.php`), así
 * que con el anti-bot activo `api.js` ya se ha pedido antes de que el cajón se abra siquiera. El flag
 * significa «alguien EMPEZÓ a cargarlo», no «está listo»: quien llega segundo no tiene nada a lo que
 * engancharse. Si este módulo hiciera `if (win.__cfTurnstileLoading) return;`, **el widget del cajón no
 * se pintaría jamás**. Y si usara un flag propio, `api.js` se inyectaría dos veces. El único predicado
 * válido es `win.turnstile && win.turnstile.render`, con sondeo.
 *
 * **2. Se GUARDA el `widgetId`.** Es lo único que el original descarta (`app.js`: llama a `render()` y
 * tira el retorno), y sin él no hay asidero para `reset()` ni `remove()`. Aquí hace falta de verdad:
 * el token de Turnstile **es de un solo uso**, y `SelfSignup` lo quema ANTES de mirar si el correo ya
 * existe. Sin `reset()`, quien se equivoca de correo recibe «ya tienes cuenta», corrige, reenvía con el
 * mismo token, y Cloudflare lo rechaza por duplicado → «no eres un robot» **con el tick verde puesto**,
 * y sin salida que no sea recargar la página.
 *
 * **3. No se rinde en silencio.** El original agota 12 s de sondeo y no dice nada: el usuario ve un
 * hueco y solo se entera al enviar. Aquí, al agotarse, se avisa por consola y se fuerza el token vacío.
 * Importa más de lo que parece: `Turnstile::verify('')` corta ANTES del POST a Cloudflare y antes de su
 * `Log::warning`, así que un widget que no llega a pintarse produce un 422 con **cero rastro** en los
 * logs del servidor y **cero** en el panel de Cloudflare. La consola del navegador es el único sitio
 * donde ese fallo deja huella.
 *
 * Las opciones que se pasan a Cloudflare son **exactamente las cuatro** del motor Livewire
 * (`sitekey`, `callback`, `error-callback`, `expired-callback`). Ni `theme` ni `appearance` ni
 * `action`: cualquier extra sería una diferencia de comportamiento entre los dos motores que ninguna
 * paridad puede ver.
 */

export const TURNSTILE_SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

/** 150 ms × 80 ≈ 12 s. Mismos números que el motor Livewire, que lleva meses funcionando. */
const POLL_INTERVAL_MS = 150;
const POLL_MAX_TRIES = 80;

/**
 * Monta el widget sobre `el` y avisa por `onToken` cada vez que el token cambia.
 *
 * @param {object} el Nodo contenedor. Llega por `ref` del componente, NUNCA por `querySelector`:
 *   con el anti-bot activo hay DOS widgets vivos en la página (este y el del modal de la cabecera),
 *   y un selector se llevaría el que no es.
 * @param {object} deps
 * @param {string} deps.sitekey Clave pública. Si viene vacía no se monta nada.
 * @param {(token: string) => void} deps.onToken Recibe el token, o `''` cuando caduca o falla.
 * @param {object} [deps.win] Doble de `window` (para los tests).
 * @param {object} [deps.doc] Doble de `document` (para los tests).
 * @param {(msg: string) => void} [deps.warn] Doble de `console.warn` (para los tests).
 * @param {number} [deps.intervalMs]
 * @param {number} [deps.maxTries]
 * @returns {{reset: () => void, destroy: () => void}} `reset()` pide un token nuevo tras un envío
 *   fallido; `destroy()` desmonta el widget y para el sondeo.
 */
export function mountTurnstile(el, {
    sitekey,
    onToken,
    win = typeof window === 'undefined' ? null : window,
    doc = typeof document === 'undefined' ? null : document,
    warn = null,
    intervalMs = POLL_INTERVAL_MS,
    maxTries = POLL_MAX_TRIES,
} = {}) {
    const noop = { reset: () => {}, destroy: () => {} };

    if (! el || ! sitekey || ! win || ! doc) {
        return noop;
    }

    const emit = (token) => { if (typeof onToken === 'function') onToken(token); };
    const ready = () => !! (win.turnstile && win.turnstile.render);

    let widgetId = null;
    let poll = null;

    const stopPolling = () => {
        if (poll !== null) {
            win.clearInterval(poll);
            poll = null;
        }
    };

    const render = () => {
        // `widgetId !== null` es CINTURÓN, no una guarda viva: hoy es inalcanzable, porque los dos
        // únicos sitios que llaman aquí lo hacen una sola vez (el camino directo) o tras parar el
        // sondeo. Medido mutándola: quitarla no pone nada en rojo. Se queda por si alguien añade un
        // tercer sitio de llamada — y quien lo añada tendrá que traer el caso que la cubra.
        if (widgetId !== null || ! ready()) {
            return;
        }

        // El retorno es el `widgetId`, y es justo lo que el original tira. Ver trampa 2.
        widgetId = win.turnstile.render(el, {
            sitekey,
            callback: (token) => emit(token ?? ''),
            'error-callback': () => emit(''),
            'expired-callback': () => emit(''),
        });
    };

    if (ready()) {
        render();

        return {
            reset: () => { if (widgetId !== null) win.turnstile.reset(widgetId); emit(''); },
            destroy: () => { stopPolling(); if (widgetId !== null) win.turnstile.remove(widgetId); },
        };
    }

    // Cargar `api.js` UNA sola vez para toda la página, compartiendo el guard del motor Livewire.
    // `createElement` sí ejecuta; un `<script>` inyectado por morph no (esa es la regresión que el
    // comentario de `register.blade.php` narra).
    if (! win.__cfTurnstileLoading) {
        win.__cfTurnstileLoading = true;
        const script = doc.createElement('script');
        script.src = TURNSTILE_SCRIPT;
        script.async = true;
        script.defer = true;
        doc.head.appendChild(script);
    }

    let tries = 0;
    poll = win.setInterval(() => {
        if (ready()) {
            stopPolling();
            render();

            return;
        }

        if (++tries > maxTries) {
            stopPolling();
            // Ver trampa 3: sin esto el fallo no deja rastro en NINGÚN sitio.
            (warn ?? win.console?.warn)?.(`[turnstile] no cargó ${TURNSTILE_SCRIPT} tras ${maxTries} intentos.`);
            emit('');
        }
    }, intervalMs);

    return {
        reset: () => { if (widgetId !== null) win.turnstile.reset(widgetId); emit(''); },
        destroy: () => { stopPolling(); if (widgetId !== null) win.turnstile.remove(widgetId); },
    };
}
