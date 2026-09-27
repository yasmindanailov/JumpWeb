/**
 * **SIN CONEXIÓN EN MI CUENTA, en marcha** (T5f de `docs/specs/isla-y-landing-nueva.md` §4.13): si hay red —oyendo
 * `online`/`offline` del navegador— y el fallo de lo que no se intentó, con su reintento. Lo que decide, en
 * `conexion.js`, puro y probado.
 *
 * @param {{alFallar?: () => void}} [opciones]  lo que hace la capa al fallar algo (retirar la confirmación, subir al aviso)
 */
import { onMounted, onUnmounted, ref, shallowRef } from 'vue';
import { intentar, sinRed } from './conexion.js';

export function useConexion({ alFallar = () => {} } = {}) {
    const enLinea = ref(! sinRed());
    // El reintento de lo último que no se intentó (una función), o `null`.
    const fallo = shallowRef(null);
    const alCambiar = () => { enLinea.value = ! sinRed(); };

    onMounted(() => {
        window.addEventListener('online', alCambiar);
        window.addEventListener('offline', alCambiar);
        alCambiar();
    });
    onUnmounted(() => {
        window.removeEventListener('online', alCambiar);
        window.removeEventListener('offline', alCambiar);
    });

    /** Lo que GUARDA, envuelto: sin red no se intenta, y queda su fallo con «Volver a intentarlo». */
    const guarda = (hace) => intentar(hace, {
        fallar: (reintento) => { fallo.value = reintento; alFallar(); },
        limpiar: () => { fallo.value = null; },
    });

    return { enLinea, fallo, guarda, olvidar: () => { fallo.value = null; } };
}
