<script setup>
import { computed } from 'vue';
import { t as translate } from '../i18n.js';

/**
 * **«¿Para quién son estas entradas?»** — el selector de menores a cargo (Fase 6 · tanda 4,
 * `docs/specs/menores-a-cargo.md` §4.7, §9.9.3 D9). Una casilla por menor declarado; las que no se
 * pueden marcar dicen por qué (ya tiene 18, exención sin firmar o de una versión anterior), y con la
 * línea llena las libres se deshabilitan: nunca más menores que unidades.
 *
 * **Pinta y recoge.** Quién se ofrece y qué cabe lo decide `assignment.js`; el estado vive en el
 * store de la selección (paso 3) o en el de la cesta (paso 4). Lo pintan los DOS pasos con las mismas
 * clases que ya existen —`eventfields`, `form__checks`, `check`—: cero CSS nuevo, como la zona de
 * menores.
 */
const props = defineProps({
    /** Lo que ofrece `assignableOptions()`: `{id, name, label, assignable, reasonKey}`. */
    options: { type: Array, default: () => [] },
    /** Los ids marcados en ESTA línea. */
    selected: { type: Array, default: () => [] },
    /** Las unidades de la línea: el tope del conjunto. */
    quantity: { type: Number, default: 0 },
    /** El grupo del EMBUDO (`tickets.dependents.*`): viaja siempre, con y sin sesión. */
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['toggle']);

const t = (key) => translate(props.messages, key);
const checked = (id) => props.selected.includes(id);
const full = computed(() => props.selected.length >= props.quantity);
</script>

<template>
    <div class="eventfields">
        <p class="eventfields__label">{{ t('dependents.title') }}</p>
        <p class="form__hint">{{ t('dependents.hint') }}</p>
        <div class="form__checks">
            <label v-for="option in options" :key="option.id" class="check">
                <input type="checkbox" :checked="checked(option.id)"
                       :disabled="! option.assignable || (full && ! checked(option.id))"
                       @change="$emit('toggle', option.id)">
                <span>{{ option.label }}<template v-if="option.reasonKey"> — {{ t(option.reasonKey) }}</template></span>
            </label>
        </div>
        <p v-if="full" class="form__hint">{{ t('dependents.full') }}</p>
    </div>
</template>
