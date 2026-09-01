/**
 * **RELEER EL CONTEXTO DE CUENTA AL VOLVER A LA PESTAÑA** (`DECISIONES #340`;
 * ficha de `DEUDA.md` abierta el día del lanzamiento, `#326`).
 *
 * Lo vivió el owner con la web recién abierta: verificó su correo en OTRA pestaña y la original
 * siguió diciéndole «tienes pendiente la exención». **El servidor ya contestaba bien** —medido:
 * `signed=true · pending=false`—; lo que pasaba es que el cajón había cargado su contexto ANTES de la
 * verificación y **no lo volvía a pedir nunca**.
 *
 * ▶ La pieza que faltaba era pequeñísima: `stores/accountContext.js::refresh()` ya existía, ya tenía
 * su guarda de concurrencia y ya vaciaba el contexto con un 401. **Lo que no tenía era un
 * disparador**: solo lo llamaban el login sin recarga (`account/session-gained.js`) y la zona de
 * privacidad. Aquí se le da el tercero.
 *
 * ⚠️⚠️ **Solo con sesión, y no es una optimización: es lo que evita convertir cada página pública en
 * un sondeo.** Sin esa puerta, todo visitante anónimo pediría `/me/account-context` cada vez que
 * cambia de pestaña, para que el servidor le conteste `null`. El caso del owner ocurre **con** sesión
 * —él estaba dentro—, así que la puerta no le quita nada.
 * ▶ Lo que esto NO cubre, dicho a propósito: *conseguir* sesión en otra pestaña. Esa pestaña es
 * anónima y seguiría siéndolo. Cubrirlo exigiría sondear a los anónimos, y además `#331`/`#332` ya
 * decidieron que conseguir sesión NAVEGA.
 *
 * ⚠️ **Se escucha `visibilitychange` Y `focus`** porque ninguno de los dos cubre solo todos los
 * casos: cambiar de ventana sin ocultar la pestaña no siempre dispara el primero, y volver de otra
 * aplicación no siempre dispara el segundo. Que salten los dos no duplica la petición: lo impiden el
 * intervalo mínimo de aquí y, por debajo, el `loading` del store.
 *
 * ⚠️ **El intervalo mínimo existe porque alternar de pestaña es un gesto BARATO y frecuente.** Sin
 * él, quien va y viene veinte veces en un minuto lanza veinte peticiones a un endpoint que compone
 * reservas, formularios pendientes y estado del descargo. Diez segundos no le quitan nada al caso
 * real —ir al correo, abrir el enlace y volver no baja de ahí— y cortan el martilleo.
 *
 * Módulo PLANO con el `window` **por parámetro** (`CE-6`, el patrón de `account/session-gained.js` y
 * `account/after-auth.js`): es lo que permite probarlo con `node --test` sin navegador.
 */

import { useAccountContextStore } from '../stores/accountContext.js';

/** Milisegundos mínimos entre dos relecturas. Ver el porqué en el docblock del módulo. */
export const MIN_GAP_MS = 10_000;

/**
 * La DECISIÓN, aparte del cableado y sin tocar red ni DOM: ¿toca releer?
 *
 * @param {{identified: boolean, hidden: boolean, lastAt: number, now: number, minGapMs?: number}} estado
 * @returns {boolean}
 */
export function shouldRefresh({ identified, hidden, lastAt, now, minGapMs = MIN_GAP_MS }) {
    // Sin titular no hay contexto que releer, y preguntarlo convertiría cada página pública en un sondeo.
    if (! identified) {
        return false;
    }

    // `focus` puede llegar con la pestaña todavía oculta (una ventana emergente que se cierra, por
    // ejemplo). Volver es hacerse VISIBLE, no recibir el foco.
    if (hidden) {
        return false;
    }

    return (now - lastAt) >= minGapMs;
}

/**
 * Cablea la relectura a los dos eventos.
 *
 * ⚠️ `lastAt` arranca en «ahora» a propósito: la carga de página **ya trajo el contexto sembrado**
 * por el servidor, así que un `focus` inmediato después de abrir no tiene nada nuevo que pedir.
 *
 * ⚠️ **No devuelve con qué desconectar, y es deliberado**: el motor del cajón se monta UNA vez por
 * carga de página y no se desmonta, así que un `stop()` nacería sin consumidor — la regla de `#287`
 * («una pieza nace en el MISMO cambio que su consumidor»), y aquí además el chunk va tan justo que
 * esos bytes eran reales. Si algún día el motor se desmonta, esa necesidad traerá su código.
 *
 * @param {{context?: {identified: boolean, refresh: Function}, win?: Window, now?: () => number, minGapMs?: number}} deps
 */
export function watchTabReturn({
    context = useAccountContextStore(),
    win = window,
    now = () => Date.now(),
    minGapMs = MIN_GAP_MS,
} = {}) {
    const doc = win?.document ?? null;
    let lastAt = now();

    const alVolver = () => {
        const visible = doc ? doc.hidden !== true : true;

        if (! shouldRefresh({ identified: context?.identified === true, hidden: ! visible, lastAt, now: now(), minGapMs })) {
            return;
        }

        // El sello se pone ANTES de pedir: si la petición tarda, los `focus` que lleguen mientras
        // tanto ya no vuelven a disparar. Un fallo no se anuncia — es la misma cortesía de interfaz
        // que el store documenta.
        lastAt = now();
        context?.refresh?.();
    };

    doc?.addEventListener?.('visibilitychange', alVolver);
    win?.addEventListener?.('focus', alVolver);
}
