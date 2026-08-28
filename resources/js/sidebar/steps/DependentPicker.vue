<script setup>
import { computed } from 'vue';
import { t as translate } from '../i18n.js';

/**
 * **«¿Para quién son estas entradas?»** — el selector de menores a cargo (Fase 6 · tanda 4,
 * `docs/specs/menores-a-cargo.md` §4.7, §9.9.3 D9; rediseñado en §9.11 D·2, `DECISIONES #217`).
 *
 * Una FILA por menor declarado con **nombre · edad · estado de la exención**; las que no se pueden
 * marcar dicen por qué, y con la línea llena las libres se deshabilitan: nunca más menores que
 * unidades.
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
    /** Lo que ofrece `assignableOptions()`: `{id, name, age, assignable, reasonKey, statusKey}`. */
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
const full = computed(() => props.selected.length >= props.quantity);
</script>

<template>
    <div class="addons dep-pick">
        <p class="addons__intro">{{ t('dependents.title') }}</p>
        <p class="form__hint">{{ t('dependents.hint') }}</p>

        <ul class="dep-pick__list">
            <!--
              ⚠️ El modificador se aplica por lo que NO se puede marcar NUNCA (`assignable`), no por lo
              que está deshabilitado AHORA: una casilla libre bloqueada porque la línea está llena es
              un estado temporal —se desmarca otra y vuelve— y apagar la fila entera diría que ese
              menor no vale, que es falso. El aviso de «no caben más» va abajo, una vez.
            -->
            <li v-for="option in options" :key="option.id"
                class="dep-pick__row" :class="option.assignable ? '' : 'dep-pick__row--off'">
                <label class="check dep-pick__pick">
                    <!-- ⚠️ `aria-describedby` (2026-08-28, revisión de `#217`): al sacar el motivo
                         FUERA del `<label>` —que es lo que lo hace legible para el ojo— dejó de
                         formar parte del nombre de la casilla, así que un lector de pantalla decía
                         «Lior, 9 años, casilla, no disponible» **sin decir por qué**. Esto lo vuelve
                         a unir sin devolver el texto dentro del rótulo. -->
                    <input type="checkbox" :checked="checked(option.id)"
                           :disabled="! option.assignable || (full && ! checked(option.id))"
                           :aria-describedby="option.reasonKey ? whyId(option.id) : null"
                           @change="$emit('toggle', option.id)">
                    <span class="dep-pick__who">
                        <span class="dep-pick__name">{{ option.name }}</span>
                        <span class="dep-pick__age">{{ option.age }}</span>
                        <!-- El estado POSITIVO solo existe donde hay firma que comprobar (modo
                             interno): fuera de él `statusKey` es `null` y aquí no se pinta nada. -->
                        <span v-if="option.statusKey" class="dep-pick__ok">{{ t(option.statusKey) }}</span>
                    </span>
                </label>

                <!-- ⚠️ El motivo va en SU elemento y debajo, no pegado al nombre: es la mitad del
                     defecto que el owner cazó. Sangrado hasta el nombre por CSS, no con espacios. -->
                <p v-if="option.reasonKey" :id="whyId(option.id)" class="dep-pick__why">{{ t(option.reasonKey) }}</p>
            </li>
        </ul>

        <p v-if="full" class="form__hint">{{ t('dependents.full') }}</p>
    </div>
</template>
