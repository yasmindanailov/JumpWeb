/**
 * Entrada del BANCO de la isla (`scripts/banco-isla.php`): monta `IslaFlotante.vue` en `#isla` con las props y los
 * textos que la página deja en `window.BANCO`. No es código del producto: es el lado «B» del juez de píxeles,
 * y por eso vive en `scripts/` y se compila aparte (`scripts/banco-isla/vite.config.mjs`), nunca en el paquete.
 *
 * `"@fn"` en las props es «aquí va una función»: el JSON no las lleva, y el lado A las convierte igual.
 * `window.BANCO_SET(props)` (Z3, `#782`): cambia props en vivo —las mismas en los dos lados—, para comparar el
 * MOVIMIENTO con el del diseño (`scripts/sonda-banco-movimiento.mjs`). Sin llamarlo, el banco es el de siempre.
 */
import { createApp, h, reactive } from 'vue';
import Isla from '../../resources/js/isla/IslaFlotante.vue';
import '../../resources/js/isla/isla.css';

const conFunciones = (v) => (v === '@fn' ? () => {}
    : Array.isArray(v) ? v.map(conFunciones)
        : v && typeof v === 'object' ? Object.fromEntries(Object.entries(v).map(([k, x]) => [k, conFunciones(x)]))
            : v);

const { props, textos } = window.BANCO;
const estado = reactive({ ...conFunciones(props), textos });
createApp({ render: () => h(Isla, estado) }).mount('#isla');
window.BANCO_SET = (cambio) => { Object.assign(estado, conFunciones(cambio)); };
