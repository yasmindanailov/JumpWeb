/**
 * **¿Está abierta la compra de la ISLA?** (T3e·2 de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #682`).
 *
 * La apertura es del controlador del paquete (`cajon/controller.js`), que decide la SUPERFICIE de cada una: con la
 * isla como carcasa, la compra se abre en la isla y la cuenta en el lateral. Aquí solo se OYE lo que anuncia
 * (`jw:cajon:open` con su `surface`, `jw:cajon:close`) y se le pide cerrar; el estado no se duplica.
 *
 * ⚠️⚠️ **Y la cuenta puede pedir la compra DESDE DENTRO del lateral** —«volver» sin historia, reintentar un pago
 * desde «Mis pedidos», abrir un producto—, conmutando de sección sin pasar por el controlador. En el cajón eso
 * enseñaba su sección de compra; con la isla, el lateral no tiene ninguna, así que se le pide al controlador que
 * abra la compra en SU superficie: el lateral se cierra y aparece la isla.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { cajonHost } from '../../sidebar/host-bridge.js';
import { ISLA } from '../../sidebar/carcasa.js';
import { useSectionStore } from '../../sidebar/stores/section.js';
import { SECTIONS } from '../../sidebar/section.js';

export function useSuperficie() {
    const enLaIsla = () => Boolean(cajonHost()?.isOpen && cajonHost()?.surface === ISLA);
    const abierta = ref(enLaIsla());
    const alAbrir = (evento) => { abierta.value = evento.detail?.surface === ISLA; };
    const alCerrar = () => { abierta.value = false; };

    onMounted(() => {
        document.addEventListener('jw:cajon:open', alAbrir);
        document.addEventListener('jw:cajon:close', alCerrar);
        // Por si se abrió mientras llegaba este trozo: el controlador ya lo sabe, el evento pasó antes.
        abierta.value = enLaIsla();
    });
    onUnmounted(() => {
        document.removeEventListener('jw:cajon:open', alAbrir);
        document.removeEventListener('jw:cajon:close', alCerrar);
    });

    const seccion = useSectionStore();
    watch(() => seccion.active, (activa) => {
        const host = cajonHost();

        if (activa === SECTIONS.PURCHASE && host?.isOpen && host.surface !== ISLA) host.open();
    });

    return { abierta, cerrar: () => cajonHost()?.close() };
}
