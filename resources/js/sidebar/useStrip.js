import { onUnmounted, ref, watch } from 'vue';
import { scrollState, scrollStep } from './strip.js';

/**
 * El cableado Vue de una tira desplazable (`DECISIONES #241`).
 *
 * **Existe para que las dos tiras del embudo compartan UNA implementación.** La aritmética vive en
 * `strip.js`, que es plano y tiene sus casos en `node --test`; aquí solo está lo que necesita el
 * ciclo de vida de un componente — y eso, por sí solo, no se puede probar sin navegador.
 *
 * ⚠️⚠️ **Se engancha al NODO, no al montaje del componente, y la primera versión hacía lo segundo.**
 * Con `onMounted` las flechas nacían muertas: el carril vive dentro de un `v-if` que espera a la
 * oferta del servidor, así que **cuando el componente monta el nodo todavía no existe** — `track.value`
 * era `null`, no se registraba ningún oyente y `nav` se quedaba en `false` para siempre. Medido en
 * navegador: 11.535 px de recorrido en un carril de 440 y la flecha con `display: none` puesto por el
 * propio `v-show`. ▶ *Un composable que asume que su elemento existe al montar falla justo en los
 * componentes que esperan datos, que son casi todos.*
 *
 * ⚠️ **No corre en SSR**: el `watch` no dispara sin nodo, así que el árbol que compara el contrato
 * sale con las dos flechas ocultas. Es el estado correcto para un árbol sin medidas: sin layout no se
 * sabe si hay recorrido.
 */
export function useStrip() {
    const track = ref(null);

    /** `{prev, next}` — si cada flecha lleva a algún sitio. Nace en `false`: sin medir, no se ofrece. */
    const nav = ref({ prev: false, next: false });

    const sync = () => {
        nav.value = scrollState(track.value);
    };

    /** Un clic de flecha. `smooth` porque un salto seco de 280 px pierde al que mira. */
    const move = (direction) => {
        track.value?.scrollBy({ left: direction * scrollStep(track.value), behavior: 'smooth' });
    };

    let observer = null;
    let attached = null;

    const detach = () => {
        attached?.removeEventListener('scroll', sync);
        observer?.disconnect();
        attached = null;
        observer = null;
    };

    watch(track, (el) => {
        detach();

        if (! el) {
            sync();

            return;
        }

        attached = el;
        el.addEventListener('scroll', sync, { passive: true });

        // ⚠️ **El contenido de la tira CAMBIA sin que nadie la desplace**: otro producto trae otra
        // oferta de días y otras horas. Sin volver a medir, las flechas se quedarían con el estado del
        // producto anterior — la clase de fallo que no se ve hasta que un cliente cambia de idea.
        if (typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(sync);
            observer.observe(el);
        }

        sync();
    }, { immediate: true, flush: 'post' });

    onUnmounted(detach);

    return { track, nav, move };
}
