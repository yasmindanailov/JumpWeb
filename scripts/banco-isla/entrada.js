/**
 * Entrada del BANCO de la isla (`scripts/banco-isla.php`): monta `IslaFlotante.vue` en `#isla` con las props y los
 * textos que la página deja en `window.BANCO`. No es código del producto: es el lado «B» del juez de píxeles,
 * y por eso vive en `scripts/` y se compila aparte (`scripts/banco-isla/vite.config.mjs`), nunca en el paquete.
 *
 * `"@fn"` en las props es «aquí va una función»: el JSON no las lleva, y el lado A las convierte igual.
 */
import { createApp } from 'vue';
import Isla from '../../resources/js/isla/IslaFlotante.vue';
import '../../resources/js/isla/isla.css';

const conFunciones = (v) => (v === '@fn' ? () => {}
    : Array.isArray(v) ? v.map(conFunciones)
        : v && typeof v === 'object' ? Object.fromEntries(Object.entries(v).map(([k, x]) => [k, conFunciones(x)]))
            : v);

const { props, textos } = window.BANCO;
createApp(Isla, { ...conFunciones(props), textos }).mount('#isla');
