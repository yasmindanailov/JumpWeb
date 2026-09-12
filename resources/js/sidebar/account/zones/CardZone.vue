<script setup>
import ConfirmInline from '../ConfirmInline.vue';
import { useCardStore } from '../../stores/card.js';
import ZoneLoading from '../ZoneLoading.vue';
import { cardImageUrl, cardIsDrawable, tokenGroups } from '../card.js';
import { t as translate } from '../../i18n.js';

/**
 * **«Mi QR»** (Fase 6 · A, `docs/specs/identidad-qr-puerta.md` §4.1, §4.5, §9.6 B·2): el QR que el
 * cliente enseña en la puerta — verlo, dictarlo, descargarlo y renovarlo.
 *
 * **Pinta y recoge; no decide nada.** El dibujo lo hace el SERVIDOR (`png_url`: los mismos bytes que
 * el adjunto del correo, B·1), si hay QR que dibujar lo dice él (`token` nulo con la clave rotada,
 * §8.1) y qué mata una renovación también (§4.5). `stores/card.js` coloca lo que responda y
 * `account/card.js` compone los grupos del token y la URL con su versión.
 *
 * ⚠️⚠️ **La zona se presenta como una CREDENCIAL, y el marco NO es nuevo** (2026-08-28, encargo del
 * owner: «esa presentación del QR la quiero más profesional… no el QR así suelto, respeta los diseños
 * y estilos»). Hasta hoy era una pila plana —imagen suelta, el token con la tipografía de un TÍTULO
 * de tarjeta, «Descargar» como enlace subrayado y «Renovar» como botón principal— y se leía como una
 * lista de cosas, no como algo que se enseña en un mostrador.
 * ▶ Lo que se usa aquí es **el marco de QR que el producto YA tiene**: `qr-frame` / `qr-tile` /
 * `qr-slot` / las cuatro `qr-corner`, las clases de `<x-site.registration-qr>` que el visitante ve en
 * la landing (papel claro de contraste FIJO, esquinas al color de marca). No se inventa un segundo
 * lenguaje para el mismo objeto, y un paquete de instalación que retoque ese marco retoca los dos.
 * Lo único propio es `qr-pass*`: el tamaño del recuadro dentro del cajón, el bloque monoespaciado del
 * código y la separación de las dos acciones.
 *
 * ⚠️ **La JERARQUÍA de las dos acciones cambia, y es lo contrario de lo que había.** «Descargar
 * (PNG)» pasa de enlace subrayado a botón de zona a todo el ancho —es lo que el cliente hace con el
 * QR: guardárselo antes de llegar—, y «Renovar mi QR» baja a botón discreto tras una línea de
 * separación, porque **invalida en el acto el QR del correo y cualquier copia impresa** (§4.5): una
 * acción destructiva y rara no puede tener el aspecto de la principal.
 *
 * ⚠️⚠️ **Renovar avisa SIEMPRE y confirma DENTRO del cajón** (§9.7 C·4, `[DECIDIDO owner]`
 * `DECISIONES #217`). Hasta el 2026-08-28 la confirmación era `window.confirm`, y eso tenía tres
 * problemas medidos en la prueba del owner en staging: el diálogo lo pinta el NAVEGADOR —fuera del
 * cajón, con la tipografía del sistema y un «Aceptar/Cancelar» que no habla nuestros tres idiomas—,
 * el owner **no llegó a verlo** (lo describió como «renueva sin avisar»), y el aviso solo existía
 * DENTRO del diálogo: quien no pulsaba nunca leía que el QR anterior deja de valer.
 * ▶ Ahora son dos cosas separadas: un párrafo **permanente** bajo el botón (el aviso) y una
 * confirmación en marcado propio (la decisión). El QR actual —el del correo y el impreso— deja de
 * valer EN EL ACTO, sin ventana de gracia (§4.5).
 *
 * ⚠️ **El estado degradado tiene sitio propio y no encoge la pantalla**: con la clave del servidor
 * rotada no hay nada que dibujar (§8.1), y en vez de un párrafo suelto se pinta un recuadro del mismo
 * tamaño que el QR con el motivo dentro. Así «no hay QR» ocupa el hueco de un QR y el botón de
 * renovar —que es lo que lo arregla— cae donde el ojo ya estaba.
 *
 * ⚠️ **La secuencia se queda aquí y no va a un módulo plano** (`CE-6`): son dos asignaciones y una
 * llamada al store, sin ninguna condición de negocio — «pedir» y «confirmar» no deciden nada que un
 * `node --test` pudiera cazar. Medido con el contador del propio gate: el componente queda en **25
 * líneas de código, techo 40** (`SidebarComponentBudgetTest`) — el rediseño es todo plantilla y CSS,
 * y por eso no mueve ese número ni una línea. Si algún día crece con una condición más, se extrae.
 *
 * ⚠️ El `<img>` lleva su tamaño natural (264 px: versión 2 con zona de silencio de 4, a escala 8) en
 * atributos, y el CSS lo encoge al ancho real del cajón: los atributos son la relación de aspecto que
 * evita el salto de maquetación mientras carga, no la medida final.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae el carné. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = useCardStore();
store.reset();
store.ensure();

const a = (key) => translate(props.account, key);

/**
 * ⚠️ **Toda la mecánica de la pregunta vive en `ConfirmInline` desde `#565`**: abrir, esconder el
 * disparador, llevar el foco al botón que confirma y devolverlo al cerrar. Aquí queda el GESTO, que
 * es lo único de esto que es de esta pantalla.
 */
async function rotate() {
    await store.rotate({ messages: props.messages, auth: props.auth });
}
</script>

<template>
    <div class="auth account__grid">
        <p class="purchase__note">{{ a('account.card.intro') }}</p>

        <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>
        <div v-if="store.done" class="purchase__confirm" role="status">{{ a('account.card.rotated') }}</div>
        <p v-if="store.expired" class="account__card-sub">{{ a('account.card.expired') }}</p>

        <!-- El spinner ANTES de cualquier estado: mientras se pide, no hay nada que afirmar. -->
        <ZoneLoading v-if="store.loading && ! store.loaded" :ui="ui" />

        <section v-else-if="store.loaded" class="account__card qr-pass">
            <template v-if="cardIsDrawable(store.card)">
                <!--
                  ⚠️ **El marco es el de `<x-site.registration-qr>`**, copiado en su estructura: las
                  cuatro esquinas y el `qr-slot` son las clases con las que el producto ya dibuja un
                  QR en la landing. Aquí dentro va un `<img>` en vez de un SVG en línea —el dibujo lo
                  hace el SERVIDOR (B·1)—, y esa es la única regla propia que necesita el marco.
                  ⚠️ El `src` lleva la versión para que renovar lo repinte.
                -->
                <div class="qr-frame">
                    <div class="qr-tile">
                        <span class="qr-corner qr-corner--tl" aria-hidden="true"></span>
                        <span class="qr-corner qr-corner--tr" aria-hidden="true"></span>
                        <span class="qr-corner qr-corner--bl" aria-hidden="true"></span>
                        <span class="qr-corner qr-corner--br" aria-hidden="true"></span>
                        <div class="qr-slot">
                            <img :src="cardImageUrl(store.card)" :alt="a('account.card.alt')" width="264" height="264" decoding="async">
                        </div>
                    </div>
                </div>

                <!--
                  El código, para dictarlo si la cámara falla. ⚠️ Va en MONOESPACIADO
                  (`--font-mono`, el token del sistema) y no con la tipografía de título que tenía:
                  esto se lee en voz alta letra a letra, y el alfabeto del token excluye a propósito
                  las parejas que se confunden (`CardToken`). Una tipografía de ancho fijo es la que
                  deja ver que son cinco grupos de cuatro.
                -->
                <p class="purchase__note">{{ a('account.card.token_label') }}</p>
                <p class="qr-pass__code">{{ tokenGroups(store.card.token) }}</p>

                <!--
                  ⚠️ La acción PRINCIPAL es descargar, y por eso es el botón de zona a todo el ancho:
                  es lo que se hace con un QR antes de llegar a la puerta. `<a download>` y no un
                  botón con JS: el navegador ya sabe guardar una imagen del mismo origen.
                -->
                <div class="acc-actions qr-pass__acts">
                    <a class="btn btn--ink" :href="cardImageUrl(store.card)" download="carne-qr.png">{{ a('account.card.download') }}</a>
                </div>
            </template>

            <!-- La clave del servidor rotó (§8.1): no hay nada que dibujar, y renovar lo arregla. -->
            <p v-else class="qr-pass__empty">{{ a('account.card.unavailable') }}</p>

            <p class="purchase__note qr-pass__hint">{{ a('account.card.hint') }}</p>

            <!--
              ⚠️ Renovar vive tras una línea de separación y con el botón discreto: es la acción que
              **invalida el QR del correo y el impreso en el acto**, no la que se ofrece por defecto.
            -->
            <div class="qr-pass__renew">
                <!-- ⚠️ El botón DESAPARECE mientras se pregunta, y la confirmación ocupa su sitio:
                     dejarlo permitiría pulsarlo otra vez sobre la pregunta abierta, que es la
                     ambigüedad que este cambio existe para quitar. -->
                <!-- ⚠️ `role="group"` + `aria-labelledby`, no `alertdialog`: no hay trampa de foco propia
                     —la del panel del cajón ya envuelve todo esto— y anunciar un diálogo que no lo es
                     deja al lector de pantalla esperando un cierre que nadie va a emitir. -->
                <!-- ⚠️ **La pregunta es `ConfirmInline` desde `#565`**, no marcado propio: era la
                     ÚNICA del cajón que se hacía dentro, y al llevarla también a «quitar un menor» y
                     a «borrar la cuenta» pasó a ser una pieza. -->
                <ConfirmInline id="acct-card-rotate-q"
                               :question="a('account.card.rotate_confirm_title')"
                               :confirm-label="a('account.card.rotate_confirm_yes')"
                               :cancel-label="a('account.card.rotate_confirm_no')"
                               :busy-label="a('account.card.rotating')"
                               :busy="store.busy"
                               @confirm="rotate">
                    <template #trigger="{ ask }">
                        <button type="button" class="btn btn--ghost" :disabled="store.busy" @click="ask">
                            {{ store.busy ? a('account.card.rotating') : a('account.card.rotate') }}
                        </button>
                    </template>
                </ConfirmInline>

                <!-- ⚠️ El aviso va SIEMPRE visible y FUERA de la confirmación: es lo que hace que el
                     cliente sepa qué va a pasar ANTES de pulsar. Dentro del diálogo llegaba tarde. -->
                <p class="account__card-sub qr-pass__notice">{{ a('account.card.rotate_notice') }}</p>
            </div>
        </section>
    </div>
</template>
