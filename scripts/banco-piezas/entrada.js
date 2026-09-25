/**
 * Entrada del BANCO DE PIEZAS (`scripts/banco-piezas.php`, T3b de `specs/isla-y-landing-nueva.md` §4.10): pinta
 * los casos que la página deja en `window.BANCO` con las piezas del producto (`resources/js/isla/ui/`), en la
 * columna clara y en la de tinta. Es el lado «B» del juez de píxeles: vive en `scripts/` y se compila aparte
 * (`scripts/banco-piezas/vite.config.mjs`), nunca en el paquete.
 *
 * Los casos vienen en el lenguaje del lado A (las props de React del diseño) y AQUÍ se traducen a las del
 * producto: `value`/`checked` → `v-model`, y lo que en React es un elemento en una prop (`iconLeft`, `icon`,
 * `action`, `actions`, `cta`, el `control` de una línea) → su ranura. Esa tabla es el contrato entre las dos
 * APIs, y el juez la comprueba: una traducción mal hecha es una diferencia de píxeles.
 */
import { createApp, h } from 'vue';
import { CLAVE_TEXTOS } from '../../resources/js/isla/piezas/textos.js';
import '../../resources/js/isla/isla.css';
import BotonSistema from '../../resources/js/isla/ui/BotonSistema.vue';
import EnlaceSistema from '../../resources/js/isla/ui/EnlaceSistema.vue';
import IconoLucide from '../../resources/js/isla/ui/IconoLucide.vue';
import CampoSistema from '../../resources/js/isla/ui/CampoSistema.vue';
import CasillaSistema from '../../resources/js/isla/ui/CasillaSistema.vue';
import SelectorHoras from '../../resources/js/isla/ui/SelectorHoras.vue';
import TarjetasOpcion from '../../resources/js/isla/ui/TarjetasOpcion.vue';
import ContadorCantidad from '../../resources/js/isla/ui/ContadorCantidad.vue';
import ResumenPrecio from '../../resources/js/isla/ui/ResumenPrecio.vue';
import AvisoDestacado from '../../resources/js/isla/ui/AvisoDestacado.vue';
import TiraDias from '../../resources/js/isla/ui/TiraDias.vue';
import AccesoSocial from '../../resources/js/isla/ui/AccesoSocial.vue';
import CabeceraDesenlace from '../../resources/js/isla/ui/CabeceraDesenlace.vue';
import PaseQr from '../../resources/js/isla/ui/PaseQr.vue';
import TarjetaTarea from '../../resources/js/isla/ui/TarjetaTarea.vue';
import EsqueletoCarga from '../../resources/js/isla/ui/EsqueletoCarga.vue';
import CargaRebote from '../../resources/js/isla/ui/CargaRebote.vue';
import CalendarioMes from '../../resources/js/isla/ui/CalendarioMes.vue';

/** Pieza del diseño → pieza del producto, y qué props suyas son ranuras (prop de React → nombre de la ranura). */
const PIEZAS = {
    Button: { c: BotonSistema, ranuras: { iconLeft: 'icono-izquierda', iconRight: 'icono-derecha' } },
    Link: { c: EnlaceSistema, ranuras: { icon: 'icono' } },
    Icon: { c: IconoLucide },
    Field: { c: CampoSistema, modelo: 'value', ranuras: { prefix: 'prefijo', suffix: 'sufijo' } },
    Checkbox: { c: CasillaSistema, modelo: 'checked' },
    TimeSlotPicker: { c: SelectorHoras, modelo: 'value' },
    OptionCards: { c: TarjetasOpcion, modelo: 'value' },
    QuantityStepper: { c: ContadorCantidad, modelo: 'value' },
    PriceSummary: { c: ResumenPrecio, ranuras: { cta: 'cta' } },
    InfoCallout: { c: AvisoDestacado, ranuras: { icon: 'icono', action: 'accion' } },
    DayStrip: { c: TiraDias, modelo: 'value' },
    SocialSignIn: { c: AccesoSocial },
    OutcomeHeader: { c: CabeceraDesenlace },
    QrPass: { c: PaseQr },
    TaskCard: { c: TarjetaTarea, ranuras: { actions: 'acciones' } },
    Skeleton: { c: EsqueletoCarga },
    BounceLoader: { c: CargaRebote },
    AvailabilityCalendar: { c: CalendarioMes, modelo: 'value' },
};

const esElemento = (x) => x !== null && typeof x === 'object' && ! Array.isArray(x) && typeof x.$ === 'string';

/** Un valor del caso: `"@fn"` es una función vacía y `{"@unidad": [uno, varios]}`, el `format` de una cantidad. */
function valor(x) {
    if (x === '@fn') return () => {};
    if (x && typeof x === 'object' && Array.isArray(x['@unidad'])) {
        const [uno, varios] = x['@unidad'];

        return (v) => `${v} ${v === 1 ? uno : varios}`;
    }
    if (Array.isArray(x)) return x.map((y) => (esElemento(y) ? nodo(y) : valor(y)));
    if (esElemento(x)) return nodo(x);
    if (x && typeof x === 'object') return Object.fromEntries(Object.entries(x).map(([k, v]) => [k, valor(v)]));

    return x;
}

/** Un elemento del caso → su nodo de Vue. */
function nodo(x) {
    if (! esElemento(x)) return typeof x === 'number' ? String(x) : x;
    const { $, hijos = [], ...crudas } = x;
    const pieza = PIEZAS[$];
    const props = {};
    const ranuras = {};

    for (const [k, v] of Object.entries(crudas)) {
        if (pieza?.ranuras?.[k]) {
            const contenido = Array.isArray(v) ? v : [v];
            ranuras[pieza.ranuras[k]] = () => contenido.map(nodo);
        } else if (pieza?.modelo === k) {
            props.modelValue = v;
        } else if (k === 'onChange' && pieza) {
            // El `onChange` de React es el `update:modelValue` de Vue; en el banco no hace nada.
        } else {
            props[k] = valor(v);
        }
    }

    // El control de cada línea del resumen: en React es un elemento dentro de la línea; aquí, la ranura `control`.
    if ($ === 'PriceSummary' && Array.isArray(props.lines)) {
        const controles = {};
        props.lines = crudas.lines.map((l) => {
            if (! l.control) return valor(l);
            controles[l.id] = l.control;

            return { ...valor({ ...l, control: null }), control: true };
        });
        ranuras.control = ({ linea }) => [nodo(controles[linea.id])];
    }

    if (hijos.length) ranuras.default = () => hijos.map(nodo);

    return pieza ? h(pieza.c, props, ranuras) : h($, props, hijos.map(nodo));
}

const { claro, tinta, textos } = globalThis.BANCO;
for (const [id, casos] of [['claro', claro], ['tinta', tinta]]) {
    const app = createApp({ render: () => casos.map(nodo) });
    app.provide(CLAVE_TEXTOS, () => textos);
    app.mount(`#${id}`);
}
