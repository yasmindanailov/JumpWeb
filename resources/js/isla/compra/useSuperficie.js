/**
 * **¿Está abierta esta capa de la ISLA?** (T3e·2 de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #682`; y
 * desde la T5, `#773`, §4.13, con DOS capas: la compra y Mi cuenta).
 *
 * La apertura es del controlador del paquete (`cajon/controller.js`), que decide la SUPERFICIE de cada una y, desde
 * la T5, si es de la CUENTA. Aquí solo se OYE lo que anuncia (`jw:cajon:open` con su `surface` y su `cuenta`,
 * `jw:cajon:close`) y se le pide cerrar; el estado no se duplica: cada capa se da por abierta cuando la isla lo está
 * y la apertura es la suya.
 *
 * ⚠️⚠️ **La sección del motor sigue a la capa, y lo hace UN solo dueño: la compra** (siempre montada con la isla).
 * Una apertura de compra deja la sección en la compra; y si algo del motor pide la compra estando en Mi cuenta —un
 * reintento de pago, un «volver» sin historia—, se le pide al controlador que abra la compra: la capa cambia sola.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { cajonHost } from '../../sidebar/host-bridge.js';
import { ISLA } from '../../sidebar/carcasa.js';
import { useSectionStore } from '../../sidebar/stores/section.js';
import { SECTIONS } from '../../sidebar/section.js';

/**
 * @param {{cuenta?: boolean}} [capa]  `cuenta: true` para Mi cuenta; por defecto, la compra
 */
export function useSuperficie({ cuenta = false } = {}) {
    const esLaMia = (surface, deCuenta) => surface === ISLA && Boolean(deCuenta) === cuenta;
    const enLaIsla = () => Boolean(cajonHost()?.isOpen && esLaMia(cajonHost()?.surface, cajonHost()?.cuenta));
    const abierta = ref(enLaIsla());
    const seccion = useSectionStore();
    const alAbrir = (evento) => {
        abierta.value = esLaMia(evento.detail?.surface, evento.detail?.cuenta);
        if (! cuenta && abierta.value) seccion.showPurchase();
    };
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

    if (! cuenta) {
        watch(() => seccion.active, (activa) => {
            const host = cajonHost();

            if (activa === SECTIONS.PURCHASE && host?.isOpen && (host.surface !== ISLA || host.cuenta)) host.open();
        });
    }

    return { abierta, cerrar: () => cajonHost()?.close() };
}
