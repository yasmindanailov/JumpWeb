<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import { useAccountContextStore } from '../../stores/accountContext.js';
import { useDependentsStore } from '../../stores/dependents.js';
import { useWaiverStore } from '../../stores/waiver.js';
import ZoneLoading from '../ZoneLoading.vue';
import DependentCard from './DependentCard.vue';
import { fieldError } from '../form-outcome.js';
import { RELATIONSHIPS, dependentsPager, dependentsView, signupNeedsWaiver } from '../dependents.js';
import { t as translate } from '../../i18n.js';

/**
 * **«Menores a cargo»** (Fase 6 · C, `docs/specs/menores-a-cargo.md` §4.1–§4.5, §9.8): declarar de
 * quién se hace responsable el titular, quitarlo, y firmar la exención EN SU NOMBRE.
 *
 * **Pinta y recoge; no decide nada.** El tope por cuenta, la regla de los 18, si «quitar» borra o
 * desvincula, y si la exención de cada uno está al día lo dice el SERVIDOR; `stores/dependents.js`
 * coloca lo que responda y `account/dependents.js` lo traduce a frases, decide qué página se ve y
 * qué pasa con ella después de añadir o quitar.
 *
 * ⚠️⚠️ **El alta se DESPLIEGA desde un botón** (encargo del owner, 2026-08-28: «en vez de añadir el
 * formulario completo para añadir menor, añade un botón y que salga el form»). Hasta hoy el
 * formulario estaba SIEMPRE abierto al final de la lista, y eso tiene dos costes medidos en el
 * cajón: con dos menores declarados la pantalla ya la ocupaba a medias un formulario vacío, y el
 * final de la lista —que es donde caen los nuevos— quedaba detrás de él.
 * ▶ El disparador es una **revelación** de manual: se queda visible con `aria-expanded`, apunta con
 * `aria-controls` al `id` del formulario, **da nombre a esa región** (`aria-labelledby` al botón, para
 * que el título no se diga dos veces en pantalla) y al desplegar el foco viaja al PRIMER campo (sin
 * eso, quien navega con teclado abre un formulario y sigue con el foco en el botón, tabulando a
 * ciegas). Se pliega solo al guardar bien, y a mano con «Cancelar» o con el propio disparador.
 * ⚠️ **El formulario se oculta con `v-show`, no con `v-if`**: `aria-controls` tiene que apuntar a un
 * elemento que EXISTA, y con `v-if` el `id` desaparece con él y el atributo queda colgando. Lo que
 * `display: none` sí hace —y es lo que aquí importa— es sacar los dos campos del orden de tabulación.
 *
 * ⚠️⚠️ **La lista se PAGINA, y el número de la decisión está en `account/dependents.js`**: el tope
 * por cuenta es **20 por defecto y hasta 100** desde Ajustes (`DependentSettings`), y además la lista
 * real puede superar al tope vigente (bajarlo no retira a nadie; cumplir 18 no borra la fila). Pero
 * el paginador **no se pinta si todo cabe en una página**, que es la otra mitad del encargo: una
 * cuenta con dos menores no puede ver una barra de páginas para dos tarjetas.
 *
 * ⚠️ **El texto que se firma es el que se ENSEÑA** (`stores/waiver.js`, CAJ-1): el `document_id`
 * sale del texto en pantalla. Si el servidor dice que cambió (`stale`), se relee y las casillas se
 * desmarcan: lo que se leyó ya no es lo que se firma.
 *
 * ⚠️ **`view` es un objeto del módulo plano envuelto en `reactive()`, no tres `ref()` sueltos**: las
 * transiciones —abrir, cancelar, «tras añadir salta a la página donde ha caído el nuevo», «tras quitar
 * recoloca la página que se quedó vacía»— son reglas con casos, y allí tienen `node --test`; aquí solo
 * se llaman. Medido con el contador del propio gate: el componente queda en **38 líneas de código,
 * techo 40** (`SidebarComponentBudgetTest`, `CE-6`), y con el estado suelto serían tres más —`page`,
 * `adding` y un `cancelAdd()`—: **41, por encima del techo**. El gate volvió a hacer lo que existe
 * para hacer: provocar la pregunta antes de que la lógica se escondiera en un `.vue`.
 *
 * ⚠️ Clases nuevas: solo `dep-add` y `dep-page`, las dos de separación. El disparador es el botón de
 * zona a todo el ancho del resto del área (`btn--ink auth__submit`), los dos botones del formulario
 * van en la fila `acc-actions` que estrenó la confirmación del QR, y el paginador es el `.pagination`
 * del sitio — el MISMO que «Mis pedidos» y «Mis reservas» usan dentro de este cajón.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = useDependentsStore();
store.reset();
store.ensure();

// El texto firmable, el MISMO que enseña Privacidad: sin él no hay casilla que ofrecer.
const waiver = useWaiverStore();
waiver.ensureLegal();

// ⚠️ `#441` · si el titular puede FIRMAR depende de su correo verificado, y eso lo dice el contexto
// de cuenta —el mismo que gobierna el aviso del índice—, no la respuesta de menores. Sin él, la
// tarjeta ofrecía casilla y botón a quien solo podía recibir un 409.
const context = useAccountContextStore();

/** Página y formulario desplegado, con sus transiciones (`account/dependents.js`). */
const view = reactive(dependentsView());
const nameInput = ref(null);

const a = (key) => translate(props.account, key);
// ⚠️ `#441` · el `document_id` viaja en el CONTEXTO y no como argumento de `add()`: es del mismo
// tipo que `messages` y `auth` —lo que la pantalla sabe y el store necesita—, y así la acción sigue
// siendo una línea. Las otras dos que lo reciben lo ignoran.
const ctx = () => ({ messages: props.messages, auth: props.auth, documentId: waiver.currentDocumentId });
const pager = computed(() => dependentsPager(store.items.length, view.page, props.account));

// ⚠️ `store.forget()` ANTES de abrir, y no `store.reset()`: si el intento anterior falló y el cliente
// plegó el formulario, al volver a abrirlo se leería el error de aquel intento bajo un campo vacío.
// `reset()` borraría además `expired`, y perder esa señal esconde una sesión caducada.
function openAdd() { store.forget(); view.open(); nextTick(() => nameInput.value?.focus()); }

/**
 * ⚠️ **Plegar devuelve el foco al disparador** (2026-08-28, revisión de `#217`): el formulario se va
 * del árbol y el navegador manda el foco al `<body>`, así que quien navega con teclado o con lector
 * de pantalla aparece al principio del documento tras cancelar o tras dar de alta a un menor. Es el
 * ÚNICO sitio por el que pasan los tres caminos (cancelar, alta correcta y el propio disparador).
 */
function closeAdd() { view.cancel(); nextTick(() => addBtn.value?.focus()); }
function toggleAdd() { if (view.adding) closeAdd(); else openAdd(); }

async function add() {
    if (await store.add(view.form, ctx())) { view.added(store.items.length); nextTick(() => addBtn.value?.focus()); }
}

/**
 * ⚠️ **La PREGUNTA ya no vive aquí** (`#565`, grieta 09): era un `window.confirm`, que lo pinta el
 * navegador —con su tipografía y un «Aceptar/Cancelar» que no habla nuestros tres idiomas— y que
 * enseñaba **el nombre de un menor** en un diálogo del sistema operativo. Hoy la hace `ConfirmInline`
 * dentro de la tarjeta de ese menor, que es donde se sabe a cuál se refiere; aquí solo queda el
 * gesto, que llega ya confirmado.
 */
async function remove(dependent) {
    if (await store.remove(dependent.id, ctx())) view.removed(store.items.length);
}

async function sign(dependent) {
    const { ok, stale } = await store.signWaiver({ id: dependent.id, documentId: waiver.currentDocumentId }, ctx());

    if (! ok && stale) { await waiver.reloadLegal(); view.rereadDocument(); }
}
</script>

<template>
    <div class="auth account__grid">
        <p class="purchase__note">{{ a('account.dependents.intro') }}</p>

        <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>

        <!-- El spinner ANTES del «no hay ninguno»: mientras se piden, decir que no hay es decir algo falso. -->
        <ZoneLoading v-if="store.listLoading && ! store.loaded" :ui="ui" />

        <!-- ⚠️ Todo lo demás exige `store.loaded`: sin la lista no se puede ni decir que está vacía ni
             ofrecer declarar uno (el tope lo cuenta el servidor sobre lo que ya hay). -->
        <template v-else-if="store.loaded">
            <!--
              El disparador del alta. Va ARRIBA y no al final: es la acción de la pantalla, y con la
              lista paginada «el final» deja de ser un sitio fijo. Se rotula con `add_title`, el mismo
              texto que titula lo que abre.
            -->
            <button id="acct-dep-add-btn" type="button" class="btn btn--ink auth__submit"
                    ref="addBtn" :aria-expanded="view.adding" aria-controls="acct-dep-add"
                    :disabled="store.busy" @click="toggleAdd">
                {{ a('account.dependents.add_title') }}
            </button>

            <!--
              Declarar uno: nombre y fecha de nacimiento, NADA MÁS (§4.2).
              ⚠️ **Sin título propio, y a propósito**: el disparador ya se llama «Añadir un menor» y
              repetirlo 30 px más abajo se lee como un fallo. El nombre accesible de la sección lo da
              el propio botón con `aria-labelledby` —es lo que la abre—, así que la región sigue
              teniendo nombre para un lector de pantalla sin decir dos veces lo mismo en pantalla.
            -->
            <section v-show="view.adding" id="acct-dep-add" class="account__card dep-add" aria-labelledby="acct-dep-add-btn">
                <form class="form auth__form" novalidate @submit.prevent="add">
                    <div class="form__field">
                        <label class="form__label" for="acct-dep-name">{{ a('account.dependents.name') }}</label>
                        <input id="acct-dep-name" ref="nameInput" v-model="view.form.name" type="text" autocomplete="off" required maxlength="120">
                        <span class="purchase__note">{{ a('account.dependents.name_hint') }}</span>
                        <span v-if="fieldError(store.fields, 'name')" class="form__error">{{ fieldError(store.fields, 'name') }}</span>
                    </div>

                    <!-- Apellidos y relación (`#236`). Van entre el nombre y la fecha porque es el
                         orden en que se dicen: quién es, cómo se apellida, qué eres tú suyo. -->
                    <div class="form__field">
                        <label class="form__label" for="acct-dep-surname">{{ a('account.dependents.surname') }}</label>
                        <input id="acct-dep-surname" v-model="view.form.surname" type="text" autocomplete="off" required maxlength="120">
                        <span v-if="fieldError(store.fields, 'surname')" class="form__error">{{ fieldError(store.fields, 'surname') }}</span>
                    </div>

                    <!-- ⚠️ Lista cerrada, no texto libre: es lo que sostiene que este adulto pueda
                         firmar la exención por el menor, y «madre» escrito de veinte formas no
                         sostiene nada. Las opciones y su orden salen del servidor a través de los
                         rótulos, así que añadir una no toca este fichero. -->
                    <div class="form__field">
                        <label class="form__label" for="acct-dep-rel">{{ a('account.dependents.relationship') }}</label>
                        <select id="acct-dep-rel" v-model="view.form.relationship" required>
                            <option value="" disabled>{{ a('account.dependents.relationship_choose') }}</option>
                            <option v-for="key in RELATIONSHIPS" :key="key" :value="key">
                                {{ a('account.dependents.relationship_' + key) }}
                            </option>
                        </select>
                        <span class="purchase__note">{{ a('account.dependents.relationship_hint') }}</span>
                        <span v-if="fieldError(store.fields, 'relationship')" class="form__error">{{ fieldError(store.fields, 'relationship') }}</span>
                    </div>

                    <div class="form__field">
                        <label class="form__label" for="acct-dep-born">{{ a('account.dependents.born_on') }}</label>
                        <input id="acct-dep-born" v-model="view.form.born_on" type="date" required>
                        <span v-if="fieldError(store.fields, 'born_on')" class="form__error">{{ fieldError(store.fields, 'born_on') }}</span>
                    </div>

                    <!--
                      ❗ `#441` · **DECLARAR Y ACEPTAR SON UN SOLO GESTO** (`[DECIDIDO owner]`): el
                      encargo era que «muchos clientes añaden un menor y después no firman», y la
                      respuesta no es insistir más tarde sino que el estado intermedio no exista.
                      ⚠️ Se pinta SOLO si el servidor sirve texto firmable: en modo `externo` o sin
                      versión publicada no hay nada que aceptar y el alta funciona como siempre.
                      ⚠️ Es el MISMO tratamiento que la tarjeta y que el alta de la cuenta —`<details>`
                      con el texto y `.check`—: un cuarto tratamiento para lo mismo es como murió el
                      sistema de sombras de `#196`.
                    -->
                    <template v-if="signupNeedsWaiver(waiver.document)">
                        <details class="form__hint">
                            <summary>{{ a('register.waiver_read') }}</summary>
                            <p v-for="(section, i) in waiver.document.sections" :key="i">
                                <strong v-if="section.h">{{ section.h }}</strong> {{ section.p }}
                            </p>
                        </details>
                        <div class="form__checks">
                            <label class="check">
                                <input v-model="view.form.accept_waiver" type="checkbox" required>
                                <span>{{ a('account.dependents.accept_waiver') }}</span>
                            </label>
                            <span v-if="fieldError(store.fields, 'accept_waiver')" class="form__error">{{ fieldError(store.fields, 'accept_waiver') }}</span>
                        </div>
                    </template>

                    <!-- Guardar y cancelar juntos y en ese orden: la fila que estrenó la confirmación
                         del QR, para que las dos decisiones del área se ofrezcan igual. -->
                    <div class="acc-actions">
                        <button type="submit" class="btn btn--ink" :disabled="store.busy">
                            {{ store.busy && ! store.signingId && ! store.removingId ? a('account.dependents.adding') : a('account.dependents.add') }}
                        </button>
                        <button type="button" class="btn btn--ghost" :disabled="store.busy" @click="closeAdd()">
                            {{ a('account.dependents.add_cancel') }}
                        </button>
                    </div>
                </form>
            </section>

            <p v-if="! store.items.length" class="account__card-sub">{{ a('account.dependents.empty') }}</p>

            <!-- ⚠️ `view.rows(...)`, no `store.items`: la página, no la lista entera. -->
            <DependentCard
                v-for="dependent in view.rows(store.items)"
                :key="dependent.id"
                :dependent="dependent"
                :account="account"
                :document="waiver.document"
                :busy="store.busy"
                :signing="store.signingId === dependent.id"
                :removing="store.removingId === dependent.id"
                :signed-ok="store.signedId === dependent.id"
                :reread="view.reread"
                :email-verified="context.emailVerified"
                @remove="remove(dependent)"
                @sign="sign(dependent)" />

            <!-- ⚠️ `pager` es `null` cuando todo cabe en una página, y entonces aquí no hay nada: una
                 barra de páginas para tres tarjetas es el adorno que el encargo descarta. -->
            <nav v-if="pager" class="pagination dep-page" :aria-label="pager.label">
                <button type="button" class="btn btn--ghost" :disabled="! pager.canPrev" @click="view.go(pager.current - 1, store.items.length)">{{ pager.prevLabel }}</button>
                <span class="pagination__info">{{ pager.pageLabel }}</span>
                <button type="button" class="btn btn--ghost" :disabled="! pager.canNext" @click="view.go(pager.current + 1, store.items.length)">{{ pager.nextLabel }}</button>
            </nav>
        </template>
    </div>
</template>
