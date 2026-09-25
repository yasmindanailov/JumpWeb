/**
 * Entrada del lado B del banco de la CALCULADORA (pieza `calculadora` de `scripts/banco-entradas.php`, T4d·2): monta
 * la vista del producto (`resources/js/isla/calculadora/CalculadoraEntradas.vue`) donde la pieza 3 de la instancia le
 * deja sitio, con la lógica del diseño transcrita (`diseno.js`) en lugar del motor. Se compila aparte
 * (`scripts/banco-calculadora/vite.config.mjs`), nunca en el paquete.
 */
import { createApp, h, reactive } from 'vue';
import { CLAVE_TEXTOS } from '../../resources/js/isla/piezas/textos.js';
import '../../resources/js/isla/isla.css';
import CalculadoraEntradas from '../../resources/js/isla/calculadora/CalculadoraEntradas.vue';
import { cambiarDiseno, estadoInicial, vistaDiseno } from './diseno.js';

const { z, textos } = globalThis.BANCO;
const s = reactive(estadoInicial(z));
const app = createApp({
    render: () => h(CalculadoraEntradas, {
        v: vistaDiseno(z, s),
        lado: '[data-jw-calculadora-lado]',
        onCambiar: (campo, valor) => cambiarDiseno(z, s, campo, valor),
    }),
});
app.provide(CLAVE_TEXTOS, () => textos);
app.mount('[data-jw-calculadora]');
