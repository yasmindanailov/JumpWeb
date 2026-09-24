/**
 * LOS PANELES de la isla (`ParkIsland.jsx`, bloques «Cerrar» y «pj-island:open»): una PILA —el menú, y dentro de
 * él la cuenta o la ayuda; «volver» desapila—, quién la abrió (para devolverle el foco al cerrar) y la capa que la
 * rodea mientras está abierta: Esc, tocar fuera, el foco atrapado con Tab y el panel nuevo enfocado al entrar.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export function usePila() {
    const stack = ref([]);
    const plansFromToday = ref(false);
    const view = computed(() => (stack.value.length ? stack.value[stack.value.length - 1] : null));
    let trigger = null;

    function cerrar() {
        stack.value = [];
        const tr = trigger;
        if (tr && tr.focus) requestAnimationFrame(() => tr.focus());
    }
    const abrirPanel = (id, e) => { if (e && e.currentTarget) trigger = e.currentTarget; stack.value = [id]; };
    const apilarPanel = (id) => { stack.value = stack.value.concat([id]); };
    const atras = () => { stack.value = stack.value.slice(0, -1); };
    function alternarPanel(id, e) {
        if (view.value === id && stack.value.length === 1) { cerrar(); return; }
        abrirPanel(id, e);
    }

    /**
     * Un botón de la página puede abrir un panel de la isla («Reservar» del héroe abre el selector de plan): un
     * solo selector en toda la web. `window.dispatchEvent(new CustomEvent('isla:abrir', { detail: { panel:
     * 'plans', trigger } }))` —el `pj-island:open` del diseño, con nombre del producto—.
     */
    function alAbrirDesdeLaPagina(ev) {
        const d = ev.detail || {};
        if (!d.panel) return;
        if (d.trigger) trigger = d.trigger;
        if (d.panel === 'plans') plansFromToday.value = Boolean(d.fromToday);
        stack.value = [d.panel];
    }

    return { stack, view, plansFromToday, cerrar, apilarPanel, atras, alternarPanel, alAbrirDesdeLaPagina };
}

/**
 * La capa de un panel abierto. La compra no se cierra tocando fuera (hay dinero en juego); con Escape, sí: es su
 * X, que cierra sin perder nada (`checkout.onClose`).
 */
export function useCapa({ islandRef, panelRef, isOpen, inCheckout, checkout, pila }) {
    function alTocarFuera(e) {
        if (islandRef.value && !islandRef.value.contains(e.target)) pila.stack.value = [];
    }

    watch([isOpen, inCheckout], ([abierta, compra]) => {
        document.removeEventListener('pointerdown', alTocarFuera, true);
        if (abierta && !compra) document.addEventListener('pointerdown', alTocarFuera, true);
    });

    watch(pila.view, async (v) => {
        if (!v) return;
        await nextTick();
        if (panelRef.value) panelRef.value.enfocar();
    }, { flush: 'post' });

    function alTeclear(e) {
        if (e.key === 'Escape' && isOpen.value && !inCheckout.value) { e.stopPropagation(); pila.cerrar(); return; }
        if (e.key === 'Escape' && inCheckout.value && checkout()?.onClose) { e.stopPropagation(); checkout().onClose(); return; }
        if (e.key !== 'Tab' || !isOpen.value || !islandRef.value) return;
        const f = islandRef.value.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!f.length) return;
        const primero = f[0];
        const ultimo = f[f.length - 1];
        if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
        else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
    }

    onMounted(() => window.addEventListener('isla:abrir', pila.alAbrirDesdeLaPagina));
    onBeforeUnmount(() => {
        window.removeEventListener('isla:abrir', pila.alAbrirDesdeLaPagina);
        document.removeEventListener('pointerdown', alTocarFuera, true);
    });

    return { alTeclear };
}
