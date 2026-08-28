<script setup>
import { computed } from 'vue';
import { t as translate } from '../i18n.js';

/**
 * **«¿Para quién son estas entradas?»** — el selector de menores a cargo (Fase 6 · tanda 4,
 * `docs/specs/menores-a-cargo.md` §4.7, §9.9.3 D9; rediseñado en §9.11 D·2, `DECISIONES #217`).
 *
 * Una FILA por menor declarado con **nombre y edad**; las que no se pueden marcar se apagan **y dicen
 * por qué**, y con la línea llena las libres se apagan también —sin motivo, porque desmarcar a otro
 * las devuelve—: nunca más menores que unidades.
 *
 * ⚠️⚠️ **Dos cambios del owner el 2026-08-29** (`DECISIONES #242`), y los dos van delante del texto
 * que corrigen más abajo:
 *  1. **fuera «exención firmada»** — «es innecesario, porque es obvio: no podemos asignar menores sin
 *     firmar la exención». Cierto: **una fila marcable ya lo significa**. En su hueco va lo que el
 *     control NO puede decir solo, «1 entrada asignada», y en segundo plano;
 *  2. **la línea «No caben más» se sustituye por apagar las filas que no caben** — con una entrada y
 *     tres menores dice lo mismo y ahorra una línea, que en 390 px es sitio de verdad.
 *
 * **Pinta y recoge.** Quién se ofrece, qué cabe y qué estado tiene cada uno lo decide
 * `assignment.js`; el estado vive en el store de la selección (paso 3) o en el de la cesta (paso 4).
 *
 * ⚠️⚠️ **El defecto que este rediseño arregla, y es de LECTURA, no de lógica.** El owner probó el
 * embudo en staging y dijo «no me deja darle al checkbox» a una fila que estaba `disabled` **a
 * propósito** (`[DECIDIDO owner]` `#202`·2: sin exención firmada no se asigna). Medido en el
 * navegador: la casilla sí estaba deshabilitada, pero su rótulo seguía con `opacity: 1` y
 * `cursor: pointer` —lo pone `.check`—, así que **nada decía que la fila estuviera apagada**; y el
 * motivo iba dentro del MISMO `<span>` que el nombre («Vera · 6 años — exención sin firmar»), que se
 * lee como si fuera parte del nombre.
 * ▶ Tres cambios y **ninguno toca la regla**: la fila apagada lleva `dep-pick__row--off` y se ve
 * apagada, el motivo tiene su propio elemento debajo del nombre, y el bloque se separa con una línea
 * de lo que tiene encima porque es una pregunta distinta de «cuántas entradas quieres». Quién puede
 * marcarse lo sigue decidiendo `assignment.js` y lo vuelve a decidir `DependentAssigner` en el
 * servidor.
 *
 * ⚠️ **Nació envuelto en `.eventfields` y se veía ROTO**: esa clase es el bloque de campos de TEXTO del
 * pack y trae `.eventfields input { width: 100% … }` (site.css ~1526), que por descendencia y orden de
 * hoja le gana a `.check input`: el checkbox medía 400 px y el nombre quedaba FUERA del cajón. Lo vio
 * el owner, no ninguna guarda: el contrato de árbol compara nodos y clases, no la cascada. **Una clase
 * que existe no es una clase que sirva: mira sus reglas de descendencia antes de reutilizarla.**
 * ▶ Por eso el vocabulario sigue siendo el de los complementos del paso 3 (`addons`, `addons__intro`,
 * `form__hint`, `check`) y lo nuevo (`dep-pick*`) es lo mínimo: la fila, sus tres datos y su motivo.
 */
const props = defineProps({
    /** Lo que ofrece `assignableOptions()`: `{id, name, age, assignable, reasonKey}`. */
    options: { type: Array, default: () => [] },
    /** Los ids marcados en ESTA línea. */
    selected: { type: Array, default: () => [] },
    /** Las unidades de la línea: el tope del conjunto. */
    quantity: { type: Number, default: 0 },
    /** El grupo del EMBUDO (`tickets.dependents.*`): viaja siempre, con y sin sesión. */
    messages: { type: Object, default: () => ({}) },
    /**
     * ⚠️ **Prefijo para los `id` del marcado, y no es cosmético**: en el paso 4 este componente se
     * pinta UNA VEZ POR LÍNEA de la cesta, con la misma lista de menores. Sin un prefijo por línea,
     * el `id` del motivo se repetiría y `aria-describedby` apuntaría al de otra línea — un lector de
     * pantalla leería el motivo equivocado. Quien monta sabe en qué línea está; el componente no.
     */
    scope: { type: String, default: 'sel' },
});

defineEmits(['toggle']);

const t = (key) => translate(props.messages, key);

/** El `id` del motivo de un menor, único dentro de la pantalla (ver la prop `scope`). */
const whyId = (id) => `dep-why-${props.scope}-${id}`;
const checked = (id) => props.selected.includes(id);

/** ¿La línea ya tiene tantos menores como unidades? */
const full = computed(() => props.selected.length >= props.quantity);

/**
 * ¿Esta fila está bloqueada AHORA? Por dos motivos distintos, y el marcado los distingue:
 *
 *  - **nunca** (`! assignable`) — y entonces la fila lleva su MOTIVO debajo;
 *  - **ahora** (la línea está llena y este no es de los marcados) — y no lleva nada, porque
 *    desmarcar a otro lo devuelve.
 *
 * ⚠️ Las dos apagan la fila desde `#242`: con una entrada y tres menores, ver apagados a los dos que
 * no caben dice lo mismo que la frase «no caben más» y ahorra la línea (`[OWNER]`).
 */
const blocked = (option) => ! option.assignable || (full.value && ! checked(option.id));
</script>

<template>
    <div class="addons dep-pick">
        <p class="addons__intro">{{ t('dependents.title') }}</p>
        <p class="form__hint">{{ t('dependents.hint') }}</p>

        <ul class="dep-pick__list">
            <!--
              ⚠️⚠️ **CORRECCIÓN, y va delante del texto que corrige** (`DECISIONES #242`, `[OWNER]`).
              Aquí decía que el modificador se aplica solo por lo que NO se puede marcar NUNCA, y que
              una fila bloqueada por línea llena no se apaga porque es un estado temporal — el aviso
              iba abajo, una vez. **El owner pidió lo contrario y tiene razón**: con una entrada y tres
              menores, apagar los dos que no caben dice lo mismo que la frase y **se ahorra la línea**,
              que en un cajón de 390 px es sitio de verdad.
              ▶ **Y las dos clases de fila apagada siguen distinguiéndose**, que era el motivo original:
              la que no se puede marcar NUNCA lleva su MOTIVO debajo; la que está llena, no. El motivo
              es lo único que no es obvio, y es lo que se conserva.
            -->
            <li v-for="option in options" :key="option.id"
                class="dep-pick__row" :class="blocked(option) ? 'dep-pick__row--off' : ''">
                <label class="check dep-pick__pick">
                    <!-- ⚠️ `aria-describedby` (2026-08-28, revisión de `#217`): al sacar el motivo
                         FUERA del `<label>` —que es lo que lo hace legible para el ojo— dejó de
                         formar parte del nombre de la casilla, así que un lector de pantalla decía
                         «Lior, 9 años, casilla, no disponible» **sin decir por qué**. Esto lo vuelve
                         a unir sin devolver el texto dentro del rótulo. -->
                    <input type="checkbox" :checked="checked(option.id)"
                           :disabled="blocked(option)"
                           :aria-describedby="option.reasonKey ? whyId(option.id) : null"
                           @change="$emit('toggle', option.id)">
                    <span class="dep-pick__who">
                        <span class="dep-pick__name">{{ option.name }}</span>
                        <span class="dep-pick__age">{{ option.age }}</span>
                        <!-- ⚠️⚠️ Aquí vivía «exención firmada», y se RETIRÓ (`#242`, `[OWNER]`): **una
                             fila marcable YA significa que la exención está en regla**, así que el
                             rótulo repetía con palabras lo que el propio control decía. En su sitio va
                             lo que el control NO puede decir: que a este menor ya se le asignó una. -->
                        <span v-if="checked(option.id)" class="dep-pick__ok">{{ t('dependents.assigned') }}</span>
                    </span>
                </label>

                <!-- ⚠️ El motivo va en SU elemento y debajo, no pegado al nombre: es la mitad del
                     defecto que el owner cazó. Sangrado hasta el nombre por CSS, no con espacios. -->
                <p v-if="option.reasonKey" :id="whyId(option.id)" class="dep-pick__why">{{ t(option.reasonKey) }}</p>
            </li>
        </ul>
    </div>
</template>
