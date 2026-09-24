/**
 * Los textos de la isla llegan a sus piezas por `provide`/`inject`: los da `IslaFlotante.vue` (el grupo `isla` de
 * `lang/`) y cada pieza los lee con `t()` / `tp()`. Así ninguna pieza escribe un texto a mano.
 */
import { inject } from 'vue';
import { t, tp } from '../../sidebar/i18n.js';

export const CLAVE_TEXTOS = Symbol('isla:textos');

export function useTextos() {
    const textos = inject(CLAVE_TEXTOS, () => ({}));
    return {
        t: (clave) => t(textos(), clave),
        tp: (clave, params) => tp(textos(), clave, params),
    };
}
